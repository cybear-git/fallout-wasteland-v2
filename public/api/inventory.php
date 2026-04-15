<?php
/**
 * УПРАВЛЕНИЕ ИНВЕНТАРЕМ
 * Просмотр, использование предметов, экипировка, продажа
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$player = getCurrentPlayer();
$pdo = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Показать весь инвентарь
            $stmt = $pdo->prepare("
                SELECT i.*, pi.quantity, pi.equipped, it.name as type_name, it.slug as type_slug
                FROM player_inventory pi
                JOIN items i ON i.id = pi.item_id
                JOIN item_types it ON it.id = i.type_id
                WHERE pi.player_id = ?
                ORDER BY it.slug, i.name
            ");
            $stmt->execute([$player['id']]);
            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Получаем крышки
            $caps = $player['caps'];
            
            echo json_encode([
                'success' => true,
                'inventory' => $inventory,
                'caps' => $caps,
                'has_junk_jet' => (bool)$player['has_junk_jet'],
                'junk_jet_ammo' => (int)$player['junk_jet_ammo']
            ]);
            break;

        case 'use':
            // Использовать предмет (расходник)
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            // Проверка наличия
            $stmt = $pdo->prepare("SELECT quantity, equipped FROM player_inventory WHERE player_id = ? AND item_id = ?");
            $stmt->execute([$player['id'], $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item || $item['quantity'] <= 0) {
                throw new Exception("Предмет не найден");
            }
            
            // Загрузка данных предмета
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($itemData['type_id'] != 3) { // Не расходник
                throw new Exception("Этот предмет нельзя использовать");
            }
            
            $pdo->beginTransaction();
            
            // Применение эффекта
            $message = "";
            if ($itemData['effect_stat'] === 'health') {
                $newHp = min($player['max_hp'], $player['current_hp'] + $itemData['effect_value']);
                $stmt = $pdo->prepare("UPDATE players SET current_hp = ? WHERE id = ?");
                $stmt->execute([$newHp, $player['id']]);
                $message = "Здоровье восстановлено на {$itemData['effect_value']}";
            } elseif ($itemData['effect_stat'] === 'radiation') {
                $newRad = max(0, $player['radiation'] + $itemData['effect_value']);
                $stmt = $pdo->prepare("UPDATE players SET radiation = ? WHERE id = ?");
                $stmt->execute([$newRad, $player['id']]);
                $message = "Радиация снижена на " . abs($itemData['effect_value']);
            }
            // TODO: Добавить временные баффы (Психо, Баффбафф) с таймером
            
            // Удаление предмета
            $stmt = $pdo->prepare("UPDATE player_inventory SET quantity = quantity - 1 WHERE player_id = ? AND item_id = ?");
            $stmt->execute([$player['id'], $itemId]);
            
            // Удаление записи если количество 0
            $stmt = $pdo->prepare("DELETE FROM player_inventory WHERE player_id = ? AND item_id = ? AND quantity <= 0");
            $stmt->execute([$player['id'], $itemId]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'new_hp' => isset($newHp) ? $newHp : $player['current_hp'],
                'new_radiation' => isset($newRad) ? $newRad : $player['radiation']
            ]);
            break;

        case 'equip':
            // Экипировать/снять оружие или броню
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            $pdo->beginTransaction();
            
            // Снять всё текущее этого типа
            $stmt = $pdo->prepare("SELECT type_id FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!in_array($type['type_id'], [1, 2])) {
                throw new Exception("Можно экипировать только оружие и броню");
            }
            
            // Снять предыдущее
            $stmt = $pdo->prepare("
                UPDATE player_inventory 
                SET equipped = FALSE 
                WHERE player_id = ? 
                AND item_id IN (SELECT id FROM items WHERE type_id = ?)
            ");
            $stmt->execute([$player['id'], $type['type_id']]);
            
            // Надеть новое
            $stmt = $pdo->prepare("UPDATE player_inventory SET equipped = TRUE WHERE player_id = ? AND item_id = ?");
            $stmt->execute([$player['id'], $itemId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Предмет экипирован']);
            break;

        case 'scrap':
            // Разобрать предмет на хлам (для хламотрона)
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                DELETE FROM player_inventory 
                WHERE player_id = ? AND item_id = ? AND quantity > 0
            ");
            $stmt->execute([$player['id'], $itemId]);
            
            // Начисление хлама в хламотрон (если есть)
            if ($player['has_junk_jet']) {
                $junkValue = 5; // Упрощенно, можно брать из предмета
                $stmt = $pdo->prepare("UPDATE players SET junk_jet_ammo = junk_jet_ammo + ? WHERE id = ?");
                $stmt->execute([$junkValue, $player['id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "Предмет разобран. +{$junkValue} ед. хлама для хламотрона"
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "Предмет разобран на запчасти (хламотрон отсутствует)"
                ]);
            }
            break;

        default:
            throw new Exception("Неизвестное действие");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

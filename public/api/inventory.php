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
            $stmt = $pdo->prepare("
                SELECT inv.id, inv.item_type, inv.item_key, inv.quantity, inv.equipped,
                       i.name, i.description, i.weight, i.value
                FROM inventory inv
                LEFT JOIN items i ON i.name = inv.item_key
                WHERE inv.character_id = ?
                ORDER BY inv.item_type, i.name
            ");
            $stmt->execute([$player['character_id']]);
            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT caps FROM characters WHERE id = ?");
            $stmt->execute([$player['character_id']]);
            $caps = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'inventory' => $inventory,
                'caps' => $caps ?? 0,
                'has_junk_jet' => (bool)$player['has_junk_jet'],
                'junk_jet_ammo' => (int)$player['junk_jet_ammo']
            ]);
            break;

        case 'use':
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$itemData) {
                throw new Exception("Предмет не найден");
            }
            
            if ($itemData['type_id'] != 3) {
                throw new Exception("Этот предмет нельзя использовать");
            }
            
            $pdo->beginTransaction();
            
            $message = "";
            if ($itemData['effect_stat'] === 'health') {
                $newHp = min($player['max_hp'], $player['hp'] + $itemData['effect_value']);
                $stmt = $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?");
                $stmt->execute([$newHp, $player['character_id']]);
                $message = "Здоровье восстановлено на {$itemData['effect_value']}";
            } elseif ($itemData['effect_stat'] === 'radiation') {
                $newRad = max(0, $player['radiation'] + $itemData['effect_value']);
                $stmt = $pdo->prepare("UPDATE characters SET radiation = ? WHERE id = ?");
                $stmt->execute([$newRad, $player['character_id']]);
                $message = "Радиация снижена на " . abs($itemData['effect_value']);
            }
            
            $stmt = $pdo->prepare("
                UPDATE inventory SET quantity = quantity - 1 
                WHERE character_id = ? AND item_key = ?
            ");
            $stmt->execute([$player['character_id'], $itemData['name']]);
            
            $stmt = $pdo->prepare("
                DELETE FROM inventory WHERE character_id = ? AND item_key = ? AND quantity <= 0
            ");
            $stmt->execute([$player['character_id'], $itemData['name']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'new_hp' => $newHp ?? $player['hp'],
                'new_radiation' => $newRad ?? $player['radiation']
            ]);
            break;

        case 'equip':
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$itemData) {
                throw new Exception("Предмет не найден");
            }
            
            $typeId = $itemData['type_id'];
            if (!in_array($typeId, [1, 2])) {
                throw new Exception("Можно экипировать только оружие и броню");
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE inventory SET equipped = FALSE WHERE character_id = ? AND item_type = ?");
            $typeStr = $typeId == 1 ? 'weapon' : 'armor';
            $stmt->execute([$player['character_id'], $typeStr]);
            
            $stmt = $pdo->prepare("UPDATE inventory SET equipped = TRUE WHERE character_id = ? AND item_key = ?");
            $stmt->execute([$player['character_id'], $itemData['name']]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Предмет экипирован']);
            break;

        case 'scrap':
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($itemData && $player['has_junk_jet']) {
                $junkValue = $itemData['junk_value'] ?? 5;
                
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE character_id = ? AND item_key = ? LIMIT 1");
                $stmt->execute([$player['character_id'], $itemData['name']]);
                
                $stmt = $pdo->prepare("UPDATE players SET junk_jet_ammo = junk_jet_ammo + ? WHERE id = ?");
                $stmt->execute([$junkValue, $player['player_id']]);
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Предмет разобран. +{$junkValue} ед. хлама для хламотрона"
                ]);
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE character_id = ? AND id = ?");
                $stmt->execute([$player['character_id'], $itemId]);
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => $player['has_junk_jet'] ? "Предмет утилизирован" : "Предмет разобран на запчасти"
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$player = getCurrentPlayer();
$pdo = getDbConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $dx = (int)($data['dx'] ?? 0);
    $dy = (int)($data['dy'] ?? 0);

    if ($dx === 0 && $dy === 0) {
        throw new Exception("Не указано направление движения.");
    }

    // Получаем текущую позицию персонажа
    $stmt = $pdo->prepare("SELECT id, pos_x, pos_y FROM characters WHERE player_id = ?");
    $stmt->execute([$player['player_id']]);
    $char = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$char) {
        throw new Exception("Персонаж не найден.");
    }

    $newX = $char['pos_x'] + $dx;
    $newY = $char['pos_y'] + $dy;

    // Проверка границ карты (если карта пуста, разрешаем движение)
    $stmt = $pdo->query("SELECT MIN(pos_x) as min_x, MAX(pos_x) as max_x, MIN(pos_y) as min_y, MAX(pos_y) as max_y FROM map_nodes");
    $bounds = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bounds['min_x'] !== null && ($newX < $bounds['min_x'] || $newX > $bounds['max_x'] || $newY < $bounds['min_y'] || $newY > $bounds['max_y'])) {
        throw new Exception("Дальше идти некуда.");
    }

    // Проверка проходимости клетки
    $stmt = $pdo->prepare("
        SELECT mn.id, mn.is_border, mn.border_direction,
               lt.type_name, l.name as location_name, l.danger_level, l.radiation_level
        FROM map_nodes mn
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        LEFT JOIN locations l ON l.id = mn.location_id
        WHERE mn.pos_x = ? AND mn.pos_y = ?
    ");
    $stmt->execute([$newX, $newY]);
    $targetNode = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetNode) {
        throw new Exception("Эта область недоступна.");
    }

    if ($targetNode['is_border'] && $targetNode['border_direction']) {
        $borderMsg = match($targetNode['border_direction']) {
            'n' => "На севере возвышаются непроходимые горы.",
            's' => "На юге раскинулась бесплодная пустыня.",
            'e' => "На востоке расположен Комплекс Братства Стали. Туда нельзя без приглашения.",
            'w' => "На западе - опасные горные перевалы.",
            default => "Проход заблокирован."
        };
        throw new Exception($borderMsg);
    }

    // Перемещение персонажа
    $stmt = $pdo->prepare("UPDATE characters SET pos_x = ?, pos_y = ? WHERE id = ?");
    $stmt->execute([$newX, $newY, $char['id']]);

    // Получение случайной фразы для типа локации
    $stmt = $pdo->prepare("
        SELECT lq.quote_text 
        FROM location_quotes lq
        JOIN location_types lt ON lt.type_key = lq.tile_type
        WHERE lt.type_name = :type AND lq.is_active = 1
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([':type' => $targetNode['type_name']]);
    $quoteRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $quote = $quoteRow ? $quoteRow['quote_text'] : "Тишина...";

    // Шанс встретить монстра
    $monsterEncounter = null;
    $dangerLevel = $targetNode['danger_level'] ?? 1;
    if (mt_rand(1, 100) <= 10 + ($dangerLevel * 3)) {
        $stmt = $pdo->prepare("
            SELECT id, name, hp, max_hp, damage, level 
            FROM monsters 
            WHERE is_active = 1 AND level BETWEEN ? AND ?
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([max(1, $dangerLevel - 1), $dangerLevel + 2]);
        $monster = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($monster) {
            $monsterEncounter = $monster;
        }
    }

    // Обновляем статус персонажа
    $stmt = $pdo->prepare("SELECT hp, max_hp, caps FROM characters WHERE id = ?");
    $stmt->execute([$char['id']]);
    $updatedChar = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => "Вы переместились на ({$newX}, {$newY})",
        'quote' => $quote,
        'monster_encounter' => $monsterEncounter,
        'player' => [
            'pos_x' => $newX,
            'pos_y' => $newY,
            'location_name' => $targetNode['location_name'] ?: $targetNode['type_name'],
            'tile_type' => $targetNode['type_name'],
            'danger_level' => $dangerLevel,
            'radiation_level' => $targetNode['radiation_level'] ?? 0,
            'hp' => $updatedChar['hp'],
            'max_hp' => $updatedChar['max_hp'],
            'caps' => $updatedChar['caps'] ?? 0
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

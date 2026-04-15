<?php
/**
 * СИСТЕМА ПОИСКА И ЛУТА
 * Обработка действия "Искать предметы" на текущей локации
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Проверка авторизации и сессии
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$player = getCurrentPlayer();
$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    // 1. Проверка кулдауна поиска (нельзя искать чаще 1 раза в 30 сек)
    $stmt = $pdo->prepare("
        SELECT created_at 
        FROM search_logs 
        WHERE player_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$player['id']]);
    $lastSearch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastSearch) {
        $lastTime = strtotime($lastSearch['created_at']);
        $cooldown = 30; // секунд
        if (time() - $lastTime < $cooldown) {
            $remaining = $cooldown - (time() - $lastTime);
            throw new Exception("Поиск еще недоступен. Подождите {$remaining} сек.");
        }
    }

    // 2. Получение типа текущей локации
    $stmt = $pdo->prepare("
        SELECT mn.tile_type, lt.name as location_name
        FROM map_nodes mn
        JOIN players p ON p.current_node_id = mn.id
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        WHERE p.id = ?
    ");
    $stmt->execute([$player['id']]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        throw new Exception("Не удалось определить локацию");
    }

    $tileType = $location['tile_type'];
    $locationName = $location['location_name'] ?? 'Пустошь';

    // 3. Определение шансов лута для типа местности
    // Базовые шансы модифицируются Восприятием игрока
    $perception = $player['perception'];
    $baseChance = 0.30; // 30% базовый шанс найти что-то
    $luckBonus = $player['luck'] * 0.02; // +2% за удачу
    $percBonus = $perception * 0.03; // +3% за восприятие
    
    $finalChance = min(0.95, $baseChance + $luckBonus + $percBonus);

    // 4. Бросок кубика
    $roll = mt_rand() / mt_getrandmax(); // 0.0 - 1.0

    $result = [
        'success' => true,
        'message' => '',
        'found_item' => null,
        'monster_encounter' => false,
        'xp_gained' => 0,
        'quote' => ''
    ];

    if ($roll > $finalChance) {
        // Ничего не найдено
        $result['message'] = "Вы тщательно обыскали {$locationName}, но ничего ценного не обнаружили.";
        
        // Шанс встретить монстра при неудачном поиске (10%)
        if (mt_rand(1, 100) <= 10) {
            $result['monster_encounter'] = true;
            $result['message'] .= " Внезапно из укрытия выпрыгнул враг!";
            // Тут можно запустить встречу с монстром (логика боя)
        }
    } else {
        // Что-то найдено!
        $stmt = $pdo->prepare("
            SELECT i.*, lt.min_qty, lt.max_qty
            FROM loot_tables lt
            JOIN items i ON i.id = lt.item_id
            WHERE lt.location_type = ?
            ORDER BY lt.chance DESC
        ");
        
        // Маппинг tile_type на location_type для лута
        $lootTypeMap = [
            'wasteland' => 'wasteland',
            'city_ruins' => 'city_ruins',
            'factory' => 'factory',
            'military_base' => 'military',
            'vault_entrance' => 'military',
            'forest' => 'wasteland',
            'desert' => 'wasteland'
        ];
        
        $lootKey = $lootTypeMap[$tileType] ?? 'wasteland';
        $stmt->execute([$lootKey]);
        $lootItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($lootItems)) {
            // Выбор случайного предмета из доступных
            $foundItem = $lootItems[array_rand($lootItems)];
            $qty = mt_rand($foundItem['min_qty'], $foundItem['max_qty']);

            // Добавление в инвентарь
            $stmt = $pdo->prepare("
                INSERT INTO player_inventory (player_id, item_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$player['id'], $foundItem['id'], $qty]);

            $xpGain = 5 + ($perception * 2);
            $stmt = $pdo->prepare("UPDATE players SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpGain, $player['id']]);

            $result['found_item'] = [
                'name' => $foundItem['name'],
                'quantity' => $qty,
                'description' => $foundItem['description']
            ];
            $result['xp_gained'] = $xpGain;
            $result['message'] = "Найдено: {$foundItem['name']} x{$qty}! (+{$xpGain} XP)";
        } else {
            $result['message'] = "В этом месте пока пусто.";
        }
    }

    // 5. Получение атмосферной фразы для поиска
    $stmt = $pdo->prepare("
        SELECT text 
        FROM location_quotes 
        WHERE location_type = ? OR location_type IS NULL
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->execute([$tileType]);
    $quoteRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['quote'] = $quoteRow['text'] ?? '';

    // 6. Логирование
    $stmt = $pdo->prepare("
        INSERT INTO search_logs (player_id, map_node_id, result, item_found_id, xp_gained)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $logResult = $result['monster_encounter'] ? 'monster_encounter' : 
                 ($result['found_item'] ? 'found_item' : 'nothing');
    $itemId = $result['found_item'] ? 
              (isset($foundItem['id']) ? $foundItem['id'] : null) : null;
    
    $stmt->execute([
        $player['id'], 
        $player['current_node_id'], 
        $logResult, 
        $itemId, 
        $result['xp_gained']
    ]);

    $pdo->commit();
    echo json_encode($result);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

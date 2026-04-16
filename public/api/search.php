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
    $pdo->beginTransaction();

    // Проверка кулдауна поиска (нельзя искать чаще 1 раза в 30 сек)
    $stmt = $pdo->prepare("
        SELECT created_at FROM search_logs 
        WHERE player_id = ? ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$player['player_id']]);
    $lastSearch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastSearch) {
        $lastTime = strtotime($lastSearch['created_at']);
        $cooldown = 30;
        if (time() - $lastTime < $cooldown) {
            $remaining = $cooldown - (time() - $lastTime);
            throw new Exception("Поиск еще недоступен. Подождите {$remaining} сек.");
        }
    }

    // Получение персонажа и текущей позиции
    $stmt = $pdo->prepare("
        SELECT c.*, lt.type_name, l.danger_level, l.loot_quality, l.radiation_level
        FROM characters c
        JOIN players p ON p.id = c.player_id
        LEFT JOIN map_nodes mn ON mn.pos_x = c.pos_x AND mn.pos_y = c.pos_y
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        LEFT JOIN locations l ON l.id = mn.location_id
        WHERE p.id = ?
    ");
    $stmt->execute([$player['player_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        throw new Exception("Персонаж не найден");
    }

    $tileType = $character['type_name'] ?? 'Пустошь';
    $lootQuality = $character['loot_quality'] ?? 1;
    $dangerLevel = $character['danger_level'] ?? 1;

    // Определение шансов лута
    $perception = $character['perception'] ?? 5;
    $luck = $character['luck'] ?? 5;
    $baseChance = 0.25 + ($lootQuality * 0.05);
    $luckBonus = $luck * 0.02;
    $percBonus = $perception * 0.03;
    $finalChance = min(0.90, $baseChance + $luckBonus + $percBonus);

    $result = [
        'success' => true,
        'message' => '',
        'found_item' => null,
        'monster_encounter' => null,
        'xp_gained' => 0,
        'quote' => ''
    ];

    // Поиск лута
    if (mt_rand(1, 100) / 100 <= $finalChance) {
        // Получаем случайный лут из базы
        $stmt = $pdo->prepare("
            SELECT * FROM items 
            WHERE type_id IN (1, 2, 3, 5)
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $qty = max(1, rand(1, $lootQuality + 2));
            
            $itemType = match($item['type_id']) {
                1 => 'weapon',
                2 => 'armor',
                3 => 'consumable',
                5 => 'loot',
                default => 'loot'
            };

            // Добавляем в инвентарь
            $stmt = $pdo->prepare("
                INSERT INTO inventory (character_id, item_type, item_key, quantity, condition_pct)
                VALUES (?, ?, ?, ?, 100.00)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$character['id'], $itemType, $item['name'], $qty]);

            $xpGain = 5 + ($perception * 2);
            $stmt = $pdo->prepare("UPDATE characters SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpGain, $character['id']]);

            $result['found_item'] = [
                'name' => $item['name'],
                'quantity' => $qty,
                'type' => $itemType
            ];
            $result['xp_gained'] = $xpGain;
            $result['message'] = "Найдено: {$item['name']} x{$qty}! (+{$xpGain} XP)";
        }
    }

    if (empty($result['message'])) {
        $result['message'] = "Вы обыскали местность, но ничего ценного не нашли.";
        
        // Шанс встретить монстра при неудачном поиске
        if (mt_rand(1, 100) <= 10 + ($dangerLevel * 2)) {
            $stmt = $pdo->prepare("
                SELECT id, name, hp, max_hp, damage, level 
                FROM monsters 
                WHERE is_active = 1 AND level BETWEEN ? AND ?
                ORDER BY RAND() LIMIT 1
            ");
            $stmt->execute([max(1, $dangerLevel - 1), $dangerLevel + 2]);
            $monster = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($monster) {
                $result['monster_encounter'] = $monster;
                $result['message'] .= " Внезапно из укрытия выпрыгнул {$monster['name']}!";
            }
        }
    }

    // Шанс найти крышки
    if (mt_rand(1, 100) <= 20 + ($luck * 2)) {
        $caps = rand(1, 5 + ($luck * 2));
        $stmt = $pdo->prepare("UPDATE characters SET caps = caps + ? WHERE id = ?");
        $stmt->execute([$caps, $character['id']]);
        $result['message'] .= " Вы нашли {$caps} крышек.";
    }

    // Атмосферная фраза
    $stmt = $pdo->prepare("
        SELECT lq.quote_text FROM location_quotes lq
        JOIN location_types lt ON lt.type_key = lq.tile_type
        WHERE lt.type_name = ? AND lq.is_active = 1
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$tileType]);
    $quoteRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['quote'] = $quoteRow['quote_text'] ?? '';

    // Логирование
    $stmt = $pdo->prepare("
        INSERT INTO search_logs (player_id, map_node_id, result, item_found_key, xp_gained)
        VALUES (?, 0, ?, ?, ?)
    ");
    $logResult = $result['monster_encounter'] ? 'monster_encounter' : 
                 ($result['found_item'] ? 'found_item' : 'nothing');
    $itemKey = $result['found_item'] ? ($result['found_item']['name'] ?? null) : null;
    $stmt->execute([$player['player_id'], $logResult, $itemKey, $result['xp_gained']]);

    $pdo->commit();
    echo json_encode($result);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

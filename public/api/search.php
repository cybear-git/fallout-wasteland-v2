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

// Функция для получения настройки из БД
function getSetting($pdo, string $key, float $default = 0.0): float {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM game_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $result = $value !== false ? (float)$value : $default;
    $cache[$key] = $result;
    return $result;
}

try {
    $pdo->beginTransaction();

    // Получение настроек из БД
    $lootBaseChance = getSetting($pdo, 'search_loot_base_chance', 75) / 100; // 0.75
    $capsChanceBase = getSetting($pdo, 'search_caps_chance_base', 15); // 15%
    $capsMin = (int)getSetting($pdo, 'search_caps_min', 1);
    $capsMax = (int)getSetting($pdo, 'search_caps_max', 3);
    
    // Шансы на типы предметов (в процентах)
    $weaponChance = getSetting($pdo, 'search_weapon_chance', 0.4); // 0.4%
    $armorChance = getSetting($pdo, 'search_armor_chance', 0.25); // 0.25%
    $consumableChance = getSetting($pdo, 'search_consumable_chance', 2); // 2%
    
    // Модификаторы
    $luckBonusMultiplier = getSetting($pdo, 'search_luck_bonus_multiplier', 0.3);
    $locationQualityMultiplier = getSetting($pdo, 'search_location_quality_multiplier', 0.15);
    
    // Pity timer и кулдаун
    $pityTimerThreshold = (int)getSetting($pdo, 'search_pity_timer_threshold', 50);
    $cooldownSeconds = (int)getSetting($pdo, 'search_cooldown_seconds', 30);
    
    // Шанс встречи монстров
    $monsterEncounterBase = getSetting($pdo, 'search_monster_encounter_base', 10);
    $monsterDangerMultiplier = getSetting($pdo, 'search_monster_danger_multiplier', 2);

    // Проверка кулдауна поиска
    $stmt = $pdo->prepare("
        SELECT created_at FROM search_logs 
        WHERE player_id = ? ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$player['player_id']]);
    $lastSearch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastSearch) {
        $lastTime = strtotime($lastSearch['created_at']);
        if (time() - $lastTime < $cooldownSeconds) {
            $remaining = $cooldownSeconds - (time() - $lastTime);
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

    // Характеристики персонажа
    $perception = $character['perception'] ?? 5;
    $luck = $character['luck'] ?? 5;
    $playerLevel = $character['level'] ?? 1;
    
    // Pity timer - проверка сколько поисков без редкого предмета
    $stmt = $pdo->prepare("SELECT searches_without_rare FROM players WHERE id = ?");
    $stmt->execute([$player['player_id']]);
    $searchesWithoutRare = (int)($stmt->fetchColumn() ?? 0);
    
    // Расчет модифицированных шансов
    $luckBonus = $luck * $luckBonusMultiplier; // например +3% за luck=10
    $locationMult = 1 + ($lootQuality * $locationQualityMultiplier); // например +45% за loot_quality=3
    
    // Динамические пороги с МИЗЕРНЫМ шансом на редкие предметы
    // Шансы берутся из БД и модифицируются характеристиками
    $armorThreshold = min(3, ($armorChance + $luckBonus * 0.5) * $locationMult);     // ~0.2-0.3%
    $weaponThreshold = min(5, ($weaponChance + $luckBonus * 0.8) * $locationMult);   // ~0.3-0.5%
    $consumableThreshold = min(25, ($consumableChance + $luckBonus * 5) * $locationMult); // ~1.5-2.5%
    
    // Pity timer - гарантия после N неудачных поисков
    $forcedRare = ($searchesWithoutRare >= $pityTimerThreshold);
    
    // Бросок кубика (1-1000 для точности)
    $roll = mt_rand(1, 1000);
    
    if ($forcedRare) {
        $roll = mt_rand(1, 50); // принудительно редкий предмет
    }
    
    // Определение типа предмета
    $foundType = 'loot'; // по умолчанию мусор
    $typeId = 5;
    
    if ($roll <= $armorThreshold * 10) { // умножаем на 10 т.к. roll 1-1000
        $foundType = 'armor';
        $typeId = 3;
    } elseif ($roll <= $weaponThreshold * 10) {
        $foundType = 'weapon';
        $typeId = 2;
    } elseif ($roll <= $consumableThreshold * 10) {
        $foundType = 'consumable';
        $typeId = 4;
    }

    $result = [
        'success' => true,
        'message' => '',
        'found_item' => null,
        'monster_encounter' => null,
        'xp_gained' => 0,
        'quote' => ''
    ];

    // Поиск лута - теперь с разными типами предметов
    if (mt_rand(1, 100) / 100 <= $lootBaseChance) { // Шанс из БД (по умолчанию 75%)
        // Получаем случайный предмет нужного типа
        $stmt = $pdo->prepare("
            SELECT * FROM items 
            WHERE type_id = ?
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$typeId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // Количество зависит от типа и качества локации
            if ($foundType === 'loot') {
                $qty = max(1, rand(1, $lootQuality + 1));
            } elseif ($foundType === 'consumable') {
                $qty = max(1, rand(1, 2));
            } else {
                $qty = 1; // оружие и броня по одному
            }
            
            // Добавляем в инвентарь
            $stmt = $pdo->prepare("
                INSERT INTO inventory (character_id, item_type, item_key, quantity, condition_pct)
                VALUES (?, ?, ?, ?, 100.00)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$character['id'], $foundType, $item['name'], $qty]);

            // XP за находку зависит от редкости
            $xpBase = match($foundType) {
                'armor' => 25,
                'weapon' => 20,
                'consumable' => 10,
                default => 5
            };
            $xpGain = $xpBase + ($perception * 2);
            $stmt = $pdo->prepare("UPDATE characters SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpGain, $character['id']]);

            $rarityLabel = match($foundType) {
                'armor' => '🛡️ Броня',
                'weapon' => '🔫 Оружие',
                'consumable' => '💊 Снадобье',
                default => '📦 Мусор'
            };

            $result['found_item'] = [
                'name' => $item['name'],
                'quantity' => $qty,
                'type' => $foundType,
                'rarity' => $rarityLabel
            ];
            $result['xp_gained'] = $xpGain;
            $result['message'] = "{$rarityLabel}: {$item['name']} x{$qty}! (+{$xpGain} XP)";
            
            // Сброс pity timer при находке редкого предмета
            if ($foundType !== 'loot') {
                $stmt = $pdo->prepare("UPDATE players SET searches_without_rare = 0 WHERE id = ?");
                $stmt->execute([$player['player_id']]);
            } else {
                // Увеличиваем счетчик если нашли только мусор
                $stmt = $pdo->prepare("UPDATE players SET searches_without_rare = searches_without_rare + 1 WHERE id = ?");
                $stmt->execute([$player['player_id']]);
            }
        }
    }

    if (empty($result['message'])) {
        $result['message'] = "Вы обыскали местность, но ничего ценного не нашли.";
        
        // Шанс встретить монстра при неудачном поиске (из БД)
        $monsterChance = $monsterEncounterBase + ($dangerLevel * $monsterDangerMultiplier);
        if (mt_rand(1, 100) <= $monsterChance) {
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

    // Шанс найти крышки (из БД)
    $capsChance = $capsChanceBase + ($luck * 1.5);
    if (mt_rand(1, 100) <= $capsChance) {
        $caps = rand($capsMin, $capsMax + $luck);
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

    $stmt = $pdo->prepare("
        INSERT INTO search_logs (player_id, map_node_id, result, item_found_key, xp_gained)
        VALUES (?, ?, ?, ?, ?)
    ");
    $logResult = $result['monster_encounter'] ? 'monster_encounter' :
                 ($result['found_item'] ? 'found_item' : 'nothing');
    $itemKey = $result['found_item'] ? ($result['found_item']['name'] ?? null) : null;
    $mapNodeId = 0; // TODO: получить актуальный ID ноды
    $stmt->execute([$player['player_id'], $mapNodeId, $logResult, $itemKey, $result['xp_gained']]);
    
    $pdo->commit();
    echo json_encode($result);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

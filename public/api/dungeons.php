<?php
/**
 * API Подземелий и Боссов
 * Вход в подземелье, бой с боссом, получение наград
 * 
 * CRITICAL FIX: Changed all references from 'users'/'user_id' to 'characters'/'character_id'
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/combat.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$player = getCurrentPlayer();
$characterId = $player['character_id'];
$pdo = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_dungeons':
            // Получить список доступных подземелий - ИСПРАВЛЕНО: character_id вместо user_id
            $stmt = $pdo->prepare("
                SELECT l.id, l.name, l.description, l.location_type, l.min_level, l.boss_id,
                       (SELECT COUNT(*) FROM player_fast_travel pft
                        WHERE pft.location_id = l.id AND pft.character_id = ?) as discovered
                FROM locations l
                WHERE l.location_type IN ('dungeon', 'boss_arena')
                ORDER BY l.min_level ASC
            ");
            $stmt->execute([$characterId]);
            $dungeons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Добавить информацию о боссах
            foreach ($dungeons as &$dungeon) {
                if ($dungeon['boss_id']) {
                    $bossStmt = $pdo->prepare("SELECT name, level, hp_max FROM monsters WHERE id = ?");
                    $bossStmt->execute([$dungeon['boss_id']]);
                    $boss = $bossStmt->fetch(PDO::FETCH_ASSOC);
                    $dungeon['boss_info'] = $boss;
                } else {
                    $dungeon['boss_info'] = null;
                }

                // Проверить доступность по уровню - ИСПРАВЛЕНО: characters вместо users
                $playerStmt = $pdo->prepare("SELECT level FROM characters WHERE id = ?");
                $playerStmt->execute([$characterId]);
                $playerLevel = $playerStmt->fetchColumn();
                $dungeon['accessible'] = $playerLevel >= $dungeon['min_level'];
            }

            echo json_encode(['success' => true, 'dungeons' => $dungeons]);
            break;

        case 'enter_dungeon':
            // Войти в подземелье (начать серию боев)
            $locationId = (int)($_POST['location_id'] ?? 0);

            if ($locationId <= 0) {
                throw new Exception('Некорректный ID локации');
            }

            // Проверка локации
            $locStmt = $pdo->prepare("
                SELECT id, name, location_type, min_level, boss_id
                FROM locations
                WHERE id = ? AND location_type IN ('dungeon', 'boss_arena')
            ");
            $locStmt->execute([$locationId]);
            $location = $locStmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                throw new Exception('Локация не найдена или недоступна');
            }

            // Проверка уровня игрока - ИСПРАВЛЕНО: characters вместо users
            $playerStmt = $pdo->prepare("SELECT level, hp, max_hp FROM characters WHERE id = ?");
            $playerStmt->execute([$characterId]);
            $playerData = $playerStmt->fetch(PDO::FETCH_ASSOC);

            if ($playerData['level'] < $location['min_level']) {
                throw new Exception("Требуется уровень {$location['min_level']} для входа");
            }

            if ($playerData['hp'] <= 0) {
                throw new Exception('Вы не можете войти в подземелье с нулевым здоровьем');
            }

            // Открыть точку быстрого перемещения если еще не открыта - ИСПРАВЛЕНО: character_id вместо user_id
            $discoverStmt = $pdo->prepare("
                INSERT IGNORE INTO player_fast_travel (character_id, location_id) VALUES (?, ?)
            ");
            $discoverStmt->execute([$characterId, $locationId]);

            // Если это босс-арена - начать бой с боссом
            if ($location['location_type'] === 'boss_arena' && $location['boss_id']) {
                $combatResult = startBossCombat($characterId, $location['boss_id'], $locationId);
                echo json_encode([
                    'success' => true,
                    'combat' => true,
                    'data' => $combatResult
                ]);
            } else {
                // Обычное подземелье - серия из 3-5 боев
                $floors = rand(3, 5);
                echo json_encode([
                    'success' => true,
                    'combat' => false,
                    'dungeon_name' => $location['name'],
                    'floors' => $floors,
                    'message' => "Вы вошли в {$location['name']}. Вас ждет {$floors} уровней испытаний."
                ]);
            }
            break;

        case 'complete_dungeon':
            // Завершить подземелье (после всех боев)
            $locationId = (int)($_POST['location_id'] ?? 0);

            $locStmt = $pdo->prepare("SELECT name, location_type FROM locations WHERE id = ?");
            $locStmt->execute([$locationId]);
            $location = $locStmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                throw new Exception('Локация не найдена');
            }

            // Награда за прохождение - ИСПРАВЛЕНО: characters вместо users
            $baseReward = 50;
            $capsReward = rand($baseReward, $baseReward * 2);
            $xpReward = rand(20, 40);

            $updateStmt = $pdo->prepare("
                UPDATE characters
                SET caps = caps + ?,
                    xp = xp + ?
                WHERE id = ?
            ");
            $updateStmt->execute([$capsReward, $xpReward, $characterId]);

            // Шанс на уникальный предмет (10%) - ИСПРАВЛЕНО: character_items вместо user_inventory
            $lootMsg = '';
            if (rand(1, 100) <= 10) {
                $lootStmt = $pdo->prepare("
                    SELECT id, name FROM items
                    WHERE type_id IN (2, 3) -- оружие или броня
                    ORDER BY RAND() LIMIT 1
                ");
                $lootStmt->execute();
                $loot = $lootStmt->fetch(PDO::FETCH_ASSOC);

                if ($loot) {
                    $addLootStmt = $pdo->prepare("
                        INSERT INTO character_items (character_id, item_id, quantity)
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE quantity = quantity + 1
                    ");
                    $addLootStmt->execute([$characterId, $loot['id']]);
                    $lootMsg = " Вы нашли: {$loot['name']}!";
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Подземелье '{$location['name']}' пройдено!",
                'rewards' => [
                    'caps' => $capsReward,
                    'xp' => $xpReward
                ],
                'loot_message' => $lootMsg
            ]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Начать бой с боссом - ИСПРАВЛЕНО: character_id вместо user_id
 */
function startBossCombat($characterId, $bossId, $locationId) {
    global $pdo;

    // Получить данные игрока - ИСПРАВЛЕНО: characters вместо users
    $playerStmt = $pdo->prepare("
        SELECT c.*,
               COALESCE((SELECT SUM(stat_value) FROM character_item_stats cis
                         JOIN items i ON cis.item_id = i.id
                         WHERE cis.character_id = c.id AND i.equipped = 1), 0) as bonus_stats
        FROM characters c
        WHERE c.id = ?
    ");
    $playerStmt->execute([$characterId]);
    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);

    // Получить данные босса
    $bossStmt = $pdo->prepare("SELECT * FROM monsters WHERE id = ?");
    $bossStmt->execute([$bossId]);
    $boss = $bossStmt->fetch(PDO::FETCH_ASSOC);

    if (!$boss) {
        throw new Exception('Босс не найден');
    }

    // Создать запись о бое - ИСПРАВЛЕНО: character_id вместо user_id
    $combatStmt = $pdo->prepare("
        INSERT INTO combats (character_id, monster_id, location_id, player_hp_start, monster_hp_start, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $combatStmt->execute([
        $characterId,
        $bossId,
        $locationId,
        $player['hp'],
        $boss['hp_max']
    ]);
    $combatId = $pdo->lastInsertId();

    return [
        'combat_id' => $combatId,
        'monster' => [
            'id' => $boss['id'],
            'name' => $boss['name'],
            'level' => $boss['level'],
            'hp' => $boss['hp_max'],
            'hp_max' => $boss['hp_max'],
            'damage_min' => $boss['damage_min'],
            'damage_max' => $boss['damage_max'],
            'is_boss' => true
        ],
        'player' => [
            'hp' => $player['hp'],
            'max_hp' => $player['max_hp'],
            'damage' => calculatePlayerDamage($player)
        ]
    ];
}

/**
 * Рассчитать урон игрока с учетом снаряжения
 */
function calculatePlayerDamage($player) {
    $baseDamage = 5 + floor($player['level'] * 1.5) + floor($player['strength'] / 2);
    // Здесь можно добавить бонусы от оружия
    return $baseDamage;
}
?>

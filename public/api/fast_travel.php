<?php
/**
 * API Быстрого Перемещения
 * Открытие точек, телепортация между открытыми локациями
 * 
 * CRITICAL FIX: Changed all references from 'users'/'user_id' to 'characters'/'character_id'
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

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
        case 'get_points':
            // Получить все открытые точки быстрого перемещения - ИСПРАВЛЕНО: character_id вместо user_id
            $stmt = $pdo->prepare("
                SELECT l.id, l.name, l.x, l.y, l.description, pft.discovered_at
                FROM player_fast_travel pft
                JOIN locations l ON pft.location_id = l.id
                WHERE pft.character_id = ?
                ORDER BY l.name ASC
            ");
            $stmt->execute([$characterId]);
            $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'points' => $points]);
            break;

        case 'travel':
            // Телепортироваться к открытой точке
            $targetLocationId = (int)($_POST['location_id'] ?? 0);

            if ($targetLocationId <= 0) {
                throw new Exception('Некорректный ID локации');
            }

            // Проверка что точка открыта игроком - ИСПРАВЛЕНО: character_id вместо user_id
            $checkStmt = $pdo->prepare("
                SELECT l.id, l.name, l.x, l.y
                FROM player_fast_travel pft
                JOIN locations l ON pft.location_id = l.id
                WHERE pft.character_id = ? AND pft.location_id = ?
            ");
            $checkStmt->execute([$characterId, $targetLocationId]);
            $location = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                throw new Exception('Вы еще не открыли эту точку быстрого перемещения!');
            }

            // Проверка что игрок не в бою - ИСПРАВЛЕНО: character_id вместо user_id
            $combatStmt = $pdo->prepare("SELECT id FROM combats WHERE character_id = ? AND status = 'active'");
            $combatStmt->execute([$characterId]);
            if ($combatStmt->fetch()) {
                throw new Exception('Нельзя телепортироваться во время боя!');
            }

            // Стоимость телепортации (10 крышек)
            $travelCost = 10;

            // Получение данных игрока - ИСПРАВЛЕНО: characters вместо users
            $playerStmt = $pdo->prepare("SELECT caps, pos_x, pos_y FROM characters WHERE id = ?");
            $playerStmt->execute([$characterId]);
            $playerData = $playerStmt->fetch(PDO::FETCH_ASSOC);

            if ($playerData['caps'] < $travelCost) {
                throw new Exception("Недостаточно крышек для телепортации (нужно {$travelCost})");
            }

            // Списать крышки и переместить игрока - ИСПРАВЛЕНО: characters вместо users
            $updateStmt = $pdo->prepare("
                UPDATE characters
                SET caps = caps - ?, pos_x = ?, pos_y = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$travelCost, $location['x'], $location['y'], $characterId]);

            // Логирование перемещения - ИСПРАВЛЕНО: character_id вместо user_id
            $logStmt = $pdo->prepare("
                INSERT INTO character_activity_log (character_id, action_type, details)
                VALUES (?, 'fast_travel', ?)
            ");
            $logStmt->execute([$characterId, "Teleported to {$location['name']} ({$location['x']}, {$location['y']})"]);

            echo json_encode([
                'success' => true,
                'message' => "Телепортация в '{$location['name']}' успешна!",
                'cost' => $travelCost,
                'new_position' => [
                    'x' => $location['x'],
                    'y' => $location['y']
                ],
                'remaining_caps' => $playerData['caps'] - $travelCost
            ]);
            break;

        case 'discover':
            // Принудительно открыть точку (например, после посещения локации)
            $locationId = (int)($_POST['location_id'] ?? 0);

            if ($locationId <= 0) {
                throw new Exception('Некорректный ID локации');
            }

            // Проверка существования локации
            $locStmt = $pdo->prepare("SELECT name, fast_travel_point FROM locations WHERE id = ?");
            $locStmt->execute([$locationId]);
            $location = $locStmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                throw new Exception('Локация не найдена');
            }

            // Если локация уже помечена как точка ФП, добавить игроку - ИСПРАВЛЕНО: character_id вместо user_id
            if ($location['fast_travel_point']) {
                $discoverStmt = $pdo->prepare("
                    INSERT IGNORE INTO player_fast_travel (character_id, location_id)
                    VALUES (?, ?)
                ");
                $discoverStmt->execute([$characterId, $locationId]);

                echo json_encode([
                    'success' => true,
                    'message' => "Точка быстрого перемещения '{$location['name']}' открыта!"
                ]);
            } else {
                throw new Exception('Эта локация не является точкой быстрого перемещения');
            }
            break;

        default:
            throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

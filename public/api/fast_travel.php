<?php
/**
 * API Быстрого Перемещения
 * Открытие точек, телепортация между открытыми локациями
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_points':
            // Получить все открытые точки быстрого перемещения
            $stmt = $pdo->prepare("
                SELECT l.id, l.name, l.x, l.y, l.description, pft.discovered_at
                FROM player_fast_travel pft
                JOIN locations l ON pft.location_id = l.id
                WHERE pft.user_id = ?
                ORDER BY l.name ASC
            ");
            $stmt->execute([$userId]);
            $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'points' => $points]);
            break;

        case 'travel':
            // Телепортироваться к открытой точке
            $targetLocationId = (int)($_POST['location_id'] ?? 0);
            
            if ($targetLocationId <= 0) {
                throw new Exception('Некорректный ID локации');
            }
            
            // Проверка что точка открыта игроком
            $checkStmt = $pdo->prepare("
                SELECT l.id, l.name, l.x, l.y 
                FROM player_fast_travel pft
                JOIN locations l ON pft.location_id = l.id
                WHERE pft.user_id = ? AND pft.location_id = ?
            ");
            $checkStmt->execute([$userId, $targetLocationId]);
            $location = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$location) {
                throw new Exception('Вы еще не открыли эту точку быстрого перемещения!');
            }
            
            // Проверка что игрок не в бою
            $combatStmt = $pdo->prepare("SELECT id FROM combats WHERE user_id = ? AND status = 'active'");
            $combatStmt->execute([$userId]);
            if ($combatStmt->fetch()) {
                throw new Exception('Нельзя телепортироваться во время боя!');
            }
            
            // Стоимость телепортации (10 крышек)
            $travelCost = 10;
            
            $playerStmt = $pdo->prepare("SELECT caps, x, y FROM users WHERE id = ?");
            $playerStmt->execute([$userId]);
            $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($player['caps'] < $travelCost) {
                throw new Exception("Недостаточно крышек для телепортации (нужно {$travelCost})");
            }
            
            // Списать крышки и переместить игрока
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET caps = caps - ?, x = ?, y = ?, location_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$travelCost, $location['x'], $location['y'], $targetLocationId, $userId]);
            
            // Логирование перемещения
            $logStmt = $pdo->prepare("
                INSERT INTO user_activity_log (user_id, action_type, details)
                VALUES (?, 'fast_travel', ?)
            ");
            $logStmt->execute([$userId, "Teleported to {$location['name']} ({$location['x']}, {$location['y']})"]);
            
            echo json_encode([
                'success' => true,
                'message' => "Телепортация в '{$location['name']}' успешна!",
                'cost' => $travelCost,
                'new_position' => [
                    'x' => $location['x'],
                    'y' => $location['y']
                ],
                'remaining_caps' => $player['caps'] - $travelCost
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
            
            // Если локация уже помечена как точка ФП, добавить игроку
            if ($location['fast_travel_point']) {
                $discoverStmt = $pdo->prepare("
                    INSERT IGNORE INTO player_fast_travel (user_id, location_id) 
                    VALUES (?, ?)
                ");
                $discoverStmt->execute([$userId, $locationId]);
                
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

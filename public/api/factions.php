<?php
/**
 * API Фракций и Репутации
 * Обработка действий, влияющих на репутацию, получение статуса
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
    $pdo->beginTransaction();

    switch ($action) {
        case 'get_status':
            // Получить статус всех фракций
            $stmt = $pdo->prepare("
                SELECT f.id, f.name, f.description, f.color_code, f.base_attitude,
                       COALESCE(pfr.reputation, 0) as reputation,
                       COALESCE(pfr.rank_title, 'Незнакомец') as rank_title
                FROM factions f
                LEFT JOIN player_faction_reputation pfr ON f.id = pfr.faction_id AND pfr.user_id = ?
                ORDER BY f.id
            ");
            $stmt->execute([$userId]);
            $factions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Рассчитать ранги
            foreach ($factions as &$faction) {
                $rep = (int)$faction['reputation'];
                if ($rep >= 1000) $faction['rank_title'] = 'Легенда';
                elseif ($rep >= 750) $faction['rank_title'] = 'Герой';
                elseif ($rep >= 500) $faction['rank_title'] = 'Почетный член';
                elseif ($rep >= 250) $faction['rank_title'] = 'Друг';
                elseif ($rep >= 100) $faction['rank_title'] = 'Союзник';
                elseif ($rep >= 50) $faction['rank_title'] = 'Знакомый';
                elseif ($rep >= -50) $faction['rank_title'] = 'Нейтрал';
                elseif ($rep >= -100) $faction['rank_title'] = 'Подозрительный';
                elseif ($rep >= -250) $faction['rank_title'] = 'Враг';
                elseif ($rep >= -500) $faction['rank_title'] = 'Заклятый враг';
                else $faction['rank_title'] = 'Изгой';
                
                // Обновить титул в БД если изменился
                if ($faction['rank_title'] !== 'Незнакомец') {
                    $updateStmt = $pdo->prepare("
                        INSERT INTO player_faction_reputation (user_id, faction_id, reputation, rank_title)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rank_title = VALUES(rank_title)
                    ");
                    $updateStmt->execute([$userId, $faction['id'], $rep, $faction['rank_title']]);
                }
            }
            
            echo json_encode(['success' => true, 'factions' => $factions]);
            break;

        case 'update_reputation':
            // Изменить репутацию (вызывается после квестов, убийств и т.д.)
            $factionId = (int)($_POST['faction_id'] ?? 0);
            $change = (int)($_POST['change'] ?? 0);
            $reason = $_POST['reason'] ?? 'unknown';
            
            if ($factionId <= 0 || $change === 0) {
                throw new Exception('Некорректные параметры');
            }
            
            // Проверка существования фракции
            $checkStmt = $pdo->prepare("SELECT id FROM factions WHERE id = ?");
            $checkStmt->execute([$factionId]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Фракция не найдена');
            }
            
            // Обновление репутации
            $stmt = $pdo->prepare("
                INSERT INTO player_faction_reputation (user_id, faction_id, reputation, rank_title)
                VALUES (?, ?, 0, 'Незнакомец')
                ON DUPLICATE KEY UPDATE 
                    reputation = GREATEST(-1000, LEAST(1000, reputation + ?)),
                    last_action = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $factionId, $change]);
            
            // Логирование действия
            $logStmt = $pdo->prepare("
                INSERT INTO faction_action_log (user_id, faction_id, action_type, reputation_change)
                VALUES (?, ?, ?, ?)
            ");
            $logStmt->execute([$userId, $factionId, $reason, $change]);
            
            // Получить обновленный статус
            $statusStmt = $pdo->prepare("
                SELECT reputation, rank_title 
                FROM player_faction_reputation 
                WHERE user_id = ? AND faction_id = ?
            ");
            $statusStmt->execute([$userId, $factionId]);
            $newStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);
            
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Репутация изменена на {$change}",
                'new_reputation' => (int)$newStatus['reputation'],
                'new_rank' => $newStatus['rank_title']
            ]);
            break;

        case 'get_effects':
            // Получить бонусы от репутации
            $stmt = $pdo->prepare("
                SELECT f.name, pfr.reputation, pfr.rank_title
                FROM player_faction_reputation pfr
                JOIN factions f ON pfr.faction_id = f.id
                WHERE pfr.user_id = ? AND pfr.reputation != 0
            ");
            $stmt->execute([$userId]);
            $effects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $bonuses = [];
            foreach ($effects as $effect) {
                $rep = (int)$effect['reputation'];
                $bonus = [];
                
                if ($rep >= 500) {
                    $bonus[] = 'Скидка 20% у торговцев фракции';
                    $bonus[] = 'Доступ к уникальным квестам';
                } elseif ($rep >= 250) {
                    $bonus[] = 'Скидка 10% у торговцев фракции';
                } elseif ($rep >= 100) {
                    $bonus[] = 'Безопасный проход через территории';
                } elseif ($rep <= -500) {
                    $bonus[] = 'Атаки членов фракции при встрече';
                    $bonus[] = 'Отказ в торговле';
                } elseif ($rep <= -250) {
                    $bonus[] = 'Враждебность членов фракции';
                }
                
                if (!empty($bonus)) {
                    $bonuses[] = [
                        'faction' => $effect['name'],
                        'rank' => $effect['rank_title'],
                        'effects' => $bonus
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'bonuses' => $bonuses]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

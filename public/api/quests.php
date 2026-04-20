<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$player = getCurrentPlayer();
$characterId = $player['character_id'];
$pdo = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Получить доступные и активные квесты игрока
            $sql = "SELECT q.*, pq.status, pq.progress, pq.started_at, pq.completed_at
                    FROM quests q
                    LEFT JOIN player_quests pq ON q.id = pq.quest_id AND pq.character_id = ?
                    WHERE q.active = TRUE
                    ORDER BY 
                        CASE WHEN pq.status = 'active' THEN 0 ELSE 1 END,
                        q.id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$characterId]);
            $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'quests' => $quests]);
            break;

        case 'start':
            $questId = (int)($_POST['quest_id'] ?? 0);
            
            if (!$questId) {
                throw new Exception('Invalid quest ID');
            }
            
            // Проверка существования квеста
            $checkSql = "SELECT * FROM quests WHERE id = ? AND active = TRUE";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$questId]);
            $quest = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quest) {
                throw new Exception('Quest not found or inactive');
            }
            
            // Проверка, не выполняет ли игрок уже этот квест (если он не повторяемый)
            if (!$quest['is_repeatable']) {
                $existingSql = "SELECT id FROM player_quests WHERE character_id = ? AND quest_id = ? AND status != 'failed'";
                $existingStmt = $pdo->prepare($existingSql);
                $existingStmt->execute([$characterId, $questId]);
                if ($existingStmt->fetch()) {
                    throw new Exception('You already have this quest');
                }
            }
            
            // Начало квеста
            $insertSql = "INSERT INTO player_quests (character_id, quest_id, status, progress) VALUES (?, ?, 'active', 0)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$characterId, $questId]);
            
            echo json_encode([
                'success' => true,
                'message' => "Квест начат: {$quest['title']}"
            ]);
            break;

        case 'progress':
            // Обновление прогресса квеста (вызывается при убийстве монстра или сборе предмета)
            $questId = (int)($_POST['quest_id'] ?? 0);
            $increment = (int)($_POST['increment'] ?? 1);
            
            if (!$questId) {
                throw new Exception('Invalid quest ID');
            }
            
            // Получение текущего прогресса
            $sql = "SELECT pq.*, q.target_count 
                    FROM player_quests pq
                    JOIN quests q ON pq.quest_id = q.id
                    WHERE pq.character_id = ? AND pq.quest_id = ? AND pq.status = 'active'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$characterId, $questId]);
            $playerQuest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$playerQuest) {
                throw new Exception('Quest not active');
            }
            
            $newProgress = min($playerQuest['progress'] + $increment, $playerQuest['target_count']);
            
            // Обновление прогресса
            $updateSql = "UPDATE player_quests SET progress = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$newProgress, $playerQuest['id']]);
            
            $completed = ($newProgress >= $playerQuest['target_count']);
            
            echo json_encode([
                'success' => true,
                'progress' => $newProgress,
                'target' => $playerQuest['target_count'],
                'completed' => $completed
            ]);
            break;

        case 'complete':
            $questId = (int)($_POST['quest_id'] ?? 0);
            
            if (!$questId) {
                throw new Exception('Invalid quest ID');
            }
            
            // Проверка готовности квеста
            $sql = "SELECT pq.*, q.reward_caps, q.reward_xp, q.reward_item_id, q.reward_item_count, q.title
                    FROM player_quests pq
                    JOIN quests q ON pq.quest_id = q.id
                    WHERE pq.character_id = ? AND pq.quest_id = ? AND pq.status = 'active'
                    AND pq.progress >= q.target_count";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$characterId, $questId]);
            $playerQuest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$playerQuest) {
                throw new Exception('Quest not ready for completion');
            }
            
            // Транзакция завершения квеста
            $pdo->beginTransaction();
            
            // Выдача награды: крышки
            if ($playerQuest['reward_caps'] > 0) {
                $capsSql = "UPDATE characters SET caps = caps + ? WHERE id = ?";
                $capsStmt = $pdo->prepare($capsSql);
                $capsStmt->execute([$playerQuest['reward_caps'], $characterId]);
            }
            
            // Выдача награды: опыт
            if ($playerQuest['reward_xp'] > 0) {
                $xpSql = "UPDATE characters SET xp = xp + ? WHERE id = ?";
                $xpStmt = $pdo->prepare($xpSql);
                $xpStmt->execute([$playerQuest['reward_xp'], $characterId]);
                
                // Здесь можно добавить логику повышения уровня
            }
            
            // Выдача награды: предмет (ИСПРАВЛЕНО: character_items вместо user_items)
            if ($playerQuest['reward_item_id'] && $playerQuest['reward_item_count'] > 0) {
                $itemSql = "INSERT INTO character_items (character_id, item_id, quantity) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE quantity = quantity + ?";
                $itemStmt = $pdo->prepare($itemSql);
                $itemStmt->execute([
                    $characterId, 
                    $playerQuest['reward_item_id'], 
                    $playerQuest['reward_item_count'],
                    $playerQuest['reward_item_count']
                ]);
            }
            
            // Обновление статуса квеста
            $statusSql = "UPDATE player_quests SET status = 'completed', completed_at = NOW() WHERE id = ?";
            $statusStmt = $pdo->prepare($statusSql);
            $statusStmt->execute([$playerQuest['id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Квест завершен: {$playerQuest['title']}",
                'rewards' => [
                    'caps' => $playerQuest['reward_caps'],
                    'xp' => $playerQuest['reward_xp'],
                    'item_id' => $playerQuest['reward_item_id'],
                    'item_count' => $playerQuest['reward_item_count']
                ]
            ]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/combat.php';

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
    $action = $data['action'] ?? '';
    $combatId = (int)($data['combat_id'] ?? 0);
    
    $characterId = $player['character_id'];

    switch ($action) {
        case 'start':
            $monsterId = (int)($data['monster_id'] ?? 0);
            $locationId = (int)($data['location_id'] ?? 0);
            $dungeonNodeId = (int)($data['dungeon_node_id'] ?? 0);
            
            if ($monsterId <= 0) {
                throw new Exception("Монстр не указан.");
            }
            
            $result = startCombat($characterId, $monsterId, $locationId, $dungeonNodeId);
            echo json_encode($result);
            break;

        case 'attack':
            $targetIndex = (int)($data['target_index'] ?? 0);
            if ($combatId <= 0) throw new Exception("Бой не найден.");
            
            $result = combatAttack($combatId, $characterId, $targetIndex);
            echo json_encode($result);
            break;

        case 'flee':
            if ($combatId <= 0) throw new Exception("Бой не найден.");
            $result = fleeCombat($combatId, $characterId);
            echo json_encode($result);
            break;

        case 'use_item':
            $itemId = (int)($data['item_id'] ?? 0);
            if ($combatId <= 0 || $itemId <= 0) throw new Exception("Недостаточно данных.");
            
            $result = useItemInCombat($combatId, $characterId, $itemId);
            echo json_encode($result);
            break;

        case 'status':
            if ($combatId <= 0) throw new Exception("Бой не найден.");
            $stmt = $pdo->prepare("SELECT * FROM combats WHERE id = ?");
            $stmt->execute([$combatId]);
            $combat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$combat) throw new Exception("Бой завершен или не найден.");
            
            echo json_encode([
                'success' => true,
                'combat' => $combat,
                'enemies' => json_decode($combat['enemy_json'], true),
                'player' => getCharacterStats($characterId)
            ]);
            break;

        default:
            throw new Exception("Неизвестное действие боя.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

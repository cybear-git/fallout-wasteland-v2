<?php
/**
 * API: Начало боя
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/combat_engine.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$pdo = getDbConnection();
$characterId = getCurrentCharacterId();
$engine = new CombatEngine($pdo, $characterId);

$data = json_decode(file_get_contents('php://input'), true);
$monsterId = (int)($data['monster_id'] ?? 0);

if ($monsterId <= 0) {
    echo json_encode(['error' => 'Неверный ID монстра']);
    exit;
}

$result = $engine->startCombat($monsterId);
echo json_encode($result);

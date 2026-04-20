<?php
/**
 * API: Побег из боя
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
$sessionId = $data['session_id'] ?? '';

if (empty($sessionId)) {
    echo json_encode(['error' => 'Нет сессии боя']);
    exit;
}

$result = $engine->flee($sessionId);
echo json_encode($result);

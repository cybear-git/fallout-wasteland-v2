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

$centerX = (int)($_GET['x'] ?? 0);
$centerY = (int)($_GET['y'] ?? 0);
$radius = 4;

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT 
            mn.pos_x, mn.pos_y,
            lt.type_key, lt.type_name,
            l.danger_level, l.radiation_level, l.is_vault, l.is_dungeon
        FROM map_nodes mn
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        LEFT JOIN locations l ON l.id = mn.location_id
        WHERE mn.pos_x BETWEEN :x - :r AND :x + :r
          AND mn.pos_y BETWEEN :y - :r AND :y + :r
    ");
    $stmt->execute([
        ':x' => $centerX,
        ':y' => $centerY,
        ':r' => $radius
    ]);

    $nodes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nodes[] = [
            'dx' => (int)$row['pos_x'] - $centerX,
            'dy' => (int)$row['pos_y'] - $centerY,
            'pos_x' => (int)$row['pos_x'],
            'pos_y' => (int)$row['pos_y'],
            'type_key' => $row['type_key'] ?? 'wasteland',
            'type_name' => $row['type_name'] ?? 'Пустошь',
            'danger_level' => (int)($row['danger_level'] ?? 1),
            'rad_level' => (int)($row['radiation_level'] ?? 0),
            'is_vault' => (int)($row['is_vault'] ?? 0),
            'is_dungeon' => (int)($row['is_dungeon'] ?? 0)
        ];
    }

    echo json_encode([
        'success' => true,
        'center_x' => $centerX,
        'center_y' => $centerY,
        'nodes' => $nodes
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

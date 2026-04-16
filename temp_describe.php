<?php
$pdo = new PDO("mysql:host=localhost;dbname=fallout_wastelands_v2;charset=utf8mb4", "root", "12345678");

$tables = ['inventory', 'search_logs', 'player_ammo', 'items', 'loot_tables'];

foreach ($tables as $table) {
    echo "\n=== $table ===\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' ' . $row['Type'] . "\n";
    }
}

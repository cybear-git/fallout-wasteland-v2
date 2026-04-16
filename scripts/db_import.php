<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

echo "Starting database import...\n";

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $tables = [
        'map_adjacency', 'map_nodes', 'locations', 'players', 'characters',
        'inventory', 'weapons', 'armors', 'consumables', 'loot', 'monsters',
        'dungeons', 'dungeon_nodes', 'game_settings', 'admin_logs',
        'location_quotes', 'combat_logs', 'combats', 'loot_tables',
        'loot_table_items', 'player_effects', 'search_logs', 'player_ammo',
        'roles', 'location_types', 'dungeon_tile_types', 'equipment_slots',
        'effect_types', 'combat_states', 'ammo_types'
    ];
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Database cleared.\n";

    $sqlFiles = glob(__DIR__ . '/../database/0*.sql');
    sort($sqlFiles);
    
    foreach ($sqlFiles as $file) {
        $filename = basename($file);
        echo "Importing $filename... ";
        $errorCount = 0;
        
        $sql = file_get_contents($file);
        
        if (preg_match('/DELIMITER\s+\$\$/i', $sql)) {
            $parts = preg_split('/DELIMITER\s+\$\$/i', $sql);
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                $part = preg_replace('/DELIMITER\s*;?\s*$/i', '', $part);
                if (str_contains($part, 'CREATE TRIGGER') || str_contains($part, 'CREATE PROCEDURE')) {
                    $part = preg_replace('/\$\$/i', '', $part);
                }
                try {
                    $pdo->exec($part);
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
                        continue;
                    }
                    echo "\n  Warning: " . substr($msg, 0, 80) . "\n";
                    $errorCount++;
                }
            }
        } else {
            $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
            
            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
                        continue;
                    }
                    if (strpos($msg, 'Unknown column') !== false || strpos($msg, "doesn't exist") !== false) {
                        continue;
                    }
                    if (strpos($msg, 'Data truncated') !== false) {
                        continue;
                    }
                    echo "\n  Warning: " . substr($msg, 0, 80) . "\n";
                    $errorCount++;
                }
            }
        }
        
        echo "Done" . ($errorCount > 0 ? " ($errorCount warnings)" : "") . ".\n";
    }

    echo "\nDatabase import completed!\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

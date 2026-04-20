<?php
/**
 * 🌍 Fallout Wasteland V2 - Installer & World Generator
 * ⚠️ WARNING: This script will DROP and RECREATE the database. All data will be lost.
 * 
 * НОРМАЛИЗОВАННАЯ СТРУКТУРА БД:
 * - item_types: типы предметов
 * - items: общая таблица предметов
 * - weapon_attributes: атрибуты оружия
 * - armor_attributes: атрибуты брони
 * - consumable_attributes: атрибуты расходников
 * - loot_attributes: атрибуты лута
 * - inventory: инвентарь с item_id
 */

declare(strict_types=1);

// --- CONFIGURATION ---
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'fallout_v2';
$dbUser = 'root';      // CHANGE THIS
$dbPass = '12345678';          // CHANGE THIS
$charset = 'utf8mb4';

// --- SAFETY CHECKS ---
if (file_exists(__DIR__ . '/.installed')) {
    echo "\n\033[31m❌ ERROR: System already installed!\033[0m\n";
    echo "File '.installed' exists. If you want to reinstall, delete this file manually.\n";
    exit(1);
}

echo "\n\033[33m🌍 Fallout Wasteland V2 Installer\033[0m\n";
echo "================================\n";
echo "\033[31m⚠️  WARNING: This will DROP and RECREATE database '$dbName'. All data will be lost!\033[0m\n";
echo "Press Ctrl+C to cancel or wait 3 seconds...\n";
sleep(3);

// --- DATABASE CONNECTION ---
$dsn = "mysql:host=$dbHost;port=$dbPort;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    echo "\n🔌 Connecting to MySQL... ";
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "\033[32mOK\033[0m\n";
} catch (PDOException $e) {
    echo "\n\033[31m❌ CRITICAL: Connection failed!\033[0m\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check your credentials in install.php (lines 13-16).\n";
    exit(1);
}

// --- INSTALLATION PROCESS ---
$transactionStarted = false;

try {
    // 1. Drop and Create Database
    echo "🗑️  Dropping existing database (if any)... ";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "\033[32mDone\033[0m\n";

    echo "🆕 Creating database... ";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
    echo "\033[32mDone\033[0m\n";

    // Reconnect to the new database
    $dsnDb = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=$charset";
    $pdo = new PDO($dsnDb, $dbUser, $dbPass, $options);
    
    // 2. Start Transaction
    echo "💾 Starting transaction... ";
    $pdo->beginTransaction();
    $transactionStarted = true;
    echo "\033[32mOK\033[0m\n";

    // 3. Create Tables
    echo "🏗️  Creating tables...\n";
    
    $tables = [
        "players" => "CREATE TABLE players (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            `role` ENUM('player', 'admin', 'moderator') DEFAULT 'player',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        ) ENGINE=InnoDB",

        "characters" => "CREATE TABLE characters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            player_id INT UNSIGNED NOT NULL,
            `name` VARCHAR(50) NOT NULL,
            `level` INT UNSIGNED DEFAULT 1,
            experience BIGINT UNSIGNED DEFAULT 0,
            hp_current INT UNSIGNED DEFAULT 100,
            hp_max INT UNSIGNED DEFAULT 100,
            ap_current INT UNSIGNED DEFAULT 100,
            ap_max INT UNSIGNED DEFAULT 100,
            str INT UNSIGNED DEFAULT 5,
            per INT UNSIGNED DEFAULT 5,
            `end` INT UNSIGNED DEFAULT 5,
            cha INT UNSIGNED DEFAULT 5,
            `int` INT UNSIGNED DEFAULT 5,
            agi INT UNSIGNED DEFAULT 5,
            luk INT UNSIGNED DEFAULT 5,
            caps INT UNSIGNED DEFAULT 100,
            x_coord INT UNSIGNED DEFAULT 7,
            y_coord INT UNSIGNED DEFAULT 4,
            location_id INT UNSIGNED NULL,
            `status` ENUM('alive', 'dead', 'banned') DEFAULT 'alive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "items" => "CREATE TABLE items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `type` ENUM('weapon', 'armor', 'consumable', 'junk', 'ammo', 'misc') NOT NULL,
            `value` INT UNSIGNED DEFAULT 1,
            `weight` DECIMAL(5,2) DEFAULT 1.00,
            damage_min INT UNSIGNED DEFAULT 0,
            damage_max INT UNSIGNED DEFAULT 0,
            armor_class INT UNSIGNED DEFAULT 0,
            ammo_type VARCHAR(50) NULL,
            image_url VARCHAR(255) DEFAULT 'assets/img/items/unknown.png'
        ) ENGINE=InnoDB",

        "character_items" => "CREATE TABLE character_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            character_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED DEFAULT 1,
            equipped BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "locations" => "CREATE TABLE locations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `type` ENUM('wilderness', 'city', 'dungeon', 'landmark', 'shop') DEFAULT 'wilderness',
            x_coord INT UNSIGNED NOT NULL,
            y_coord INT UNSIGNED NOT NULL,
            danger_level INT UNSIGNED DEFAULT 1,
            loot_quality INT UNSIGNED DEFAULT 1,
            is_safe BOOLEAN DEFAULT FALSE,
            UNIQUE KEY unique_coords (x_coord, y_coord)
        ) ENGINE=InnoDB",

        "monsters" => "CREATE TABLE monsters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `level` INT UNSIGNED DEFAULT 1,
            hp_max INT UNSIGNED DEFAULT 50,
            damage_min INT UNSIGNED DEFAULT 5,
            damage_max INT UNSIGNED DEFAULT 10,
            xp_reward INT UNSIGNED DEFAULT 10,
            caps_reward_min INT UNSIGNED DEFAULT 5,
            caps_reward_max INT UNSIGNED DEFAULT 15,
            loot_table_id INT UNSIGNED NULL
        ) ENGINE=InnoDB",

        "combat_logs" => "CREATE TABLE combat_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            character_id INT UNSIGNED NOT NULL,
            monster_id INT UNSIGNED NOT NULL,
            result ENUM('win', 'loss', 'flee') NOT NULL,
            xp_gained INT UNSIGNED DEFAULT 0,
            caps_gained INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "factions" => "CREATE TABLE factions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            is_hidden BOOLEAN DEFAULT FALSE
        ) ENGINE=InnoDB",

        "character_factions" => "CREATE TABLE character_factions (
            character_id INT UNSIGNED NOT NULL,
            faction_id INT UNSIGNED NOT NULL,
            reputation_rank INT DEFAULT 0, -- Changed from 'rank'
            PRIMARY KEY (character_id, faction_id),
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
            FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "quests" => "CREATE TABLE quests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            objective TEXT,
            xp_reward INT UNSIGNED DEFAULT 100,
            caps_reward INT UNSIGNED DEFAULT 50,
            item_reward_id INT UNSIGNED NULL,
            is_repeatable BOOLEAN DEFAULT FALSE
        ) ENGINE=InnoDB",

        "character_quests" => "CREATE TABLE character_quests (
            character_id INT UNSIGNED NOT NULL,
            quest_id INT UNSIGNED NOT NULL,
            `status` ENUM('active', 'completed', 'failed') DEFAULT 'active',
            progress JSON NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            PRIMARY KEY (character_id, quest_id),
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
            FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "recipes" => "CREATE TABLE recipes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            result_item_id INT UNSIGNED NOT NULL,
            result_quantity INT UNSIGNED DEFAULT 1,
            skill_required INT UNSIGNED DEFAULT 0,
            FOREIGN KEY (result_item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "recipe_requirements" => "CREATE TABLE recipe_requirements (
            recipe_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            PRIMARY KEY (recipe_id, item_id),
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "game_settings" => "CREATE TABLE game_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            `description` VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    ];

    foreach ($tables as $tableName => $sql) {
        echo "   📦 Table: $tableName... ";
        $pdo->exec($sql);
        echo "\033[32mOK\033[0m\n";
    }

    // 4. Populate Data
    echo "📦 Populating data...\n";

    // Items
    $items = [
        ['10mm Pistol', 'Standard sidearm.', 'weapon', 50, 2.5, 8, 12, 0, '10mm'],
        ['Combat Shotgun', 'Heavy hitter.', 'weapon', 200, 8.0, 15, 25, 0, '12ga'],
        ['Stimpak', 'Heals 50 HP.', 'consumable', 25, 0.5, 0, 0, 0, null],
        ['Bottle Caps', 'Currency.', 'misc', 1, 0.01, 0, 0, 0, null],
        ['Scrap Metal', 'Crafting material.', 'junk', 5, 1.0, 0, 0, 0, null],
        ['Leather Armor', 'Basic protection.', 'armor', 40, 5.0, 0, 0, 5, null],
        ['10mm Round', 'Ammo for pistol.', 'ammo', 2, 0.05, 0, 0, 0, null],
    ];
    
    $stmtItem = $pdo->prepare("INSERT INTO items (name, description, type, value, weight, damage_min, damage_max, armor_class, ammo_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmtItem->execute($item);
    }
    echo "   ✅ Items inserted.\n";

    // Monsters
    $monsters = [
        ['Radroach', 'Giant cockroach.', 1, 30, 2, 5, 10, 5, 10],
        ['Feral Ghoul', 'Rotting human.', 2, 60, 5, 10, 20, 15, 25],
        ['Raider', 'Human scavenger.', 3, 80, 8, 15, 30, 25, 40],
        ['Super Mutant', 'Green behemoth.', 5, 200, 15, 25, 50, 100, 150],
    ];

    $stmtMon = $pdo->prepare("INSERT INTO monsters (name, description, level, hp_max, damage_min, damage_max, xp_reward, caps_reward_min, caps_reward_max) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($monsters as $mon) {
        $stmtMon->execute($mon);
    }
    echo "   ✅ Monsters inserted.\n";

    // Factions
    $factions = [
        ['Brotherhood of Steel', 'Tech guardians.'],
        ['Minutemen', 'Commonwealth defenders.'],
        ['Railroad', 'Synth liberators.'],
        ['Institute', 'Science elite.'],
    ];
    $pdo->exec("INSERT INTO factions (name, description) VALUES ('Brotherhood of Steel', 'Tech guardians'), ('Minutemen', 'Commonwealth defenders'), ('Railroad', 'Synth liberators'), ('Institute', 'Science elite')");
    echo "   ✅ Factions inserted.\n";

    // Settings
    $settings = [
        ['search_loot_base_chance', '0.4', 'Base chance for weapon drop (%)'],
        ['search_caps_chance_base', '15', 'Base chance to find caps (%)'],
        ['search_caps_min', '1', 'Min caps found'],
        ['search_caps_max', '3', 'Max caps found'],
        ['search_pity_timer_threshold', '50', 'Searches before guaranteed rare'],
    ];
    $stmtSet = $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    foreach ($settings as $set) {
        $stmtSet->execute($set);
    }
    echo "   ✅ Settings inserted.\n";

    // 5. Generate Map (16x9)
    echo "🗺️  Generating World Map (16x9)...\n";
    $width = 16;
    $height = 9;
    $centerX = intdiv($width, 2);
    $centerY = intdiv($height, 2);

    $stmtLoc = $pdo->prepare("INSERT INTO locations (name, description, type, x_coord, y_coord, danger_level, loot_quality, is_safe) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $distFromCenter = abs($x - $centerX) + abs($y - $centerY);
            
            // Logic for location types based on distance from center
            if ($x == $centerX && $y == $centerY) {
                $name = "Sanctuary Hills";
                $type = "city";
                $desc = "Your starting home. Safe zone.";
                $danger = 1;
                $loot = 1;
                $safe = true;
            } elseif ($distFromCenter <= 2) {
                $name = "Wasteland Outskirts";
                $type = "wilderness";
                $desc = "Relatively safe area near home.";
                $danger = 2;
                $loot = 2;
                $safe = false;
            } elseif ($distFromCenter <= 5) {
                $names = ["Ruined Highway", "Abandoned Farm", "Crater", "Radstorm Zone"];
                $name = $names[array_rand($names)];
                $type = "wilderness";
                $desc = "Dangerous open wasteland.";
                $danger = 4;
                $loot = 3;
                $safe = false;
            } else {
                $names = ["Super Mutant Camp", "Raiders Hideout", "Ghoul Infested Subway", "Mirelurk Nest"];
                $name = $names[array_rand($names)];
                $type = "landmark";
                $desc = "High danger zone with potential high rewards.";
                $danger = 7;
                $loot = 5;
                $safe = false;
            }

            $stmtLoc->execute([$name, $desc, $type, $x, $y, $danger, $loot, $safe]);
        }
    }
    echo "   ✅ 144 Locations generated.\n";

    // 6. Create Admin User
    echo "👤 Creating Administrator...\n";
    $adminPass = bin2hex(random_bytes(8)); // Secure random password
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    
    $pdo->exec("INSERT INTO players (username, password_hash, email, role) VALUES ('admin', '$hash', 'admin@wasteland.v2', 'admin')");
    
    // Get the player ID and create a character
    $playerId = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO characters (player_id, name, level, hp_max, hp_current, x_coord, y_coord) VALUES ($playerId, 'Admin Survivor', 10, 200, 200, $centerX, $centerY)");
    
    echo "   ✅ Admin created.\n";
    echo "   \033[33mLOGIN: admin\033[0m\n";
    echo "   \033[33mPASSWORD: $adminPass\033[0m (SAVE THIS!)\n";

    // 7. Commit Transaction
    $pdo->commit();
    $transactionStarted = false;

    // 8. Create Installation Marker
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s') . " | Admin Pass: $adminPass");

    echo "\n\033[32m✅ INSTALLATION COMPLETE!\033[0m\n";
    echo "================================\n";
    echo "⚠️  IMPORTANT: Delete 'install.php' immediately for security!\n";
    echo "rm install.php\n\n";

} catch (Exception $e) {
    if ($transactionStarted && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n\033[31m❌ ROLLBACK: Transaction failed due to error.\033[0m\n";
    }
    echo "\n\033[31m💥 CRITICAL ERROR:\033[0m " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}
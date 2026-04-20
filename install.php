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
    // 1. Drop and Create Database (outside transaction)
    echo "🗑️  Dropping existing database (if any)... ";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "\033[32mDone\033[0m\n";

    echo "🆕 Creating database... ";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
    echo "\033[32mDone\033[0m\n";

    // Reconnect to the new database
    $dsnDb = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=$charset";
    $pdo = new PDO($dsnDb, $dbUser, $dbPass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    

    // 2. Create Tables (DDL operations auto-commit)
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

        "item_types" => "CREATE TABLE item_types (
            id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type_key VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "items" => "CREATE TABLE items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_key VARCHAR(50) UNIQUE NOT NULL,
            item_type_id TINYINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            weight DECIMAL(5,2) DEFAULT 0.00,
            value INT UNSIGNED DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_key (item_key),
            INDEX idx_item_type (item_type_id),
            INDEX idx_active (is_active),
            CONSTRAINT fk_items_type FOREIGN KEY (item_type_id) REFERENCES item_types(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "weapon_attributes" => "CREATE TABLE weapon_attributes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL UNIQUE,
            dmg_dice TINYINT UNSIGNED DEFAULT 4,
            dmg_mod TINYINT SIGNED DEFAULT 0,
            crit_chance DECIMAL(4,1) DEFAULT 5.0,
            crit_mult DECIMAL(3,2) DEFAULT 1.5,
            range_type ENUM('melee', 'short', 'medium', 'long') DEFAULT 'melee',
            min_range TINYINT UNSIGNED DEFAULT 0,
            max_range TINYINT UNSIGNED DEFAULT 1,
            min_str TINYINT UNSIGNED DEFAULT 0,
            min_per TINYINT UNSIGNED DEFAULT 0,
            min_end TINYINT UNSIGNED DEFAULT 0,
            min_cha TINYINT UNSIGNED DEFAULT 0,
            min_int TINYINT UNSIGNED DEFAULT 0,
            min_agi TINYINT UNSIGNED DEFAULT 0,
            min_luk TINYINT UNSIGNED DEFAULT 0,
            ammo_type_id INT UNSIGNED DEFAULT NULL,
            CONSTRAINT fk_weapon_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_range (range_type),
            INDEX idx_damage (dmg_dice, dmg_mod)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "armor_attributes" => "CREATE TABLE armor_attributes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL UNIQUE,
            defense TINYINT UNSIGNED DEFAULT 0,
            rad_resistance TINYINT UNSIGNED DEFAULT 0,
            slot_type ENUM('head', 'tors', 'arms', 'legs', 'full_body') DEFAULT 'tors',
            min_str TINYINT UNSIGNED DEFAULT 0,
            min_per TINYINT UNSIGNED DEFAULT 0,
            min_end TINYINT UNSIGNED DEFAULT 0,
            min_cha TINYINT UNSIGNED DEFAULT 0,
            min_int TINYINT UNSIGNED DEFAULT 0,
            min_agi TINYINT UNSIGNED DEFAULT 0,
            min_luk TINYINT UNSIGNED DEFAULT 0,
            CONSTRAINT fk_armor_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_slot (slot_type),
            INDEX idx_defense (defense, rad_resistance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "consumable_attributes" => "CREATE TABLE consumable_attributes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL UNIQUE,
            heal_amount SMALLINT SIGNED DEFAULT 0,
            rad_heal SMALLINT SIGNED DEFAULT 0,
            addiction_chance DECIMAL(4,1) DEFAULT 0.0,
            boost_type VARCHAR(50) DEFAULT NULL,
            boost_value TINYINT SIGNED DEFAULT 0,
            boost_duration TINYINT UNSIGNED DEFAULT 0,
            effect_duration TINYINT UNSIGNED DEFAULT 0,
            special_effect VARCHAR(100) DEFAULT NULL,
            CONSTRAINT fk_consumable_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_boost (boost_type),
            INDEX idx_heal (heal_amount, rad_heal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "loot_attributes" => "CREATE TABLE loot_attributes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL UNIQUE,
            category ENUM('junk', 'key_item', 'quest', 'component', 'currency') DEFAULT 'junk',
            stackable TINYINT(1) DEFAULT 1,
            max_stack INT UNSIGNED DEFAULT 99,
            CONSTRAINT fk_loot_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_category (category),
            INDEX idx_stackable (stackable)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "inventory" => "CREATE TABLE inventory (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            character_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED DEFAULT 1,
            equipped TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_character (character_id),
            INDEX idx_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "locations" => "CREATE TABLE locations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `type` ENUM('wilderness', 'city', 'dungeon', 'landmark', 'shop') DEFAULT 'wilderness',
            x_coord INT UNSIGNED NOT NULL,
            y_coord INT UNSIGNED NOT NULL,
            danger_level INT UNSIGNED DEFAULT 1,
            loot_quality INT UNSIGNED DEFAULT 1,
            is_safe TINYINT(1) DEFAULT 0,
            UNIQUE KEY unique_coords (x_coord, y_coord)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "combat_logs" => "CREATE TABLE combat_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            character_id INT UNSIGNED NOT NULL,
            monster_id INT UNSIGNED NOT NULL,
            result ENUM('win', 'loss', 'flee') NOT NULL,
            xp_gained INT UNSIGNED DEFAULT 0,
            caps_gained INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "factions" => "CREATE TABLE factions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            is_hidden TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "character_factions" => "CREATE TABLE character_factions (
            character_id INT UNSIGNED NOT NULL,
            faction_id INT UNSIGNED NOT NULL,
            reputation_rank INT DEFAULT 0,
            PRIMARY KEY (character_id, faction_id),
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
            FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "quests" => "CREATE TABLE quests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            objective TEXT,
            xp_reward INT UNSIGNED DEFAULT 100,
            caps_reward INT UNSIGNED DEFAULT 50,
            item_reward_id INT UNSIGNED NULL,
            is_repeatable TINYINT(1) DEFAULT 0,
            FOREIGN KEY (item_reward_id) REFERENCES items(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "recipes" => "CREATE TABLE recipes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            result_item_id INT UNSIGNED NOT NULL,
            result_quantity INT UNSIGNED DEFAULT 1,
            skill_required INT UNSIGNED DEFAULT 0,
            FOREIGN KEY (result_item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "recipe_requirements" => "CREATE TABLE recipe_requirements (
            recipe_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            PRIMARY KEY (recipe_id, item_id),
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "game_settings" => "CREATE TABLE game_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            `description` VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $tableName => $sql) {
        echo "   📦 Table: $tableName... ";
        $pdo->exec($sql);
        echo "\033[32mOK\033[0m\n";
    }

    
    // 3. Start Transaction (after creating tables, before data insertion)
    echo "💾 Starting transaction... ";
    $pdo->beginTransaction();
    $transactionStarted = true;
    echo "\033[32mOK\033[0m\n";

    // 4. Populate Data
    echo "📦 Populating data...\n";

    // Item Types
    echo "   📋 Inserting item types...\n";
    $pdo->exec("INSERT INTO item_types (type_key, name, description) VALUES
        ('weapon', 'Оружие', 'Все виды оружия: от ножей до энергетического оружия'),
        ('armor', 'Броня', 'Защитное снаряжение: одежда, броня, шлемы'),
        ('consumable', 'Расходники', 'Еда, лекарства, стимуляторы'),
        ('loot', 'Лут', 'Разные предметы: мусор, компоненты, ключевые предметы')");
    echo "   ✅ Item types inserted.\n";

    // Items with attributes - using new normalized structure
    echo "   📦 Inserting items...\n";
    
    // Weapons
    $weapons = [
        ['switchblade', '1', 'Switchblade', 'Simple knife.', 0.5, 5, 1, 4, 1, 5.0, 1.5, 'melee', 0, 1, 0, 0, 0, 0, 0, 1, 0, null],
        ['baseball_bat', '1', 'Baseball Bat', 'Wooden bat.', 1.5, 10, 1, 6, 2, 10.0, 1.5, 'melee', 0, 1, 4, 0, 0, 0, 0, 1, 0, null],
        ['10mm_pistol', '1', '10mm Pistol', 'Standard sidearm.', 2.5, 50, 1, 8, 4, 15.0, 1.5, 'short', 5, 30, 0, 4, 0, 0, 0, 1, 0, null],
        ['combat_shotgun', '1', 'Combat Shotgun', 'Heavy hitter.', 8.0, 200, 1, 15, 10, 25.0, 1.5, 'short', 3, 15, 5, 0, 0, 0, 0, 1, 0, null],
        ['assault_rifle', '1', 'Assault Rifle', 'Automatic rifle.', 6.0, 350, 1, 12, 8, 20.0, 1.5, 'medium', 10, 100, 4, 4, 0, 0, 0, 1, 0, null],
    ];
    
    $stmtWeapon = $pdo->prepare("INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtWeaponAttr = $pdo->prepare("INSERT INTO weapon_attributes (item_id, dmg_dice, dmg_mod, crit_chance, crit_mult, range_type, min_range, max_range, min_str, min_per, min_end, min_cha, min_int, min_agi, min_luk, ammo_type_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($weapons as $w) {
        $stmtWeapon->execute([$w[0], $w[1], $w[2], $w[3], $w[4], $w[5], $w[6]]);
        $itemId = $pdo->lastInsertId();
        $stmtWeaponAttr->execute([$itemId, $w[7], $w[8], $w[9], $w[10], $w[11], $w[12], $w[13], $w[14], $w[15], $w[16], $w[17], $w[18], $w[19], $w[20], $w[21]]);
    }
    echo "   ✅ Weapons inserted.\n";

    // Armor
    $armors = [
        ['vault_suit', '2', 'Vault Suit', 'Standard jumpsuit.', 2.0, 10, 1, 1, 0, 'full_body', 0, 0, 0, 0, 0, 0, 0, 0],
        ['leather_armor', '2', 'Leather Armor', 'Basic protection.', 5.0, 40, 1, 3, 0, 'tors', 0, 0, 0, 0, 0, 0, 0],
        ['metal_armor', '2', 'Metal Armor', 'Heavy metal plating.', 12.0, 150, 1, 6, 5, 'tors', 4, 0, 5, 0, 0, 0, 0],
        ['combat_armor', '2', 'Combat Armor', 'Military grade.', 8.0, 300, 1, 8, 10, 'tors', 5, 4, 5, 0, 0, 0, 0],
        ['power_armor', '2', 'Power Armor T-45', 'Advanced power armor.', 25.0, 1000, 1, 15, 20, 'full_body', 8, 0, 10, 0, 0, 0, 0],
    ];
    
    $stmtArmor = $pdo->prepare("INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtArmorAttr = $pdo->prepare("INSERT INTO armor_attributes (item_id, defense, rad_resistance, slot_type, min_str, min_per, min_end, min_cha, min_int, min_agi, min_luk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($armors as $a) {
        $stmtArmor->execute([$a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6]]);
        $itemId = $pdo->lastInsertId();
        $stmtArmorAttr->execute([$itemId, $a[7], $a[8], $a[9], $a[10], $a[11], $a[12], $a[13], $a[14], $a[15], $a[16]]);
    }
    echo "   ✅ Armor inserted.\n";

    // Consumables
    $consumables = [
        ['stimpak', '3', 'Stimpak', 'Heals 50 HP.', 0.5, 25, 1, 50, 0, 5.0, null, 0, 0, 0, null],
        ['radaway', '3', 'RadAway', 'Removes radiation.', 0.5, 30, 1, 0, -50, 10.0, null, 0, 0, 0, null],
        ['food_can', '3', 'Canned Food', 'Restores hunger.', 0.8, 10, 1, 20, 0, 0.0, null, 0, 0, 0, null],
        ['buffout', '3', 'Buffout', '+2 STR for 10 min.', 0.3, 50, 1, 0, 0, 15.0, 'str', 2, 10, 60, null],
        ['mentats', '3', 'Mentats', '+2 INT for 10 min.', 0.3, 50, 1, 0, 0, 15.0, 'int', 2, 10, 60, null],
    ];
    
    $stmtConsumable = $pdo->prepare("INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtConsumableAttr = $pdo->prepare("INSERT INTO consumable_attributes (item_id, heal_amount, rad_heal, addiction_chance, boost_type, boost_value, boost_duration, effect_duration, special_effect) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($consumables as $c) {
        $stmtConsumable->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]]);
        $itemId = $pdo->lastInsertId();
        $stmtConsumableAttr->execute([$itemId, $c[7], $c[8], $c[9], $c[10], $c[11], $c[12], $c[13], $c[14]]);
    }
    echo "   ✅ Consumables inserted.\n";

    // Loot/Junk
    $loot = [
        ['scrap_metal', '4', 'Scrap Metal', 'Crafting material.', 1.0, 5, 1, 'component', 1, 99],
        ['bottle_caps', '4', 'Bottle Caps', 'Currency.', 0.01, 1, 1, 'currency', 1, 9999],
        ['prewar_money', '4', 'Pre-War Money', 'Worthless paper.', 0.1, 0, 1, 'junk', 1, 99],
        ['circuit_board', '4', 'Circuit Board', 'Electronics component.', 0.5, 15, 1, 'component', 1, 50],
        ['oil_can', '4', 'Oil Can', 'Lubricant.', 0.8, 8, 1, 'component', 1, 20],
    ];
    
    $stmtLoot = $pdo->prepare("INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtLootAttr = $pdo->prepare("INSERT INTO loot_attributes (item_id, category, stackable, max_stack) VALUES (?, ?, ?, ?)");
    
    foreach ($loot as $l) {
        $stmtLoot->execute([$l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6]]);
        $itemId = $pdo->lastInsertId();
        $stmtLootAttr->execute([$itemId, $l[7], $l[8], $l[9]]);
    }
    echo "   ✅ Loot/Junk inserted.\n";

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
    $pdo->exec("INSERT INTO factions (name, description) VALUES 
        ('Brotherhood of Steel', 'Tech guardians.'), 
        ('Minutemen', 'Commonwealth defenders.'), 
        ('Railroad', 'Synth liberators.'), 
        ('Institute', 'Science elite.')");
    echo "   ✅ Factions inserted.\n";

    // Settings
    $settings = [
        ['search_loot_base_chance', '0.4', 'Base chance for loot drop'],
        ['search_caps_chance_base', '15', 'Base chance to find caps (%)'],
        ['search_caps_min', '1', 'Min caps found'],
        ['search_caps_max', '3', 'Max caps found'],
        ['search_pity_timer_threshold', '50', 'Searches before guaranteed rare'],
        ['combat_xp_multiplier', '1.0', 'XP multiplier for combat'],
        ['crafting_enabled', '1', 'Enable crafting system'],
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
                $safe = 1; // Explicit integer for is_safe column
            } elseif ($distFromCenter <= 2) {
                $name = "Wasteland Outskirts";
                $type = "wilderness";
                $desc = "Relatively safe area near home.";
                $danger = 2;
                $loot = 2;
                $safe = 0; // Explicit integer for is_safe column
            } elseif ($distFromCenter <= 5) {
                $names = ["Ruined Highway", "Abandoned Farm", "Crater", "Radstorm Zone"];
                $name = $names[array_rand($names)];
                $type = "wilderness";
                $desc = "Dangerous open wasteland.";
                $danger = 4;
                $loot = 3;
                $safe = 0; // Explicit integer for is_safe column
            } else {
                $names = ["Super Mutant Camp", "Raiders Hideout", "Ghoul Infested Subway", "Mirelurk Nest"];
                $name = $names[array_rand($names)];
                $type = "landmark";
                $desc = "High danger zone with potential high rewards.";
                $danger = 7;
                $loot = 5;
                $safe = 0; // Explicit integer for is_safe column
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
<?php
/**
 * FALLOUT WASTELAND V2 - INSTALLER & WORLD GENERATOR
 * 
 * Этот скрипт полностью пересоздает базу данных, генерирует карту 16:9
 * и наполняет игру контентом. Старые миграции больше не нужны.
 * 
 * ИНСТРУКЦИЯ:
 * 1. Отредактируйте настройки подключения ниже.
 * 2. Запустите: php install.php
 * 3. Удалите этот файл после успешной установки!
 */

// ================= НАСТРОЙКИ ПОДКЛЮЧЕНИЯ =================
$dbHost = 'localhost';
$dbName = 'fallout_v2';
$dbUser = 'root';
$dbPass = ''; // Укажите ваш пароль

// Настройки мира
$mapWidth = 16;  // Ширина карты (пропорция 16:9 -> 16x9 или масштабированная)
$mapHeight = 9;  // Высота карты
$scaleFactor = 1; // Масштаб (1 = 16x9, 2 = 32x18 и т.д.)

$finalWidth = $mapWidth * $scaleFactor;
$finalHeight = $mapHeight * $scaleFactor;

echo "🌍 Fallout Wasteland V2 Installer\n";
echo "================================\n";
echo "Генерация карты: {$finalWidth}x{$finalHeight} (" . ($finalWidth * $finalHeight) . " локаций)\n\n";

try {
    // 1. Подключение к серверу (без выбора БД, так как будем её создавать/ронять)
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "✅ Подключение к MySQL успешно.\n";

    // 2. Удаление старой базы (если есть) и создание новой
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    
    echo "✅ База данных `$dbName` пересоздана.\n";

    // 3. Начало транзакции (все или ничего)
    $pdo->beginTransaction();

    // ================= СОЗДАНИЕ ТАБЛИЦ =================
    
    // Пользователи и персонажи
    $pdo->exec("CREATE TABLE players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        role ENUM('player', 'admin', 'super_admin') DEFAULT 'player',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        level INT DEFAULT 1,
        experience INT DEFAULT 0,
        hp INT DEFAULT 100,
        max_hp INT DEFAULT 100,
        ap INT DEFAULT 10,
        max_ap INT DEFAULT 10,
        strength INT DEFAULT 5,
        perception INT DEFAULT 5,
        endurance INT DEFAULT 5,
        charisma INT DEFAULT 5,
        intelligence INT DEFAULT 5,
        agility INT DEFAULT 5,
        luck INT DEFAULT 5,
        caps INT DEFAULT 100,
        x_coord INT DEFAULT 0,
        y_coord INT DEFAULT 0,
        location_id INT DEFAULT 1,
        status ENUM('alive', 'dead', 'banned') DEFAULT 'alive',
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Предметы
    $pdo->exec("CREATE TABLE items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        type ENUM('weapon', 'armor', 'consumable', 'junk', 'ammo', 'misc') NOT NULL,
        rarity ENUM('common', 'uncommon', 'rare', 'legendary') DEFAULT 'common',
        value INT DEFAULT 1,
        weight FLOAT DEFAULT 1.0,
        damage INT DEFAULT 0,
        defense INT DEFAULT 0,
        heal_amount INT DEFAULT 0,
        ammo_type VARCHAR(20),
        image_url VARCHAR(255) DEFAULT 'assets/img/items/unknown.png'
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE character_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT DEFAULT 1,
        equipped BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Локации и Карта
    $pdo->exec("CREATE TABLE locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        type ENUM('city', 'dungeon', 'wilderness', 'landmark', 'shop') NOT NULL,
        x_coord INT NOT NULL,
        y_coord INT NOT NULL,
        difficulty INT DEFAULT 1,
        loot_quality INT DEFAULT 1,
        monster_spawn_chance FLOAT DEFAULT 0.3,
        is_safe_zone BOOLEAN DEFAULT FALSE,
        music_track VARCHAR(50) DEFAULT 'wasteland_ambience.mp3',
        UNIQUE KEY unique_coords (x_coord, y_coord)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE location_connections (
        location_from INT NOT NULL,
        location_to INT NOT NULL,
        distance INT DEFAULT 1,
        PRIMARY KEY (location_from, location_to),
        FOREIGN KEY (location_from) REFERENCES locations(id) ON DELETE CASCADE,
        FOREIGN KEY (location_to) REFERENCES locations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Монстры
    $pdo->exec("CREATE TABLE monsters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        level INT DEFAULT 1,
        hp INT DEFAULT 20,
        damage_min INT DEFAULT 2,
        damage_max INT DEFAULT 5,
        xp_reward INT DEFAULT 10,
        loot_table_id INT,
        image_url VARCHAR(255) DEFAULT 'assets/img/monsters/radroach.png'
    ) ENGINE=InnoDB");

    // Бой
    $pdo->exec("CREATE TABLE combat_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        monster_id INT NOT NULL,
        result ENUM('win', 'loss', 'flee'),
        xp_gained INT DEFAULT 0,
        loot_received TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Настройки игры (для админки)
    $pdo->exec("CREATE TABLE game_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        category VARCHAR(50) DEFAULT 'general',
        description TEXT
    ) ENGINE=InnoDB");

    // Квесты
    $pdo->exec("CREATE TABLE quests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        objective TEXT,
        reward_xp INT DEFAULT 0,
        reward_caps INT DEFAULT 0,
        reward_item_id INT,
        min_level INT DEFAULT 1,
        is_repeatable BOOLEAN DEFAULT FALSE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE character_quests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        quest_id INT NOT NULL,
        status ENUM('available', 'active', 'completed', 'failed') DEFAULT 'available',
        progress INT DEFAULT 0,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
        UNIQUE KEY unique_char_quest (character_id, quest_id)
    ) ENGINE=InnoDB");

    // Фракции
    $pdo->exec("CREATE TABLE factions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        reputation_step INT DEFAULT 10
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE character_factions (
        character_id INT NOT NULL,
        faction_id INT NOT NULL,
        reputation INT DEFAULT 0,
        rank VARCHAR(50) DEFAULT 'Neutral',
        PRIMARY KEY (character_id, faction_id),
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Крафт
    $pdo->exec("CREATE TABLE crafting_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        output_item_id INT NOT NULL,
        skill_required VARCHAR(20) DEFAULT 'science',
        level_required INT DEFAULT 1,
        time_seconds INT DEFAULT 5,
        FOREIGN KEY (output_item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE crafting_requirements (
        recipe_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL,
        PRIMARY KEY (recipe_id, item_id),
        FOREIGN KEY (recipe_id) REFERENCES crafting_recipes(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Торговцы
    $pdo->exec("CREATE TABLE vendors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        location_id INT,
        caps_reset_hours INT DEFAULT 24,
        current_caps INT DEFAULT 500,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE vendor_inventory (
        vendor_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT DEFAULT 1,
        price_modifier FLOAT DEFAULT 1.0,
        PRIMARY KEY (vendor_id, item_id),
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    echo "✅ Таблицы созданы.\n";

    // ================= НАПОЛНЕНИЕ ДАННЫМИ =================

    // 1. Базовые предметы
    $items = [
        ['Pipe Gun', 'Weapon', 'common', 10, 5, 0],
        ['Laser Pistol', 'Weapon', 'uncommon', 50, 15, 0],
        ['Combat Armor', 'Armor', 'uncommon', 100, 0, 10],
        ['Stimpak', 'Consumable', 'common', 25, 0, 0, 20],
        ['RadAway', 'Consumable', 'common', 30, 0, 0],
        ['Bottle Caps', 'Misc', 'common', 1, 0, 0],
        ['Scrap Metal', 'Junk', 'common', 2, 1, 0],
        ['Circuitry', 'Junk', 'uncommon', 5, 0.5, 0],
        ['5mm Round', 'Ammo', 'common', 1, 0.05, 0],
        ['Energy Cell', 'Ammo', 'common', 2, 0.05, 0],
    ];

    $stmt = $pdo->prepare("INSERT INTO items (name, type, rarity, value, damage, defense, heal_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $heal = isset($item[7]) ? $item[7] : 0;
        $stmt->execute([$item[0], strtolower($item[1]), $item[2], $item[3], $item[4], $item[5], $heal]);
    }
    echo "✅ Предметы добавлены.\n";

    // 2. Монстры
    $monsters = [
        ['Radroach', 'Гигантский таракан', 1, 15, 2, 4, 5],
        ['Mole Rat', 'Крот-крыса', 2, 25, 3, 6, 10],
        ['Feral Ghoul', 'Дикий гуль', 3, 40, 5, 8, 20],
        ['Raider', 'Мародер', 4, 50, 6, 10, 25],
        ['Super Mutant', 'Супермутант', 6, 80, 8, 12, 50],
        ['Deathclaw', 'Коготь смерти', 10, 150, 15, 25, 200],
    ];
    $stmt = $pdo->prepare("INSERT INTO monsters (name, description, level, hp, damage_min, damage_max, xp_reward) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($monsters as $m) {
        $stmt->execute($m);
    }
    echo "✅ Монстры добавлены.\n";

    // 3. Фракции
    $factions = [
        ['Brotherhood of Steel', 'Технократы, хранители оружия'],
        ['Minutemen', 'Защитники Содружества'],
        ['Railroad', 'Освободители синтов'],
        ['Institute', 'Подземные ученые'],
    ];
    $stmt = $pdo->prepare("INSERT INTO factions (name, description) VALUES (?, ?)");
    foreach ($factions as $f) {
        $stmt->execute($f);
    }
    echo "✅ Фракции добавлены.\n";

    // 4. Генерация карты (Алгоритм 16:9)
    echo "🗺️ Генерация карты {$finalWidth}x{$finalHeight}...\n";
    $locStmt = $pdo->prepare("INSERT INTO locations (name, type, x_coord, y_coord, description, difficulty, loot_quality, monster_spawn_chance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $connStmt = $pdo->prepare("INSERT INTO location_connections (location_from, location_to, distance) VALUES (?, ?, ?)");

    $types = ['wilderness', 'wilderness', 'wilderness', 'landmark', 'city'];
    $names = ['Ruins', 'Wasteland', 'Crater', 'Outpost', 'Settlement', 'Cave', 'Forest', 'Highway'];
    
    $locationIds = [];

    for ($y = 0; $y < $finalHeight; $y++) {
        for ($x = 0; $x < $finalWidth; $x++) {
            // Центр карты - безопасная зона (Стартовая локация)
            $isCenter = ($x == floor($finalWidth/2) && $y == floor($finalHeight/2));
            
            if ($isCenter) {
                $type = 'city';
                $name = 'Sanctuary Hills';
                $safe = true;
                $diff = 1;
                $loot = 5;
                $spawn = 0.0;
                $desc = 'Безопасное убежище. Стартовая точка.';
            } else {
                $type = $types[array_rand($types)];
                $name = $names[array_rand($names)] . " Sector {$x}-{$y}";
                $safe = false;
                $diff = rand(1, 5) + floor(($x+$y)/4);
                $loot = rand(1, 3);
                $spawn = 0.2 + ($diff * 0.05);
                $desc = "Пустошь сектор {$x}:{$y}. Опасность: " . ($diff > 5 ? 'ЭКСТРЕМАЛЬНАЯ' : 'НОРМАЛЬНАЯ');
            }

            $locStmt->execute([$name, $type, $x, $y, $desc, $diff, $loot, $spawn]);
            $locationIds[$x][$y] = $pdo->lastInsertId();
        }
    }

    // Создание связей (соседние клетки)
    foreach ($locationIds as $x => $row) {
        foreach ($row as $y => $id) {
            // Связь вправо
            if (isset($locationIds[$x+1][$y])) {
                $target = $locationIds[$x+1][$y];
                $connStmt->execute([$id, $target, 1]);
                $connStmt->execute([$target, $id, 1]);
            }
            // Связь вниз
            if (isset($locationIds[$x][$y+1])) {
                $target = $locationIds[$x][$y+1];
                $connStmt->execute([$id, $target, 1]);
                $connStmt->execute([$target, $id, 1]);
            }
        }
    }
    echo "✅ Карта сгенерирована и связана.\n";

    // 5. Настройки шансов (из админки)
    $settings = [
        ['search_loot_base_chance', '60', 'loot', 'Базовый шанс найти лут'],
        ['search_caps_chance_base', '15', 'loot', 'Базовый шанс найти крышки'],
        ['search_weapon_chance', '0.4', 'loot', 'Шанс найти оружие (%)'],
        ['search_armor_chance', '0.2', 'loot', 'Шанс найти броню (%)'],
        ['search_consumable_chance', '2.0', 'loot', 'Шанс найти расходник (%)'],
        ['combat_xp_multiplier', '1.0', 'combat', 'Множитель опыта'],
    ];
    $stmt = $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    echo "✅ Настройки игры добавлены.\n";

    // 6. Создание Администратора
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO players (username, password_hash, role, email) VALUES ('admin', '$adminPass', 'super_admin', 'admin@fallout.local')");
    $playerId = $pdo->lastInsertId();
    
    $pdo->exec("INSERT INTO characters (player_id, name, level, hp, max_hp, x_coord, y_coord, location_id) 
                VALUES ($playerId, 'Admin Commander', 10, 200, 200, " . floor($finalWidth/2) . ", " . floor($finalHeight/2) . ", " . $locationIds[floor($finalWidth/2)][floor($finalHeight/2)] . ")");
    
    echo "✅ Аккаунт администратора создан.\n";
    echo "   Логин: admin\n";
    echo "   Пароль: admin123\n";

    // Коммит транзакции
    $pdo->commit();

    echo "\n🎉 УСТАНОВКА ЗАВЕРШЕНА УСПЕШНО!\n";
    echo "================================\n";
    echo "База данных готова к работе.\n";
    echo "Не забудьте удалить файл install.php перед запуском сайта!\n";

} catch (PDOException $e) {
    // Откат при ошибке
    if (isset($pdo)) {
        try { $pdo->rollBack(); } catch (Exception $i) {}
    }
    die("\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n");
}
?>

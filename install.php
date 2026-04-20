<?php
/**
 * PIP-BOY WASTELAND: CLEAN INSTALL SCRIPT
 * 
 * Этот скрипт полностью пересоздает базу данных с нуля.
 * Он заменяет все предыдущие миграции (001-034).
 * 
 * ИНСТРУКЦИЯ:
 * 1. Отредактируйте конфигурацию ниже ($dbConfig).
 * 2. Запустите через CLI: php install.php
 * 3. Или через браузер: http://your-site/install.php (только для локальной разработки!)
 */

// === КОНФИГУРАЦИЯ ===
$dbConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'pipboy_wasteland',
    'user' => 'root',      // ИЗМЕНИТЕ НА СВОЕГО
    'pass' => '',          // ИЗМЕНИТЕ НА СВОЙ ПАРОЛЬ
    'charset' => 'utf8mb4',
];

$adminConfig = [
    'username' => 'admin',
    'password' => 'admin123', // СМЕНите сразу после входа!
    'email'    => 'admin@wasteland.local',
];

// === ПОДКЛЮЧЕНИЕ К MySQL (без выбора БД) ===
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
        $dbConfig['user'],
        $dbConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Подключено к MySQL серверу.\n";
} catch (PDOException $e) {
    die("❌ Ошибка подключения к MySQL: " . $e->getMessage() . "\n");
}

// === 1. СОЗДАНИЕ БАЗЫ ДАННЫХ ===
$dbName = $dbConfig['name'];
$pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
$pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");
echo "✅ База данных `$dbName` пересоздана.\n";

// === 2. СОЗДАНИЕ ТАБЛИЦ (ОЧИЩЕННАЯ СХЕМА) ===
$queries = [
    // --- ЯДРО ---
    "CREATE TABLE `players` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `role` ENUM('player', 'moderator', 'admin') DEFAULT 'player',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_login` TIMESTAMP NULL
    ) ENGINE=InnoDB;",

    "CREATE TABLE `characters` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `player_id` INT UNSIGNED NOT NULL,
        `name` VARCHAR(50) NOT NULL,
        `level` INT UNSIGNED DEFAULT 1,
        `xp` INT UNSIGNED DEFAULT 0,
        `hp_current` INT UNSIGNED DEFAULT 100,
        `hp_max` INT UNSIGNED DEFAULT 100,
        `ap_current` INT UNSIGNED DEFAULT 100,
        `ap_max` INT UNSIGNED DEFAULT 100,
        `str` TINYINT UNSIGNED DEFAULT 5,
        `per` TINYINT UNSIGNED DEFAULT 5,
        `end` TINYINT UNSIGNED DEFAULT 5,
        `cha` TINYINT UNSIGNED DEFAULT 5,
        `int` TINYINT UNSIGNED DEFAULT 5,
        `agi` TINYINT UNSIGNED DEFAULT 5,
        `luk` TINYINT UNSIGNED DEFAULT 5,
        `caps` INT UNSIGNED DEFAULT 50,
        `location_id` INT UNSIGNED DEFAULT 1,
        `status` ENUM('alive', 'dead', 'banned') DEFAULT 'alive',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    // --- ПРЕДМЕТЫ (UNIFIED) ---
    "CREATE TABLE `items` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `type` ENUM('weapon', 'armor', 'consumable', 'misc', 'junk', 'ammo', 'quest') NOT NULL,
        `rarity` ENUM('common', 'uncommon', 'rare', 'legendary') DEFAULT 'common',
        `value` INT UNSIGNED DEFAULT 0,
        `weight` DECIMAL(5,2) DEFAULT 0.0,
        `damage_min` INT UNSIGNED DEFAULT 0,
        `damage_max` INT UNSIGNED DEFAULT 0,
        `armor_class` INT UNSIGNED DEFAULT 0,
        `consumable_effect` VARCHAR(100),
        `consumable_value` INT DEFAULT 0,
        `icon` VARCHAR(50) DEFAULT 'default.png',
        `stackable` BOOLEAN DEFAULT TRUE,
        `max_stack` INT UNSIGNED DEFAULT 999
    ) ENGINE=InnoDB;",

    "CREATE TABLE `character_items` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `character_id` INT UNSIGNED NOT NULL,
        `item_id` INT UNSIGNED NOT NULL,
        `quantity` INT UNSIGNED DEFAULT 1,
        `equipped` BOOLEAN DEFAULT FALSE,
        `slot` ENUM('head', 'body', 'arms', 'legs', 'main_hand', 'off_hand', 'none') DEFAULT 'none',
        `durability` INT UNSIGNED DEFAULT 100,
        `obtained_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_item_slot` (`character_id`, `item_id`, `slot`) -- Упрощено для примера
    ) ENGINE=InnoDB;",

    # --- МИР И ЛОКАЦИИ ---
    "CREATE TABLE `locations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `type` ENUM('town', 'dungeon', 'wilderness', 'landmark') DEFAULT 'wilderness',
        `x` INT NOT NULL DEFAULT 0,
        `y` INT NOT NULL DEFAULT 0,
        `z` INT NOT NULL DEFAULT 0,
        `parent_id` INT UNSIGNED NULL, -- Для подземелий внутри локаций
        `danger_level` TINYINT UNSIGNED DEFAULT 1,
        `loot_quality` TINYINT UNSIGNED DEFAULT 1,
        `music_track` VARCHAR(50),
        `background_image` VARCHAR(100),
        FOREIGN KEY (`parent_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB;",

    "CREATE TABLE `map_nodes` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `location_id` INT UNSIGNED NOT NULL,
        `connections` JSON, -- Массив ID связанных локаций
        `discovered_by` JSON, -- Массив character_id
        FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    # --- БОЙ И СУЩНОСТИ ---
    "CREATE TABLE `monsters` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `level` TINYINT UNSIGNED NOT NULL,
        `hp` INT UNSIGNED NOT NULL,
        `damage_min` INT UNSIGNED NOT NULL,
        `damage_max` INT UNSIGNED NOT NULL,
        `xp_reward` INT UNSIGNED NOT NULL,
        `caps_reward_min` INT UNSIGNED DEFAULT 0,
        `caps_reward_max` INT UNSIGNED DEFAULT 0,
        `loot_table_id` INT UNSIGNED NULL,
        `image` VARCHAR(100)
    ) ENGINE=InnoDB;",

    "CREATE TABLE `combat_logs` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `character_id` INT UNSIGNED NOT NULL,
        `monster_id` INT UNSIGNED NOT NULL,
        `result` ENUM('win', 'loss', 'flee') NOT NULL,
        `xp_gained` INT UNSIGNED DEFAULT 0,
        `caps_gained` INT UNSIGNED DEFAULT 0,
        `occurred_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    # --- КВЕСТЫ И ТОРГОВЛЯ ---
    "CREATE TABLE `quests` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `objective` TEXT,
        `reward_xp` INT UNSIGNED DEFAULT 0,
        `reward_caps` INT UNSIGNED DEFAULT 0,
        `reward_item_id` INT UNSIGNED NULL,
        `min_level` TINYINT UNSIGNED DEFAULT 1,
        `is_repeatable` BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (`reward_item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB;",

    "CREATE TABLE `character_quests` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `character_id` INT UNSIGNED NOT NULL,
        `quest_id` INT UNSIGNED NOT NULL,
        `status` ENUM('available', 'active', 'completed', 'failed') DEFAULT 'available',
        `progress` JSON, -- {"kill_count": 5, "items_collected": [...]}
        `started_at` TIMESTAMP NULL,
        `completed_at` TIMESTAMP NULL,
        FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_quest` (`character_id`, `quest_id`)
    ) ENGINE=InnoDB;",

    "CREATE TABLE `vendors` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `location_id` INT UNSIGNED NOT NULL,
        `caps_reserve` INT UNSIGNED DEFAULT 500,
        `inventory_refresh_hours` INT UNSIGNED DEFAULT 24,
        FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    "CREATE TABLE `vendor_items` (
        `vendor_id` INT UNSIGNED NOT NULL,
        `item_id` INT UNSIGNED NOT NULL,
        `quantity` INT UNSIGNED DEFAULT -1, -- -1 означает бесконечно
        `price_modifier` DECIMAL(3,2) DEFAULT 1.00,
        PRIMARY KEY (`vendor_id`, `item_id`),
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    # --- ФРАКЦИИ ---
    "CREATE TABLE `factions` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `description` TEXT,
        `base_relation` INT DEFAULT 0 -- -1000 до 1000
    ) ENGINE=InnoDB;",

    "CREATE TABLE `character_factions` (
        `character_id` INT UNSIGNED NOT NULL,
        `faction_id` INT UNSIGNED NOT NULL,
        `reputation` INT DEFAULT 0,
        `rank` VARCHAR(50) DEFAULT 'Stranger',
        PRIMARY KEY (`character_id`, `faction_id`),
        FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`faction_id`) REFERENCES `factions`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    # --- НАСТРОЙКИ И АДМИНКА ---
    "CREATE TABLE `game_settings` (
        `key_name` VARCHAR(50) PRIMARY KEY,
        `value` TEXT,
        `type` ENUM('int', 'float', 'string', 'json') DEFAULT 'string',
        `description` VARCHAR(255)
    ) ENGINE=InnoDB;",

    "CREATE TABLE `admin_logs` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT UNSIGNED NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`admin_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "⚠️ Ошибка при создании таблицы: " . $e->getMessage() . "\n";
        // Не прерываем, чтобы увидеть все ошибки сразу
    }
}
echo "✅ Таблицы созданы.\n";

// === 3. ГЕНЕРАЦИЯ КАРТЫ (16:9) ===
echo "🗺️ Генерация карты мира...\n";
$mapWidth = 16;
$mapHeight = 9;
$centerX = intdiv($mapWidth, 2);
$centerY = intdiv($mapHeight, 2);

// Вставка стартовой локации
$pdo->exec("INSERT INTO `locations` (`name`, `type`, `x`, `y`, `danger_level`, `loot_quality`) 
            VALUES ('Убежище 101', 'town', $centerX, $centerY, 1, 1)");
$startLocationId = $pdo->lastInsertId();

// Генерация сетки
$stmt = $pdo->prepare("INSERT INTO `locations` (`name`, `type`, `x`, `y`, `danger_level`, `loot_quality`) VALUES (?, ?, ?, ?, ?, ?)");
$types = ['wilderness', 'landmark', 'dungeon'];
$names = ['Руины', 'Пещера', 'Лагерь рейдеров', 'Заброшенный бункер', 'Радиоактивное озеро', 'Старая заправка'];

for ($y = 0; $y < $mapHeight; $y++) {
    for ($x = 0; $x < $mapWidth; $x++) {
        if ($x == $centerX && $y == $centerY) continue; // Пропускаем центр
        
        $dist = sqrt(pow($x - $centerX, 2) + pow($y - $centerY, 2));
        $danger = min(10, floor($dist / 1.5) + 1);
        $loot = max(1, 5 - floor($dist / 3));
        
        $typeIdx = ($danger > 7) ? 2 : (($danger > 4) ? 1 : 0);
        $name = $names[array_rand($names)] . " ($x:$y)";
        
        $stmt->execute([$name, $types[$typeIdx], $x, $y, $danger, $loot]);
    }
}

// Заполнение связей (простой алгоритм: связь с соседями)
$locStmt = $pdo->query("SELECT id, x, y FROM locations");
$locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
$mapStmt = $pdo->prepare("INSERT INTO `map_nodes` (`location_id`, `connections`) VALUES (?, ?)");

foreach ($locations as $loc) {
    $connections = [];
    foreach ($locations as $neighbor) {
        $dist = abs($loc['x'] - $neighbor['x']) + abs($loc['y'] - $neighbor['y']);
        if ($dist == 1) { // Только ортогональные соседи
            $connections[] = $neighbor['id'];
        }
    }
    $mapStmt->execute([$loc['id'], json_encode($connections)]);
}
echo "✅ Карта сгенерирована ({$mapWidth}x{$mapHeight}).\n";

// === 4. НАПОЛНЕНИЕ ДАННЫМИ (ITEMS, MONSTERS) ===
echo "📦 Наполнение справочников...\n";

// Предметы
$items = [
    ['10mm Пистолет', 'weapon', 10, 5, 15, 0, 0],
    ['Бита', 'weapon', 5, 3, 8, 0, 0],
    ['Лазерный пистолет', 'weapon', 50, 15, 25, 0, 0],
    ['Кожаная куртка', 'armor', 15, 0, 0, 5, 0],
    ['Броня рейдера', 'armor', 40, 0, 0, 15, 0],
    ['Стимулятор', 'consumable', 25, 0, 0, 0, 20, 'heal', 50],
    ['Ядер-кола', 'consumable', 5, 0, 0, 0, 5, 'heal', 10],
    ['Крышка от бутылки', 'junk', 1, 0, 0, 0, 0],
    ['Изолента', 'junk', 5, 0, 0, 0, 0],
    ['Микросхема', 'junk', 10, 0, 0, 0, 0],
];

$itemStmt = $pdo->prepare("INSERT INTO `items` (`name`, `type`, `value`, `weight`, `damage_min`, `damage_max`, `armor_class`, `consumable_effect`, `consumable_value`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($items as $item) {
    $itemStmt->execute($item);
}

// Монстры
$monsters = [
    ['Гулль-бродяга', 1, 30, 2, 5, 10, 5, 15],
    ['Рейдер', 2, 50, 5, 10, 25, 10, 30],
    ['Дикий пес', 1, 20, 3, 6, 15, 5, 10],
    ['Супермутант', 5, 150, 15, 25, 100, 20, 50],
    ['Коготь смерти', 8, 300, 30, 50, 250, 50, 100],
];

$monsterStmt = $pdo->prepare("INSERT INTO `monsters` (`name`, `level`, `hp`, `damage_min`, `damage_max`, `xp_reward`, `caps_reward_min`, `caps_reward_max`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($monsters as $m) {
    $monsterStmt->execute($m);
}

// Фракции
$factions = [
    ['Братство Стали', 'Технократы, хранящие знания.', 0],
    ['Институт', 'Подземные ученые, создающие синтов.', -200],
    ['Минитмены', 'Защитники простых людей.', 100],
    ['Подземка', 'Помощь синтам и беглецам.', 50],
];
$factionStmt = $pdo->prepare("INSERT INTO `factions` (`name`, `description`, `base_relation`) VALUES (?, ?, ?)");
foreach ($factions as $f) {
    $factionStmt->execute($f);
}

// Настройки шансов (из предыдущих требований)
$settings = [
    ['search_loot_base_chance', '60', 'int', 'Базовый шанс найти мусор (%)'],
    ['search_caps_chance_base', '15', 'float', 'Базовый шанс найти крышки (%)'],
    ['search_weapon_chance', '0.4', 'float', 'Шанс найти оружие (%)'],
    ['search_armor_chance', '0.2', 'float', 'Шанс найти броню (%)'],
    ['search_consumable_chance', '2.0', 'float', 'Шанс найти расходники (%)'],
    ['search_pity_timer_threshold', '50', 'int', 'Поисков до гарантированного лута'],
];
$setStmt = $pdo->prepare("INSERT INTO `game_settings` (`key_name`, `value`, `type`, `description`) VALUES (?, ?, ?, ?)");
foreach ($settings as $s) {
    $setStmt->execute($s);
}

echo "✅ Справочники наполнены.\n";

// === 5. СОЗДАНИЕ АДМИНА ===
echo "👤 Создание администратора...\n";
$hash = password_hash($adminConfig['password'], PASSWORD_DEFAULT);
try {
    $pdo->prepare("INSERT INTO `players` (`username`, `password_hash`, `email`, `role`) VALUES (?, ?, ?, 'admin')")
        ->execute([$adminConfig['username'], $hash, $adminConfig['email']]);
    
    // Создаем персонажа для админа
    $pid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO `characters` (`player_id`, `name`, `hp_max`, `caps`, `location_id`) VALUES (?, 'AdminCommander', 200, 1000, ?)")
        ->execute([$pid, $startLocationId]);
        
    echo "✅ Админ создан: {$adminConfig['username']} / {$adminConfig['password']}\n";
} catch (PDOException $e) {
    echo "⚠️ Админ уже существует или ошибка: " . $e->getMessage() . "\n";
}

echo "\n🎉 УСТАНОВКА ЗАВЕРШЕНА УСПЕШНО!\n";
echo "🔗 Подключитесь к БД: {$dbConfig['name']}\n";
echo "⚠️ НЕ ЗАБУДЬТЕ УДАЛИТЬ ЭТОТ ФАЙЛ ИЛИ ЗАПРЕТИТЬ ДОСТУП К НЕМУ В ПРОДАКШЕНЕ!\n";

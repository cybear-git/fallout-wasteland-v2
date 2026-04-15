-- Миграция 021: Massive World Map, Shelters, Weather & Creatures
-- Размер карты: 160x90
-- Добавляет биомы, границы, систему убежищ, погоду и мигрирующие группы

-- 1. Обновление таблицы map_nodes (добавляем биомы и атрибуты)
ALTER TABLE `map_nodes` 
    ADD COLUMN `biome` VARCHAR(50) DEFAULT 'wasteland' COMMENT 'wasteland, mountain, forest, desert, brotherhood_border',
    ADD COLUMN `is_impassable` TINYINT(1) DEFAULT 0 COMMENT 'Горы, границы братства, радиация',
    ADD COLUMN `radiation_level` INT DEFAULT 0 COMMENT 'Уровень радиации клетки',
    ADD COLUMN `weather_id` INT DEFAULT NULL COMMENT 'Текущая погода на клетке',
    ADD INDEX `idx_biome` (`biome`),
    ADD INDEX `idx_impassable` (`is_impassable`);

-- 2. Таблица убежищ (Shelters/Vaults)
-- Хранит 8 статических убежищ и логику кулдауна
CREATE TABLE `shelters` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `map_node_id` BIGINT UNSIGNED NOT NULL, -- Вход на глобальной карте
    `last_spawn_time` DATETIME DEFAULT NULL,
    `last_spawn_player_id` BIGINT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    UNIQUE KEY `unique_node` (`map_node_id`),
    CONSTRAINT `fk_shelter_node` FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shelter_player` FOREIGN KEY (`last_spawn_player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Таблица динамической погоды
CREATE TABLE `world_weather_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` ENUM('dust_storm', 'acid_rain', 'radiation_storm', 'clear') NOT NULL,
    `center_x` SMALLINT NOT NULL,
    `center_y` SMALLINT NOT NULL,
    `radius` TINYINT DEFAULT 5,
    `direction_x` TINYINT DEFAULT 0, -- Вектор движения (-1, 0, 1)
    `direction_y` TINYINT DEFAULT 0,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ends_at` DATETIME NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX `idx_active` (`is_active`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Таблица мигрирующих групп (Когти Смерти, Рейдеры)
CREATE TABLE `world_creature_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_type` ENUM('deathclaw_pack', 'raider_band', 'super_mutant_patrol') NOT NULL,
    `monster_id` BIGINT UNSIGNED NOT NULL, -- ID монстра-лидера (для шаблона)
    `current_node_ids` TEXT COMMENT 'JSON список ID нод, которые занимает группа',
    `direction_x` TINYINT DEFAULT 0,
    `direction_y` TINYINT DEFAULT 0,
    `last_move_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `move_interval_minutes` INT DEFAULT 15, -- Как часто двигаются
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Обновление таблицы players (статы, текущая позиция, состояние)
ALTER TABLE `players`
    ADD COLUMN `current_node_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Текущая позиция на глобальной карте',
    ADD COLUMN `shelter_id` INT UNSIGNED DEFAULT NULL COMMENT 'Родное убежище',
    ADD COLUMN `stat_points` INT DEFAULT 0 COMMENT 'Очки характеристик для распределения',
    ADD COLUMN `strength` TINYINT DEFAULT 5,
    ADD COLUMN `perception` TINYINT DEFAULT 5,
    ADD COLUMN `endurance` TINYINT DEFAULT 5,
    ADD COLUMN `charisma` TINYINT DEFAULT 5,
    ADD COLUMN `intelligence` TINYINT DEFAULT 5,
    ADD COLUMN `agility` TINYINT DEFAULT 5,
    ADD COLUMN `luck` TINYINT DEFAULT 5,
    ADD COLUMN `is_inside_shelter` TINYINT(1) DEFAULT 1 COMMENT 'Находится ли сейчас внутри убежища',
    ADD COLUMN `addiction_timer` DATETIME DEFAULT NULL COMMENT 'Время последней дозы',
    ADD COLUMN `addiction_level` INT DEFAULT 0 COMMENT 'Уровень зависимости',
    ADD CONSTRAINT `fk_player_node` FOREIGN KEY (`current_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_player_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters`(`id`) ON DELETE SET NULL;

-- 6. Таблица эффектов препаратов (зависимость и баффы)
CREATE TABLE `item_effects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `effect_type` ENUM('buff', 'addiction', 'heal') NOT NULL,
    `stat_name` VARCHAR(50) DEFAULT NULL, -- strength, perception и т.д.
    `stat_value` INT DEFAULT 0,
    `duration_minutes` INT DEFAULT 0,
    `addiction_chance` INT DEFAULT 0, -- Шанс зависимости 0-100
    `withdrawal_stat` VARCHAR(50) DEFAULT NULL, -- Какой стат падает при ломке
    `withdrawal_value` INT DEFAULT 0,
    CONSTRAINT `fk_effect_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FALLOUT RPG: DATABASE SCHEMA
-- Версия: 1.0 (Alpha)
-- Описание: Полная структура базы данных
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. СИСТЕМНЫЕ ТАБЛИЦЫ И АДМИНИСТРИРОВАНИЕ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  INDEX `idx_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
  INDEX `idx_admin_log_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_config` (
  `key_name` VARCHAR(50) PRIMARY KEY,
  `value` TEXT,
  `description` VARCHAR(255),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. ИГРОКИ И АВТОРИЗАЦИЯ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `players` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100),
  
  -- Статус
  `is_online` TINYINT(1) DEFAULT 0,
  `last_action_at` TIMESTAMP NULL,
  `in_combat` TINYINT(1) DEFAULT 0,
  `combat_timeout_at` TIMESTAMP NULL,
  `shock_until` TIMESTAMP NULL,
  `current_dungeon_id` BIGINT UNSIGNED NULL,
  
  -- Локация
  `map_node_id` BIGINT UNSIGNED NULL,
  `vault_id` INT UNSIGNED NULL,
  
  -- Характеристики (S.P.E.C.I.A.L. упрощенный)
  `strength` TINYINT UNSIGNED DEFAULT 5,
  `perception` TINYINT UNSIGNED DEFAULT 5,
  `endurance` TINYINT UNSIGNED DEFAULT 5,
  `charisma` TINYINT UNSIGNED DEFAULT 5,
  `intelligence` TINYINT UNSIGNED DEFAULT 5,
  `agility` TINYINT UNSIGNED DEFAULT 5,
  `luck` TINYINT UNSIGNED DEFAULT 5,
  
  -- Боевые статы
  `level` INT UNSIGNED DEFAULT 1,
  `experience` BIGINT UNSIGNED DEFAULT 0,
  `hp_current` INT UNSIGNED DEFAULT 100,
  `hp_max` INT UNSIGNED DEFAULT 100,
  `ap_current` INT UNSIGNED DEFAULT 100,
  `ap_max` INT UNSIGNED DEFAULT 100,
  `radiation` INT UNSIGNED DEFAULT 0,
  
  -- Ресурсы
  `caps` INT UNSIGNED DEFAULT 0,
  
  -- Мета
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`vault_id`) REFERENCES `vaults`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`current_dungeon_id`) REFERENCES `dungeons`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_player_username` (`username`),
  INDEX `idx_player_online` (`is_online`),
  INDEX `idx_player_location` (`map_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. МИР И КАРТА
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `map_nodes` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `x` SMALLINT UNSIGNED NOT NULL,
  `y` SMALLINT UNSIGNED NOT NULL,
  `tile_type` ENUM('wasteland','city','forest','desert','ruins','camp','mountain','water','vault_entrance','blocked') NOT NULL DEFAULT 'wasteland',
  `biome` ENUM('center','west_mountains','east_brotherhood','north_cold','south_desert') DEFAULT 'center',
  `elevation` TINYINT DEFAULT 0,
  `radiation_level` TINYINT UNSIGNED DEFAULT 0,
  `dungeon_id` BIGINT UNSIGNED NULL,
  `is_spawn_point` TINYINT(1) DEFAULT 0,
  
  UNIQUE KEY `unique_coords` (`x`, `y`),
  FOREIGN KEY (`dungeon_id`) REFERENCES `dungeons`(`id`) ON DELETE SET NULL,
  INDEX `idx_map_coords` (`x`, `y`),
  INDEX `idx_map_tile` (`tile_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vaults` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `map_node_id` BIGINT UNSIGNED NOT NULL,
  `keeper_npc_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_player_left_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE,
  INDEX `idx_vault_node` (`map_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vault_keepers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `vault_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `dialogue_intro` TEXT,
  `dialogue_mission` TEXT,
  `dialogue_farewell` TEXT,
  
  FOREIGN KEY (`vault_id`) REFERENCES `vaults`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. ДАНЖИ И ЛОКАЦИИ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dungeons` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `type` ENUM('cave','building','bunker','factory','military') DEFAULT 'cave',
  `min_level` TINYINT UNSIGNED DEFAULT 1,
  `max_level` TINYINT UNSIGNED DEFAULT 10,
  `boss_id` BIGINT UNSIGNED NULL,
  `reward_item_id` BIGINT UNSIGNED NULL,
  `reward_xp` INT UNSIGNED DEFAULT 0,
  `reward_caps` INT UNSIGNED DEFAULT 0,
  `entrance_node_id` BIGINT UNSIGNED NOT NULL,
  `is_building` TINYINT(1) DEFAULT 0,
  
  FOREIGN KEY (`boss_id`) REFERENCES `monsters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`entrance_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE,
  INDEX `idx_dungeon_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dungeon_nodes` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dungeon_id` BIGINT UNSIGNED NOT NULL,
  `node_name` VARCHAR(100),
  `node_type` ENUM('corridor','room','boss_room','exit','entrance') DEFAULT 'corridor',
  `x` TINYINT UNSIGNED,
  `y` TINYINT UNSIGNED,
  `monster_id` BIGINT UNSIGNED NULL,
  `loot_table_id` BIGINT UNSIGNED NULL,
  `is_entrance` TINYINT(1) DEFAULT 0,
  `is_exit` TINYINT(1) DEFAULT 0,
  
  FOREIGN KEY (`dungeon_id`) REFERENCES `dungeons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`monster_id`) REFERENCES `monsters`(`id`) ON DELETE SET NULL,
  INDEX `idx_dungeon_node_dungeon` (`dungeon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. СУЩЕСТВА И МОНСТРЫ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `monsters` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `level` TINYINT UNSIGNED DEFAULT 1,
  `hp_max` INT UNSIGNED DEFAULT 50,
  `damage_min` TINYINT UNSIGNED DEFAULT 5,
  `damage_max` TINYINT UNSIGNED DEFAULT 10,
  `armor` TINYINT UNSIGNED DEFAULT 0,
  `xp_reward` INT UNSIGNED DEFAULT 10,
  `caps_reward` INT UNSIGNED DEFAULT 5,
  `is_boss` TINYINT(1) DEFAULT 0,
  `is_wandering` TINYINT(1) DEFAULT 0,
  `current_map_node_id` BIGINT UNSIGNED NULL,
  
  FOREIGN KEY (`current_map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE SET NULL,
  INDEX `idx_monster_wandering` (`is_wandering`),
  INDEX `idx_monster_boss` (`is_boss`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monster_spawns` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `map_node_id` BIGINT UNSIGNED NOT NULL,
  `monster_id` BIGINT UNSIGNED NOT NULL,
  `respawn_time_minutes` INT UNSIGNED DEFAULT 60,
  `last_killed_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`monster_id`) REFERENCES `monsters`(`id`) ON DELETE CASCADE,
  INDEX `idx_spawn_node` (`map_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. ПРЕДМЕТЫ И ИНВЕНТАРЬ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `type` ENUM('weapon','armor','consumable','junk','quest','aid') NOT NULL,
  `subtype` VARCHAR(50),
  `weight` DECIMAL(5,2) DEFAULT 0.00,
  `value` INT UNSIGNED DEFAULT 0,
  
  -- Параметры для оружия
  `damage_min` TINYINT UNSIGNED DEFAULT 0,
  `damage_max` TINYINT UNSIGNED DEFAULT 0,
  `range` TINYINT UNSIGNED DEFAULT 0,
  `ammo_type` VARCHAR(50),
  `uses_junk` TINYINT(1) DEFAULT 0,
  
  -- Параметры для брони
  `armor_rating` TINYINT UNSIGNED DEFAULT 0,
  
  -- Параметры для расходников
  `hp_restore` INT DEFAULT 0,
  `ap_restore` INT DEFAULT 0,
  `radiation_remove` INT DEFAULT 0,
  `effect_duration_seconds` INT DEFAULT 0,
  `addiction_chance` DECIMAL(5,2) DEFAULT 0.00,
  
  -- Junk параметры
  `junk_category` VARCHAR(50),
  
  INDEX `idx_item_type` (`type`),
  INDEX `idx_item_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `player_inventory` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `player_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED DEFAULT 1,
  `is_equipped` TINYINT(1) DEFAULT 0,
  `condition` TINYINT UNSIGNED DEFAULT 100,
  
  FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_player_item` (`player_id`, `item_id`),
  INDEX `idx_inventory_player` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loot_tables` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `min_level` TINYINT UNSIGNED DEFAULT 1,
  `max_level` TINYINT UNSIGNED DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loot_entries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `loot_table_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `chance` DECIMAL(5,2) NOT NULL,
  `min_quantity` INT UNSIGNED DEFAULT 1,
  `max_quantity` INT UNSIGNED DEFAULT 1,
  
  FOREIGN KEY (`loot_table_id`) REFERENCES `loot_tables`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. БОЙ И ЛОГИ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `combat_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `player_id` BIGINT UNSIGNED NOT NULL,
  `monster_id` BIGINT UNSIGNED NULL,
  `opponent_player_id` BIGINT UNSIGNED NULL,
  `result` ENUM('win','lose','flee','draw') NOT NULL,
  `xp_gained` INT UNSIGNED DEFAULT 0,
  `caps_gained` INT UNSIGNED DEFAULT 0,
  `items_looted` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`monster_id`) REFERENCES `monsters`(`id`) ON DELETE SET NULL,
  INDEX `idx_combat_player` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. ПОГОДА И СОБЫТИЯ
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `weather_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_type` ENUM('dust_storm','acid_rain','radiation_storm','clear') NOT NULL,
  `center_x` SMALLINT UNSIGNED,
  `center_y` SMALLINT UNSIGNED,
  `radius` TINYINT UNSIGNED DEFAULT 1,
  `damage_per_tick` TINYINT UNSIGNED DEFAULT 0,
  `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ends_at` TIMESTAMP NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  
  INDEX `idx_weather_active` (`is_active`),
  INDEX `idx_weather_coords` (`center_x`, `center_y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deathclaw_packs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `leader_id` BIGINT UNSIGNED NOT NULL,
  `current_map_node_id` BIGINT UNSIGNED NOT NULL,
  `size` TINYINT UNSIGNED DEFAULT 3,
  `last_move_at` TIMESTAMP NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  
  FOREIGN KEY (`leader_id`) REFERENCES `monsters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`current_map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. КОНТЕНТ И АТМОСФЕРА
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `location_quotes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quote_text` TEXT NOT NULL,
  `tile_type` ENUM('wasteland','city','forest','desert','ruins','camp','mountain','water','vault_entrance','blocked'),
  `mood` ENUM('neutral','danger','hope','mystery','sad'),
  `language` VARCHAR(10) DEFAULT 'ru',
  
  INDEX `idx_quote_tile` (`tile_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `player_search_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `player_id` BIGINT UNSIGNED NOT NULL,
  `map_node_id` BIGINT UNSIGNED NOT NULL,
  `success` TINYINT(1) DEFAULT 0,
  `item_found_id` BIGINT UNSIGNED NULL,
  `monster_encountered_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`) ON DELETE CASCADE,
  INDEX `idx_search_player_time` (`player_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

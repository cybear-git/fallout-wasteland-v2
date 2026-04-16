-- Миграция 021: Таблицы для боевой системы и дропа лута
-- Цель: Реализация пошагового боя, таблицы лута с врагов, инвентаря игрока

-- 1. Таблица активных боёв
CREATE TABLE IF NOT EXISTS combats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    location_id INT DEFAULT NULL COMMENT 'ID локации на глобальной карте',
    dungeon_node_id INT DEFAULT NULL COMMENT 'ID ноды в данже (если бой внутри)',
    enemy_json JSON NOT NULL COMMENT 'Массив врагов: [{monster_id, hp, ac, status}]',
    initiative_order JSON DEFAULT NULL COMMENT 'Порядок ходов [player_id, monster_id, ...]',
    current_turn_index TINYINT UNSIGNED DEFAULT 0,
    combat_state ENUM('active', 'won', 'lost', 'fled') DEFAULT 'active',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    
    CONSTRAINT fk_combat_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_combat_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    CONSTRAINT fk_combat_dungeon_node FOREIGN KEY (dungeon_node_id) REFERENCES dungeon_nodes(id) ON DELETE SET NULL,
    INDEX idx_combat_player (player_id),
    INDEX idx_combat_state (combat_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Таблица действий в бою (лог)
CREATE TABLE IF NOT EXISTS combat_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    combat_id INT NOT NULL,
    actor_type ENUM('player', 'monster') NOT NULL,
    actor_id INT NOT NULL COMMENT 'player_id или monster_index в enemy_json',
    action_type ENUM('attack', 'crit', 'miss', 'dodge', 'use_item', 'flee', 'special') NOT NULL,
    target_type ENUM('player', 'monster') DEFAULT NULL,
    target_id INT DEFAULT NULL,
    damage_dealt INT UNSIGNED DEFAULT 0,
    damage_taken INT UNSIGNED DEFAULT 0,
    hp_before INT UNSIGNED DEFAULT NULL,
    hp_after INT UNSIGNED DEFAULT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_log_combat FOREIGN KEY (combat_id) REFERENCES combats(id) ON DELETE CASCADE,
    INDEX idx_log_combat (combat_id),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Таблица лут-таблиц (шаблоны дропа для монстров)
CREATE TABLE IF NOT EXISTS loot_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    min_level TINYINT UNSIGNED DEFAULT 1,
    max_level TINYINT UNSIGNED DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Таблица предметов в лут-таблицах
CREATE TABLE IF NOT EXISTS loot_table_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loot_table_id INT NOT NULL,
    item_type ENUM('loot', 'weapon', 'armor', 'consumable') NOT NULL,
    item_key VARCHAR(50) NOT NULL COMMENT 'Ключ предмета из соответствующей таблицы',
    chance DECIMAL(5,4) NOT NULL DEFAULT 0.0 COMMENT 'Шанс выпадения (0.0-1.0)',
    min_qty SMALLINT UNSIGNED DEFAULT 1,
    max_qty SMALLINT UNSIGNED DEFAULT 1,
    guaranteed TINYINT(1) DEFAULT 0,
    
    CONSTRAINT fk_lti_loot_table FOREIGN KEY (loot_table_id) REFERENCES loot_tables(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_item_in_table (loot_table_id, item_type, item_key),
    INDEX idx_lti_chance (chance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Расширение таблицы inventory для поддержки экипировки и статов
ALTER TABLE inventory 
ADD COLUMN equipped_slot ENUM('head', 'tors', 'l_arm', 'r_arm', 'legs', 'main_hand', 'off_hand', 'ring', 'ammo') DEFAULT NULL AFTER quantity,
ADD COLUMN condition_pct DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Состояние предмета (%)',
ADD COLUMN mod_json JSON DEFAULT NULL COMMENT 'Модификаторы предмета';

-- Индекс для быстрого поиска экипированных предметов
CREATE INDEX idx_inventory_equipped ON inventory(player_id, equipped_slot);

-- 6. Таблица эффектов (баффы/дебаффы)
CREATE TABLE IF NOT EXISTS player_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    effect_type ENUM('buff', 'debuff', 'addiction', 'radiation') NOT NULL,
    effect_key VARCHAR(50) NOT NULL,
    effect_name VARCHAR(100) NOT NULL,
    stat_modifier JSON DEFAULT NULL COMMENT '{"str": -1, "agi": 2, "dmg_resist": 10}',
    duration_seconds INT UNSIGNED DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    source VARCHAR(100) DEFAULT NULL COMMENT 'Источник эффекта (предмет, способность)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_effects_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    INDEX idx_effects_player (player_id),
    INDEX idx_effects_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. is_boss already exists in monsters table (added in 005)

-- 8. Создаем стандартные лут-таблицы для типов монстров
INSERT INTO loot_tables (table_name, description, min_level, max_level) VALUES
('mole_rat_standard', 'Стандартный дроп с кротокрыса', 1, 5),
('ghoul_standard', 'Стандартный дроп с гуля', 3, 10),
('raider_standard', 'Стандартный дроп с рейдера', 4, 15),
('super_mutant_standard', 'Стандартный дроп с супермутанта', 7, 20),
('deathclaw_standard', 'Стандартный дроп с когтя смерти', 10, 25),
('boss_colonel', 'Дроп с Полковника Морерта', 15, 15);

-- Наполняем лут-таблицы предметами
SET @lt_mole = (SELECT id FROM loot_tables WHERE table_name = 'mole_rat_standard');
SET @lt_ghoul = (SELECT id FROM loot_tables WHERE table_name = 'ghoul_standard');
SET @lt_raider = (SELECT id FROM loot_tables WHERE table_name = 'raider_standard');
SET @lt_mutant = (SELECT id FROM loot_tables WHERE table_name = 'super_mutant_standard');
SET @lt_deathclaw = (SELECT id FROM loot_tables WHERE table_name = 'deathclaw_standard');
SET @lt_boss = (SELECT id FROM loot_tables WHERE table_name = 'boss_colonel');

INSERT INTO loot_table_items (loot_table_id, item_type, item_key, chance, min_qty, max_qty, guaranteed) VALUES
-- Кротокрыс
(@lt_mole, 'loot', 'bottle_cap', 0.80, 2, 5, 0),
(@lt_mole, 'consumable', 'stimpak', 0.10, 1, 1, 0),
-- Гуль
(@lt_ghoul, 'loot', 'bottle_cap', 0.90, 5, 15, 0),
(@lt_ghoul, 'consumable', 'radaway', 0.20, 1, 2, 0),
(@lt_ghoul, 'loot', 'pre_war_money', 0.50, 10, 50, 0),
-- Рейдер
(@lt_raider, 'loot', 'bottle_cap', 1.00, 10, 30, 0),
(@lt_raider, 'weapon', 'pipe_pistol', 0.15, 1, 1, 0),
(@lt_raider, 'weapon', 'switchblade', 0.25, 1, 1, 0),
(@lt_raider, 'consumable', 'jet', 0.10, 1, 2, 0),
(@lt_raider, 'armor', 'raider_armor', 0.05, 1, 1, 0),
-- Супермутант
(@lt_mutant, 'loot', 'bottle_cap', 1.00, 20, 50, 0),
(@lt_mutant, 'weapon', 'tire_iron', 0.20, 1, 1, 0),
(@lt_mutant, 'weapon', 'shotgun', 0.10, 1, 1, 0),
(@lt_mutant, 'consumable', 'psycho', 0.15, 1, 2, 0),
-- Коготь Смерти
(@lt_deathclaw, 'loot', 'bottle_cap', 1.00, 50, 150, 0),
(@lt_deathclaw, 'loot', 'nuclear_material', 0.30, 1, 3, 0),
(@lt_deathclaw, 'consumable', 'stimpak', 0.25, 1, 3, 0),
-- Босс Полковник
(@lt_boss, 'loot', 'bottle_cap', 1.00, 500, 500, 1),
(@lt_boss, 'weapon', 'fat_man', 1.00, 1, 1, 1),
(@lt_boss, 'loot', 'nuclear_material', 0.50, 5, 10, 0),
(@lt_boss, 'consumable', 'med_x', 0.80, 3, 5, 0);

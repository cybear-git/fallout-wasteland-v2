-- ============================================================================
-- МИГРАЦИЯ 023: ИНВЕНТАРЬ, ЛУТ, ХЛАМ И ХЛАМОТРОН
-- ============================================================================
-- Цель: Реализация системы предметов, мусора для оружия и механики поиска.

-- 1. Таблица типов предметов (оружие, броня, расходники, хлам, ключи)
CREATE TABLE IF NOT EXISTS `item_types` (
    `id` TINYINT UNSIGNED PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE -- 'weapon', 'armor', 'consumable', 'junk', 'key'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `item_types` (`id`, `name`, `slug`) VALUES
(1, 'Оружие', 'weapon'),
(2, 'Броня', 'armor'),
(3, 'Расходники', 'consumable'),
(4, 'Хлам', 'junk'),
(5, 'Ключи/Разное', 'misc');

-- 2. Таблица предметов (словарь)
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `type_id` TINYINT UNSIGNED NOT NULL,
    `weight` DECIMAL(4,2) DEFAULT 0.00, -- Вес для ограничения переносимого
    `value` INT UNSIGNED DEFAULT 0, -- Цена в крышках
    `icon` VARCHAR(50) DEFAULT 'default.png',
    
    -- Характеристики для экипировки
    `damage_min` TINYINT UNSIGNED DEFAULT 0,
    `damage_max` TINYINT UNSIGNED DEFAULT 0,
    `armor_class` TINYINT UNSIGNED DEFAULT 0,
    `range` TINYINT UNSIGNED DEFAULT 0,
    
    -- Характеристики для хлама (патроны для хламотрона)
    `junk_value` TINYINT UNSIGNED DEFAULT 0, -- "Мощность" хлама как патрона
    
    -- Эффекты для химии
    `effect_stat` VARCHAR(50) DEFAULT NULL, -- e.g., 'strength', 'perception'
    `effect_value` SMALLINT DEFAULT 0,
    `effect_duration` INT UNSIGNED DEFAULT 0, -- в секундах
    `addiction_chance` TINYINT UNSIGNED DEFAULT 0, -- шанс зависимости %
    `addiction_penalty` VARCHAR(255) DEFAULT NULL, -- описание штрафа
    
    FOREIGN KEY (`type_id`) REFERENCES `item_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Инвентарь игрока
CREATE TABLE IF NOT EXISTS `player_inventory` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED DEFAULT 1,
    `equipped` BOOLEAN DEFAULT FALSE, -- Надето ли (для оружия/брони)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_player_item` (`player_id`, `item_id`),
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Отдельное поле для ХЛАМОТРОНА (уникальное оружие)
-- Добавляем флаг в таблицу игроков: есть ли хламотрон и сколько хлама в магазине
ALTER TABLE `players` 
ADD COLUMN `has_junk_jet` BOOLEAN DEFAULT FALSE,
ADD COLUMN `junk_jet_ammo` INT UNSIGNED DEFAULT 0; -- Количество хлама в магазине оружия

-- 5. Таблица вероятностей лута для разных типов локаций
CREATE TABLE IF NOT EXISTS `loot_tables` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `location_type` VARCHAR(50) NOT NULL, -- 'wasteland', 'city_ruins', 'factory', 'military'
    `item_id` INT UNSIGNED NOT NULL,
    `chance` DECIMAL(5,4) NOT NULL, -- 0.0000 to 1.0000 (например, 0.05 = 5%)
    `min_qty` TINYINT UNSIGNED DEFAULT 1,
    `max_qty` TINYINT UNSIGNED DEFAULT 1,
    
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Журнал поисковых операций (для лога и баланса)
CREATE TABLE IF NOT EXISTS `search_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id` BIGINT UNSIGNED NOT NULL,
    `map_node_id` INT UNSIGNED NOT NULL,
    `result` ENUM('nothing', 'found_item', 'monster_encounter', 'trap') NOT NULL,
    `item_found_id` INT UNSIGNED DEFAULT NULL,
    `xp_gained` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`),
    FOREIGN KEY (`map_node_id`) REFERENCES `map_nodes`(`id`),
    FOREIGN KEY (`item_found_id`) REFERENCES `items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- НАПОЛНЕНИЕ ДАННЫМИ (ПРИМЕРЫ)
-- ============================================================================

-- Предметы: Хлам (для хламотрона)
INSERT INTO `items` (`name`, `type_id`, `weight`, `value`, `junk_value`, `description`) VALUES
('Ржавая пружина', 4, 0.5, 1, 5, 'Старая пружина, может сгодиться.'),
('Обломок металла', 4, 1.0, 2, 8, 'Кусок искореженного железа.'),
('Старая шестеренка', 4, 0.3, 1, 4, 'Зубчатое колесо от неизвестного механизма.'),
('Пустая консервная банка', 4, 0.2, 0, 2, 'Когда-то тут были бобы.'),
('Микросхема', 4, 0.1, 5, 15, 'Искрящаяся плата, ценный ресурс.');

-- Предметы: Оружие (Хламотрон)
INSERT INTO `items` (`name`, `type_id`, `weight`, `value`, `damage_min`, `damage_max`, `description`) VALUES
('Хламотрон', 1, 15.0, 500, 10, 25, 'Экспериментальное оружие, стреляющее любым мусором. Требует хлам в магазине.');

-- Предметы: Расходники
INSERT INTO `items` (`name`, `type_id`, `weight`, `value`, `effect_stat`, `effect_value`, `effect_duration`, `addiction_chance`, `description`) VALUES
('Стимпак', 3, 0.5, 50, 'health', 50, 0, 0, 'Восстанавливает здоровье.'),
('Антирадин', 3, 0.3, 30, 'radiation', -50, 0, 0, 'Снижает уровень радиации.'),
('Психо', 3, 0.1, 40, 'damage', 5, 300, 20, 'Временно увеличивает урон. Вызывает зависимость.'),
('Баффбафф', 3, 0.2, 45, 'strength', 3, 600, 15, 'Повышает силу. Возможна ломка.');

-- Таблицы лута (пример для пустоши)
INSERT INTO `loot_tables` (`location_type`, `item_id`, `chance`, `min_qty`, `max_qty`)
SELECT 'wasteland', id, 0.30, 1, 3 FROM `items` WHERE name = 'Ржавая пружина';

INSERT INTO `loot_tables` (`location_type`, `item_id`, `chance`, `min_qty`, `max_qty`)
SELECT 'wasteland', id, 0.10, 1, 1 FROM `items` WHERE name = 'Стимпак';

INSERT INTO `loot_tables` (`location_type`, `item_id`, `chance`, `min_qty`, `max_qty`)
SELECT 'city_ruins', id, 0.40, 1, 5 FROM `items` WHERE name = 'Обломок металла';

-- Индекс для быстрого поиска лута
CREATE INDEX idx_loot_location ON `loot_tables`(`location_type`);

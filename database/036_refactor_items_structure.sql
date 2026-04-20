-- Миграция: Рефакторинг структуры предметов (нормализация)
-- Удаляем старые таблицы и создаем новую нормализованную структуру

-- Шаг 1: Создаем таблицу типов предметов
CREATE TABLE IF NOT EXISTS item_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_key VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 2: Создаем общую таблицу items
CREATE TABLE IF NOT EXISTS items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 3: Таблица атрибутов оружия
CREATE TABLE IF NOT EXISTS weapon_attributes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 4: Таблица атрибутов брони
CREATE TABLE IF NOT EXISTS armor_attributes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 5: Таблица атрибутов расходников
CREATE TABLE IF NOT EXISTS consumable_attributes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 6: Таблица атрибутов лута
CREATE TABLE IF NOT EXISTS loot_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL UNIQUE,
    category ENUM('junk', 'key_item', 'quest', 'component', 'currency') DEFAULT 'junk',
    stackable TINYINT(1) DEFAULT 1,
    max_stack INT UNSIGNED DEFAULT 99,
    CONSTRAINT fk_loot_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_stackable (stackable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаг 7: Обновляем таблицу inventory для работы с новой структурой
-- Добавляем item_id вместо item_key + item_type
ALTER TABLE inventory 
    ADD COLUMN item_id INT UNSIGNED DEFAULT NULL AFTER item_key,
    ADD CONSTRAINT fk_inventory_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE;

-- Создаем индекс для нового поля
CREATE INDEX idx_inventory_item_id ON inventory(item_id);

-- Шаг 8: Заполняем таблицу типов предметов
INSERT INTO item_types (type_key, name, description) VALUES
    ('weapon', 'Оружие', 'Все виды оружия: от ножей до энергетического оружия'),
    ('armor', 'Броня', 'Защитное снаряжение: одежда, броня, шлемы'),
    ('consumable', 'Расходники', 'Еда, лекарства, стимуляторы'),
    ('loot', 'Лут', 'Разные предметы: мусор, компоненты, ключевые предметы');

-- Примечание: Данные из старых таблиц нужно будет перенести скриптом миграции
-- Этот скрипт создает только структуру

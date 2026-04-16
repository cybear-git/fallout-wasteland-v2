-- ==========================================
-- ЭТАП 2: Основные игровые таблицы
-- Связи через внешние ключи к справочникам
-- ==========================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Игроки
CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Ссылка на роль вместо ENUM
    role_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    
    -- Характеристики
    level INT UNSIGNED DEFAULT 1,
    experience BIGINT UNSIGNED DEFAULT 0,
    hp_current INT UNSIGNED DEFAULT 100,
    hp_max INT UNSIGNED DEFAULT 100,
    ap_current INT UNSIGNED DEFAULT 100,
    ap_max INT UNSIGNED DEFAULT 100,
    
    -- Позиция
    current_location_id INT UNSIGNED DEFAULT 1,
    
    -- Статусы
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_player_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Локации
CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Ссылка на тип локации
    type_id TINYINT UNSIGNED NOT NULL,
    
    -- Координаты (для карты в будущем)
    coord_x INT DEFAULT 0,
    coord_y INT DEFAULT 0,
    
    -- Сложность и связи
    difficulty_level TINYINT UNSIGNED DEFAULT 1,
    parent_location_id INT UNSIGNED DEFAULT NULL, -- Для вложенности (комната в данже)
    
    -- Настройки спавна
    spawn_rate_minutes INT DEFAULT 30,
    max_monsters INT DEFAULT 5,

    CONSTRAINT fk_location_type FOREIGN KEY (type_id) REFERENCES location_types(id) ON UPDATE CASCADE,
    CONSTRAINT fk_location_parent FOREIGN KEY (parent_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Предметы (Шаблоны)
CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Ссылки на справочники
    type_id TINYINT UNSIGNED NOT NULL,
    rarity_id TINYINT UNSIGNED NOT NULL DEFAULT 2, -- common
    condition_id TINYINT UNSIGNED NOT NULL DEFAULT 3, -- normal
    
    -- Характеристики
    weight DECIMAL(5,2) DEFAULT 0.00,
    value INT UNSIGNED DEFAULT 0, -- Цена в крышках
    
    -- Боевые статы (если оружие/броня)
    damage_min INT UNSIGNED DEFAULT 0,
    damage_max INT UNSIGNED DEFAULT 0,
    armor_class_bonus INT UNSIGNED DEFAULT 0,
    
    -- Требования
    required_level INT UNSIGNED DEFAULT 1,
    
    -- Иконка (путь к файлу или класс CSS)
    icon_path VARCHAR(255) DEFAULT 'default_item.png',

    CONSTRAINT fk_item_type FOREIGN KEY (type_id) REFERENCES item_types(id) ON UPDATE CASCADE,
    CONSTRAINT fk_item_rarity FOREIGN KEY (rarity_id) REFERENCES item_rarities(id) ON UPDATE CASCADE,
    CONSTRAINT fk_item_condition FOREIGN KEY (condition_id) REFERENCES item_conditions(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Инвентарь игрока (Экземпляры предметов)
CREATE TABLE IF NOT EXISTS player_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    
    quantity INT UNSIGNED DEFAULT 1,
    equipped BOOLEAN DEFAULT FALSE, -- Надето ли сейчас
    
    -- Индивидуальная прочность экземпляра
    current_durability INT UNSIGNED DEFAULT 100,
    max_durability INT UNSIGNED DEFAULT 100,
    
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_player_id (player_id),
    INDEX idx_equipped (player_id, equipped)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Монстры (Шаблоны)
CREATE TABLE IF NOT EXISTS monsters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Ссылка на тип
    type_id TINYINT UNSIGNED NOT NULL,
    
    level INT UNSIGNED DEFAULT 1,
    hp_max INT UNSIGNED DEFAULT 50,
    damage_min INT UNSIGNED DEFAULT 5,
    damage_max INT UNSIGNED DEFAULT 10,
    xp_reward INT UNSIGNED DEFAULT 10,
    
    -- Дроп (ссылка на предмет, который выпадает)
    loot_table_id INT UNSIGNED DEFAULT NULL, -- Можно реализовать отдельную таблицу лута, пока упростим

    CONSTRAINT fk_monster_type FOREIGN KEY (type_id) REFERENCES monster_types(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Спавн монстров на локациях
CREATE TABLE IF NOT EXISTS location_monsters (
    location_id INT UNSIGNED NOT NULL,
    monster_id INT UNSIGNED NOT NULL,
    spawn_chance DECIMAL(5,2) DEFAULT 100.00, -- Шанс появления в %
    
    PRIMARY KEY (location_id, monster_id),
    CONSTRAINT fk_lm_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_lm_monster FOREIGN KEY (monster_id) REFERENCES monsters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Выходы из локаций (Навигация)
CREATE TABLE IF NOT EXISTS location_exits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    target_location_id INT UNSIGNED NOT NULL,
    exit_name VARCHAR(50) DEFAULT 'Перейти', -- Текст на кнопке (напр. "На Север", "В пещеру")
    is_locked BOOLEAN DEFAULT FALSE,
    required_key_item_id INT UNSIGNED DEFAULT NULL,

    CONSTRAINT fk_exit_loc FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_exit_target FOREIGN KEY (target_location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_exit_key FOREIGN KEY (required_key_item_id) REFERENCES items(id) ON DELETE SET NULL,
    UNIQUE KEY unique_exit (location_id, target_location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
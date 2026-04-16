-- database/026_normalize_enums.sql
-- Перевод ENUM в справочные таблицы для стабильности и расширяемости

-- 1. Справочник ролей
CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (role_name) VALUES ('player'), ('admin');

-- 2. Типы локаций
CREATE TABLE IF NOT EXISTS location_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO location_types (type_name) VALUES 
('wasteland'), ('city'), ('dungeon'), ('radzone'), ('vault'), 
('vault_ext'), ('mountain'), ('forest'), ('desert'), ('ruins'), 
('camp'), ('military'), ('military_base');

-- 3. Типы нод данжей
CREATE TABLE IF NOT EXISTS dungeon_tile_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO dungeon_tile_types (type_name) VALUES 
('corridor'), ('room'), ('boss'), ('treasure'), ('exit'), ('trap');

-- 4. Слоты экипировки
CREATE TABLE IF NOT EXISTS equipment_slots (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO equipment_slots (slot_name) VALUES 
('head'), ('tors'), ('l_arm'), ('r_arm'), ('legs'), 
('main_hand'), ('off_hand'), ('ring'), ('ammo');

-- 5. Типы эффектов
CREATE TABLE IF NOT EXISTS effect_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO effect_types (type_name) VALUES 
('buff'), ('debuff'), ('addiction'), ('radiation');

-- 6. Состояния боя
CREATE TABLE IF NOT EXISTS combat_states (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO combat_states (state_name) VALUES 
('active'), ('won'), ('lost'), ('fled');

-- 7. Типы боеприпасов
CREATE TABLE IF NOT EXISTS ammo_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ammo_types (type_name) VALUES 
('bullet'), ('energy'), ('junk'), ('none');

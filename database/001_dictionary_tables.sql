-- ==========================================
-- ЭТАП 1: Справочные таблицы (Dictionaries)
-- Замена всем ENUM для гибкости системы
-- ==========================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Роли игроков
CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'player', 'admin', 'moderator'
    description VARCHAR(255)
);

-- 2. Типы локаций
CREATE TABLE IF NOT EXISTS location_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'town', 'dungeon', 'wasteland', 'boss_room'
    description VARCHAR(255)
);

-- 3. Типы предметов
CREATE TABLE IF NOT EXISTS item_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'weapon', 'armor', 'consumable', 'misc', 'quest'
    description VARCHAR(255)
);

-- 4. Редкость предметов
CREATE TABLE IF NOT EXISTS item_rarities (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE, -- 'junk', 'common', 'rare', 'legendary'
    color_code VARCHAR(7) DEFAULT '#ffffff' -- Для отображения цветом на фронтенде
);

-- 5. Состояние предметов
CREATE TABLE IF NOT EXISTS item_conditions (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE, -- 'broken', 'worn', 'normal', 'pristine'
    durability_modifier DECIMAL(3,2) DEFAULT 1.00 -- Множитель прочности
);

-- 6. Типы монстров
CREATE TABLE IF NOT EXISTS monster_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'human', 'mutant', 'robot', 'beast'
    description VARCHAR(255)
);

-- ==========================================
-- НАПОЛНЕНИЕ СПРАВОЧНИКОВ
-- ==========================================

INSERT INTO roles (name, description) VALUES
('player', 'Обычный игрок'),
('admin', 'Администратор сервера'),
('moderator', 'Модератор чата');

INSERT INTO location_types (name, description) VALUES
('town', 'Безопасный город'),
('wasteland', 'Пустошь, случайные встречи'),
('dungeon', 'Подземелье с лутом'),
('dungeon_entrance', 'Вход в подземелье'),
('boss_room', 'Комната босса'),
('landmark', 'Известная достопримечательность');

INSERT INTO item_types (name, description) VALUES
('weapon', 'Оружие для боя'),
('armor', 'Броня для защиты'),
('consumable', 'Еда, лекарства, стимуляторы'),
('misc', 'Разное, хлам, компоненты'),
('quest', 'Квестовые предметы');

INSERT INTO item_rarities (name, color_code) VALUES
('junk', '#999999'),   -- Серый
('common', '#ffffff'), -- Белый
('rare', '#00ff00'),   -- Зеленый
('epic', '#a335ee'),   -- Фиолетовый
('legendary', '#ffa500'); -- Оранжевый

INSERT INTO item_conditions (name, durability_modifier) VALUES
('broken', 0.50),
('worn', 0.75),
('normal', 1.00),
('pristine', 1.20);

INSERT INTO monster_types (name, description) VALUES
('human', 'Люди, рейдеры, торговцы'),
('mutant', 'Гули, супермутанты'),
('robot', 'Роботы, турели'),
('beast', 'Дикие животные, брамины');

SET FOREIGN_KEY_CHECKS = 1;
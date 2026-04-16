-- database/024_ammo_system.sql
-- Система боеприпасов для оружия

-- 1. Добавляем тип патронов к оружию
ALTER TABLE weapons 
ADD COLUMN ammo_type ENUM('bullet', 'energy', 'junk', 'none') DEFAULT 'none';

-- 2. Таблица хранения боеприпасов у игроков
CREATE TABLE IF NOT EXISTS player_ammo (
    player_id INT UNSIGNED NOT NULL,
    ammo_type ENUM('bullet', 'energy', 'junk', 'none') NOT NULL,
    quantity INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (player_id, ammo_type),
    CONSTRAINT fk_ammo_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Наполняем базовые типы патронов для существующих игроков
INSERT INTO player_ammo (player_id, ammo_type, quantity)
SELECT id, 'bullet', 50 FROM players;

INSERT INTO player_ammo (player_id, ammo_type, quantity)
SELECT id, 'energy', 20 FROM players;

INSERT INTO player_ammo (player_id, ammo_type, quantity)
SELECT id, 'junk', 100 FROM players;

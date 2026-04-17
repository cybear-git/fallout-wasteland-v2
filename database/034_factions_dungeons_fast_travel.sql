-- Миграция 034: Фракции, Подземелья и Быстрое перемещение
-- Дата: 2023-10-27

-- 1. Таблица фракций
CREATE TABLE IF NOT EXISTS factions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    base_attitude INT DEFAULT 0, -- -100 (Враг) до 100 (Союзник)
    color_code VARCHAR(7) DEFAULT '#ffffff'
);

-- 2. Таблица репутации игрока
CREATE TABLE IF NOT EXISTS player_faction_reputation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    faction_id INT NOT NULL,
    reputation INT DEFAULT 0, -- -1000 до 1000
    rank_title VARCHAR(50) DEFAULT 'Незнакомец',
    last_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_faction (user_id, faction_id)
);

-- 3. Обновление таблицы локаций (добавляем тип и сложность)
ALTER TABLE locations 
ADD COLUMN location_type ENUM('wasteland', 'dungeon', 'boss_arena', 'settlement') DEFAULT 'wasteland',
ADD COLUMN min_level INT DEFAULT 1,
ADD COLUMN boss_id INT NULL, -- ID монстра, если это босс
ADD COLUMN fast_travel_point BOOLEAN DEFAULT FALSE;

-- 4. Таблица открытых точек быстрого перемещения
CREATE TABLE IF NOT EXISTS player_fast_travel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    location_id INT NOT NULL,
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_location (user_id, location_id)
);

-- 5. Таблица логов действий фракций (для истории)
CREATE TABLE IF NOT EXISTS faction_action_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    faction_id INT NOT NULL,
    action_type VARCHAR(50), -- 'kill_member', 'complete_quest', 'donate'
    reputation_change INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE
);

-- 6. Начальные данные для фракций
INSERT INTO factions (name, description, base_attitude, color_code) VALUES
('Братство Стали', 'Технократы, хранящие довоенные технологии.', 0, '#FFD700'),
('Анклав', 'Остатки правительства США, элита.', -20, '#4B0082'),
('Рейдеры', 'Бандиты Пустоши.', -50, '#8B0000'),
('Караванщики', 'Торговая гильдия.', 20, '#228B22'),
('Подземный Житель', 'Герой Убежища.', 50, '#0000CD');

-- 7. Пример обновления существующих локаций (будет выполнено скриптом заполнения)
-- UPDATE locations SET location_type = 'dungeon', min_level = 5 WHERE name LIKE '%Бункер%';
-- UPDATE locations SET location_type = 'boss_arena', min_level = 10, boss_id = 1 WHERE name LIKE '%Улей%';

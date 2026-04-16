-- ==========================================
-- ЭТАП 3: Боевая система и Данные
-- ==========================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Активные бои
CREATE TABLE IF NOT EXISTS combats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    monster_id INT UNSIGNED NOT NULL, -- Шаблон монстра
    location_id INT UNSIGNED NOT NULL,
    
    monster_hp_current INT UNSIGNED NOT NULL,
    monster_ap_current INT UNSIGNED DEFAULT 0, -- Если у монстра есть АР
    
    turn_order JSON DEFAULT NULL, -- Хранит очередь ходов, если нужно
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_combat_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_combat_monster FOREIGN KEY (monster_id) REFERENCES monsters(id) ON DELETE CASCADE,
    CONSTRAINT fk_combat_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Логи боев (для истории)
CREATE TABLE IF NOT EXISTS combat_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_id INT UNSIGNED NOT NULL,
    actor_type ENUM('player', 'monster') NOT NULL,
    actor_id INT UNSIGNED NOT NULL, -- ID игрока или ID монстра (шаблона)
    action_type VARCHAR(50) NOT NULL, -- 'attack', 'heal', 'miss', 'crit'
    description TEXT,
    damage_dealt INT UNSIGNED DEFAULT 0,
    hp_remaining INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_combat FOREIGN KEY (combat_id) REFERENCES combats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- НАПОЛНЕНИЕ ДАННЫМИ (SEED)
-- ==========================================

-- Создаем админа (пароль: admin123)
-- Хэш получен через password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO players (username, email, password_hash, role_id, level, hp_max, hp_current) VALUES
('admin', 'admin@fallout.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1, 100, 100);

-- Локации
INSERT INTO locations (name, description, type_id, difficulty_level, coord_x, coord_y) VALUES
('Убежище 101', 'Ваш дом. Безопасное место.', 1, 1, 0, 0), -- Town
('Пустошь Столичной области', 'Радиоактивная пустошь.', 2, 2, 10, 0), -- Wasteland
('Вход в Метро', 'Темный вход в подземелье.', 4, 3, 10, 5), -- Dungeon Entrance
('Станция метро "Вест-Сайд"', 'Заброшенная станция, полная гулей.', 3, 4, 10, 6); -- Dungeon

-- Связи локаций (Выходы)
-- Из Убежища можно выйти в Пустошь
INSERT INTO location_exits (location_id, target_location_id, exit_name) VALUES (1, 2, 'Выйти в Пустошь');
-- Из Пустоши можно вернуться в Убежище или пойти в Метро
INSERT INTO location_exits (location_id, target_location_id, exit_name) VALUES 
(2, 1, 'Вернуться в Убежище'),
(2, 3, 'Войти в Метро');
-- Из входа в Метро можно выйти обратно или пройти глубже
INSERT INTO location_exits (location_id, target_location_id, exit_name) VALUES 
(3, 2, 'Выйти на поверхность'),
(3, 4, 'Спуститься на станцию');
-- Со станции только выход назад (пока что)
INSERT INTO location_exits (location_id, target_location_id, exit_name) VALUES (4, 3, 'Подняться на поверхность');

-- Предметы
-- Оружие: Ржавая дубина
INSERT INTO items (name, description, type_id, rarity_id, damage_min, damage_max, weight, value) VALUES
('Ржавая дубина', 'Лучше, чем ничего.', 1, 1, 2, 5, 3.5, 5);
-- Броня: Кожаная куртка
INSERT INTO items (name, description, type_id, rarity_id, armor_class_bonus, weight, value) VALUES
('Кожаная куртка', 'Защищает от легких ударов.', 2, 2, 3, 2.0, 25);
-- Лекарство: Стимулятор
INSERT INTO items (name, description, type_id, rarity_id, value) VALUES
('Стимулятор', 'Восстанавливает 50 HP.', 3, 2, 50);

-- Монстры
-- Гуль
INSERT INTO monsters (name, description, type_id, level, hp_max, damage_min, damage_max, xp_reward) VALUES
('Дикий гуль', 'Медленный, но опасный в ближнем бою.', 2, 2, 40, 4, 8, 15);
-- Рейдер
INSERT INTO monsters (name, description, type_id, level, hp_max, damage_min, damage_max, xp_reward) VALUES
('Рейдер-наркоман', 'Агрессивен и непредсказуем.', 1, 3, 60, 6, 12, 25);

-- Привязка монстров к локациям
-- В Пустоши встречаются гули и рейдеры
INSERT INTO location_monsters (location_id, monster_id, spawn_chance) VALUES (2, 1, 60.00), (2, 2, 40.00);
-- В Метро только гули
INSERT INTO location_monsters (location_id, monster_id, spawn_chance) VALUES (3, 1, 100.00), (4, 1, 100.00);

SET FOREIGN_KEY_CHECKS = 1;
-- Фаза 1: Экономика и Квесты
-- Таблица торговцев
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_id INT, -- NULL означает глобального торговца
    caps INT DEFAULT 1000, -- Стартовый капитал
    refresh_time INT DEFAULT 86400, -- Время обновления ассортимента (сек)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ассортимент торговцев
CREATE TABLE IF NOT EXISTS vendor_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    item_id INT NOT NULL,
    price_multiplier FLOAT DEFAULT 1.0, -- Множитель цены (0.5 = дешево, 2.0 = дорого)
    stock_count INT DEFAULT -1, -- -1 = бесконечно
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Рецепты крафта
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    result_item_id INT NOT NULL,
    result_count INT DEFAULT 1,
    required_skill INT DEFAULT 0, -- Требуемый уровень навыка (если нужно)
    craft_time INT DEFAULT 0, -- Время крафта в секундах
    FOREIGN KEY (result_item_id) REFERENCES items(id)
);

-- Ингредиенты для рецептов
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    PRIMARY KEY (recipe_id, item_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Таблица квестов (шаблоны)
CREATE TABLE IF NOT EXISTS quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('kill', 'collect', 'explore') NOT NULL,
    target_id INT, -- ID монстра или предмета
    target_count INT DEFAULT 1,
    reward_caps INT DEFAULT 0,
    reward_xp INT DEFAULT 0,
    reward_item_id INT, -- ID награды-предмета
    reward_item_count INT DEFAULT 0,
    is_repeatable BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE
);

-- Прогресс квестов игрока
CREATE TABLE IF NOT EXISTS player_quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    status ENUM('active', 'completed', 'failed') DEFAULT 'active',
    progress INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_quest (user_id, quest_id, status)
);

-- Заполнение тестовыми данными
-- Торговец "Михалыч" в Убежище (location_id=1, предположительно)
INSERT INTO vendors (name, location_id, caps) VALUES ('Михалыч (Торговец)', 1, 2000) 
ON DUPLICATE KEY UPDATE name=name;

-- Добавим пару товаров для Михалыча (предполагаем наличие базовых предметов в items)
-- Внимание: ID предметов могут отличаться, используем безопасную вставку через игнорирование ошибок при отсутствии предметов
INSERT IGNORE INTO vendor_items (vendor_id, item_id, price_multiplier, stock_count)
SELECT v.id, i.id, 1.2, 5
FROM vendors v, items i
WHERE v.name = 'Михалыч (Торговец)' AND i.type_id IN (3, 4); -- Оружие и броня

-- Рецепт: "Очистка воды" (пример)
INSERT INTO recipes (name, result_item_id, result_count, required_skill)
SELECT 'Чистая вода', id, 1, 0
FROM items WHERE name = 'Чистая вода' LIMIT 1
ON DUPLICATE KEY UPDATE name=name;

-- Квест: "Зачистка территории"
INSERT INTO quests (title, description, type, target_id, target_count, reward_caps, reward_xp, is_repeatable)
VALUES ('Охота на крыс', 'Убейте 5 радиоактивных крыс вокруг убежища.', 'kill', 1, 5, 50, 100, TRUE)
ON DUPLICATE KEY UPDATE title=title;

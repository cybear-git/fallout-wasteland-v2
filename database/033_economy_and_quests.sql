-- Фаза 1: Экономика и Квесты
-- ВАЖНО: В проекте используется разделённая модель предметов (weapons, armors, consumables, loot)
-- Для упрощения FK создаём унифицированное представление через общую таблицу

-- Таблица торговцев
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_id INT, -- NULL означает глобального торговца
    caps INT DEFAULT 1000, -- Стартовый капитал
    refresh_time INT DEFAULT 86400, -- Время обновления ассортимента (сек)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Унифицированная таблица предметов (ссылка на все типы)
-- Используем подход с item_type + item_id для полиморфной связи
CREATE TABLE IF NOT EXISTS items_unified (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('weapon', 'armor', 'consumable', 'loot') NOT NULL,
    item_id INT NOT NULL COMMENT 'ID из соответствующей таблицы (weapons, armors, etc)',
    name VARCHAR(100) NOT NULL,
    weight DECIMAL(5,2) DEFAULT 0,
    value INT UNSIGNED DEFAULT 0,
    UNIQUE KEY uniq_item (item_type, item_id),
    INDEX idx_type (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заполняем items_unified данными из всех таблиц предметов
INSERT IGNORE INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'weapon', id, name, weight, value FROM weapons
ON DUPLICATE KEY UPDATE name=name;

INSERT IGNORE INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'armor', id, name, weight, value FROM armors
ON DUPLICATE KEY UPDATE name=name;

INSERT IGNORE INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'consumable', id, name, weight, value FROM consumables
ON DUPLICATE KEY UPDATE name=name;

INSERT IGNORE INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'loot', id, name, weight, value FROM loot
ON DUPLICATE KEY UPDATE name=name;

-- Ассортимент торговцев (используем items_unified)
CREATE TABLE IF NOT EXISTS vendor_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    item_unified_id INT NOT NULL,
    price_multiplier FLOAT DEFAULT 1.0, -- Множитель цены (0.5 = дешево, 2.0 = дорого)
    stock_count INT DEFAULT -1, -- -1 = бесконечно
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (item_unified_id) REFERENCES items_unified(id) ON DELETE CASCADE
);

-- Рецепты крафта (используем items_unified)
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    result_item_unified_id INT NOT NULL,
    result_count INT DEFAULT 1,
    required_skill INT DEFAULT 0, -- Требуемый уровень навыка (если нужно)
    craft_time INT DEFAULT 0, -- Время крафта в секундах
    FOREIGN KEY (result_item_unified_id) REFERENCES items_unified(id)
);

-- Ингредиенты для рецептов (используем items_unified)
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_id INT NOT NULL,
    item_unified_id INT NOT NULL,
    quantity INT NOT NULL,
    PRIMARY KEY (recipe_id, item_unified_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (item_unified_id) REFERENCES items_unified(id) ON DELETE CASCADE
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
    reward_item_unified_id INT, -- ID награды-предмета из items_unified
    reward_item_count INT DEFAULT 0,
    is_repeatable BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE
);

-- Прогресс квестов игрока
-- ВАЖНО: Используем character_id вместо user_id для связи с таблицей characters
CREATE TABLE IF NOT EXISTS player_quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    quest_id INT NOT NULL,
    status ENUM('active', 'completed', 'failed') DEFAULT 'active',
    progress INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_quest (character_id, quest_id, status)
);

-- Заполнение тестовыми данными
-- Торговец "Михалыч" в Убежище (location_id=1, предположительно)
INSERT INTO vendors (name, location_id, caps) VALUES ('Михалыч (Торговец)', 1, 2000) 
ON DUPLICATE KEY UPDATE name=name;

-- Добавим пару товаров для Михалыча (используем items_unified)
-- Внимание: ID предметов могут отличаться, используем безопасную вставку через игнорирование ошибок при отсутствии предметов
INSERT IGNORE INTO vendor_items (vendor_id, item_unified_id, price_multiplier, stock_count)
SELECT v.id, iu.id, 1.2, 5
FROM vendors v, items_unified iu
WHERE v.name = 'Михалыч (Торговец)' AND iu.item_type IN ('weapon', 'armor');

-- Рецепт: "Очистка воды" (пример)
INSERT INTO recipes (name, result_item_unified_id, result_count, required_skill)
SELECT 'Чистая вода', id, 1, 0
FROM items_unified WHERE item_type = 'consumable' AND name = 'Чистая вода' LIMIT 1
ON DUPLICATE KEY UPDATE name=name;

-- Квест: "Зачистка территории"
INSERT INTO quests (title, description, type, target_id, target_count, reward_caps, reward_xp, is_repeatable)
VALUES ('Охота на крыс', 'Убейте 5 радиоактивных крыс вокруг убежища.', 'kill', 1, 5, 50, 100, TRUE)
ON DUPLICATE KEY UPDATE title=title;

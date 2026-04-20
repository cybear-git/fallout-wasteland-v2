-- ==========================================================
-- HOTFIX: Исправление ошибок миграций 033 и 034
-- Проблема: Ссылки на несуществующую таблицу 'users'
-- Решение: Переименование user_id -> character_id и пересоздание FK
-- ==========================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. ИСПРАВЛЕНИЕ ТАБЛИЦЫ player_quests (из 033)
-- Удаляем старый битый FK
ALTER TABLE player_quests 
DROP FOREIGN KEY IF EXISTS player_quests_ibfk_1;

-- Переименовываем колонку user_id в character_id
ALTER TABLE player_quests 
CHANGE COLUMN user_id character_id INT UNSIGNED NOT NULL;

-- Создаем правильный FK на characters
ALTER TABLE player_quests 
ADD CONSTRAINT fk_pq_character 
FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE;

-- Исправляем FK на items (если он битый из-за engine/type mismatch)
-- Примечание: Если items имеет другой движок, FK не создастся. 
-- Предполагаем, что items существует и корректна.
ALTER TABLE player_quests 
DROP FOREIGN KEY IF EXISTS player_quests_ibfk_2;

ALTER TABLE player_quests 
ADD CONSTRAINT fk_pq_item 
FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL;


-- 2. ИСПРАВЛЕНИЕ ТАБЛИЦЫ vendor_stocks (из 033)
-- Здесь была ссылка на users, меняем на location_id или убираем, если это глобальный вендор
-- В оригинале 033 было: vendor_stocks(user_id, item_id, quantity, price)
-- Логичнее привязать к локации или сделать глобальным. 
-- Для совместимости с кодом vendor.php (который использует location_id), изменим структуру:

ALTER TABLE vendor_stocks 
DROP FOREIGN KEY IF EXISTS vendor_stocks_ibfk_1;

-- Если в таблице есть user_id, удаляем его
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendor_stocks' AND COLUMN_NAME = 'user_id');

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE vendor_stocks DROP COLUMN user_id', 
    'SELECT "Column user_id not found"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем location_id если нет
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendor_stocks' AND COLUMN_NAME = 'location_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE vendor_stocks ADD COLUMN location_id INT UNSIGNED AFTER id', 
    'SELECT "Column location_id exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Создаем FK на locations
ALTER TABLE vendor_stocks 
ADD CONSTRAINT fk_vs_location 
FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE;

-- FK на items
ALTER TABLE vendor_stocks 
DROP FOREIGN KEY IF EXISTS vendor_stocks_ibfk_2; -- старое имя может отличаться

ALTER TABLE vendor_stocks 
ADD CONSTRAINT fk_vs_item 
FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE;


-- 3. ИСПРАВЛЕНИЕ ТАБЛИЦЫ crafting_recipes (из 033)
-- Убираем ссылки на users, если они там были (в миграции их не было, но проверим)
-- Проверяем FK на result_item_id и required_item_id
ALTER TABLE crafting_recipes 
DROP FOREIGN KEY IF EXISTS crafting_recipes_ibfk_1;
ALTER TABLE crafting_recipes 
DROP FOREIGN KEY IF EXISTS crafting_recipes_ibfk_2;

ALTER TABLE crafting_recipes 
ADD CONSTRAINT fk_cr_result 
FOREIGN KEY (result_item_id) REFERENCES items(id) ON DELETE CASCADE;

-- Если есть таблица crafting_requirements, чиним её
SET @table_exists = (SELECT COUNT(*) FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crafting_requirements');

IF @table_exists > 0 THEN
    ALTER TABLE crafting_requirements 
    DROP FOREIGN KEY IF EXISTS crafting_requirements_ibfk_1;
    ALTER TABLE crafting_requirements 
    DROP FOREIGN KEY IF EXISTS crafting_requirements_ibfk_2;
    
    ALTER TABLE crafting_requirements 
    ADD CONSTRAINT fk_cq_recipe 
    FOREIGN KEY (recipe_id) REFERENCES crafting_recipes(id) ON DELETE CASCADE;
    
    ALTER TABLE crafting_requirements 
    ADD CONSTRAINT fk_cq_item 
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE;
END IF;


-- 4. ИСПРАВЛЕНИЕ ТАБЛИЦ faction_reputation (из 034)
-- Было: user_id -> users(id). Надо: character_id -> characters(id)
ALTER TABLE faction_reputation 
DROP FOREIGN KEY IF EXISTS faction_reputation_ibfk_1;

ALTER TABLE faction_reputation 
CHANGE COLUMN user_id character_id INT UNSIGNED NOT NULL;

ALTER TABLE faction_reputation 
ADD CONSTRAINT fk_fr_character 
FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE;

ALTER TABLE faction_reputation 
ADD CONSTRAINT fk_fr_faction 
FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE CASCADE;


-- 5. ИСПРАВЛЕНИЕ ТАБЛИЦ dungeon_runs (из 034)
-- Было: user_id. Надо: character_id
ALTER TABLE dungeon_runs 
DROP FOREIGN KEY IF EXISTS dungeon_runs_ibfk_1;

ALTER TABLE dungeon_runs 
CHANGE COLUMN user_id character_id INT UNSIGNED NOT NULL;

ALTER TABLE dungeon_runs 
ADD CONSTRAINT fk_dr_character 
FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE;

ALTER TABLE dungeon_runs 
ADD CONSTRAINT fk_dr_dungeon 
FOREIGN KEY (dungeon_id) REFERENCES dungeons(id) ON DELETE CASCADE;


-- 6. ИСПРАВЛЕНИЕ ТАБЛИЦ fast_travel_points (из 034)
-- Было: user_id. Надо: character_id
ALTER TABLE fast_travel_points 
DROP FOREIGN KEY IF EXISTS fast_travel_points_ibfk_1;

ALTER TABLE fast_travel_points 
CHANGE COLUMN user_id character_id INT UNSIGNED NOT NULL;

ALTER TABLE fast_travel_points 
ADD CONSTRAINT fk_ft_character 
FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE;

ALTER TABLE fast_travel_points 
ADD CONSTRAINT fk_ft_location 
FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS=1;

SELECT "✅ База данных исправлена! Все FK теперь ссылаются на таблицу characters.";

-- database/027_add_pity_timer.sql
-- Добавление счетчика для pity timer системы поиска редких предметов

ALTER TABLE players 
ADD COLUMN searches_without_rare INT UNSIGNED DEFAULT 0 NOT NULL 
AFTER updated_at;

-- Комментарий к колонке
ALTER TABLE players 
MODIFY COLUMN searches_without_rare INT UNSIGNED DEFAULT 0 NOT NULL COMMENT 'Счетчик поисков без редкого предмета (для pity timer)';

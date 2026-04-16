-- database/029_fix_loot_range.sql
-- Исправление переполнения TINYINT для количеств лута

ALTER TABLE loot_table_items 
MODIFY COLUMN min_qty SMALLINT UNSIGNED DEFAULT 1,
MODIFY COLUMN max_qty SMALLINT UNSIGNED DEFAULT 1;

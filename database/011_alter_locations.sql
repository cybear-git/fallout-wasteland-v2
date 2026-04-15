-- Миграция 011: Расширение ENUM для tile_type
-- ДОБАВЛЕНЫ типы: 'forest','desert','ruins','camp' (убран 'military' как несуществующий)
-- Эта миграция должна применяться ПОСЛЕ 006 и ДО 013

ALTER TABLE locations 
MODIFY COLUMN tile_type ENUM('wasteland','city','dungeon','radzone','vault','mountain','forest','desert','ruins','camp') DEFAULT 'wasteland';
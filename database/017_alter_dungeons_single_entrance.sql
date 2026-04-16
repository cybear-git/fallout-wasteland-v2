-- database/017_alter_dungeons_single_entrance.sql
-- Изменение структуры данжей: одна точка входа, привязка к глобальной карте

-- 1. Добавляем поле для связи с глобальной картой
ALTER TABLE dungeons 
ADD COLUMN entrance_node_id INT DEFAULT NULL COMMENT 'ID клетки на global map где вход в данж';

ALTER TABLE dungeons 
ADD CONSTRAINT fk_dungeon_entrance FOREIGN KEY (entrance_node_id) REFERENCES map_nodes(id) ON DELETE SET NULL;

-- 2. Добавляем индекс для быстрого поиска
CREATE INDEX idx_entrance ON dungeons(entrance_node_id);

-- 3. Обновляем dungeon_nodes: убираем is_entrance у всех кроме одной ноды
ALTER TABLE dungeon_nodes
MODIFY COLUMN is_entrance TINYINT(1) DEFAULT 0 COMMENT 'Только одна нода может быть входом';

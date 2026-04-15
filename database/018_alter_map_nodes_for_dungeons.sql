-- database/018_alter_map_nodes_for_dungeons.sql
-- Добавление полей для работы с данжами на глобальной карте

-- 1. Добавляем поле для связи с данжом
ALTER TABLE map_nodes
ADD COLUMN dungeon_id INT DEFAULT NULL COMMENT 'Если клетка содержит вход в данж - ссылка на dungeons.id',
ADD CONSTRAINT fk_map_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeons(id) ON DELETE SET NULL;

-- 2. Добавляем индекс для быстрого поиска данжей на карте
CREATE INDEX idx_dungeon ON map_nodes(dungeon_id);

-- 3. Добавляем описание для клеток с данжами (может переопределять описание локации)
ALTER TABLE map_nodes
ADD COLUMN has_dungeon_entrance TINYINT(1) GENERATED ALWAYS AS (CASE WHEN dungeon_id IS NOT NULL THEN 1 ELSE 0 END) VIRTUAL,
ADD INDEX idx_has_dungeon (has_dungeon_entrance);

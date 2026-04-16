-- database/017_alter_dungeons_single_entrance.sql
-- Изменение структуры данжей: одна точка входа, привязка к глобальной карте

-- 1. Добавляем поле для связи с глобальной картой
ALTER TABLE dungeons 
ADD COLUMN entrance_node_id INT DEFAULT NULL COMMENT 'ID клетки на global map где вход в данж',
ADD CONSTRAINT fk_dungeon_entrance FOREIGN KEY (entrance_node_id) REFERENCES map_nodes(id) ON DELETE SET NULL;

-- 2. Добавляем индекс для быстрого поиска
CREATE INDEX idx_entrance ON dungeons(entrance_node_id);

-- 3. Обновляем dungeon_nodes: убираем is_entrance у всех кроме одной ноды
-- Это будет контролироваться при генерации, но добавим проверку
ALTER TABLE dungeon_nodes
MODIFY COLUMN is_entrance TINYINT(1) DEFAULT 0 COMMENT 'Только одна нода может быть входом';

-- 4. Добавляем ограничение CHECK (эмуляция через триггер для MySQL < 8.0.16)
DELIMITER $$
CREATE TRIGGER trg_single_entrance_before_insert
BEFORE INSERT ON dungeon_nodes
FOR EACH ROW
BEGIN
    DECLARE entrance_count INT;
    IF NEW.is_entrance = 1 THEN
        SELECT COUNT(*) INTO entrance_count 
        FROM dungeon_nodes 
        WHERE dungeon_id = NEW.dungeon_id AND is_entrance = 1;
        IF entrance_count > 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'У данжа уже есть точка входа. Может быть только один вход.';
        END IF;
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_single_entrance_before_update
BEFORE UPDATE ON dungeon_nodes
FOR EACH ROW
BEGIN
    DECLARE entrance_count INT;
    IF NEW.is_entrance = 1 AND OLD.is_entrance = 0 THEN
        SELECT COUNT(*) INTO entrance_count 
        FROM dungeon_nodes 
        WHERE dungeon_id = NEW.dungeon_id AND is_entrance = 1;
        IF entrance_count > 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'У данжа уже есть точка входа. Может быть только один вход.';
        END IF;
    END IF;
END$$
DELIMITER ;

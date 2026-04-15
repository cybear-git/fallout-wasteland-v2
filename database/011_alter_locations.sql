ALTER TABLE locations 
MODIFY COLUMN tile_type ENUM('wasteland','city','dungeon','radzone','vault','mountain','forest','desert','ruins','camp') DEFAULT 'wasteland';
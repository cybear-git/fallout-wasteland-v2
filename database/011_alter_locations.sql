ALTER TABLE locations 
MODIFY COLUMN tile_type ENUM('wasteland','city','dungeon','radzone','vault','mountain','ruins','desert','forest','military','camp') DEFAULT 'wasteland';
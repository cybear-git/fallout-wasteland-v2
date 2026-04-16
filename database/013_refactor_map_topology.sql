-- database/013_refactor_map_topology.sql
-- Рефакторинг карты: каталог шаблонов, физическая сетка и граф переходов

-- 1. Пересоздаём locations как ЧИСТЫЙ КАТАЛОГ (без координат)
DROP TABLE IF EXISTS locations;
CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_key VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    tile_type ENUM('wasteland','city','dungeon','radzone','vault','vault_ext','mountain','forest','desert','ruins','camp','military','military_base') DEFAULT 'wasteland',
    description TEXT,
    danger_level TINYINT UNSIGNED DEFAULT 1,
    radiation_level TINYINT UNSIGNED DEFAULT 0,
    loot_quality TINYINT UNSIGNED DEFAULT 1,
    is_vault TINYINT(1) DEFAULT 0,
    is_dungeon TINYINT(1) DEFAULT 0,
    dungeon_size TINYINT UNSIGNED DEFAULT 0,
    weather_resistant TINYINT(1) DEFAULT 0,
    scene_key VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (tile_type, is_vault, is_dungeon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Физические клетки сетки (геометрия)
DROP TABLE IF EXISTS map_nodes;
CREATE TABLE IF NOT EXISTS map_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_x INT NOT NULL,
    pos_y INT NOT NULL,
    location_id INT DEFAULT NULL,
    is_border TINYINT(1) DEFAULT 0,
    border_direction ENUM('n','s','e','w','ne','nw','se','sw') NULL,
    border_message VARCHAR(255) NULL,
    description_override TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_coords (pos_x, pos_y),
    CONSTRAINT fk_node_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    INDEX idx_coords (pos_x, pos_y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Примечание: Дополнительные поля (biome, is_impassable, radiation_level, weather_id)
-- добавляются в миграции 021_massive_world_mechanics.sql

-- 3. Граф переходов (кто с кем соединён)
DROP TABLE IF EXISTS map_adjacency;
CREATE TABLE IF NOT EXISTS map_adjacency (
    from_node_id INT NOT NULL,
    to_node_id INT NOT NULL,
    direction ENUM('n','s','e','w','ne','nw','se','sw') NOT NULL,
    PRIMARY KEY (from_node_id, direction),
    CONSTRAINT fk_adj_from FOREIGN KEY (from_node_id) REFERENCES map_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_to FOREIGN KEY (to_node_id) REFERENCES map_nodes(id) ON DELETE CASCADE,
    INDEX idx_to (to_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dungeons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dungeon_key VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    min_level TINYINT UNSIGNED DEFAULT 1,
    boss_key VARCHAR(50) DEFAULT NULL COMMENT 'Ключ монстра-босса из таблицы monsters',
    reward_json JSON DEFAULT NULL COMMENT 'Награда за прохождение (крышки, лут, XP)',
    respawn_hours TINYINT UNSIGNED DEFAULT 24,
    last_cleared_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dungeon_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dungeon_id INT NOT NULL,
    pos_x INT NOT NULL,
    pos_y INT NOT NULL,
    tile_type ENUM('corridor','room','boss','treasure','exit','trap') DEFAULT 'corridor',
    location_id INT DEFAULT NULL,
    is_entrance TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    
    UNIQUE KEY uniq_dungeon_node (dungeon_id, pos_x, pos_y),
    CONSTRAINT fk_dn_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeons(id) ON DELETE CASCADE,
    CONSTRAINT fk_dn_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
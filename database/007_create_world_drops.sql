CREATE TABLE IF NOT EXISTS world_drops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_x INT NOT NULL,
    pos_y INT NOT NULL,
    owner_character_id INT NOT NULL,
    item_type ENUM('weapon','armor','consumable','loot') NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    expires_at DATETIME NOT NULL,
    is_looted TINYINT(1) DEFAULT 0,
    looted_by_character_id INT DEFAULT NULL,
    looted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coords (pos_x, pos_y, is_looted),
    INDEX idx_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    item_type ENUM('weapon','armor','consumable','loot') NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    equipped TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE RESTRICT,
    UNIQUE KEY uniq_char_item (character_id, item_type, item_key),
    INDEX idx_character (character_id),
    INDEX idx_type_key (item_type, item_key),
    INDEX idx_equipped (equipped)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
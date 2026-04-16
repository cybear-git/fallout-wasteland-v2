-- ============================================================================
-- МИГРАЦИЯ 023: ЖУРНАЛ ПОИСКА И ХЛАМОТРОН
-- ============================================================================
-- Цель: Таблица для логирования поисков и поддержка хламотрона

-- 1. Журнал поисковых операций (для лога и баланса)
CREATE TABLE IF NOT EXISTS `search_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT UNSIGNED NOT NULL,
    `map_node_id` INT UNSIGNED NOT NULL,
    `result` ENUM('nothing', 'found_item', 'monster_encounter', 'trap') NOT NULL,
    `item_found_key` VARCHAR(50) DEFAULT NULL,
    `xp_gained` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_player (`player_id`),
    INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Добавляем колонки для ХЛАМОТРОНА в таблицу игроков
-- Проверяем существование колонок перед добавлением
SET @has_junk_jet = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'has_junk_jet');
SET @sql = IF(@has_junk_jet = 0, 
    'ALTER TABLE players ADD COLUMN has_junk_jet BOOLEAN DEFAULT FALSE', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_junk_ammo = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'junk_jet_ammo');
SET @sql = IF(@has_junk_ammo = 0, 
    'ALTER TABLE players ADD COLUMN junk_jet_ammo INT UNSIGNED DEFAULT 0', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

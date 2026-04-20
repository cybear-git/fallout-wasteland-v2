-- Migration 035: Admin System Core
-- Добавляет систему ролей, прав доступа и журнал аудита действий

-- 1. Таблица ролей администраторов
CREATE TABLE IF NOT EXISTS `admin_roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE, -- 'super_admin', 'moderator', 'support'
    `permissions` JSON NOT NULL, -- {"ban": true, "edit_items": true, "view_logs": false}
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Расширение таблицы players для привязки к роли
-- Добавляем колонку admin_role_id. NULL = обычный игрок
ALTER TABLE `players` 
ADD COLUMN `admin_role_id` INT UNSIGNED NULL AFTER `last_login`,
ADD CONSTRAINT `fk_players_admin_role` 
FOREIGN KEY (`admin_role_id`) REFERENCES `admin_roles`(`id`) ON DELETE SET NULL;

-- 3. Журнал аудита (Action Log) - самое важное!
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL, -- ID игрока-админа
    `action` VARCHAR(100) NOT NULL, -- 'BAN_PLAYER', 'GIVE_ITEM', 'CHANGE_SETTING'
    `target_id` INT UNSIGNED NULL, -- ID цели (игрока, предмета), если есть
    `details` JSON NULL, -- Детали: {"item_id": 5, "count": 10, "reason": "Cheat"}
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_logs_action` (`action`),
    INDEX `idx_admin_logs_admin` (`admin_id`),
    INDEX `idx_admin_logs_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Заполняем базовые роли
INSERT INTO `admin_roles` (`name`, `permissions`) VALUES
('super_admin', '{"all": true}'),
('moderator', '{"ban": true, "kick": true, "view_logs": true, "edit_items": false, "change_settings": false}'),
('support', '{"view_logs": true, "edit_items": false, "ban": false}');

-- ПРИМЕЧАНИЕ: Вручную установите admin_role_id = 1 для вашего главного аккаунта в таблице players после запуска!
-- UPDATE players SET admin_role_id = 1 WHERE id = 1; 

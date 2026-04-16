-- database/008_alter_characters_add_status.sql
-- Добавление полей статуса персонажа
-- Since db_import.php drops tables first, IF NOT EXISTS is safe but we use a safer approach

ALTER TABLE characters 
ADD COLUMN status ENUM('alive', 'dead') DEFAULT 'alive' AFTER player_id,
ADD COLUMN died_at DATETIME DEFAULT NULL AFTER status,
ADD COLUMN death_reason VARCHAR(255) DEFAULT NULL AFTER died_at;

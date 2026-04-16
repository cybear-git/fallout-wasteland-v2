ALTER TABLE characters
ADD COLUMN status ENUM('alive', 'dead') DEFAULT 'alive' AFTER player_id,
ADD COLUMN died_at DATETIME DEFAULT NULL AFTER status,
ADD COLUMN death_reason VARCHAR(255) DEFAULT NULL AFTER died_at;
-- 1. Журнал действий администратора (Логи)
-- Позволяет отслеживать, кто, когда и что изменил в базе.
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,          -- Что сделали (CREATE_MONSTER, DELETE_USER)
    table_name VARCHAR(50) NOT NULL,       -- В какой таблице (monsters, players)
    record_id INT DEFAULT NULL,            -- ID измененной записи
    ip_address VARCHAR(45) DEFAULT '127.0.0.1', -- IP админа
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Таблица настроек (Game Settings)
-- Хранит конфигурацию игры в формате Ключ-Значение.
-- Позволяет менять баланс (например, множитель опыта) без влезания в код.
CREATE TABLE IF NOT EXISTS game_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL, -- Например: 'xp_multiplier'
    setting_value TEXT DEFAULT NULL,         -- Значение: '1.5'
    category VARCHAR(50) DEFAULT 'general',  -- Группа: 'combat', 'economy'
    description TEXT DEFAULT NULL,           -- Пояснение: 'Множитель получаемого опыта'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
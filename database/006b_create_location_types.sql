-- Миграция 006b: Создание справочника типов локаций
-- Цель: Нормализация данных - вынос типов локаций в отдельную таблицу
-- Используем TINYINT для совместимости с 026_normalize_enums

CREATE TABLE IF NOT EXISTS location_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_key VARCHAR(50) UNIQUE NOT NULL COMMENT 'Ключ типа (vault_entrance, dungeon_entrance)',
    type_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Название типа',
    description TEXT COMMENT 'Описание типа локации',
    base_difficulty TINYINT UNSIGNED DEFAULT 1 COMMENT 'Базовая сложность',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_key (type_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Миграция 032: Добавление настроек шансов поиска лута
-- Цель: Вынос шансов выпадения предметов из кода в базу данных для управления через админку

INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES
-- Базовые шансы поиска (в процентах, значение 0-100)
('search_loot_base_chance', '75', 'loot', 'Базовый шанс найти что-то при поиске (75%)'),
('search_caps_chance_base', '15', 'loot', 'Базовый шанс найти крышки (15%)'),
('search_caps_min', '1', 'loot', 'Минимальное количество крышек'),
('search_caps_max', '3', 'loot', 'Максимальное количество крышек'),

-- Шансы на типы предметов (в процентах от 0-100, применяются если найден предмет)
('search_weapon_chance', '0.4', 'loot', 'Шанс найти оружие при поиске (0.4%)'),
('search_armor_chance', '0.25', 'loot', 'Шанс найти броню при поиске (0.25%)'),
('search_consumable_chance', '2', 'loot', 'Шанс найти расходники при поиске (2%)'),
('search_loot_junk_chance', '97.35', 'loot', 'Шанс найти мусор при поиске (остаток до 100%)'),

-- Модификаторы от характеристик
('search_luck_bonus_multiplier', '0.3', 'loot', 'Множитель бонуса от удачи (0.3 = +3% за luck=10)'),
('search_location_quality_multiplier', '0.15', 'loot', 'Множитель от качества локации (0.15 = +15% за качество)'),

-- Лимиты и пороги
('search_pity_timer_threshold', '50', 'loot', 'Количество поисков без редкого предмета до гарантированной находки'),
('search_cooldown_seconds', '30', 'loot', 'Кулдаун между поисками (секунды)'),

-- Шансы на встречу монстров
('search_monster_encounter_base', '10', 'combat', 'Базовый шанс встречи монстра при неудачном поиске (10%)'),
('search_monster_danger_multiplier', '2', 'combat', 'Множитель опасности локации (+2% за уровень опасности)')

ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;

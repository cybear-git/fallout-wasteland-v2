-- Миграция 020: Начальное наполнение базы данных для тестирования
-- Включает: админа, стартовые локации, монстров (в т.ч. боссов), предметы, фразы

-- 1. Создаем администратора (пароль: admin123)
-- Хэш получен через password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO admins (username, password_hash, email, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@vault101.com', 'superadmin', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- 2. Добавляем недостающие типы локаций в справочник (если их нет)
INSERT INTO location_types (type_key, type_name, description, base_difficulty) VALUES
('vault_entrance', 'Вход в Убежище', 'Тяжелая металлическая дверь в скале. Единственный выход.', 1),
('dungeon_entrance', 'Вход в Подземелье', 'Зияющая темнота входа. Пахнет сыростью и смертью.', 5)
ON DUPLICATE KEY UPDATE type_name = VALUES(type_name);

-- 3. Добавляем специальных монстров-боссов
INSERT INTO monsters (monster_key, name, level, speed, status, base_hp, base_armor, base_dmg, xp_reward, spawn_weight, habitat, loot_table, is_boss) VALUES
('colonel_mortert', 'Полковник Морерт', 15, 2, 'boss', 400, 25, 45, 1000, 0, 'city', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":500},{"type":"weapon","key":"fat_man","chance":1.0,"qty":1}]', 1),
('overlord_fawkes', 'Надзиратель Фокс', 12, 3, 'boss', 300, 20, 35, 750, 0, 'vault', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":300},{"type":"armor","key":"power_armor_t51","chance":0.5,"qty":1}]', 1),
('nightkin_leader', 'Лидер Найткинов', 10, 5, 'boss', 220, 15, 30, 500, 0, 'ruins', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":200},{"type":"weapon","key":"laser_rifle","chance":0.7,"qty":1}]', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4. Добавляем уникальные предметы для наград
INSERT INTO loot (item_key, name, description, weight, value, category, stackable, max_stack, is_unique) VALUES
('vault_101_key', 'Ключ от Убежища 101', 'Старый магнитный ключ. Дверь уже открыта.', 0.1, 0, 'quest', 1, 1, 1),
('liberty_prime_core', 'Ядро Либерти Прайм', 'Мощный энергетический источник.', 5.0, 5000, 'quest', 1, 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 5. Добавляем атмосферные фразы для разных типов локаций (выборка 20 из 100)
INSERT INTO location_quotes (quote_text, tile_type, mood, source_character, is_spoiler) VALUES
-- Пустошь (wasteland)
('Война... Война никогда не меняется.', 'wasteland', 'melancholy', 'Narrator', 0),
('Пустошь учит одному: доверяй только своему оружию.', 'wasteland', 'grim', 'Vault Dweller', 0),
('Радиация здесь — как ветер. Всегда с тобой.', 'wasteland', 'grim', 'Traveler', 0),
('Выжил? Значит, ты либо удачлив, либо опасен.', 'wasteland', 'neutral', 'Merchant', 0),
('Крышки решают всё. Всё остальное — пыль.', 'wasteland', 'pragmatic', 'Trader', 0),
-- Руины (ruins)
('Здесь когда-то жили люди. Теперь только эхо.', 'ruins', 'melancholy', 'Explorer', 0),
('Рейдеры приходят ночью. Молись, чтобы ты спал крепко.', 'ruins', 'dark', 'Survivor', 0),
('Камень помнит крики. Стены помнят кровь.', 'ruins', 'dark', 'Ghoul', 0),
-- Убежище (vault)
('Убежище — это не стены. Это люди внутри.', 'vault', 'hopeful', 'Overseer', 0),
('Дверь закрыта. Но ты ведь нашел выход, да?', 'vault', 'mysterious', 'Vault Dweller', 0),
('111 дней. Столько длился наш сон.', 'vault', 'melancholy', 'Sole Survivor', 0),
-- Радиационная зона (radzone)
('Счетчик щелкает. Значит, ты еще жив.', 'radzone', 'grim', 'Stalker', 0),
('Зеленое свечение — последний закат этого мира.', 'radzone', 'poetic', 'Hermit', 0),
('Мутанты молятся на свечение. Я молюсь на стимпак.', 'radzone', 'pragmatic', 'Doctor', 0),
-- Город (city)
('Capital Wastes помнит величие. И предательство.', 'city', 'epic', 'Elder Lyons', 0),
('Небоскребы — это надгробия старой цивилизации.', 'city', 'melancholy', 'Scribe', 0),
('Братство Стали держит этот сектор. Пока что.', 'city', 'proud', 'Paladin', 0),
-- Подземелье (dungeon)
('Глубоко под землей демоны носят человеческие лица.', 'dungeon', 'dark', 'Super Mutant', 0),
('Каждый шаг в темноте может стать последним.', 'dungeon', 'tense', 'Mercenary', 0),
('Босс ждет на нижнем уровне. Он всегда ждет.', 'dungeon', 'ominous', 'Survivor', 0),
-- Горы (mountain)
('Горы — это границы мира. Дальше — только смерть.', 'mountain', 'grim', 'Scout', 0),
('Ветер здесь срезает кожу. Воздух режет легкие.', 'mountain', 'harsh', 'Climber', 0);

-- 6. Создаем первый тестовый данж (для проверки механики)
-- Сначала убедимся, что есть локация-вход
INSERT INTO locations (pos_x, pos_y, tile_type, tile_name, description, danger_level, radiation_level, loot_quality, is_vault, is_dungeon, dungeon_size, is_border) VALUES
(15, 15, 'dungeon_entrance', 'Вход в Бункер Альфа', 'Тяжелая стальная дверь с символом Анклава.', 10, 20, 8, 0, 1, 5, 0)
ON DUPLICATE KEY UPDATE tile_name = VALUES(tile_name);

-- Получаем ID созданной локации и создаем данж
-- Примечание: В реальном сценарии это делается через админку
-- Здесь мы используем подстановку для демонстрации
SET @entrance_loc_id = (SELECT id FROM locations WHERE pos_x = 15 AND pos_y = 15 LIMIT 1);
SET @boss_id = (SELECT id FROM monsters WHERE monster_key = 'colonel_mortert' LIMIT 1);

INSERT INTO dungeons (dungeon_key, name, description, min_level, boss_id, reward_xp, reward_caps, is_active) VALUES
('bunker_alpha', 'Бункер Альфа', 'Заброшенный бункер Анклава с экспериментальным оружием.', 8, @boss_id, 500, 200, 1);

-- Создаем ноды для этого данжа (простая линейная структура)
SET @dungeon_id = (SELECT id FROM dungeons WHERE dungeon_key = 'bunker_alpha' LIMIT 1);

INSERT INTO dungeon_nodes (dungeon_id, pos_x, pos_y, tile_type, is_entrance) VALUES
(@dungeon_id, 0, 0, 'entrance', 1),
(@dungeon_id, 1, 0, 'corridor', 0),
(@dungeon_id, 2, 0, 'room', 0),
(@dungeon_id, 3, 0, 'boss', 0),
(@dungeon_id, 2, 1, 'treasure', 0);

-- 7. Обновляем конфигурацию игры
INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES
('combat_turn_timeout', '30', 'combat', 'Время на ход в бою (секунды)'),
('loot_drop_chance', '0.75', 'combat', 'Шанс выпадения лута с врага (75%)'),
('xp_death_penalty', '0.1', 'progression', 'Потеря опыта при смерти (10%)'),
('companion_max_count', '2', 'gameplay', 'Максимальное количество спутников')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

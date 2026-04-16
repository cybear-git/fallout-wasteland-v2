-- database/012_populate_expansion.sql
-- Расширение базы данных (Третий виток наполнения)
-- Добавляет новые записи, НЕ удаляя старые.
-- Поле icon УДАЛЕНО из запросов.

-- 1. НОВЫЕ НАСТРОЙКИ
INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES
('trade_markup_player', '0.8', 'economy', 'Множитель цены продажи для игрока (80% от стоимости)'),
('trade_markup_vendor', '1.2', 'economy', 'Множитель цены покупки у игрока (120% от стоимости)'),
('weather_damage_rad', '2.0', 'environment', 'Урон радиацией за ход в кислотном дожде'),
('weather_visibility_mod', '0.5', 'environment', 'Множитель видимости в пыльной буре'),
('craft_scrap_ratio', '0.5', 'crafting', 'Возврат компонентов при разборке предмета (50%)'),
('companion_max_level', '20', 'progression', 'Максимальный уровень спутников'),
('stealth_detection_radius', '5.0', 'combat', 'Радиус обнаружения скрытных атак')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 2. НОВЫЕ ЛОКАЦИИ (Лагеря, Пещеры, Аванпосты)
INSERT INTO locations (pos_x, pos_y, tile_type, tile_name, description, danger_level, radiation_level, loot_quality, is_vault, is_dungeon, dungeon_size, is_border, border_direction, border_message) VALUES
-- Пещеры и подземелья
(-6, 6, 'dungeon', 'Пещера Кротокрысов', 'Сырой вход. В глубине слышен скрежет когтей.', 3, 5, 2, 0, 1, 3, 0, NULL, NULL),
(7, -7, 'dungeon', 'Затопленный Бункер', 'Вода по щиколотку. Мигает аварийное освещение.', 6, 20, 4, 0, 1, 4, 0, NULL, NULL),
(-9, -9, 'dungeon', 'Лаборатория "Биотех"', 'Разбитые колбы. На стенах следы когтей и пуль.', 8, 30, 6, 0, 1, 5, 0, NULL, NULL),

-- Аванпосты фракций
(10, 0, 'city', 'Блокпост НКР', 'Мешки с песком и патрули. Проход только по документам.', 3, 0, 3, 0, 0, 0, 0, NULL, NULL),
(-12, 2, 'city', 'Форт Независимости', 'Каменные стены. Внутри слышны выстрелы.', 7, 10, 5, 0, 1, 6, 0, NULL, NULL),
(6, 11, 'ruins', 'Лагерь Братства Стали', 'Силовая броня на постаментах. В воздухе висит напряжение.', 5, 5, 6, 0, 0, 0, 0, NULL, NULL),

-- Опасные зоны
(15, 5, 'radzone', 'Кратер Фейрфакс', 'Зеленое свечение. Мутанты бродят у края.', 9, 90, 7, 0, 0, 0, 0, NULL, NULL),
(-15, -5, 'forest', 'Гнилое Болото', 'Вязкая трясина. Туман скрывает силуэты.', 6, 40, 4, 0, 0, 0, 0, NULL, NULL),
(8, -14, 'desert', 'Карьер "Сьерра"', 'Ржавые экскаваторы. В тени прячутся скорпионы.', 7, 25, 5, 0, 0, 0, 0, NULL, NULL);

-- 3. УНИКАЛЬНОЕ ОРУЖИЕ (Фракционное и Легендарное)
INSERT INTO weapons (item_key, name, description, weight, value, dmg_dice, dmg_mod, crit_chance, crit_mult, range_type, min_range, max_range, min_str) VALUES
('shock_baton', 'Электро-Дубинка', 'Бьёт током при каждом ударе.', 2.0, 150, 8, 4, 15.0, 1.5, 'melee', 0, 1, 4),
('power_fist', 'Силовой Кастет', 'Гидравлический удар. Ломает кости.', 4.0, 400, 12, 10, 10.0, 2.0, 'melee', 0, 1, 6),
('minigun', 'Миниган', 'Вращающиеся стволы. Требует много патронов.', 16.0, 3000, 10, 12, 5.0, 1.5, 'long', 0, 8, 9),
('missile_launcher', 'Ракетница', 'Одна ракета — одна проблема.', 12.0, 2500, 60, 20, 5.0, 3.0, 'long', 4, 15, 7),
('flamer', 'Огнемёт', 'Сжигает всё на своем пути.', 10.0, 1000, 8, 8, 0.0, 1.2, 'short', 0, 4, 5),
('gauss_rifle', 'Гаусс-Винтовка', 'Разгоняет снаряд магнитным полем.', 9.0, 2000, 24, 12, 25.0, 3.0, 'long', 4, 20, 8),
('plasma_grenade', 'Плазма-Граната', 'Липкая и едкая.', 1.0, 200, 20, 10, 5.0, 1.5, 'short', 1, 3, 0),
('vampire_edge', 'Клинок Вампира', 'Лезвие, пьющее кровь. Лечит владельца.', 3.0, 1500, 10, 8, 15.0, 2.5, 'melee', 0, 1, 5),
('lincolns_repeater', 'Винтовка Линкольна', 'Сувенир старого мира. Точная.', 6.0, 800, 14, 8, 30.0, 3.0, 'long', 2, 18, 4);

-- 4. БРОНЯ И АКСЕССУАРЫ
INSERT INTO armors (item_key, name, description, weight, value, defense, rad_resistance, slot_type, min_str, min_end) VALUES
('brotherhood_armor', 'Броня Братства Стали', 'Тяжелая, с позолотой. Знак элиты.', 14.0, 800, 10, 8, 'tors', 7, 4),
('ncr_ranger_armor', 'Броня Рейнджера НКР', 'Кожаная куртка с плащом.', 5.0, 400, 6, 2, 'tors', 4, 3),
('chinese_stealth_armor', 'Китайский Стелс-Костюм', 'Активный камуфляж. Почти невидим.', 3.0, 1200, 3, 0, 'tors', 0, 0),
('goggles_nightvision', 'Очки Ночного Видения', 'Пустошь ночью — не помеха.', 0.4, 150, 0, 0, 'head', 0, 0),
('gas_mask_advanced', 'Продвинутый Противогаз', 'Золотой фильтр. Максимальная защита.', 0.6, 80, 1, 30, 'head', 0, 0),
('combat_boots', 'Армейские Ботинки', 'Тихий шаг. Увеличивает уклонение.', 1.5, 40, 2, 0, 'legs', 0, 0),
('spiked_gloves', 'Перчатки с Шипами', 'Дополнительный урон в рукопашной.', 0.4, 30, 1, 0, 'arms', 0, 0);

-- 5. ЕДА И ХИМИЯ
INSERT INTO consumables (item_key, name, description, weight, value, heal_amount, rad_heal, addiction_chance, boost_type, boost_value, boost_duration, special_effect) VALUES
('blamco_mac', 'Бламко с Сыром', 'Сухой, но сытный ужин.', 0.5, 30, 20, 0, 0.0, 'end', 1, 2, NULL),
('salted_meat', 'Соленое Мясо', 'Твердое как камень. Но питательное.', 0.4, 15, 10, 0, 2.0, 'str', 1, 2, NULL),
('fresh_apple', 'Свежее Яблоко', 'Чудо природы. Чистое и сладкое.', 0.2, 20, 5, -5, 0.0, NULL, 0, 0, NULL),
('ant_nectar', 'Нектар Муравья', 'Сладкий яд. Бодрит.', 0.3, 25, 0, 0, 5.0, 'agi', 1, 3, NULL),
('fixer', 'Фиксер', 'Лечит зависимости (немного).', 0.1, 60, 0, 10, 0.0, NULL, 0, 0, 'addiction_cure'),
('x_cell', 'X-Клетка', 'Экспериментальный стимулятор. Опасен.', 0.1, 100, 50, 0, 40.0, 'all_stats', 1, 2, 'mutate_chance'),
('turbo', 'Турбо', 'Скорость мысли и реакции.', 0.1, 35, 0, 0, 15.0, 'agi', 3, 2, NULL);

-- 6. ЛУТ И РЕСУРСЫ
INSERT INTO loot (item_key, name, description, weight, value, category, stackable, max_stack) VALUES
('crystal_oscillator', 'Кварцевый Генератор', 'Сердце электроники.', 0.2, 40, 'component', 1, 50),
('aluminum', 'Алюминий', 'Легкий и прочный.', 0.5, 8, 'component', 1, 100),
('fiber_optics', 'Оптоволокно', 'Для передачи света.', 0.1, 15, 'component', 1, 50),
('gears_small', 'Малые Шестеренки', 'Для часов и ловушек.', 0.1, 4, 'component', 1, 99),
('holotape_blank', 'Чистый Голодиск', 'Можно записать сообщение.', 0.1, 5, 'key_item', 1, 10),
('skeleton_key', 'Отмычка Мастера', 'Открывает любые замки.', 0.1, 500, 'key_item', 1, 1),
('nuka_grenade', 'Ядер-Граната', 'Взрывная Нюка-Кола.', 0.5, 50, 'component', 1, 10),
('toy_car', 'Игрушечная Машинка', 'Хлам для обмена.', 0.5, 10, 'junk', 1, 20),
('fancy_lad_mag', 'Журнал "Модный Парень"', 'Для чтения перед сном.', 0.3, 15, 'junk', 1, 10);

-- 7. МОНСТРЫ И БОССЫ
INSERT INTO monsters (monster_key, name, level, speed, status, base_hp, base_armor, base_dmg, xp_reward, spawn_weight, habitat, loot_table) VALUES
('centaur', 'Кентавр', 6, 2, 'wandering', 80, 0, 12, 75, 15, 'dungeon,radzone', '[{"type":"consumable","key":"x_cell","chance":0.1,"qty":1}, {"type":"loot","key":"bottle_cap","chance":0.9,"qty":8}]'),
('mirelurk_king', 'Король Болотников', 9, 4, 'guarding', 180, 12, 22, 250, 2, 'radzone,dungeon', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":40}, {"type":"armor","key":"metal_armor","chance":0.1,"qty":1}, {"type":"consumable","key":"nuka_grenade","chance":0.2,"qty":1}]'),
('liberty_prime', 'Линкольн Прайм', 20, 3, 'boss', 1000, 30, 50, 2000, 0, 'military,city', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":500}, {"type":"weapon","key":"gauss_rifle","chance":0.5,"qty":1}]'),
('assaultron', 'Штурмотрон', 8, 8, 'patrol', 90, 8, 16, 130, 12, 'city,military', '[{"type":"loot","key":"crystal_oscillator","chance":0.7,"qty":1}, {"type":"loot","key":"circuit_board","chance":0.6,"qty":2}, {"type":"weapon","key":"laser_rifle","chance":0.2,"qty":1}]'),
('nightkin', 'Ночной Кин', 10, 6, 'wandering', 150, 5, 24, 190, 8, 'dungeon,forest', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":25}, {"type":"weapon","key":"shock_baton","chance":0.2,"qty":1}, {"type":"consumable","key":"stealth_boy","chance":0.05,"qty":1}]'),
('bloodbug', 'Кровосос', 3, 7, 'wandering', 25, 0, 5, 20, 40, 'forest,wasteland', '[{"type":"loot","key":"bottle_cap","chance":0.4,"qty":2}, {"type":"consumable","key":"ant_nectar","chance":0.15,"qty":1}]'),
('floaters', 'Флоатеры', 5, 1, 'wandering', 40, 10, 8, 45, 20, 'ruins,radzone', '[{"type":"loot","key":"bottle_cap","chance":0.8,"qty":6}, {"type":"consumable","key":"jet","chance":0.2,"qty":1}]');
-- 1. НАСТРОЙКИ БАЛАНСА
INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES
('xp_multiplier', '1.0', 'progression', 'Глобальный множитель опыта'),
('encounter_chance_base', '0.25', 'combat', 'Базовый шанс случайной встречи (25%)'),
('crit_chance_base', '0.05', 'combat', 'Базовый шанс критического удара (5%)'),
('caps_found_min', '5', 'economy', 'Минимум крышек, найденных при поиске'),
('caps_found_max', '25', 'economy', 'Максимум крышек, найденных при поиске'),
('repair_cost_multiplier', '0.15', 'economy', 'Стоимость починки (15% от цены предмета)'),
('max_inventory_slots', '50', 'inventory', 'Максимальное кол-во слотов в инвентаре')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 2. ЛОКАЦИИ (Карта мира)
-- Центр и ближайшие окрестности
INSERT INTO locations (pos_x, pos_y, tile_type, tile_name, description, danger_level, radiation_level, loot_quality, is_vault, is_dungeon, dungeon_size, is_border, border_direction, border_message) VALUES
-- Убежище и старт
(0, 0, 'vault', 'Выход из Убежища 101', 'Тяжелая металлическая дверь позади. Впереди — выжженная пустошь.', 1, 0, 1, 1, 0, 0, 0, NULL, NULL),
(1, 0, 'wasteland', 'Пыльная тропа', 'Сухая земля и редкие кусты. Ветер гонит радиоактивную пыль.', 2, 5, 2, 0, 0, 0, 0, NULL, NULL),
(-1, 0, 'wasteland', 'Старый перекресток', 'Ржавый знак "Capital Wastes" едва читается. Тишина пугает.', 2, 5, 2, 0, 0, 0, 0, NULL, NULL),
(0, 1, 'wasteland', 'Поле с минами', 'Торчат ржавые столбы. Лучше не сходить с тропы.', 4, 10, 3, 0, 0, 0, 0, NULL, NULL),
(0, -1, 'wasteland', 'Сухое русло реки', 'Кости левиафана виднеются на горизонте. Вода исчезла десятилетия назад.', 3, 5, 2, 0, 0, 0, 0, NULL, NULL),

-- Локации среднего уровня
(5, 5, 'city', 'Руины Супермаркета', 'Обваленная крыша. Внутри темно, но могут быть припасы.', 5, 15, 4, 0, 1, 3, 0, NULL, NULL),
(-5, 5, 'ruins', 'Лагерь Рейдеров', 'Костры еще теплые. Вокруг висят чучела. Пахнет паленым мясом.', 6, 10, 3, 0, 0, 0, 0, NULL, NULL),
(5, -5, 'radzone', 'Метеоритный кратер', 'Земля светится в темноте. Счетчик Гейгера сходит с ума.', 8, 80, 5, 0, 0, 0, 0, NULL, NULL),
(0, 5, 'forest', 'Тёмный Бор', 'Вековые сосны поглощают свет. Под ногами хрустят ветки.', 3, 5, 2, 0, 0, 0, 0, NULL, NULL),
(-8, -2, 'radzone', 'Разлом Трубы', 'Из земли сочится зелёная жижа. Воздух тяжёлый.', 7, 60, 5, 0, 0, 0, 0, NULL, NULL),
(8, 4, 'city', 'Торговый Центр', 'Обваленный атриум. Эскалаторы покрыты ржавчиной.', 6, 10, 5, 0, 1, 4, 0, NULL, NULL),
(10, 10, 'dungeon', 'Бункер 14', 'Тяжёлая дверь приоткрыта. Внутри темно и сыро.', 8, 30, 6, 0, 1, 5, 0, NULL, NULL),

-- Границы мира (Стены)
(20, 0, 'mountain', 'Горная гряда', 'Непроходимые скалы уходят в небо.', 1, 0, 0, 0, 0, 0, 1, 'east', 'Дальше только скалы. Сворачивай!'),
(-20, 0, 'mountain', 'Хребет Смерти', 'Холодный ветер и острые камни.', 1, 0, 0, 0, 0, 0, 1, 'west', 'Горы непроходимы. Возвращайся в долину.'),
(0, 20, 'mountain', 'Ледяная Пустошь', 'Снег по колену и ледяной ветер.', 1, 0, 0, 0, 0, 0, 1, 'north', 'Холод убьет тебя за секунды. Назад!'),
(0, -20, 'desert', 'Пустыня Мохаве', 'Бесконечные барханы и жара.', 1, 0, 0, 0, 0, 0, 1, 'south', 'Пустыня сожжет тебя заживо. Иди обратно.')
ON DUPLICATE KEY UPDATE tile_name = VALUES(tile_name);

-- 3. ОРУЖИЕ
INSERT INTO weapons (item_key, name, description, weight, value, dmg_dice, dmg_mod, crit_chance, crit_mult, range_type, min_range, max_range, min_str) VALUES
('fists', 'Кулаки', 'Твои родные "инструменты".', 0.0, 0, 4, 0, 5.0, 1.5, 'melee', 0, 1, 0),
('switchblade', 'Выкидной нож', 'Ржавый, но острый.', 0.2, 20, 4, 2, 10.0, 2.0, 'melee', 0, 1, 0),
('tire_iron', 'Монтировка', 'Тяжелая и надежная.', 2.5, 30, 6, 2, 5.0, 1.5, 'melee', 0, 1, 4),
('baseball_bat', 'Бита с гвоздями', 'Любимое оружие рейдеров.', 3.0, 40, 8, 3, 8.0, 1.8, 'melee', 0, 1, 5),
('pipe_pistol', 'Труба-пистолет', 'Стреляет чем попало, но стреляет.', 1.5, 50, 6, 3, 5.0, 1.5, 'short', 0, 4, 1),
('10mm_pistol', '10-мм пистолет', 'Классика пустоши.', 1.8, 100, 8, 4, 10.0, 2.0, 'short', 0, 6, 1),
('hunting_rifle', 'Охотничья винтовка', 'Точная и мощная.', 4.0, 120, 12, 5, 20.0, 2.5, 'long', 2, 12, 3),
('shotgun', 'Дробовик', 'Ближний бой — его конек.', 4.5, 150, 16, 2, 5.0, 1.5, 'short', 0, 3, 4),
('fat_man', 'Толстяк', 'Ядерная артиллерия в руках.', 15.0, 5000, 100, 50, 10.0, 5.0, 'long', 4, 15, 8),
('laser_rifle', 'Лазерная винтовка', 'Стабильный луч смерти.', 5.0, 400, 14, 6, 12.0, 2.0, 'long', 2, 15, 4),
('railgun', 'Рельсотрон', 'Разгоняет болт до гиперзвука.', 14.0, 1500, 30, 15, 25.0, 4.0, 'long', 4, 20, 9)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4. БРОНЯ
INSERT INTO armors (item_key, name, description, weight, value, defense, rad_resistance, slot_type, min_str, min_end) VALUES
('vault_suit', 'Комбинезон Убежища', 'Синяя ткань с номером 101.', 1.5, 20, 1, 1, 'tors', 0, 0),
('raider_armor', 'Броня рейдера', 'Собрана из дорожных знаков и кожи.', 5.0, 50, 4, 0, 'tors', 4, 0),
('metal_armor', 'Металлическая броня', 'Тяжелая, но пули рикошетят.', 12.0, 200, 8, 2, 'tors', 6, 0),
('leather_armor', 'Кожаная куртка', 'Плотная кожа. Держит удар.', 4.0, 80, 3, 1, 'tors', 0, 0),
('power_armor_t51', 'Силовая броня T-51b', 'Вершина инженерной мысли.', 30.0, 10000, 20, 10, 'tors', 9, 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 5. РАСХОДНИКИ
INSERT INTO consumables (item_key, name, description, weight, value, heal_amount, rad_heal, addiction_chance, boost_type, boost_value, boost_duration, special_effect) VALUES
('stimpak', 'Стимпак', 'Мгновенное заживление ран.', 0.1, 50, 40, 0, 0.0, NULL, 0, 0, NULL),
('radaway', 'РадАвей', 'Выводит радиацию.', 0.2, 40, 0, 30, 0.0, NULL, 0, 0, NULL),
('nuka_cola', 'Нюка-Кола', 'Освежает и бодрит.', 0.5, 15, 5, 0, 0.0, 'agi', 1, 3, NULL),
('jet', 'Джет', 'Войдешь в состояние гипер-реакции.', 0.1, 30, 0, 0, 25.0, 'agi', 2, 3, NULL),
('med_x', 'Мед-Х', 'Время замедляется. Боль отступает.', 0.1, 40, 0, 0, 10.0, NULL, 0, 0, 'damage_resist_50'),
('psycho', 'Психо', 'Адреналин зашкаливает. Урон растёт.', 0.1, 30, 0, 0, 20.0, 'dmg', 5, 3, NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6. ЛУТ И КОМПОНЕНТЫ
INSERT INTO loot (item_key, name, description, weight, value, category, stackable, max_stack) VALUES
('bottle_cap', 'Крышка от Нюка-Колы', 'Валюта пустоши. Твое всё.', 0.0, 1, 'currency', 1, 9999),
('scrap_metal', 'Лом металла', 'Можно переплавить.', 1.0, 2, 'component', 1, 99),
('adhesive', 'Клей', 'Находка для крафтера.', 0.1, 5, 'component', 1, 99),
('circuit_board', 'Плата', 'Электроника.', 0.2, 10, 'component', 1, 99),
('duct_tape', 'Скотч', 'Чинит всё.', 0.1, 4, 'junk', 1, 99),
('holotape_01', 'Голодиск: Коды', 'Зашифрованные данные.', 0.1, 0, 'quest', 1, 1),
('pre_war_money', 'Доллары', 'Туалетная бумага старого мира.', 0.0, 0, 'junk', 1, 999),
('nuclear_material', 'Ядерный Стержень', 'Тёплый на ощупь.', 2.0, 150, 'component', 1, 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 7. МОНСТРЫ (с Loot Table JSON)
INSERT INTO monsters (monster_key, name, level, speed, status, base_hp, base_armor, base_dmg, xp_reward, spawn_weight, habitat, loot_table) VALUES
('mole_rat', 'Кротокрыс', 1, 4, 'wandering', 15, 0, 3, 10, 80, 'wasteland', '[{"type":"loot","key":"bottle_cap","chance":0.8,"qty":2}]'),
('radroach', 'Рад-таракан', 1, 6, 'wandering', 10, 0, 2, 5, 60, 'wasteland,ruins', '[{"type":"loot","key":"bottle_cap","chance":0.3,"qty":1}]'),
('wild_ghoul', 'Дикий гуль', 3, 5, 'wandering', 30, 1, 6, 25, 40, 'ruins,radzone', '[{"type":"loot","key":"bottle_cap","chance":0.9,"qty":5}]'),
('raider_scav', 'Рейдер-мусорщик', 4, 4, 'patrol', 40, 2, 8, 40, 30, 'ruins,camp', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":10}, {"type":"weapon","key":"pipe_pistol","chance":0.1,"qty":1}]'),
('super_mutant', 'Супермутант', 7, 3, 'wandering', 100, 8, 18, 120, 20, 'city,wasteland', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":20}]'),
('deathclaw', 'Коготь Смерти', 12, 7, 'boss', 250, 15, 35, 500, 1, 'wasteland,ruins', '[{"type":"loot","key":"bottle_cap","chance":1.0,"qty":100}]'),
('robot_protector', 'Робот-защитник', 8, 4, 'guarding', 120, 12, 20, 150, 10, 'vault', '[{"type":"loot","key":"circuit_board","chance":0.8,"qty":3}]')
ON DUPLICATE KEY UPDATE name = VALUES(name);
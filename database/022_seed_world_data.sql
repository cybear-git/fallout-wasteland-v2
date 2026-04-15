-- Миграция 022: Начальные данные для огромного мира
-- Добавляет эффекты предметов, стартовый лут и конфигурацию

-- 1. Эффекты предметов (Стимпак, Антирадин, Психо, Баффаут)
INSERT INTO `item_effects` (`item_id`, `effect_type`, `stat_name`, `stat_value`, `duration_minutes`, `addiction_chance`, `withdrawal_stat`, `withdrawal_value`)
SELECT 
    i.id,
    'heal',
    'health',
    50,
    0,
    0,
    NULL,
    0
FROM items i WHERE i.name = 'Stimpak';

INSERT INTO `item_effects` (`item_id`, `effect_type`, `stat_name`, `stat_value`, `duration_minutes`, `addiction_chance`, `withdrawal_stat`, `withdrawal_value`)
SELECT 
    i.id,
    'heal',
    'radiation',
    -25,
    0,
    0,
    NULL,
    0
FROM items i WHERE i.name = 'RadAway';

-- Психо: +Сила, +Восприятие, зависимость
INSERT INTO `item_effects` (`item_id`, `effect_type`, `stat_name`, `stat_value`, `duration_minutes`, `addiction_chance`, `withdrawal_stat`, `withdrawal_value`)
SELECT 
    i.id,
    'buff',
    'strength',
    3,
    30,
    15,
    'strength',
    -2
FROM items i WHERE i.name = 'Psycho';

INSERT INTO `item_effects` (`item_id`, `effect_type`, `stat_name`, `stat_value`, `duration_minutes`, `addiction_chance`, `withdrawal_stat`, `withdrawal_value`)
SELECT 
    i.id,
    'buff',
    'perception',
    2,
    30,
    15,
    'perception',
    -1
FROM items i WHERE i.name = 'Psycho';

-- Баффаут: +Выносливость, +Скорость действия (агильность)
INSERT INTO `item_effects` (`item_id`, `effect_type`, `stat_name`, `stat_value`, `duration_minutes`, `addiction_chance`, `withdrawal_stat`, `withdrawal_value`)
SELECT 
    i.id,
    'buff',
    'endurance',
    4,
    45,
    20,
    'endurance',
    -3
FROM items i WHERE i.name = 'Buffout';

-- 2. Дополнительные предметы (Хлам для Хламотрона)
INSERT INTO `items` (`name`, `type`, `rarity`, `value`, `weight`, `description`) VALUES
('Ржавая пружина', 'junk', 'common', 1, 0.5, 'Старая ржавая пружина. Идеально для Хламотрона.'),
('Зубчатое колесо', 'junk', 'common', 2, 1.0, 'Маленькое зубчатое колесо из механизма.'),
('Медный провод', 'junk', 'common', 3, 0.3, 'Кусок медного провода. Хороший проводник.'),
('Пластиковый контейнер', 'junk', 'common', 1, 0.8, 'Пустой пластиковый контейнер.'),
('Лампочка', 'junk', 'uncommon', 5, 0.4, 'Старая лампочка, еще целая.'),
('Электронная плата', 'junk', 'rare', 15, 0.6, 'Плата с выжженными дорожками.'),
('Ядерная батарейка', 'junk', 'epic', 50, 1.5, 'Слабо светящаяся батарейка. Опасна.');

-- 3. Конфигурация игры (таймеры, настройки)
INSERT INTO `game_config` (`config_key`, `config_value`, `description`) VALUES
('shelter_cooldown_hours', '2', 'Время в часах, через которое убежище может выпустить нового игрока'),
('player_spawn_distance_min', '15', 'Минимальное расстояние от убежища, которое должен пройти игрок для освобождения слота'),
('weather_update_interval_minutes', '10', 'Как часто обновляется погода'),
('creature_move_interval_minutes', '15', 'Как часто двигаются группы существ'),
('search_time_limit_seconds', '30', 'Время на поиск предметов в секундах'),
('max_carry_weight', '200', 'Максимальный вес переносимых предметов'),
('base_hp', '100', 'Базовое здоровье персонажа'),
('xp_per_level_base', '100', 'Опыт для первого уровня'),
('xp_growth_rate', '1.5', 'Множитель опыта для каждого следующего уровня');

-- 4. Фразы для поиска лута (добавляем к existing quotes)
INSERT INTO `location_quotes` (`tile_type`, `quote`, `context`) VALUES
('ruins', 'Пыль веков покрывает эти руины. Что скрыто под ней?', 'search'),
('ruins', 'Здесь когда-то кипела жизнь. Теперь только тени...', 'search'),
('crater', 'Радиация зашкаливает. Может, здесь что-то ценное?', 'search'),
('plains', 'Ветер гуляет по пустоши. Ничего, кроме мусора.', 'search'),
('plains', 'Под камнем что-то блеснуло? Или показалось?', 'search'),
('dry_lake', 'Пересохшее озеро. Дно усыпано трещинами.', 'search'),
('rocky_wasteland', 'Среди камней легко споткнуться. И найти кое-что.', 'search'),
('frozen_forest', 'Холод пробирает до костей. Здесь ничего нет.', 'search');

-- 5. Фразы для границ мира (предупреждения)
INSERT INTO `location_quotes` (`tile_type`, `quote`, `context`) VALUES
('mountain_peak', 'Дальше только скалы. Выше не забраться.', 'border_warning'),
('brotherhood_checkpoint', 'Стой! Братство Стали не пропускает путников. Назад!', 'border_warning'),
('frozen_forest', 'Северный холод смертелен. Дальше пути нет.', 'border_warning'),
('hot_desert', 'Мексиканская пустыня выжжет тебя дотла. Возвращайся.', 'border_warning');

-- ==========================================================
-- МИГРАЦИЯ 021: Русские фразы, Старт в Убежище, Онлайн/Шок
-- ==========================================================

-- 1. Очистка старых фраз и создание новых (100 шт, RU)
TRUNCATE TABLE location_quotes;

INSERT INTO location_quotes (tile_type, quote_text, mood) VALUES
('wasteland', 'Ветер гонит ржавую пыль по бескрайней пустоши. Здесь когда-то жили люди.', 'neutral'),
('wasteland', 'Тишина. Слишком тихо для этого места. Рука сама тянется к оружию.', 'tense'),
('wasteland', 'Остов старого автомобиля ржавеет здесь уже двести лет. Кто был за рулем?', 'melancholy'),
('wasteland', 'Геоигер щелкает чуть чаще. Где-то рядом прорыв радиации.', 'danger'),
('wasteland', 'Пустошь молчит, но ты чувствуешь чей-то взгляд.', 'tense'),
('wasteland', 'Только сухая трава шуршит под ногами. Крышек не найти.', 'neutral'),
('wasteland', 'Небо цвета грязной стали. Классика Пустоши.', 'neutral'),
('wasteland', 'Здесь пахнет озоном и смертью. Иди дальше.', 'danger'),
('wasteland', 'Ничего, кроме разбитых надежд и ржавого металла.', 'melancholy'),
('wasteland', 'Ветер воет как раненый брамин.', 'neutral'),
('ruins_city', 'Бетонные скелеты небоскребов смотрят на тебя пустыми глазницами окон.', 'melancholy'),
('ruins_city', 'Здесь было много людей. Теперь только гуль и пыль.', 'neutral'),
('ruins_city', 'Старая вывеска "Лазурный берег" смешно смотрится среди радиоактивных псов.', 'ironic'),
('ruins_city', 'Асфальт вспучился от жары ядерного взрыва. Осторожно, ноги.', 'danger'),
('ruins_city', 'Эхо шагов разносится по пустому проспекту. Ты не один?', 'tense'),
('ruins_city', 'Магазин "Супер-Март". Все полки пусты, кроме трупов.', 'neutral'),
('ruins_city', 'Кто-то написал на стене: "Они придут за нами". Кто?', 'tense'),
('ruins_city', 'Радиоактивный дождь барабанит по жестяной крыше.', 'danger'),
('ruins_city', 'Здесь можно найти патрон, если очень повезет. Или пулю.', 'ironic'),
('ruins_city', 'Город спит вечным сном. Не буди его.', 'neutral'),
('forest', 'Мутантские сосны скрипят на ветру. Воздух густой и зеленый.', 'tense'),
('forest', 'Здесь темно даже днем. Листва блокирует солнце.', 'neutral'),
('forest', 'Слышен треск веток. Брамин? Коготь? Или просто ветер?', 'tense'),
('forest', 'Ягоды светятся в темноте. Лучше не есть.', 'danger'),
('forest', 'Лес помнит войну. Деревья искривлены взрывной волной.', 'melancholy'),
('forest', 'Запах гнилой листвы перебивает запах радиации.', 'neutral'),
('forest', 'Тени здесь живут своей жизнью.', 'tense'),
('forest', 'Ствол дерева обуглен, но оно все еще растет. Жизнь finds a way.', 'neutral'),
('forest', 'Кусты шиповника загораживают путь. Придется резать.', 'neutral'),
('forest', 'Птиц нет. Только насекомые размером с ладонь.', 'danger'),
('vault_ext', 'Герметичная дверь убежища. символ "Vault-Tec" почти стерся.', 'neutral'),
('vault_ext', 'Здесь безопасно. Почти. Хранитель ждет внутри.', 'safe'),
('vault_ext', 'Воздух вокруг убежища чище. Системы фильтрации работают.', 'safe'),
('vault_ext', 'Ты у выхода. Обратного пути нет.', 'tense'),
('vault_ext', 'Маленький оазис цивилизации в океане хаоса.', 'safe'),
('vault_ext', 'Дверь закрыта изнутри. Постучишься?', 'neutral'),
('vault_ext', 'Охранная турель слежит за каждым твоим движением.', 'tense'),
('vault_ext', 'Здесь пахнет озоном и стерильностью.', 'safe'),
('vault_ext', 'Убежище — это могила для тех, кто боится жить.', 'philosophical'),
('vault_ext', 'Ты слышишь гул вентиляции. Жизнь внутри.', 'safe'),
('raider_camp', 'Черепа на кольях. Приветствие рейдеров.', 'danger'),
('raider_camp', 'Пахнет жареным мясом. Надеюсь, это не человек.', 'danger'),
('raider_camp', 'Осколки бутылок хрустят под ногами. Ловушки?', 'tense'),
('raider_camp', 'Костер еще теплый. Они где-то рядом.', 'danger'),
('raider_camp', 'Граффити на стене: "Смерть чужакам!". Мило.', 'danger'),
('raider_camp', 'Ржавые клетки пустуют. Узников съели.', 'danger'),
('raider_camp', 'Здесь лучше не задерживаться. Рейдеры не любят свидетелей.', 'danger'),
('raider_camp', 'Оружие повсюду. Кто-то готовился к войне.', 'tense'),
('raider_camp', 'Смех гиен эхом разносится по лагерю.', 'danger'),
('raider_camp', 'Ты видишь тень за углом. Она вооружена.', 'danger'),
('military_base', 'Колючая проволока и бетон. Братство Стали?', 'tense'),
('military_base', 'Заброшенный бункер. Вход завален.', 'neutral'),
('military_base', 'Здесь пахнет порохом и дисциплиной.', 'tense'),
('military_base', 'Остатки силовой брони валяются в углу. Ржавые.', 'neutral'),
('military_base', 'Сирена молчит, но лампы мигают. Энергия есть.', 'tense'),
('military_base', 'Секретные документы разбросаны по полу. Гриф "Совершенно секретно".', 'neutral'),
('military_base', 'Турели deactivated. Но кто знает, надолго ли?', 'tense'),
('military_base', 'Полигон для стрельбы. Гильзы повсюду.', 'neutral'),
('military_base', 'Здесь проводили опыты. Следы когтей на стенах.', 'danger'),
('military_base', 'Вход охраняется. Пройти будет сложно.', 'tense'),
('cave', 'Темно. Холодно. Сыро. Классическая пещера.', 'neutral'),
('cave', 'Сталактиты нависают как клыки гигантского зверя.', 'tense'),
('cave', 'Вода капает с потолка. Тук. Тук. Тук.', 'neutral'),
('cave', 'Здесь живут радтараканы. Много радтараканов.', 'danger'),
('cave', 'Эхо искажает звуки. Ты слышишь шаги?', 'tense'),
('cave', 'Пещера ведет глубже. В самую суть земли.', 'neutral'),
('cave', 'Свет фонаря выхватывает кости в углу.', 'danger'),
('cave', 'Воздух спертый. Дышать тяжело.', 'neutral'),
('cave', 'Кто-то жил здесь. Очаг потух недавно.', 'tense'),
('cave', 'Выход завален. Ищи другой путь.', 'danger'),
('factory', 'Конвейер остановился навсегда.', 'melancholy'),
('factory', 'Робот-работник все еще пытается выполнить программу.', 'neutral'),
('factory', 'Химикаты разлились по полу. Светятся в темноте.', 'danger'),
('factory', 'Завод производил что-то военное. Теперь производит мусор.', 'neutral'),
('factory', 'Пар выходит из труб. Система охлаждения жива?', 'tense'),
('factory', 'Металлический лязг эхом разносится по цеху.', 'neutral'),
('factory', 'Здесь можно найти запчасти. И ржавчину.', 'neutral'),
('factory', 'Офис менеджера. Сейф вскрыт.', 'neutral'),
('factory', 'Запах горелой пластмассы.', 'neutral'),
('factory', 'Цех сборки. Роборуки замерли в последней операции.', 'melancholy'),
('deathclaw_nest', 'Гнездо Когтя Смерти. Беги.', 'danger'),
('deathclaw_nest', 'Кости повсюду. Свежие кости.', 'danger'),
('deathclaw_nest', 'Рев эхом разносится по округе. Они близко.', 'danger'),
('deathclaw_nest', 'Земля изрыта огромными когтями.', 'danger'),
('deathclaw_nest', 'Запах хищника перебивает все остальные запахи.', 'danger'),
('deathclaw_nest', 'Ты в логове зверя. Шансов мало.', 'danger'),
('deathclaw_nest', 'Яйца в углу пещеры. Не трогай их!', 'danger'),
('deathclaw_nest', 'Тень накрыла тебя. Огромная тень.', 'danger'),
('deathclaw_nest', 'Инстинкт кричит: "Беги!".', 'danger'),
('deathclaw_nest', 'Смерть смотрит на тебя желтыми глазами.', 'danger'),
('settlement', 'Люди. Настоящие люди. И они не стреляют.', 'safe'),
('settlement', 'Запах еды. Настоящей еды.', 'safe'),
('settlement', 'Торговец зовет: "Эй, путник! Заходи!"', 'safe'),
('settlement', 'Охрана на вышках. Порядок.', 'safe'),
('settlement', 'Дети играют среди руин. Надежда жива.', 'safe'),
('settlement', 'Музыка. Кто-то играет на гитаре.', 'safe'),
('settlement', 'Здесь можно отдохнуть. И потратить крышки.', 'safe'),
('settlement', 'Слухи собираются быстрее мух у трупа.', 'neutral'),
('settlement', 'Каждый второй — бывший рейдер. Но они стараются.', 'neutral'),
('settlement', 'Мирное небо над поселением. Пока что.', 'safe');

-- 2. Таблица Хранителей Убежища (NPC старта)
CREATE TABLE IF NOT EXISTS vault_keepers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vault_id INT UNSIGNED NOT NULL, -- ID мап-ноды убежища
    keeper_name VARCHAR(100) DEFAULT 'Хранитель Убежища',
    greeting_text TEXT COMMENT 'Приветствие при первом входе',
    mission_text TEXT COMMENT 'Наставление/Миссия',
    bonus_armor TINYINT DEFAULT 2 COMMENT 'Бонус к броне',
    bonus_charisma TINYINT DEFAULT 1 COMMENT 'Бонус к харизме',
    starter_caps INT DEFAULT 30,
    starter_stimpak INT DEFAULT 3,
    starter_antirad INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vault_id) REFERENCES map_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заполним данными (будет обновлено скриптом генерации при привязке к ID убежищ)
-- Вставляем шаблон, ID vault_id обновим после генерации карты или через триггер
INSERT INTO vault_keepers (vault_id, greeting_text, mission_text) 
VALUES 
(0, 'Добро пожаловать домой, Избранный. Я ждал тебя. Пустошь сурова, но ты сильнее.', 'Найди Источник Живой Воды. Легенды говорят, что он может очистить землю. Иди и верни надежду.'),
(0, 'Приветствую, житель. Твой комбинезон готов. Путь лежит через огонь и кровь.', 'В Пустоши потеряна технология предтеч. Найди её, пока она не попала к рейдерам.'),
(0, 'Ты вышел из криокамеры последним. Мир изменился. Измени его и ты.', 'Собери советы старейших племен. Только единство спасет нас от угасания.');

-- 3. Обновление таблицы игроков (Онлайн, Шок, Безопасная точка)
ALTER TABLE players 
ADD COLUMN is_online TINYINT(1) DEFAULT 0 COMMENT '1 - игрок в сети, 0 - оффлайн (невидим)',
ADD COLUMN shock_until DATETIME DEFAULT NULL COMMENT 'До этого времени игрок в шоке (не может воевать)',
ADD COLUMN last_safe_node_id INT UNSIGNED DEFAULT NULL COMMENT 'Последняя безопасная нода (для респауна)',
ADD COLUMN current_mission VARCHAR(255) DEFAULT NULL COMMENT 'Текущее задание от Хранителя',
ADD COLUMN faction_reputation JSON DEFAULT NULL COMMENT 'Репутация с фракциями (JSON)',
DROP INDEX IF EXISTS idx_players_username; -- Пересоздадим индексы ниже;

CREATE INDEX idx_players_online ON players(is_online);
CREATE INDEX idx_players_shock ON players(shock_until);
CREATE INDEX idx_players_location ON players(current_map_node_id);

-- 4. Обновление таблицы предметов (Флаг хлама для Хламотрона)
ALTER TABLE items 
ADD COLUMN is_junk TINYINT(1) DEFAULT 0 COMMENT '1 - предмет является хламом для оружия';

-- Добавим немного хлама в базу для теста
INSERT INTO items (name, type, value, weight, description, is_junk) VALUES
('Ржавая шестеренка', 'junk', 1, 0.5, 'Старая деталь механизма.', 1),
('Изолированный провод', 'junk', 2, 0.3, 'Медный провод в изоляции.', 1),
('Пустая консервная банка', 'junk', 0, 0.2, 'Из-под фасолевого супа.', 1),
('Разбитый сенсор', 'junk', 5, 0.4, 'Электроника предвоенного мира.', 1),
('Кусок пластика', 'junk', 1, 0.1, 'Обломок чего-то большого.', 1);

-- 5. Таблица логина/логаута (аудит сессий)
CREATE TABLE IF NOT EXISTS player_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME DEFAULT NULL,
    logout_reason ENUM('manual', 'combat_escape', 'afk_timeout', 'crash') DEFAULT 'manual',
    was_in_combat TINYINT(1) DEFAULT 0,
    was_in_dungeon TINYINT(1) DEFAULT 0,
    survival_check_success TINYINT(1) DEFAULT NULL COMMENT 'Результат проверки на побег',
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_sessions_player ON player_sessions(player_id);
CREATE INDEX idx_sessions_time ON player_sessions(login_time);

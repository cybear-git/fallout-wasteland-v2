-- database/031_populate_world_locations.sql
-- Заполнение справочника типов локаций и создание базовых локаций

-- 1. Очищаем и заполняем location_types
TRUNCATE TABLE location_types;

INSERT INTO location_types (type_key, type_name, description, base_difficulty) VALUES
('wasteland', 'Пустошь', 'Бескрайние просторы опустошенной земли', 1),
('city', 'Город', 'Разрушенные кварталы мертвого города', 3),
('dungeon', 'Подземелье', 'Опасные подземные лабиринты', 5),
('radzone', 'Радиоактивная зона', 'Высокий уровень радиации', 4),
('vault', 'Убежище', 'Убежище Vault-Tec', 0),
('vault_ext', 'Вход в Убежище', 'Внешняя территория убежища', 1),
('mountain', 'Горы', 'Непроходимые горные вершины', 5),
('forest', 'Лес', 'Зараженный лес', 2),
('desert', 'Пустыня', 'Выжженная пустынная местность', 3),
('ruins', 'Руины', 'Руины зданий и сооружений', 2),
('camp', 'Лагерь', 'Лагерь выживших или рейдеров', 2),
('military', 'Военная база', 'Заброшенная военная база', 4),
('military_base', 'Комплекс Братства', 'Территория Братства Стали', 5);

-- 2. Создаем базовые локации для карты
TRUNCATE TABLE locations;

-- Пустошь (основной биом)
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality) 
SELECT 'wasteland_plains', 'Равнины Пустоши', 'Бескрайние просторы серой земли', lt.id, 1, 0, 1
FROM location_types lt WHERE lt.type_name = 'wasteland';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'wasteland_hub', 'Центральный Хаб', 'Перекресток торговых путей', lt.id, 2, 0, 2
FROM location_types lt WHERE lt.type_name = 'wasteland';

-- Города и руины
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'ruins_downtown', 'Даунтаун', 'Разрушенный центр города', lt.id, 3, 5, 3
FROM location_types lt WHERE lt.type_name = 'ruins';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'ruins_mall', 'Торговый Центр', 'Когда-то здесь кипела жизнь', lt.id, 4, 10, 4
FROM location_types lt WHERE lt.type_name = 'ruins';

-- Убежища
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_vault)
SELECT 'vault_1', 'Убежище 01', 'Первое убежище на восточном побережье', lt.id, 0, 0, 1, 1
FROM location_types lt WHERE lt.type_name = 'vault_ext';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_vault)
SELECT 'vault_2', 'Убежище 02', 'Подземный комплекс глубокого залегания', lt.id, 0, 0, 1, 1
FROM location_types lt WHERE lt.type_name = 'vault_ext';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_vault)
SELECT 'vault_3', 'Убежище 03', 'Исследовательский комплекс', lt.id, 0, 0, 1, 1
FROM location_types lt WHERE lt.type_name = 'vault_ext';

-- Подземелья
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_dungeon)
SELECT 'dungeon_factory', 'Заброшенный Завод', 'Промышленный комплекс кишит мутантами', lt.id, 4, 15, 3, 1
FROM location_types lt WHERE lt.type_name = 'dungeon';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_dungeon)
SELECT 'dungeon_metro', 'Метро', 'Коллапсировавшее метро', lt.id, 5, 20, 4, 1
FROM location_types lt WHERE lt.type_name = 'dungeon';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality, is_dungeon)
SELECT 'dungeon_bunker', 'Бункер', 'Скрытый бункер с сюрпризами', lt.id, 6, 5, 5, 1
FROM location_types lt WHERE lt.type_name = 'dungeon';

-- Радиоактивные зоны
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'radzone_waste', 'Ядовитые Отходы', 'Местность пропитана радиацией', lt.id, 4, 50, 3
FROM location_types lt WHERE lt.type_name = 'radzone';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'radzone_meltdown', 'Место Катастрофы', 'Здесь произошла ядерная катастрофа', lt.id, 5, 80, 4
FROM location_types lt WHERE lt.type_name = 'radzone';

-- Горы
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'mountain_pass', 'Горный Проход', 'Опасная тропа через горы', lt.id, 3, 0, 1
FROM location_types lt WHERE lt.type_name = 'mountain';

-- Леса
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'forest_dead', 'Мертвый Лес', 'Высохшие деревья и тишина', lt.id, 2, 10, 2
FROM location_types lt WHERE lt.type_name = 'forest';

-- Лагеря
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'camp_raider', 'Лагерь Рейдеров', 'Гнездо опасных бандитов', lt.id, 3, 0, 2
FROM location_types lt WHERE lt.type_name = 'camp';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'camp_survivor', 'Поселение Выживших', 'Убежище мирных людей', lt.id, 1, 0, 2
FROM location_types lt WHERE lt.type_name = 'camp';

-- Военные базы
INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'military_outpost', 'Военный Аванпост', 'Заброшенный военный пост', lt.id, 4, 5, 3
FROM location_types lt WHERE lt.type_name = 'military';

INSERT INTO locations (location_key, name, description, location_type_id, danger_level, radiation_level, loot_quality)
SELECT 'military_base_bs', 'База Братства', 'Территория Братства Стали', lt.id, 5, 0, 4
FROM location_types lt WHERE lt.type_name = 'military_base';

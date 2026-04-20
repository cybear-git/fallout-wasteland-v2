-- Миграция данных: Перенос предметов из старых таблиц в новую структуру
-- Выполнять ПОСЛЕ применения 036_refactor_items_structure.sql

-- Временная таблица для маппинга старых ID в новые
CREATE TEMPORARY TABLE IF NOT EXISTS item_migration_map (
    old_id INT,
    old_table VARCHAR(50),
    new_item_id INT UNSIGNED,
    item_type_id TINYINT UNSIGNED
);

-- Перенос оружия
INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active)
SELECT 
    w.item_key,
    1, -- weapon type_id
    w.name,
    w.description,
    w.weight,
    w.value,
    w.is_active
FROM weapons w
WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.item_key = w.item_key);

INSERT INTO weapon_attributes (item_id, dmg_dice, dmg_mod, crit_chance, crit_mult, range_type, min_range, max_range, min_str, min_per, min_end, min_cha, min_int, min_agi, min_luk)
SELECT 
    i.id,
    w.dmg_dice,
    w.dmg_mod,
    w.crit_chance,
    w.crit_mult,
    w.range_type,
    w.min_range,
    w.max_range,
    w.min_str,
    w.min_per,
    w.min_end,
    w.min_cha,
    w.min_int,
    w.min_agi,
    w.min_luk
FROM weapons w
JOIN items i ON i.item_key = w.item_key AND i.item_type_id = 1;

-- Перенос брони
INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active)
SELECT 
    a.item_key,
    2, -- armor type_id
    a.name,
    a.description,
    a.weight,
    a.value,
    a.is_active
FROM armors a
WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.item_key = a.item_key);

INSERT INTO armor_attributes (item_id, defense, rad_resistance, slot_type, min_str, min_per, min_end, min_cha, min_int, min_agi, min_luk)
SELECT 
    i.id,
    a.defense,
    a.rad_resistance,
    a.slot_type,
    a.min_str,
    a.min_per,
    a.min_end,
    a.min_cha,
    a.min_int,
    a.min_agi,
    a.min_luk
FROM armors a
JOIN items i ON i.item_key = a.item_key AND i.item_type_id = 2;

-- Перенос расходников
INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active)
SELECT 
    c.item_key,
    3, -- consumable type_id
    c.name,
    c.description,
    c.weight,
    c.value,
    c.is_active
FROM consumables c
WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.item_key = c.item_key);

INSERT INTO consumable_attributes (item_id, heal_amount, rad_heal, addiction_chance, boost_type, boost_value, boost_duration, effect_duration, special_effect)
SELECT 
    i.id,
    c.heal_amount,
    c.rad_heal,
    c.addiction_chance,
    c.boost_type,
    c.boost_value,
    c.boost_duration,
    c.effect_duration,
    c.special_effect
FROM consumables c
JOIN items i ON i.item_key = c.item_key AND i.item_type_id = 3;

-- Перенос лута
INSERT INTO items (item_key, item_type_id, name, description, weight, value, is_active)
SELECT 
    l.item_key,
    4, -- loot type_id
    l.name,
    l.description,
    l.weight,
    l.value,
    l.is_active
FROM loot l
WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.item_key = l.item_key);

INSERT INTO loot_attributes (item_id, category, stackable, max_stack)
SELECT 
    i.id,
    l.category,
    l.stackable,
    l.max_stack
FROM loot l
JOIN items i ON i.item_key = l.item_key AND i.item_type_id = 4;

-- Обновление inventory: связываем item_key с новым item_id
UPDATE inventory inv
JOIN items i ON inv.item_key = i.item_key
SET inv.item_id = i.id
WHERE inv.item_id IS NULL;

-- После успешной миграции можно удалить старые таблицы (раскомментировать при необходимости)
-- DROP TABLE IF EXISTS weapons;
-- DROP TABLE IF EXISTS armors;
-- DROP TABLE IF EXISTS consumables;
-- DROP TABLE IF EXISTS loot;

-- Проверка результатов
SELECT 'Items migrated' as status, COUNT(*) as count FROM items;
SELECT 'Weapon attributes' as status, COUNT(*) as count FROM weapon_attributes;
SELECT 'Armor attributes' as status, COUNT(*) as count FROM armor_attributes;
SELECT 'Consumable attributes' as status, COUNT(*) as count FROM consumable_attributes;
SELECT 'Loot attributes' as status, COUNT(*) as count FROM loot_attributes;
SELECT 'Inventory updated' as status, COUNT(*) as count FROM inventory WHERE item_id IS NOT NULL;

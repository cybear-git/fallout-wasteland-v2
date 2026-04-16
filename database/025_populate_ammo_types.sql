-- database/025_populate_ammo_types.sql
-- Распределение типов патронов по оружию

UPDATE weapons SET ammo_type = 'bullet' WHERE item_key LIKE '%pistol%' OR item_key LIKE '%rifle%';
UPDATE weapons SET ammo_type = 'energy' WHERE item_key LIKE '%laser%' OR item_key LIKE '%plasma%';
UPDATE weapons SET ammo_type = 'junk' WHERE item_key LIKE '%junkjet%';
UPDATE weapons SET ammo_type = 'none' WHERE item_key LIKE '%knife%' OR item_key LIKE '%melee%';

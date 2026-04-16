-- database/027_migrate_enum_data.sql
-- Перенос данных из ENUM в новые справочники и обновление внешних ключей

-- 1. Роли игроков
ALTER TABLE players ADD COLUMN role_id TINYINT UNSIGNED NULL;
UPDATE players p JOIN roles r ON p.role = r.role_name SET p.role_id = r.id;
ALTER TABLE players DROP COLUMN role;
ALTER TABLE players ADD CONSTRAINT fk_players_role FOREIGN KEY (role_id) REFERENCES roles(id);

-- 2. Типы локаций (Каталог)
ALTER TABLE locations ADD COLUMN location_type_id TINYINT UNSIGNED NULL;
UPDATE locations l JOIN location_types lt ON l.tile_type = lt.type_name SET l.location_type_id = lt.id;
ALTER TABLE locations DROP COLUMN tile_type;
ALTER TABLE locations ADD CONSTRAINT fk_locations_type FOREIGN KEY (location_type_id) REFERENCES location_types(id);

-- 3. map_nodes - связываем через location_id -> locations -> location_type_id
-- map_nodes.tile_type не существует, но location_id указывает на locations
ALTER TABLE map_nodes ADD COLUMN location_type_id TINYINT UNSIGNED NULL;
UPDATE map_nodes mn
JOIN locations l ON l.id = mn.location_id
SET mn.location_type_id = l.location_type_id
WHERE l.location_type_id IS NOT NULL;
ALTER TABLE map_nodes ADD CONSTRAINT fk_nodes_type FOREIGN KEY (location_type_id) REFERENCES location_types(id);

-- 4. Ноды данжей
ALTER TABLE dungeon_nodes ADD COLUMN tile_type_id TINYINT UNSIGNED NULL;
UPDATE dungeon_nodes dn JOIN dungeon_tile_types dtt ON dn.tile_type = dtt.type_name SET dn.tile_type_id = dtt.id;
ALTER TABLE dungeon_nodes DROP COLUMN tile_type;
ALTER TABLE dungeon_nodes ADD CONSTRAINT fk_dungeon_nodes_type FOREIGN KEY (tile_type_id) REFERENCES dungeon_tile_types(id);

-- 5. Слоты инвентаря
ALTER TABLE inventory ADD COLUMN slot_id TINYINT UNSIGNED NULL;
UPDATE inventory i JOIN equipment_slots es ON i.equipped_slot = es.slot_name SET i.slot_id = es.id;
ALTER TABLE inventory DROP COLUMN equipped_slot;
ALTER TABLE inventory ADD CONSTRAINT fk_inventory_slot FOREIGN KEY (slot_id) REFERENCES equipment_slots(id);

-- 6. Эффекты
ALTER TABLE player_effects ADD COLUMN effect_type_id TINYINT UNSIGNED NULL;
UPDATE player_effects pe JOIN effect_types et ON pe.effect_type = et.type_name SET pe.effect_type_id = et.id;
ALTER TABLE player_effects DROP COLUMN effect_type;
ALTER TABLE player_effects ADD CONSTRAINT fk_effects_type FOREIGN KEY (effect_type_id) REFERENCES effect_types(id);

-- 7. Боевые состояния
ALTER TABLE combats ADD COLUMN state_id TINYINT UNSIGNED NULL;
UPDATE combats c JOIN combat_states cs ON c.combat_state = cs.state_name SET c.state_id = cs.id;
ALTER TABLE combats DROP COLUMN combat_state;
ALTER TABLE combats ADD CONSTRAINT fk_combats_state FOREIGN KEY (state_id) REFERENCES combat_states(id);

-- 8. Боеприпасы (Оружие)
ALTER TABLE weapons ADD COLUMN ammo_type_id TINYINT UNSIGNED NULL;
UPDATE weapons w JOIN ammo_types at ON w.ammo_type = at.type_name SET w.ammo_type_id = at.id;
ALTER TABLE weapons DROP COLUMN ammo_type;
ALTER TABLE weapons ADD CONSTRAINT fk_weapons_ammo FOREIGN KEY (ammo_type_id) REFERENCES ammo_types(id);

-- 9. Боеприпасы (Игрок)
ALTER TABLE player_ammo ADD COLUMN ammo_type_id TINYINT UNSIGNED NULL;
UPDATE player_ammo pa JOIN ammo_types at ON pa.ammo_type = at.type_name SET pa.ammo_type_id = at.id;
ALTER TABLE player_ammo DROP COLUMN ammo_type;
ALTER TABLE player_ammo ADD CONSTRAINT fk_ammo_type FOREIGN KEY (ammo_type_id) REFERENCES ammo_types(id);

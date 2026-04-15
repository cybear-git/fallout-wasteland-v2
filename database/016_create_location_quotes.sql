-- database/016_create_location_quotes.sql
-- Таблица атмосферных фраз из вселенной Fallout для каждой локации
-- Привязка к типу локации (tile_type) для автоматического подбора при перемещении

CREATE TABLE IF NOT EXISTS location_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_text TEXT NOT NULL COMMENT 'Текст фразы',
    tile_type ENUM('wasteland','city','dungeon','radzone','vault','mountain','ruins','desert','forest','military','camp') NOT NULL,
    mood ENUM('neutral', 'danger', 'discovery', 'lore', 'humor') DEFAULT 'neutral' COMMENT 'Настроение фразы',
    source VARCHAR(100) DEFAULT NULL COMMENT 'Источник (игра, персонаж, книга)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tile_type (tile_type, mood),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Наполнение: 100 фраз, распределённых по типам локаций и настроениям
INSERT INTO location_quotes (quote_text, tile_type, mood, source, is_active) VALUES
-- WASTELAND (15 фраз)
('War... war never changes.', 'wasteland', 'lore', 'Fallout Intro', 1),
('When the bombs dropped, we thought the world had ended. We were wrong.', 'wasteland', 'lore', 'Fallout Intro', 1),
('The wasteland doesn''t forgive. It doesn''t forget.', 'wasteland', 'neutral', NULL, 1),
('Radscorpions. Great. Just what I needed today.', 'wasteland', 'humor', NULL, 1),
('Another day, another dose of RadAway.', 'wasteland', 'neutral', NULL, 1),
('The wind carries the ashes of the old world.', 'wasteland', 'lore', NULL, 1),
('Survival is not about strength. It''s about adaptability.', 'wasteland', 'lore', NULL, 1),
('I''ve seen brahmin with better sense than some wastelanders.', 'wasteland', 'humor', NULL, 1),
('The Geiger counter clicks like a metronome of death.', 'wasteland', 'danger', NULL, 1),
('Every footprint could be the last one you make.', 'wasteland', 'danger', NULL, 1),
('Found an old pre-war magazine. Still readable.', 'wasteland', 'discovery', NULL, 1),
('The silence here is heavier than my power armor.', 'wasteland', 'neutral', NULL, 1),
('Vault-Tec promised safety. They lied.', 'wasteland', 'lore', NULL, 1),
('Brahmin grass looks greener on the other side of the fence.', 'wasteland', 'humor', NULL, 1),
('This road leads somewhere. Hopefully not to death.', 'wasteland', 'discovery', NULL, 1),

-- CITY (12 фраз)
('The city sleeps. But not for long.', 'city', 'neutral', NULL, 1),
('Skyscrapers cast shadows over the ruins of civilization.', 'city', 'lore', NULL, 1),
('Super mutants patrol these streets. Stay low.', 'city', 'danger', NULL, 1),
('The old metro still runs. If you know where to look.', 'city', 'discovery', NULL, 1),
('Raider graffiti marks every wall. Territory warnings.', 'city', 'danger', NULL, 1),
('Once, this was the heart of commerce. Now it''s a graveyard.', 'city', 'lore', NULL, 1),
('I hear gunfire. Multiple contacts.', 'city', 'danger', NULL, 1),
('The library survived. Knowledge is power.', 'city', 'discovery', NULL, 1),
('Power armor tracks lead into the building.', 'city', 'discovery', NULL, 1),
('Enclave scouts were here recently.', 'city', 'danger', NULL, 1),
('The streetlights flicker. Someone restored power.', 'city', 'discovery', NULL, 1),
('Brotherhood patrols keep the worst at bay. For now.', 'city', 'neutral', NULL, 1),

-- DUNGEON (15 фраз)
('The darkness breathes.', 'dungeon', 'danger', NULL, 1),
('Something moves in the shadows.', 'dungeon', 'danger', NULL, 1),
('The air is thick with radiation and decay.', 'dungeon', 'danger', NULL, 1),
('Footprints. Fresh ones.', 'dungeon', 'danger', NULL, 1),
('The walls whisper secrets of the old world.', 'dungeon', 'lore', NULL, 1),
('A terminal flickers to life. Data intact.', 'dungeon', 'discovery', NULL, 1),
('Deathclaw nests are never empty.', 'dungeon', 'danger', NULL, 1),
('The corridor stretches into endless darkness.', 'dungeon', 'neutral', NULL, 1),
('Emergency lighting casts eerie green shadows.', 'dungeon', 'neutral', NULL, 1),
('Blood trails lead deeper inside.', 'dungeon', 'danger', NULL, 1),
('The door is sealed. Requires clearance.', 'dungeon', 'discovery', NULL, 1),
('Mirelurks hiss in the flooded sections.', 'dungeon', 'danger', NULL, 1),
('Someone left a holotape near the entrance.', 'dungeon', 'discovery', NULL, 1),
('The ventilation system still works. Barely.', 'dungeon', 'neutral', NULL, 1),
('Boss chamber ahead. Prepare for combat.', 'dungeon', 'danger', NULL, 1),

-- RADZONE (12 фраз)
('The Geiger counter is screaming.', 'radzone', 'danger', NULL, 1),
('Green glow means death. Or mutations.', 'radzone', 'danger', NULL, 1),
('The crater still smolders after all these years.', 'radzone', 'lore', NULL, 1),
('My Pip-Boy shows 50 rads per second.', 'radzone', 'danger', NULL, 1),
('Mutated plants twist toward the radioactive sun.', 'radzone', 'neutral', NULL, 1),
('The water here glows. Don''t drink it.', 'radzone', 'danger', NULL, 1),
('Ghouls gather in high-radiation zones.', 'radzone', 'danger', NULL, 1),
('The meteor impact changed everything.', 'radzone', 'lore', NULL, 1),
('Radiation storms roll in without warning.', 'radzone', 'danger', NULL, 1),
('Found a hazmat suit. Perfect timing.', 'radzone', 'discovery', NULL, 1),
('The soil is contaminated for miles.', 'radzone', 'neutral', NULL, 1),
('Power armor filters can handle this. Temporarily.', 'radzone', 'neutral', NULL, 1),

-- VAULT (12 фраз)
('Home sweet home. If home was a prison.', 'vault', 'neutral', NULL, 1),
('The Overseer''s log mentions an experiment.', 'vault', 'lore', NULL, 1),
('Vault doors weigh several tons. Impressive engineering.', 'vault', 'discovery', NULL, 1),
('The cafeteria still smells of synthetic food.', 'vault', 'neutral', NULL, 1),
('Living quarters. Someone lived here once.', 'vault', 'lore', NULL, 1),
('Security terminals control the lockdown.', 'vault', 'discovery', NULL, 1),
('The reactor hums steadily. Still operational.', 'vault', 'discovery', NULL, 1),
('Vault-Tec logos everywhere. Propaganda.', 'vault', 'neutral', NULL, 1),
('Hydroponics bay. Fresh air at last.', 'vault', 'discovery', NULL, 1),
('Medical bay. Stimpaks and hope.', 'vault', 'discovery', NULL, 1),
('The vault was sealed from the inside.', 'vault', 'lore', NULL, 1),
('Emergency protocols still active.', 'vault', 'neutral', NULL, 1),

-- MOUNTAIN (6 фраз)
('The wind howls through rocky passes.', 'mountain', 'neutral', NULL, 1),
('Elevation gives tactical advantage.', 'mountain', 'neutral', NULL, 1),
('Caves dot the mountainside. Potential shelter.', 'mountain', 'discovery', NULL, 1),
('Snow caps the peaks. Cold but beautiful.', 'mountain', 'neutral', NULL, 1),
('The view from here is worth the climb.', 'mountain', 'neutral', NULL, 1),
('Rockfall blocks the northern path.', 'mountain', 'danger', NULL, 1),

-- RUINS (10 фраз)
('The building collapsed inward. Nothing salvageable.', 'ruins', 'neutral', NULL, 1),
('Raider camps leave signs everywhere.', 'ruins', 'danger', NULL, 1),
('Old world technology buried under rubble.', 'ruins', 'discovery', NULL, 1),
('Skeletal remains tell a grim story.', 'ruins', 'lore', NULL, 1),
('The foundation is stable enough to explore.', 'ruins', 'neutral', NULL, 1),
('Pre-war vehicles rust in the parking lot.', 'ruins', 'neutral', NULL, 1),
('Scorch marks indicate heavy fighting.', 'ruins', 'lore', NULL, 1),
('A safe half-buried in debris. Locked.', 'ruins', 'discovery', NULL, 1),
('The structure groans in the wind.', 'ruins', 'danger', NULL, 1),
('Salvageable components scattered about.', 'ruins', 'discovery', NULL, 1),

-- DESERT (6 фраз)
('The sun beats down mercilessly.', 'desert', 'neutral', NULL, 1),
('Sandstorms reduce visibility to zero.', 'desert', 'danger', NULL, 1),
('Oases are rare but precious.', 'desert', 'discovery', NULL, 1),
('Radscorpions burrow beneath the dunes.', 'desert', 'danger', NULL, 1),
('The heat shimmers distort the horizon.', 'desert', 'neutral', NULL, 1),
('Ancient highway disappears into the sands.', 'desert', 'neutral', NULL, 1),

-- FOREST (6 фраз)
('Trees grow twisted from radiation.', 'forest', 'neutral', NULL, 1),
('The forest floor is soft with decay.', 'forest', 'neutral', NULL, 1),
('Wildlife stirs in the underbrush.', 'forest', 'danger', NULL, 1),
('Mushrooms glow faintly in the shade.', 'forest', 'discovery', NULL, 1),
('A stream cuts through the woods. Water source.', 'forest', 'discovery', NULL, 1),
('Predators hunt in these woods. Stay alert.', 'forest', 'danger', NULL, 1),

-- MILITARY (6 фраз)
('Turrets track movement automatically.', 'military', 'danger', NULL, 1),
('Barbed wire and sandbags. Fortified position.', 'military', 'neutral', NULL, 1),
('Ammunition crates stacked neatly.', 'military', 'discovery', NULL, 1),
('Command center access restricted.', 'military', 'discovery', NULL, 1),
('NCR insignia faded on the banner.', 'military', 'lore', NULL, 1),
('Artillery shells line the perimeter.', 'military', 'danger', NULL, 1),

-- CAMP (6 фраз)
('Campfire embers still warm.', 'camp', 'neutral', NULL, 1),
('Traders set up temporary shelters.', 'camp', 'neutral', NULL, 1),
('Supply crates marked with faction symbols.', 'camp', 'discovery', NULL, 1),
('Guards patrol the perimeter.', 'camp', 'danger', NULL, 1),
('Cooking pots bubble over open flames.', 'camp', 'neutral', NULL, 1),
('Caravan brahmin rest nearby.', 'camp', 'neutral', NULL, 1);

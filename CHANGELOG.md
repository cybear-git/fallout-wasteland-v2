# 📜 CHANGELOG

## [Alpha 0.5] - 2024-01-XX
### Added
- **Core Game Loop**: Registration, character creation, vault dialogue, wasteland exploration.
- **Map System**: 160x90 grid with biomes (Wasteland, Mountains, Brotherhood Steel, etc.) and hard borders.
- **Vault System**: 8 random vaults with unique Keepers, starting gear (jumpsuit, stimpaks, caps).
- **Movement**: 8-directional movement with Russian atmospheric quotes (100 phrases).
- **Search Mechanic**: Scavenging with cooldown, success chance based on stats, random encounters.
- **Inventory System**: View, use, equip items; junk tracking for Junk Jet.
- **Multiplayer Foundation**: Turn-based tick system (20s/turn), AFK detection, auto-logout.
- **Online/Offline State**: Safe logout with escape check, "Shock" state (10 min debuff), offline invisibility.
- **Admin Panel**: Refactored into modular structure (CSS/JS separated), CRUD placeholders, dungeon editor.

### Changed
- **Database**: Consolidated into `schema.sql` and `seed.sql` (removed 20+ migration files).
- **Code Structure**: Split `admin.php` into partials, extracted `auth.php`, `csrf.php`.
- **Localization**: All quotes and dialogues translated to Russian.
- **Boss Logic**: Dungeons now require manual boss assignment and rewards via admin panel.

### Fixed
- SQL foreign key incompatibilities (`boss_id`, `tile_type` ENUM mismatches).
- Vault spawn logic (prevent consecutive spawns in same vault).
- Logout safety (escape check during combat/dungeon).

### Technical Debt
- Combat UI not yet integrated (backend ready).
- Weather system and Deathclaw packs defined in schema but not active in loop.
- Chemistry addiction mechanics pending implementation.

---

## [Pre-Alpha 0.1-0.4] - Initial Development
- Initial schema design (users, map, monsters, items).
- Basic admin panel with session auth.
- World map generator (single entrance dungeons, vaults).
- Russian quote seeding.
- Pip-Boy interface prototype.

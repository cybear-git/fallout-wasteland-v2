# ☢️ Fallout: Wasteland Chronicles

**Multiplayer Browser-Based RPG set in the Fallout Universe**

A text-driven, turn-based exploration game where players navigate a massive 160x90 wasteland map, scavenge for loot, fight mutated creatures, trade with other survivors, and uncover the mysteries of the Old World. Built with PHP, MySQL, and vanilla JavaScript.

---

## 🚀 Features

### Core Gameplay
- **Massive Open World**: 14,400 unique locations across diverse biomes (Wasteland, Mountains, Brotherhood Steel Territory, Frozen North).
- **Blind Exploration**: No mini-map; navigate using compass directions (N, NE, E, SE, S, SW, W, NW) and atmospheric descriptions.
- **Vault Origins**: Start in one of 8 unique Vaults, receive starting gear (Jumpsuit, Stimpaks, Caps) from a Vault Keeper.
- **Turn-Based System**: 20-second action timer per turn; synchronized world events (weather, creature migrations).
- **Permadeath Risks**: Combat escape mechanics, "Shock" state on logout during battle, offline invisibility.

### Mechanics
- **S.P.E.C.I.A.L. Stats**: Allocate points on creation and level-up (threshold >10 costs 2 points).
- **Scavenging**: Search locations for junk, weapons, and chems; risk random encounters.
- **Inventory Management**: Equip armor/weapons, use consumables, manage junk for the Junk Jet.
- **Dynamic Events**: Moving weather patterns (Rad Storms, Acid Rain) and roaming Deathclaw packs.
- **Player Interaction**: Detect nearby survivors, trade via barter or caps, beware of raiders.

### Admin Tools
- **World Editor**: Manually place dungeons, set bosses, define rewards.
- **Real-Time Monitoring**: View player positions, active weather, and creature packs.
- **Content Management**: CRUD for items, monsters, quotes, and configurations.

---

## 🛠️ Tech Stack

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 8.1+ (Strict Types, PDO) |
| **Database** | MySQL 8.0 (InnoDB, Triggers, Events) |
| **Frontend** | Vanilla JS (ES6+), CSS3 (Pip-Boy CRT Effect) |
| **Architecture** | Modular PHP (No Framework), REST-like API endpoints |
| **Security** | CSRF Tokens, Prepared Statements, Session Regeneration |

---

## 📦 Installation

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Composer (optional, for dev tools)

### Quick Start

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd fallout-wasteland
   ```

2. **Configure Database**
   Edit `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'fallout_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   ```

3. **Import Schema & Seed Data**
   ```bash
   mysql -u root -p fallout_db < database/schema.sql
   mysql -u root -p fallout_db < database/seed.sql
   ```

4. **Generate World Map**
   ```bash
   php scripts/generate_world_map.php
   ```

5. **Start Development Server**
   ```bash
   php -S localhost:8000 -t public
   ```

6. **Play**
   Open `http://localhost:8000` in your browser.
   - **Default Admin**: `admin` / `admin123`

---

## 🗺️ Project Structure

```
/workspace
├── config/
│   └── database.php          # DB connection settings
├── database/
│   ├── schema.sql            # Full table structure
│   └── seed.sql              # Initial data (quotes, monsters, items)
├── includes/
│   ├── auth.php              # Authentication helpers
│   ├── csrf.php              # CSRF token logic
│   └── admin_views/          # Admin panel partials
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── admin.css     # Admin panel styles
│   │   │   └── game.css      # Pip-Boy interface styles
│   │   └── js/
│   │       ├── admin.js      # Admin AJAX logic
│   │       └── game.js       # Game loop & UI
│   ├── api/
│   │   ├── search.php        # Scavenging endpoint
│   │   └── inventory.php     # Inventory management
│   ├── index.php             # Main game loop (Pip-Boy)
│   ├── shelter.php           # Vault intro & character start
│   ├── dungeon.php           # Dungeon instance logic
│   ├── admin.php             # Admin dashboard
│   └── logout.php            # Safe logout handler
├── scripts/
│   └── generate_world_map.php # World generation script
├── CHANGELOG.md              # Version history
└── README.md                 # This file
```

---

## 🎮 Game Mechanics Deep Dive

### Turn System
- Each player action (move, search, fight) consumes a **20-second tick**.
- Inactivity triggers warnings; 3-5 strikes force logout.
- World events (weather, creatures) update on global ticks independent of player count.

### Combat
- **Turn-Based**: Select actions (Attack, Use Item, Flee).
- **Junk Jet**: Uses inventory junk as ammo; auto-consumes on fire.
- **Escape Check**: Forced logout during combat triggers a Luck/Agility check. Failure = 1 HP + "Miraculous Survival".

### Exploration
- **Biomes**: Affect encounter tables and search success rates.
- **Borders**: Impassable zones (Mountains, Brotherhood Steel) block movement with warnings.
- **Dungeons**: Multi-cell interiors with bosses and unique loot.

### Multiplayer
- **Proximity Alert**: "You see a figure in the distance..." if players are within 2 cells.
- **Trading**: Direct P2P trade when on the same cell.
- **Offline Mode**: Logged-out players vanish from the world state.

---

## 📝 Roadmap

### Phase 1: Core Loop (Current: Alpha 0.5)
- [x] Map generation & movement
- [x] Vault intro & starting gear
- [x] Search & inventory
- [x] Basic combat backend
- [ ] Combat UI integration
- [ ] Weather system activation

### Phase 2: Economy & Progression
- [ ] NPC Trading posts
- [ ] Chemistry & addiction mechanics
- [ ] Leveling & perk system
- [ ] Quest framework

### Phase 3: Multiplayer & Events
- [ ] Real-time tick synchronization
- [ ] Deathclaw pack AI
- [ ] Player factions
- [ ] Chat system

---

## 🤝 Contributing

1. Fork the repo.
2. Create a feature branch (`git checkout -b feature/awesome-feature`).
3. Commit changes (`git commit -m 'Add awesome feature'`).
4. Push to branch (`git push origin feature/awesome-feature`).
5. Open a Pull Request.

**Coding Standards**:
- PHP: PSR-12, strict types.
- JS: ES6+, no `var`.
- SQL: Prepared statements only.

---

## 📄 License

This project is a fan-made tribute to the Fallout universe. All game assets and lore belong to Bethesda Softworks / Interplay. Code is released under the MIT License.

---

## 🙏 Acknowledgments

- **Fallout Universe**: Created by Interplay, developed by Black Isle, Bethesda, Obsidian.
- **Inspiration**: Classic CRPGs, text MUDs, Fallout 1/2/Tactics.

*"War. War never changes."*
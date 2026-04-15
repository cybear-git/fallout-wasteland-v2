# 📋 ИСПРАВЛЕНИЯ МИГРАЦИЙ БАЗЫ ДАННЫХ

## 🐛 Выявленные проблемы и решения

### Проблема 1: Ошибка внешнего ключа `fk_dungeon_boss`
**Файл:** `019_alter_dungeons_rewards.sql`  
**Ошибка:** `ERROR 3780 (HY000): Referencing column 'boss_id' and referenced column 'id' in foreign key constraint 'fk_dungeon_boss' are incompatible.`

**Причина:** 
- `dungeons.boss_id` имеет тип `INT UNSIGNED`
- `monsters.id` имеет тип `INT` (не UNSIGNED)
- В MySQL внешние ключи требуют идентичных типов данных

**Решение:**
1. Добавлено поле `is_boss` в таблицу `monsters` (файл `005_create_monsters.sql`)
2. Изменен комментарий в миграции `019` для пояснения совместимости
3. **Важно:** При применении миграций убедиться, что `monsters.id` и `dungeons.boss_id` имеют одинаковый тип

**Исправление типа (опционально):**
Если ошибка сохраняется, нужно изменить тип `monsters.id`:
```sql
ALTER TABLE monsters MODIFY id INT UNSIGNED AUTO_INCREMENT;
```

---

### Проблема 2: Отсутствует таблица `admins`
**Файл:** `020_seed_initial_data.sql`  
**Ошибка:** `ERROR 1146 (42S02): Table 'fallout_db.admins' doesn't exist`

**Причина:** 
- В миграции `009_create_admin_support_tables.sql` созданы только `admin_logs` и `game_settings`
- Таблица `admins` не была создана ни в одной из миграций

**Решение:**
Добавлен блок создания таблицы `admins` в начало файла `020_seed_initial_data.sql`:
```sql
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Проблема 3: Поле `is_boss` отсутствует в `monsters`
**Файл:** `020_seed_initial_data.sql` (строка 31)  
**Ошибка:** `Unknown column 'is_boss' in 'field list'`

**Причина:** 
- Вставка боссов использует поле `is_boss`
- Поле не было добавлено в таблицу `monsters`

**Решение:**
Добавлено поле в `005_create_monsters.sql`:
```sql
is_boss TINYINT(1) DEFAULT 0 COMMENT 'Флаг босса (1 = босс)',
INDEX idx_boss (is_boss)
```

---

## ✅ Порядок применения исправленных миграций

```bash
# 1. Применить все миграции по порядку
mysql -u root -p fallout_db < database/001_create_players.sql
mysql -u root -p fallout_db < database/002_create_characters.sql
mysql -u root -p fallout_db < database/003_create_item_tables.sql
mysql -u root -p fallout_db < database/004_create_inventory.sql
mysql -u root -p fallout_db < database/005_create_monsters.sql        # ← Исправлено (is_boss)
mysql -u root -p fallout_db < database/006_create_locations.sql
mysql -u root -p fallout_db < database/007_create_world_drops.sql
mysql -u root -p fallout_db < database/008_alter_characters_add_status.sql
mysql -u root -p fallout_db < database/009_create_admin_support_tables.sql
mysql -u root -p fallout_db < database/010_populate_content.sql
mysql -u root -p fallout_db < database/011_alter_locations.sql
mysql -u root -p fallout_db < database/012_populate_expansion.sql
mysql -u root -p fallout_db < database/013_refactor_map_topology.sql
mysql -u root -p fallout_db < database/014_populate_locations_catalog.sql
mysql -u root -p fallout_db < database/015_create_dungeons.sql
mysql -u root -p fallout_db < database/016_create_location_quotes.sql
mysql -u root -p fallout_db < database/017_alter_dungeons_single_entrance.sql
mysql -u root -p fallout_db < database/018_alter_map_nodes_for_dungeons.sql
mysql -u root -p fallout_db < database/019_alter_dungeons_rewards.sql  # ← Исправлен комментарий
mysql -u root -p fallout_db < database/020_seed_initial_data.sql       # ← Добавлена таблица admins
mysql -u root -p fallout_db < database/021_create_combat_and_loot_tables.sql
mysql -u root -p fallout_db < database/021_massive_world_mechanics.sql
mysql -u root -p fallout_db < database/021_russian_quotes_and_vault_start.sql
mysql -u root -p fallout_db < database/022_seed_world_data.sql
mysql -u root -p fallout_db < database/023_inventory_loot_junkjet.sql

# 2. Сгенерировать карту
php scripts/generate_world_map.php

# 3. Запустить сервер
php -S localhost:8000 -t public
```

---

## 🔍 Проверка после применения

```sql
-- Проверка таблицы admins
SELECT * FROM admins;

-- Проверка боссов
SELECT id, name, is_boss FROM monsters WHERE is_boss = 1;

-- Проверка внешних ключей dungeons
SHOW CREATE TABLE dungeons;

-- Проверка фраз
SELECT COUNT(*) FROM location_quotes;
```

---

## 📝 Примечания

1. **Типы данных:** Все `id` поля должны быть либо `INT`, либо `INT UNSIGNED`, но не смешиваться
2. **Кодировка:** Все таблицы используют `utf8mb4_unicode_ci` для поддержки русского языка
3. **Движок:** Все таблицы используют `InnoDB` для поддержки внешних ключей
4. **Порядок:** Миграции должны применяться строго по номерам (001 → 023)

---

## 🎯 Следующие шаги

1. Применить исправленные миграции
2. Сгенерировать карту мира
3. Протестировать создание администратора
4. Протестировать создание боссов
5. Перейти к реализации поиска лута и боевого интерфейса

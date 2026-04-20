# 🔧 КРИТИЧЕСКИЕ ИСПРАВЛЕНИЯ МИГРАЦИЙ БД

## 📊 Проблема

При импорте миграций возникали критические ошибки:

### Ошибки в 033_economy_and_quests.sql:
```
Failed to open the referenced table 'items'
Failed to open the referenced table 'recipes'
Failed to open the referenced table 'users'
```

### Ошибки в 034_factions_dungeons_fast_travel.sql:
```
Failed to open the referenced table 'users' (3 раза)
```

---

## 🔍 Корневая причина

В проекте используется **разделённая модель предметов**:
- `weapons` - оружие
- `armors` - броня
- `consumables` - расходники
- `loot` - лут/мусор

**НЕ СУЩЕСТВУЕТ** единой таблицы `items`, на которую ссылались новые миграции.

Также в проекте используется модель:
- `players` → `characters`
- **НЕ СУЩЕСТВУЕТ** таблицы `users`

---

## ✅ Решение

### 1. Создана унифицированная таблица `items_unified`

**Файл:** `database/033_economy_and_quests.sql`

```sql
CREATE TABLE IF NOT EXISTS items_unified (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('weapon', 'armor', 'consumable', 'loot') NOT NULL,
    item_id INT NOT NULL COMMENT 'ID из соответствующей таблицы',
    name VARCHAR(100) NOT NULL,
    weight DECIMAL(5,2) DEFAULT 0,
    value INT UNSIGNED DEFAULT 0,
    UNIQUE KEY uniq_item (item_type, item_id)
);
```

**Автоматическое заполнение:**
```sql
INSERT INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'weapon', id, name, weight, value FROM weapons;
INSERT INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'armor', id, name, weight, value FROM armors;
INSERT INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'consumable', id, name, weight, value FROM consumables;
INSERT INTO items_unified (item_type, item_id, name, weight, value)
SELECT 'loot', id, name, weight, value FROM loot;
```

### 2. Исправлены все внешние ключи

| Было | Стало |
|------|-------|
| `REFERENCES items(id)` | `REFERENCES items_unified(id)` |
| `REFERENCES users(id)` | `REFERENCES characters(id)` |
| `user_id` | `character_id` |
| `result_item_id` | `result_item_unified_id` |
| `item_id` (в recipe_ingredients) | `item_unified_id` |

### 3. Обновлённые таблицы

#### vendor_items:
```sql
CREATE TABLE IF NOT EXISTS vendor_items (
    vendor_id INT NOT NULL,
    item_unified_id INT NOT NULL, -- БЫЛО: item_id
    FOREIGN KEY (item_unified_id) REFERENCES items_unified(id)
);
```

#### recipes:
```sql
CREATE TABLE IF NOT EXISTS recipes (
    result_item_unified_id INT NOT NULL, -- БЫЛО: result_item_id
    FOREIGN KEY (result_item_unified_id) REFERENCES items_unified(id)
);
```

#### recipe_ingredients:
```sql
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    item_unified_id INT NOT NULL, -- БЫЛО: item_id
    FOREIGN KEY (item_unified_id) REFERENCES items_unified(id)
);
```

#### player_quests:
```sql
CREATE TABLE IF NOT EXISTS player_quests (
    character_id INT NOT NULL, -- БЫЛО: user_id
    FOREIGN KEY (character_id) REFERENCES characters(id)
);
```

#### player_faction_reputation:
```sql
CREATE TABLE IF NOT EXISTS player_faction_reputation (
    character_id INT NOT NULL, -- БЫЛО: user_id
    FOREIGN KEY (character_id) REFERENCES characters(id)
);
```

#### player_fast_travel:
```sql
CREATE TABLE IF NOT EXISTS player_fast_travel (
    character_id INT NOT NULL, -- БЫЛО: user_id
    FOREIGN KEY (character_id) REFERENCES characters(id)
);
```

#### faction_action_log:
```sql
CREATE TABLE IF NOT EXISTS faction_action_log (
    character_id INT NOT NULL, -- БЫЛО: user_id
    FOREIGN KEY (character_id) REFERENCES characters(id)
);
```

---

## 📋 Инструкция по применению

### Вариант 1: Чистая установка (рекомендуется)

```bash
# Удалить базу данных
DROP DATABASE wasteland_rpg;

# Создать заново
CREATE DATABASE wasteland_rpg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Импортировать все миграции по порядку
mysql wasteland_rpg < database/001_create_players.sql
mysql wasteland_rpg < database/002_create_characters.sql
# ... (все миграции до 032)
mysql wasteland_rpg < database/032_add_loot_chance_settings.sql
mysql wasteland_rpg < database/033_economy_and_quests.sql  # ✅ ИСПРАВЛЕНА
mysql wasteland_rpg < database/034_factions_dungeons_fast_travel.sql  # ✅ ИСПРАВЛЕНА
```

### Вариант 2: Применение только исправленных миграций

Если база уже существует и нужны только новые таблицы:

```sql
-- Вручную удалить проблемные таблицы (если были созданы с ошибками)
DROP TABLE IF EXISTS player_quests;
DROP TABLE IF EXISTS player_faction_reputation;
DROP TABLE IF EXISTS player_fast_travel;
DROP TABLE IF EXISTS faction_action_log;
DROP TABLE IF EXISTS vendor_items;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS recipe_ingredients;

-- Применить исправленные миграции
source database/033_economy_and_quests.sql;
source database/034_factions_dungeons_fast_travel.sql;
```

---

## ✅ Результат

После применения исправлений:
- ✅ Все FOREIGN KEY работают корректно
- ✅ Таблица `items_unified` объединяет все типы предметов
- ✅ Все связи используют `character_id` вместо несуществующего `user_id`
- ✅ Миграции импортируются без ошибок и предупреждений

---

## 📁 Изменённые файлы

1. `database/033_economy_and_quests.sql` - полностью переписан
2. `database/034_factions_dungeons_fast_travel.sql` - исправлены FK

---

## 🎯 Архитектурное решение

**Полиморфная связь через items_unified:**

```
vendors → vendor_items → items_unified → {weapons, armors, consumables, loot}
                              ↑
                         (item_type, item_id)

recipes → recipe_ingredients → items_unified
```

Это позволяет:
- Сохранить существующую разделённую модель предметов
- Использовать единый интерфейс для торговли и крафта
- Легко расширять систему новыми типами предметов
- Избежать дублирования данных

---

## ⚠️ Важно для разработчиков

При работе с предметами в коде используйте:

```php
// Получение предмета из items_unified
$stmt = $pdo->prepare("
    SELECT iu.*, 
           CASE 
               WHEN iu.item_type = 'weapon' THEN w.description
               WHEN iu.item_type = 'armor' THEN a.description
               WHEN iu.item_type = 'consumable' THEN c.description
               WHEN iu.item_type = 'loot' THEN l.description
           END as full_description
    FROM items_unified iu
    LEFT JOIN weapons w ON iu.item_type = 'weapon' AND iu.item_id = w.id
    LEFT JOIN armors a ON iu.item_type = 'armor' AND iu.item_id = a.id
    LEFT JOIN consumables c ON iu.item_type = 'consumable' AND iu.item_id = c.id
    LEFT JOIN loot l ON iu.item_type = 'loot' AND iu.item_id = l.id
    WHERE iu.id = ?
");
```

---

**Дата исправления:** 2024
**Статус:** ✅ Готово к применению

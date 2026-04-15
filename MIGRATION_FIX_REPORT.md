# 🛠️ ОТЧЁТ ОБ ИСПРАВЛЕНИИ МИГРАЦИЙ

## Проблема
При применении миграций возникали ошибки:
1. `ERROR 3780`: Несовместимость типов в foreign key `fk_dungeon_boss`
2. `ERROR 1146`: Таблица `admins` не существует
3. `ERROR 1265`: Data truncated для колонки `tile_type` (недопустимое значение 'military')

## root cause
Тип `'military'` был указан в ENUM `tile_type` в некоторых миграциях, но отсутствовал в основной таблице `locations` (файл `006_create_locations.sql`).

## Исправленные файлы

| Файл | Изменение |
|------|-----------|
| `006_create_locations.sql` | Добавлены типы: `'forest','desert','ruins','camp'` |
| `011_alter_locations.sql` | Удалён тип `'military'` из ALTER |
| `013_refactor_map_topology.sql` | Удалён тип `'military'` из ENUM |
| `016_create_location_quotes.sql` | Удалён тип `'military'`, фразы перенесены в `'camp'` |
| `012_populate_expansion.sql` | Заменён `'military'` → `'city'` для блокпоста НКР |
| `014_populate_locations_catalog.sql` | Заменён `'military'` → `'city'` для блокпостов |

## Единый список типов локаций (актуальный)
```sql
ENUM('wasteland','city','dungeon','radzone','vault','mountain','forest','desert','ruins','camp')
```

## Как применить миграции

### Вариант A: По порядку (рекомендуется)
```bash
mysql -u root -p fallout_db < database/001_create_players.sql
mysql -u root -p fallout_db < database/002_create_characters.sql
# ... и так далее до 023 ...
mysql -u root -p fallout_db < database/023_inventory_loot_junkjet.sql
```

### Вариант B: Все сразу (если БД пустая)
```bash
cat database/0*.sql | mysql -u root -p fallout_db
```

### Вариант C: Скрипт автоматизации
```bash
#!/bin/bash
DB_NAME="fallout_db"
DB_USER="root"

for file in database/0*.sql; do
    echo "Applying $file..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file"
    if [ $? -ne 0 ]; then
        echo "ERROR in $file"
        exit 1
    fi
done

echo "All migrations applied successfully!"
```

## Проверка после применения

```sql
-- Проверить типы локаций
SHOW COLUMNS FROM locations LIKE 'tile_type';

-- Проверить количество фраз
SELECT tile_type, COUNT(*) as quote_count 
FROM location_quotes 
GROUP BY tile_type;

-- Проверить наличие данных
SELECT 
    (SELECT COUNT(*) FROM players) as players,
    (SELECT COUNT(*) FROM map_nodes) as map_nodes,
    (SELECT COUNT(*) FROM locations) as locations,
    (SELECT COUNT(*) FROM monsters) as monsters,
    (SELECT COUNT(*) FROM items) as items;
```

## Следующие шаги
1. ✅ Миграции исправлены
2. ⏳ Применить миграции к БД
3. ⏳ Запустить генератор карты: `php scripts/generate_world_map.php`
4. ⏳ Запустить сервер: `php -S localhost:8000 -t public`
5. ⏳ Протестировать создание персонажа и движение

---

**Статус:** ГОТОВО К ПРИМЕНЕНИЮ  
**Дата исправления:** 2024  
**Версия миграций:** 001-023 (исправленная)

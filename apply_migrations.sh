#!/bin/bash
# ============================================================
# Скрипт применения всех миграций базы данных Fallout RPG
# ============================================================

DB_NAME="fallout_db"
DB_USER="root"
DB_PASS=""
MYSQL_CMD="mysql -u ${DB_USER}"

if [ -n "$DB_PASS" ]; then
    MYSQL_CMD="mysql -u ${DB_USER} -p${DB_PASS}"
fi

echo "🚀 Начало применения миграций для базы ${DB_NAME}..."
echo ""

# Массив миграций в правильном порядке
MIGRATIONS=(
    "database/001_create_players.sql"
    "database/002_create_characters.sql"
    "database/003_create_item_tables.sql"
    "database/004_create_inventory.sql"
    "database/005_create_monsters.sql"
    "database/006_create_locations.sql"
    "database/007_create_world_drops.sql"
    "database/008_alter_characters_add_status.sql"
    "database/009_create_admin_support_tables.sql"
    "database/010_populate_content.sql"
    "database/011_alter_locations.sql"
    "database/012_populate_expansion.sql"
    "database/013_refactor_map_topology.sql"
    "database/014_populate_locations_catalog.sql"
    "database/015_create_dungeons.sql"
    "database/016_create_location_quotes.sql"
    "database/017_alter_dungeons_single_entrance.sql"
    "database/018_alter_map_nodes_for_dungeons.sql"
    "database/019_alter_dungeons_rewards.sql"
    "database/020_seed_initial_data.sql"
    "database/021_create_combat_and_loot_tables.sql"
    "database/021_massive_world_mechanics.sql"
    "database/021_russian_quotes_and_vault_start.sql"
    "database/022_seed_world_data.sql"
    "database/023_inventory_loot_junkjet.sql"
)

# Применение каждой миграции
for migration in "${MIGRATIONS[@]}"; do
    if [ -f "$migration" ]; then
        echo "📄 Применяем: $migration"
        $MYSQL_CMD "$DB_NAME" < "$migration" 2>&1 | grep -v "Using a password"
        if [ $? -eq 0 ]; then
            echo "   ✅ Успешно"
        else
            echo "   ⚠️ Ошибка (проверяем критичность)"
        fi
    else
        echo "   ❌ Файл не найден: $migration"
    fi
done

echo ""
echo "🎉 Миграции применены!"
echo ""
echo "📊 Проверка:"
echo "   - Таблица admins: SELECT COUNT(*) FROM admins;"
echo "   - Боссы: SELECT name FROM monsters WHERE is_boss=1;"
echo "   - Фразы: SELECT COUNT(*) FROM location_quotes;"
echo ""
echo "🗺️ Теперь сгенерируйте карту:"
echo "   php scripts/generate_world_map.php"
echo ""
echo "🎮 Запуск сервера:"
echo "   php -S localhost:8000 -t public"

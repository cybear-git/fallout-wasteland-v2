### добавление с консоли в базу
mysql -u root -p fallout_wastelands_v2 < database/005_create_monsters.sql

### создание админа
Если ты запустишь скрипт через терминал (php public/create_admin.php), всё сработает идеально.

### Дебаг PHP
// === ВРЕМЕННО: Показываем ошибки на экране (НИКОГДА не оставляй это в продакшене!) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ==============================================================================
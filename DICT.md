### добавление с консоли в базу
mysql -u root -p fallout_wastelands_v2 < database/005_create_monsters.sql

### создание админа
Если ты запустишь скрипт через терминал (php public/create_admin.php), всё сработает идеально.

### Дебаг PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


### 📋 Шпаргалка команд
| Задача | Команда |
|--------|---------|
| Создать ветку и перейти в неё | `git checkout -b <имя>` |
| Отправить ветку на GitHub (1-й раз) | `git push -u origin <имя>` |
| Отправить изменения (далее) | `git push` |
| Получить ветку с GitHub | `git fetch origin && git checkout <имя>` |
| Синхронизировать ветку с `main` | `git merge main` или `git rebase main` |
| Удалить локальную ветку | `git branch -d <имя>` |
| Удалить ветку на GitHub | `git push origin --delete <имя>` |
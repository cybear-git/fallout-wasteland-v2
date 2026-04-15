# 📋 ROADMAP: Fallout Wasteland Game Development

## ✅ Выполненные работы (Этап 1)

### 1. База данных
- ✅ **016_create_location_quotes.sql** - Таблица атмосферных фраз (100 шт.)
  - Привязка к типам локаций (`tile_type`)
  - Категории настроений: `neutral`, `danger`, `discovery`, `lore`, `humor`
  - Источники из вселенной Fallout
  
- ✅ **017_alter_dungeons_single_entrance.sql** - Одиночный вход в данжи
  - Поле `entrance_node_id` в таблице `dungeons`
  - Триггеры для гарантии одного входа
  - Foreign key связь с `map_nodes`
  
- ✅ **018_alter_map_nodes_for_dungeons.sql** - Связь карты и данжей
  - Поле `dungeon_id` в `map_nodes`
  - Виртуальная колонка `has_dungeon_entrance`
  - Индексы для производительности

### 2. Рефакторинг админ-панели
- ✅ **public/assets/css/admin.css** - Вынесенные стили (507 строк)
  - iOS Light дизайн
  - Адаптивная вёрстка
  - Стили для карт и редактора данжей
  
- ✅ **public/assets/js/admin.js** - Модульный JavaScript (369 строк)
  - AJAX helper с CSRF-защитой
  - Классы `DungeonEditor` и `MapEditor`
  - CRUD операции
  - Управление модалками

### 3. Структура проекта
```
/workspace/
├── database/
│   ├── 016_create_location_quotes.sql    ✅ Новый
│   ├── 017_alter_dungeons_single_entrance.sql  ✅ Новый
│   └── 018_alter_map_nodes_for_dungeons.sql    ✅ Новый
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin.css                 ✅ Новый
│   │   └── js/
│   │       └── admin.js                  ✅ Новый
│   └── admin.php                         ⚠️ Требует обновления
└── includes/
    ├── auth.php                          ✅ Существует
    └── csrf.php                          ✅ Существует
```

---

## 🎯 Следующие шаги (Этап 2)

### Приоритет 1: Обновление `admin.php`
**Задача:** Разбить монолитный файл на модули

**План:**
1. Вынести HTML шаблоны в `/includes/admin_views/`
2. Подключение через `require_once`
3. Удалить инлайн CSS (заменить на `<link rel="stylesheet" href="assets/css/admin.css">`)
4. Удалить инлайн JS (заменить на `<script src="assets/js/admin.js"></script>`)

**Структура view-файлов:**
```
/includes/admin_views/
├── dashboard.php        # Дашборд со статистикой
├── map_editor.php       # Редактор карты мира
├── dungeon_editor.php   # Редактор данжей
├── crud_list.php        # Универсальный список (монстры, предметы, etc.)
├── crud_form.php        # Форма редактирования
└── _sidebar.php         # Боковое меню
```

### Приоритет 2: Обновление генератора карт
**Файл:** `scripts/generate_world_map.php`

**Изменения:**
1. Убежища с одним выходом:
   - Проверка соседних клеток
   - Блокировка всех направлений кроме одного
   
2. Генерация данжей с одним входом:
   - Привязка к `map_nodes.dungeon_id`
   - Создание только одной entrance-ноды

### Приоритет 3: Игровая логика перемещения
**Файл:** `public/index.php` (создать)

**Функционал:**
1. Загрузка случайной фразы при переходе:
   ```sql
   SELECT quote_text FROM location_quotes 
   WHERE tile_type = ? AND is_active = 1 
   ORDER BY RAND() LIMIT 1
   ```
   
2. Определение типа локации по координатам:
   ```sql
   SELECT l.tile_type, l.name, mn.dungeon_id
   FROM map_nodes mn
   LEFT JOIN locations l ON mn.location_id = l.id
   WHERE mn.pos_x = ? AND mn.pos_y = ?
   ```

3. Кнопка входа в данж (если `dungeon_id IS NOT NULL`)

### Приоритет 4: Мини-карта данжа
**Файл:** `public/dungeon.php` (создать)

**Логика:**
- Загрузка нодов из `dungeon_nodes`
- Отрисовка сетки 3x3 - 6x6
- Навигация между нодами
- Кнопка выхода (возврат на `entrance_node_id`)

---

## 📊 Статус задач

| Задача | Статус | Файл(ы) | Приоритет |
|--------|--------|---------|-----------|
| Миграции БД | ✅ Готово | 016-018_*.sql | Высокий |
| CSS вынесен | ✅ Готово | assets/css/admin.css | Высокий |
| JS вынесен | ✅ Готово | assets/js/admin.js | Высокий |
| Рефакторинг admin.php | ⏳ В работе | admin.php + includes/admin_views/* | Высокий |
| Генератор убежищ | ⏳ Ожидает | scripts/generate_world_map.php | Средний |
| Генератор данжей | ⏳ Ожидает | scripts/generate_dungeon.php | Средний |
| Игровой цикл | ⏳ Ожидает | public/index.php | Критичный |
| Фразы локаций | ⏳ Ожидает | includes/quote_helper.php | Средний |
| Мини-карта данжа | ⏳ Ожидает | public/dungeon.php | Средний |

---

## 🔧 Технические требования

### Код-стиль
- `declare(strict_types=1)` во всех PHP файлах
- PSR-12 для имён переменных и функций
- Комментарии только для неочевидной логики

### Безопасность
- CSRF-токены для всех форм
- Prepared statements для SQL
- `htmlspecialchars()` для вывода

### Производительность
- Индексы на всех `WHERE`/`JOIN` полях
- Пакетная вставка в БД (2000+ за раз)
- Lazy loading для больших списков

---

## ❓ Вопросы для согласования

1. **Разбиение admin.php**: 
   - Использовать паттерн "View partials" или полноценный MVC?
   - Предложение: простые partials без фреймворка

2. **Стиль игры**:
   - Pip-Boy дизайн (чёрный фон, зелёный текст)
   - Отдельный CSS: `assets/css/game.css`?

3. **Аутентификация игроков**:
   - Отдельная таблица `players` для всех
   - Или общая с админами (role-based)?

4. **Сохранение прогресса**:
   - AJAX после каждого действия?
   - Или чекпоинты при переходе между зонами?

---

## 📝 Заметки

- **Не удалять рабочий код** без тестирования
- **Git commit** после каждого завершённого этапа
- **Тестировать** на локальном окружении перед коммитом
- **Документировать** все изменения в README.md

---

**Последнее обновление:** 2024-04-15  
**Статус:** Этап 1 завершён, ожидание подтверждения для Этапа 2

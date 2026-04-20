<?php
declare(strict_types=1);

session_name('fw_adm_ssid');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true
]);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_core.php'; // НОВАЯ СИСТЕМА АДМИНКИ

// ПРОВЕРКА ДОСТУПА ЧЕРЕЗ НОВУЮ СИСТЕМУ РОЛЕЙ
$adminRole = checkAdminAccess();
if (!$adminRole) {
    session_destroy();
    header('Location: admin_login.php?error=access_denied');
    exit;
}

$pdo = getDbConnection();
$adminId = (int)$_SESSION['admin_id'];
$adminName = getCurrentPlayer()['username'] ?? 'Admin';

$action = $_GET['action'] ?? 'dashboard';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';
$items = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $error = '❌ Неверный CSRF-токен';
}

try {
    switch ($action) {
        case 'users':
            // БАН/РАЗБАН игрока
            if (isset($_POST['toggle_ban']) && hasPermission('ban')) {
                $targetId = (int)$_POST['player_id'];
                $isBanned = (int)$_POST['is_banned'] === 1;
                $reason = trim($_POST['ban_reason'] ?? '');
                $result = togglePlayerBan($targetId, !$isBanned, $reason); // Переключаем состояние
                if ($result === true) {
                    $success = $isBanned ? 'Игрок разбанен' : 'Игрок забанен';
                } else {
                    $error = $result;
                }
            }
            // Выдача предмета
            if (isset($_POST['give_item']) && hasPermission('edit_items')) {
                $targetId = (int)$_POST['player_id'];
                $itemId = (int)$_POST['item_id'];
                $qty = max(1, (int)$_POST['quantity']);
                $result = giveItemToPlayer($targetId, $itemId, $qty);
                if ($result === true) {
                    $success = "Предмет выдан (x{$qty})";
                } else {
                    $error = $result;
                }
            }
            // Удаление игрока (только супер-админ)
            if (isset($_POST['delete_user']) && hasPermission('all')) {
                $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('DELETE_PLAYER', $id);
                $success = 'Игрок удалён';
                header("Location: ?action=users");
                exit;
            }
            break;
            
        case 'settings':
            if (isset($_POST['save_setting']) && hasPermission('change_settings')) {
                $result = updateGameSetting($_POST['setting_key'], $_POST['setting_value']);
                if ($result === true) {
                    $success = 'Настройка сохранена';
                } else {
                    $error = $result;
                }
            }
            break;
            
        case 'logs':
            if (isset($_POST['clear_logs']) && hasPermission('view_logs')) {
                $pdo->exec("TRUNCATE TABLE admin_logs");
                logAdminAction('CLEAR_LOGS');
                $success = 'Логи очищены';
            }
            break;
            
        case 'logout':
            session_destroy();
            header('Location: admin_login.php');
            exit;
    }
} catch (Exception $e) {
    $error = 'Ошибка: ' . $e->getMessage();
    logAdminAction('ERROR', null, ['message' => $e->getMessage(), 'action' => $action]);
}

// Загрузка данных
$stats = [
    'players' => $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn(),
    'characters' => $pdo->query("SELECT COUNT(*) FROM characters")->fetchColumn(),
    'monsters' => $pdo->query("SELECT COUNT(*) FROM monsters WHERE is_active=1")->fetchColumn(),
    'weapons' => $pdo->query("SELECT COUNT(*) FROM weapons WHERE is_active=1")->fetchColumn(),
    'armors' => $pdo->query("SELECT COUNT(*) FROM armors WHERE is_active=1")->fetchColumn(),
    'consumables' => $pdo->query("SELECT COUNT(*) FROM consumables WHERE is_active=1")->fetchColumn(),
    'locations' => $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn(),
    'map_nodes' => $pdo->query("SELECT COUNT(*) FROM map_nodes")->fetchColumn(),
    'logs' => $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn(),
];

switch ($action) {
    case 'users':
        $items = getAdminPlayersList(100, 0);
        // Загружаем предметы для выпадающего списка выдачи
        $allItems = $pdo->query("SELECT id, name, item_key FROM items ORDER BY name LIMIT 200")->fetchAll();
        break;
        
    case 'settings':
        $items = $pdo->query("SELECT * FROM game_settings ORDER BY category, setting_key")->fetchAll();
        break;
        
    case 'logs':
        $items = getAdminLogs(200);
        break;
        
    case 'locations':
        $items = $pdo->query("
            SELECT l.*, lt.type_name as tile_type
            FROM locations l
            LEFT JOIN location_types lt ON lt.id = l.location_type_id
            ORDER BY l.name LIMIT 100
        ")->fetchAll();
        break;
        
    case 'monsters':
        $items = $pdo->query("SELECT * FROM monsters ORDER BY id DESC LIMIT 50")->fetchAll();
        break;
        
    case 'weapons':
        $items = $pdo->query("SELECT * FROM weapons ORDER BY id DESC LIMIT 50")->fetchAll();
        break;
        
    case 'armors':
        $items = $pdo->query("SELECT * FROM armors ORDER BY id DESC LIMIT 50")->fetchAll();
        break;
        
    case 'consumables':
        $items = $pdo->query("SELECT * FROM consumables ORDER BY id DESC LIMIT 50")->fetchAll();
        break;
        
        case 'dungeons':
        $items = $pdo->query("SELECT * FROM dungeons ORDER BY id DESC LIMIT 50")->fetchAll();
        break;
        
    case 'quotes':
        if (isset($_POST['add_quote'])) {
            $stmt = $pdo->prepare("
                INSERT INTO location_quotes (quote_text, tile_type, mood, source, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['quote_text'],
                $_POST['tile_type'],
                $_POST['mood'] ?: 'neutral',
                $_POST['source'] ?: null,
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $success = 'Фраза добавлена';
        }
        if (isset($_POST['update_quote'])) {
            $stmt = $pdo->prepare("
                UPDATE location_quotes 
                SET quote_text = ?, tile_type = ?, mood = ?, source = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['quote_text'],
                $_POST['tile_type'],
                $_POST['mood'] ?: 'neutral',
                $_POST['source'] ?: null,
                isset($_POST['is_active']) ? 1 : 0,
                (int)$_POST['id']
            ]);
            $success = 'Фраза обновлена';
        }
        if (isset($_POST['delete_quote'])) {
            $stmt = $pdo->prepare("DELETE FROM location_quotes WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $success = 'Фраза удалена';
        }
        $items = $pdo->query("SELECT * FROM location_quotes ORDER BY tile_type, id LIMIT 200")->fetchAll();
        $tileTypes = $pdo->query("SELECT type_name FROM location_types ORDER BY type_name")->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'generate_map':
        $output = [];
        $cmd = 'php ' . __DIR__ . '/../scripts/generate_world_map.php';
        exec($cmd, $output, $return);
        if ($return === 0) {
            $success = '✅ Карта успешно сгенерирована!';
        } else {
            $error = 'Ошибка генерации карты: ' . implode("\n", $output);
        }
        header('Location: ?action=map');
        exit;
        
    case 'map':
        if (isset($_POST['update_cell'])) {
            $stmt = $pdo->prepare("
                UPDATE map_nodes 
                SET location_type_id = ?, location_id = ?
                WHERE pos_x = ? AND pos_y = ?
            ");
            $stmt->execute([
                $_POST['location_type_id'] ?: null,
                $_POST['location_id'] ?: null,
                (int)$_POST['pos_x'],
                (int)$_POST['pos_y']
            ]);
            $success = 'Клетка обновлена';
        }
        
        $nodes = $pdo->query("
            SELECT mn.pos_x, mn.pos_y, mn.is_border, mn.border_direction,
                   l.id as location_db_id, l.name as location_name,
                   lt.type_name as tile_type, lt.id as type_db_id
            FROM map_nodes mn
            LEFT JOIN locations l ON l.id = mn.location_id
            LEFT JOIN location_types lt ON lt.id = mn.location_type_id
            ORDER BY mn.pos_y DESC, mn.pos_x ASC
        ")->fetchAll();
        
        $allTypes = $pdo->query("SELECT id, type_name FROM location_types ORDER BY type_name")->fetchAll();
        $allLocations = $pdo->query("SELECT id, name, location_key FROM locations ORDER BY name")->fetchAll();
        
        $typeColors = [
            'wasteland' => '#4a4a3a',
            'city' => '#5a5a5a',
            'dungeon' => '#3a3a5a',
            'radzone' => '#4a6a4a',
            'vault' => '#4a3a3a',
            'vault_ext' => '#5a4a4a',
            'mountain' => '#6a6a7a',
            'forest' => '#3a5a3a',
            'desert' => '#7a6a4a',
            'ruins' => '#5a4a3a',
            'camp' => '#5a5a3a',
            'military' => '#3a4a3a',
            'military_base' => '#2a3a2a',
            'border' => '#e94560',
            'empty' => '#2a2a3a'
        ];
        
        if (!empty($nodes)) {
            $xs = array_column($nodes, 'pos_x');
            $ys = array_column($nodes, 'pos_y');
            $minX = min($xs); $maxX = max($xs);
            $minY = min($ys); $maxY = max($ys);
            $grid = [];
            foreach ($nodes as $n) { $grid[$n['pos_y']][$n['pos_x']] = $n; }
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Fallout RPG</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/game.css">
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>☢️ Fallout Admin</h2>
        </div>
        <div class="nav-section">
            <div class="nav-label">Главная</div>
            <a href="?action=dashboard" class="nav-item <?= $action=='dashboard'?'active':'' ?>"><span class="icon">📊</span> Дашборд</a>
        </div>
        <div class="nav-section">
            <div class="nav-label">Контент</div>
            <a href="?action=locations" class="nav-item <?= $action=='locations'?'active':'' ?>"><span class="icon">🗺️</span> Локации</a>
            <a href="?action=monsters" class="nav-item <?= $action=='monsters'?'active':'' ?>"><span class="icon">👹</span> Монстры</a>
            <a href="?action=weapons" class="nav-item <?= $action=='weapons'?'active':'' ?>"><span class="icon">🔫</span> Оружие</a>
            <a href="?action=armors" class="nav-item <?= $action=='armors'?'active':'' ?>"><span class="icon">🛡️</span> Броня</a>
            <a href="?action=consumables" class="nav-item <?= $action=='consumables'?'active':'' ?>"><span class="icon">💊</span> Расходники</a>
            <a href="?action=dungeons" class="nav-item <?= $action=='dungeons'?'active':'' ?>"><span class="icon">⚔️</span> Данжи</a>
            <a href="?action=quotes" class="nav-item <?= $action=='quotes'?'active':'' ?>"><span class="icon">💬</span> Фразы</a>
        </div>
        <div class="nav-section">
            <div class="nav-label">Система</div>
            <a href="?action=map" class="nav-item <?= $action=='map'?'active':'' ?>"><span class="icon">🌍</span> Карта мира</a>
            <a href="?action=users" class="nav-item <?= $action=='users'?'active':'' ?>"><span class="icon">👥</span> Игроки</a>
            <a href="?action=settings" class="nav-item <?= $action=='settings'?'active':'' ?>"><span class="icon">⚙️</span> Настройки</a>
            <a href="?action=logs" class="nav-item <?= $action=='logs'?'active':'' ?>"><span class="icon">📜</span> Логи</a>
        </div>
        <div class="nav-section">
            <a href="?action=logout" class="nav-item danger"><span class="icon">🚪</span> Выход</a>
        </div>
    </nav>
    
    <main class="main">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php switch ($action):
        case 'dashboard': ?>
            <div class="page-header">
                <h1>📊 Дашборд</h1>
                <div class="subtitle">Статистика мира Fallout</div>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">👥 Игроки</div>
                    <div class="value"><?= $stats['players'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">👹 Монстры</div>
                    <div class="value"><?= $stats['monsters'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">🗺️ Локации</div>
                    <div class="value"><?= $stats['locations'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">🌍 Клетки карты</div>
                    <div class="value"><?= $stats['map_nodes'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">📜 Логи</div>
                    <div class="value"><?= $stats['logs'] ?></div>
                </div>
            </div>
            <div class="card">
                <h3 style="margin-bottom: 15px;">Предметы</h3>
                <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="stat-card"><div class="label">🔫 Оружие</div><div class="value"><?= $stats['weapons'] ?></div></div>
                    <div class="stat-card"><div class="label">🛡️ Броня</div><div class="value"><?= $stats['armors'] ?></div></div>
                    <div class="stat-card"><div class="label">💊 Расходники</div><div class="value"><?= $stats['consumables'] ?></div></div>
                    <div class="stat-card"><div class="label">👻 Персонажи</div><div class="value"><?= $stats['characters'] ?></div></div>
                </div>
            </div>
        <?php break; ?>
        
        <?php case 'users': ?>
            <div class="page-header">
                <h1>👥 Игроки</h1>
                <div class="subtitle">Управление аккаунтами</div>
            </div>
            
            <?php if (hasPermission('ban') || hasPermission('edit_items')): ?>
            <div class="card" style="margin-bottom: 20px;">
                <h3>⚡ Быстрые действия</h3>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: end;">
                    <?= csrfField() ?>
                    <div>
                        <label>Игрок ID</label>
                        <input type="number" name="player_id" required style="width: 100%;" placeholder="ID игрока">
                    </div>
                    
                    <?php if (hasPermission('ban')): ?>
                    <div>
                        <label>Причина бана</label>
                        <input type="text" name="ban_reason" placeholder="Нарушение правил" style="width: 100%;">
                    </div>
                    <div>
                        <button type="submit" name="toggle_ban" class="btn btn-red">🔒 Бан/Разбан</button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('edit_items')): ?>
                    <div>
                        <label>Предмет</label>
                        <select name="item_id" required style="width: 100%;">
                            <option value="">Выбрать предмет</option>
                            <?php foreach ($allItems as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['item_key']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Кол-во</label>
                        <input type="number" name="quantity" value="1" min="1" style="width: 100%;">
                    </div>
                    <div>
                        <button type="submit" name="give_item" class="btn btn-blue">🎁 Выдать</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Персонаж</th>
                            <th>Ур.</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Бан</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['character_name'] ?? '—') ?></td>
                                <td><?= $u['level'] ?? '—' ?></td>
                                <td>
                                    <?php if (!empty($u['role_name'])): ?>
                                        <span class="badge badge-active"><?= htmlspecialchars($u['role_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge">Игрок</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $u['is_active'] ? 'Активен' : 'Заблокирован' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($u['is_banned'])): ?>
                                        <span class="badge badge-inactive" title="<?= htmlspecialchars($u['ban_reason'] ?? '') ?>">⛔</span>
                                    <?php else: ?>
                                        <span class="badge">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <?php if (hasPermission('ban')): ?>
                                        <form method="POST" style="display:inline;" title="Бан/Разбан">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="player_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="is_banned" value="<?= $u['is_banned'] ?? 0 ?>">
                                            <button type="submit" name="toggle_ban" class="btn btn-ghost"><?= !empty($u['is_banned']) ? '✅' : '🔒' ?></button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('all')): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить игрока навсегда?')" title="Удалить">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_user" value="1">
                                            <button type="submit" class="btn btn-ghost btn-red">🗑️</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'locations': ?>
            <div class="page-header">
                <h1>🗺️ Локации</h1>
                <div class="subtitle">Справочник типов местности</div>
            </div>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ключ</th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>Опасность</th>
                            <th>Радиация</th>
                            <th>Убежище</th>
                            <th>Данж</th>
                            <th>Активен</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $l): ?>
                            <tr>
                                <td><?= $l['id'] ?></td>
                                <td><code><?= htmlspecialchars($l['location_key']) ?></code></td>
                                <td><?= htmlspecialchars($l['name']) ?></td>
                                <td><?= htmlspecialchars($l['tile_type'] ?? '—') ?></td>
                                <td><?= $l['danger_level'] ?></td>
                                <td><?= $l['radiation_level'] ?></td>
                                <td><?= $l['is_vault'] ? '✓' : '—' ?></td>
                                <td><?= $l['is_dungeon'] ? '✓' : '—' ?></td>
                                <td><?= $l['is_active'] ? '✓' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'quotes': ?>
            <div class="page-header">
                <h1>💬 Фразы локаций</h1>
                <div class="subtitle">Атмосферные фразы для каждого типа местности</div>
            </div>
            
            <div class="card" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">➕ Добавить фразу</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="add_quote" value="1">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div class="form-group">
                            <label>Тип локации</label>
                            <select name="tile_type" required>
                                <option value="wasteland">Пустошь</option>
                                <option value="city">Город</option>
                                <option value="dungeon">Подземелье</option>
                                <option value="radzone">Радиоактивная зона</option>
                                <option value="vault">Убежище</option>
                                <option value="vault_ext">Вход в Убежище</option>
                                <option value="mountain">Горы</option>
                                <option value="forest">Лес</option>
                                <option value="desert">Пустыня</option>
                                <option value="ruins">Руины</option>
                                <option value="camp">Лагерь</option>
                                <option value="military">Военная база</option>
                                <option value="military_base">Комплекс Братства</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Настроение</label>
                            <select name="mood">
                                <option value="neutral">Нейтральное</option>
                                <option value="danger">Опасность</option>
                                <option value="discovery">Открытие</option>
                                <option value="lore">Лор</option>
                                <option value="humor">Юмор</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Источник</label>
                            <input type="text" name="source" placeholder="Fallout 3, NPC, Книга...">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Текст фразы</label>
                        <textarea name="quote_text" rows="3" required style="width: 100%;"></textarea>
                    </div>
                    <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                        <input type="checkbox" name="is_active" checked> Активна
                    </label>
                    <button type="submit" class="btn btn-green" style="margin-top: 15px;">💾 Добавить</button>
                </form>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom: 15px;">📜 Все фразы (<?= count($items) ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Тип</th>
                            <th>Настроение</th>
                            <th>Фраза</th>
                            <th>Источник</th>
                            <th>Активна</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $q): ?>
                            <tr>
                                <td><?= $q['id'] ?></td>
                                <td><span class="badge"><?= htmlspecialchars($q['tile_type']) ?></span></td>
                                <td><?= htmlspecialchars($q['mood']) ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($q['quote_text']) ?>
                                </td>
                                <td><?= htmlspecialchars($q['source'] ?? '—') ?></td>
                                <td><?= $q['is_active'] ? '✓' : '✗' ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                        <input type="hidden" name="quote_text" value="<?= htmlspecialchars($q['quote_text']) ?>">
                                        <input type="hidden" name="tile_type" value="<?= htmlspecialchars($q['tile_type']) ?>">
                                        <input type="hidden" name="mood" value="<?= htmlspecialchars($q['mood']) ?>">
                                        <input type="hidden" name="source" value="<?= htmlspecialchars($q['source'] ?? '') ?>">
                                        <input type="hidden" name="is_active" value="<?= $q['is_active'] ?>">
                                        <button type="submit" formaction="?action=edit_quote" class="btn btn-ghost">✏️</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить фразу?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                        <button type="submit" name="delete_quote" class="btn btn-red">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'map': ?>
            <div class="page-header">
                <h1>🌍 Карта мира</h1>
                <div class="subtitle">Визуализация и редактирование игрового мира</div>
            </div>
            
            <div class="card" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">⚡ Управление картой</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="?action=generate_map" class="btn" style="background: var(--green); color: #000; padding: 12px 20px;">
                        🗺️ Сгенерировать карту
                    </a>
                    <button onclick="document.getElementById('editPanel').style.display=document.getElementById('editPanel').style.display?'none':'block'" class="btn btn-blue">
                        ✏️ Редактировать клетку
                    </button>
                </div>
            </div>

            <div id="editPanel" class="card" style="display: none; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">✏️ Редактирование клетки</h3>
                <form method="POST" id="cellForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="update_cell" value="1">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div class="form-group">
                            <label>Координата X</label>
                            <input type="number" name="pos_x" id="editX" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Координата Y</label>
                            <input type="number" name="pos_y" id="editY" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Тип локации</label>
                            <select name="location_type_id" id="editType">
                                <?php foreach ($allTypes as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Локация</label>
                            <select name="location_id" id="editLocation">
                                <option value="">— Пусто —</option>
                                <?php foreach ($allLocations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-green" style="margin-top: 15px;">💾 Сохранить изменения</button>
                </form>
                <div id="cellInfo" style="margin-top: 15px; padding: 10px; background: var(--bg); border-radius: 6px; display: none;"></div>
            </div>

            <div class="card">
                <?php if (empty($nodes)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <p style="color: var(--gray); font-size: 18px; margin-bottom: 20px;">🗺️ Карта пуста</p>
                        <p style="color: var(--gray);">Нажмите "Сгенерировать карту" для создания мира</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <span style="color: var(--gray);">Размер:</span>
                            <strong style="color: var(--green);"><?= $maxX - $minX + 1 ?> × <?= $maxY - $minY + 1 ?></strong>
                            <span style="color: var(--gray); margin-left: 20px;">Клеток:</span>
                            <strong style="color: var(--green);"><?= count($nodes) ?></strong>
                        </div>
                        <div style="color: var(--gray); font-size: 12px;">
                            Кликните на клетку для редактирования
                        </div>
                    </div>
                    
                    <div class="map-container" style="max-height: 600px; overflow: auto;">
                        <div class="map-grid" style="grid-template-columns: repeat(<?= $maxX - $minX + 1 ?>, 20px);">
                            <?php for ($y = $maxY; $y >= $minY; $y--): ?>
                                <?php for ($x = $minX; $x <= $maxX; $x++): ?>
                                    <?php 
                                    $node = $grid[$y][$x] ?? null;
                                    $tileType = $node['tile_type'] ?? 'empty';
                                    $locName = $node['location_name'] ?? '';
                                    $title = "({$x},{$y})";
                                    if ($locName) $title .= " - {$locName}";
                                    ?>
                                    <div class="map-cell cell-<?= $tileType ?>" 
                                         title="<?= htmlspecialchars($title) ?>"
                                         onclick="selectCell(<?= $x ?>, <?= $y ?>, '<?= htmlspecialchars(addslashes($tileType)) ?>', '<?= htmlspecialchars(addslashes($locName)) ?>')"
                                         style="cursor: pointer;"></div>
                                <?php endfor; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: var(--bg); border-radius: 8px;">
                        <h4 style="margin-bottom: 10px; color: var(--text);">Легенда:</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                            <?php foreach ($typeColors as $type => $color): ?>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <div style="width: 16px; height: 16px; background: <?= $color ?>; border-radius: 2px;"></div>
                                    <span style="font-size: 11px; color: var(--gray);"><?= $type ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <script>
            function selectCell(x, y, type, name) {
                document.getElementById('editPanel').style.display = 'block';
                document.getElementById('editX').value = x;
                document.getElementById('editY').value = y;
                document.getElementById('cellInfo').style.display = 'block';
                document.getElementById('cellInfo').innerHTML = 
                    '<strong style="color: var(--green);">Выбрана клетка:</strong> (' + x + ', ' + y + ') - ' + 
                    (name || 'Пусто') + ' <span style="color: var(--gray);">[' + type + ']</span>';
                document.getElementById('cellForm').scrollIntoView({behavior: 'smooth'});
            }
            </script>
        <?php break; ?>
        
        <?php case 'monsters': ?>
            <div class="page-header">
                <h1>👹 Монстры</h1>
                <div class="subtitle">Враги Пустоши</div>
            </div>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ключ</th>
                            <th>Имя</th>
                            <th>Ур.</th>
                            <th>HP</th>
                            <th>Урон</th>
                            <th>XP</th>
                            <th>Босс</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $m): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><code><?= htmlspecialchars($m['monster_key']) ?></code></td>
                                <td><?= htmlspecialchars($m['name']) ?></td>
                                <td><?= $m['level'] ?></td>
                                <td><?= $m['base_hp'] ?></td>
                                <td><?= $m['base_dmg'] ?></td>
                                <td><?= $m['xp_reward'] ?></td>
                                <td><?= $m['is_boss'] ? '👑' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'weapons': ?>
            <div class="page-header"><h1>🔫 Оружие</h1></div>
            <div class="card">
                <table>
                    <thead><tr><th>ID</th><th>Ключ</th><th>Имя</th><th>Урон</th><th>Вес</th><th>Цена</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $w): ?>
                            <tr>
                                <td><?= $w['id'] ?></td>
                                <td><code><?= htmlspecialchars($w['item_key']) ?></code></td>
                                <td><?= htmlspecialchars($w['name']) ?></td>
                                <td><?= $w['dmg_dice'] ?>d + <?= $w['dmg_mod'] ?></td>
                                <td><?= $w['weight'] ?></td>
                                <td><?= $w['value'] ?> 💰</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'armors': ?>
            <div class="page-header"><h1>🛡️ Броня</h1></div>
            <div class="card">
                <table>
                    <thead><tr><th>ID</th><th>Ключ</th><th>Имя</th><th>Защита</th><th>Вес</th><th>Цена</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td><code><?= htmlspecialchars($a['item_key']) ?></code></td>
                                <td><?= htmlspecialchars($a['name']) ?></td>
                                <td><?= $a['defense'] ?></td>
                                <td><?= $a['weight'] ?></td>
                                <td><?= $a['value'] ?> 💰</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'consumables': ?>
            <div class="page-header"><h1>💊 Расходники</h1></div>
            <div class="card">
                <table>
                    <thead><tr><th>ID</th><th>Ключ</th><th>Имя</th><th>HP</th><th>Рад</th><th>Зависимость</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><code><?= htmlspecialchars($c['item_key']) ?></code></td>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td><?= $c['heal_amount'] > 0 ? '+' . $c['heal_amount'] : '—' ?></td>
                                <td><?= $c['rad_heal'] > 0 ? '-' . $c['rad_heal'] : '—' ?></td>
                                <td><?= $c['addiction_chance'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'dungeons': ?>
            <div class="page-header"><h1>⚔️ Данжи</h1></div>
            <div class="card">
                <table>
                    <thead><tr><th>ID</th><th>Ключ</th><th>Имя</th><th>Мин. ур.</th><th>Размер</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $d): ?>
                            <tr>
                                <td><?= $d['id'] ?></td>
                                <td><code><?= htmlspecialchars($d['dungeon_key']) ?></code></td>
                                <td><?= htmlspecialchars($d['name']) ?></td>
                                <td><?= $d['min_level'] ?></td>
                                <td><?= $d['dungeon_size'] ?? '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'settings': ?>
            <div class="page-header"><h1>⚙️ Настройки</h1></div>
            <div class="card">
                <table>
                    <thead><tr><th>Ключ</th><th>Значение</th><th>Категория</th><th>Описание</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $s): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($s['setting_key']) ?></code></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:5px;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="setting_key" value="<?= htmlspecialchars($s['setting_key']) ?>">
                                        <input type="text" name="setting_value" value="<?= htmlspecialchars($s['setting_value']) ?>" style="width: 100px;">
                                        <button type="submit" name="save_setting" class="btn btn-blue">💾</button>
                                    </form>
                                </td>
                                <td><?= htmlspecialchars($s['category'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($s['description'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php case 'logs': ?>
            <div class="page-header">
                <h1>📜 Журнал аудита</h1>
                <?php if (hasPermission('view_logs')): ?>
                <form method="POST" style="display:inline; margin-left: 20px;">
                    <?= csrfField() ?>
                    <button type="submit" name="clear_logs" class="btn btn-red" onclick="return confirm('Очистить все логи?')">🗑️ Очистить</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Админ</th>
                            <th>Действие</th>
                            <th>Цель ID</th>
                            <th>Детали</th>
                            <th>IP</th>
                            <th>Время</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $l): ?>
                            <tr>
                                <td><?= $l['id'] ?></td>
                                <td><?= htmlspecialchars($l['admin_name'] ?? $l['admin_id']) ?></td>
                                <td>
                                    <span class="badge"><?= htmlspecialchars($l['action']) ?></span>
                                </td>
                                <td><?= $l['target_id'] ?? '—' ?></td>
                                <td>
                                    <?php if (!empty($l['details'])): ?>
                                        <?php 
                                        $details = json_decode($l['details'], true);
                                        if ($details): 
                                        ?>
                                            <code style="font-size: 11px;">
                                            <?php foreach ($details as $k => $v): ?>
                                                <?= htmlspecialchars($k) ?>: <?= is_array($v) ? json_encode($v) : htmlspecialchars((string)$v) ?>; 
                                            <?php endforeach; ?>
                                            </code>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                                <td><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php break; ?>
        
        <?php default: ?>
            <div class="page-header"><h1>404</h1><p>Страница не найдена</p></div>
        <?php endswitch; ?>
    </main>
    
    <!-- Подключаем внешний JS файл для админки -->
    <script src="/assets/js/admin.js"></script>
</body>
</html>

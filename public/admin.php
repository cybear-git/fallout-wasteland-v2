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

if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$pdo = getDbConnection();
$adminId = (int)$_SESSION['admin_id'];

$stmt = $pdo->prepare("
    SELECT p.is_active, r.role_name 
    FROM players p 
    JOIN roles r ON r.id = p.role_id 
    WHERE p.id = ?
");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin || $admin['role_name'] !== 'admin' || $admin['is_active'] != 1) {
    session_destroy();
    header('Location: admin_login.php?error=revoked');
    exit;
}

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
            if (isset($_POST['toggle_active'])) {
                $stmt = $pdo->prepare("UPDATE players SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Статус игрока изменён';
            }
            if (isset($_POST['delete_user'])) {
                $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Игрок удалён';
                header("Location: ?action=users");
                exit;
            }
            break;
            
        case 'settings':
            if (isset($_POST['save_setting'])) {
                $stmt = $pdo->prepare("UPDATE game_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$_POST['setting_value'], $_POST['setting_key']]);
                $success = 'Настройка сохранена';
            }
            break;
            
        case 'logs':
            if (isset($_POST['clear_logs'])) {
                $pdo->exec("TRUNCATE TABLE admin_logs");
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
        $items = $pdo->query("
            SELECT p.id, p.username, p.is_active, p.created_at, r.role_name,
                   c.name as character_name, c.level
            FROM players p
            LEFT JOIN roles r ON r.id = p.role_id
            LEFT JOIN characters c ON c.player_id = p.id
            ORDER BY p.id DESC LIMIT 100
        ")->fetchAll();
        break;
        
    case 'settings':
        $items = $pdo->query("SELECT * FROM game_settings ORDER BY category, setting_key")->fetchAll();
        break;
        
    case 'logs':
        $items = $pdo->query("
            SELECT l.*, p.username 
            FROM admin_logs l
            LEFT JOIN players p ON p.id = l.admin_id
            ORDER BY l.created_at DESC LIMIT 100
        ")->fetchAll();
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
    <style>
        :root { --bg: #1a1a2e; --card: #16213e; --blue: #0f3460; --accent: #e94560; --green: #00d9ff; --text: #eee; --gray: #888; --border: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }
        
        .sidebar { width: 260px; background: var(--card); border-right: 1px solid var(--border); min-height: 100vh; position: fixed; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 18px; color: var(--accent); }
        .nav-section { padding: 15px 0; }
        .nav-label { padding: 5px 20px; font-size: 11px; color: var(--gray); text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; padding: 12px 20px; color: var(--text); text-decoration: none; font-size: 14px; transition: 0.2s; }
        .nav-item:hover { background: var(--blue); }
        .nav-item.active { background: var(--accent); color: #fff; }
        .nav-item.danger { color: var(--accent); }
        .nav-item .icon { width: 24px; margin-right: 10px; }
        
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; margin-bottom: 5px; }
        .page-header .subtitle { color: var(--gray); }
        
        .card { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: var(--card); border-radius: 10px; padding: 15px; text-align: center; border: 1px solid var(--border); }
        .stat-card .label { font-size: 12px; color: var(--gray); margin-bottom: 5px; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: var(--green); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 11px; color: var(--gray); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 13px; }
        tr:hover td { background: var(--blue); }
        
        .btn { padding: 8px 14px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-blue { background: var(--blue); color: var(--green); }
        .btn-red { background: var(--accent); color: #fff; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; color: var(--gray); margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: var(--green); }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(0,217,255,0.1); border: 1px solid var(--green); color: var(--green); }
        .alert-error { background: rgba(233,69,96,0.1); border: 1px solid var(--accent); color: var(--accent); }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; }
        .badge-active { background: rgba(0,217,255,0.2); color: var(--green); }
        .badge-inactive { background: rgba(233,69,96,0.2); color: var(--accent); }
        
        .map-container { background: var(--bg); border-radius: 8px; padding: 15px; overflow-x: auto; }
        .map-grid { display: grid; gap: 2px; width: fit-content; }
        .map-cell { width: 24px; height: 24px; border: 1px solid var(--border); border-radius: 2px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px; transition: 0.1s; }
        .map-cell:hover { transform: scale(1.2); z-index: 10; border-color: var(--green); }
        .cell-wasteland { background: #4a4a3a; }
        .cell-city { background: #5a5a5a; }
        .cell-dungeon { background: #3a3a5a; }
        .cell-vault { background: #4a3a3a; }
        .cell-vault_ext { background: #5a4a4a; }
        .cell-military { background: #3a4a3a; }
        .cell-military_base { background: #2a3a2a; }
        .cell-ruins { background: #5a4a3a; }
        .cell-radzone { background: #4a6a4a; }
        .cell-forest { background: #3a5a3a; }
        .cell-mountain { background: #6a6a7a; }
        .cell-desert { background: #7a6a4a; }
        .cell-camp { background: #5a5a3a; }
        .cell-empty { background: #2a2a3a; }
        .cell-border { background: var(--accent); }
    </style>
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
                                <td><?= htmlspecialchars($u['role_name'] ?? 'player') ?></td>
                                <td>
                                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $u['is_active'] ? 'Активен' : 'Заблокирован' ?>
                                    </span>
                                </td>
                                <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="toggle_active" value="1">
                                        <button type="submit" class="btn btn-ghost"><?= $u['is_active'] ? '🔒' : '🔓' ?></button>
                                    </form>
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
                <h1>📜 Логи</h1>
                <form method="POST" style="display:inline; margin-left: 20px;">
                    <?= csrfField() ?>
                    <button type="submit" name="clear_logs" class="btn btn-red" onclick="return confirm('Очистить все логи?')">🗑️ Очистить</button>
                </form>
            </div>
            <div class="card">
                <table>
                    <thead><tr><th>ID</th><th>Админ</th><th>Действие</th><th>Таблица</th><th>IP</th><th>Время</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $l): ?>
                            <tr>
                                <td><?= $l['id'] ?></td>
                                <td><?= htmlspecialchars($l['username'] ?? $l['admin_id']) ?></td>
                                <td><?= htmlspecialchars($l['action']) ?></td>
                                <td><?= htmlspecialchars($l['table_name']) ?></td>
                                <td><?= htmlspecialchars($l['ip_address']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
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
</body>
</html>

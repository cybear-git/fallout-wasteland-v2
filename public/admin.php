<?php

// === ВРЕМЕННО: Показываем ошибки на экране (НИКОГДА не оставляй это в продакшене!) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ==============================================================================
/**
 * public/admin.php
 * Полноценная админ-панель с iOS Light стилем
 */
session_name('fw_adm_ssid');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true
]);

require_once __DIR__ . '/../config/database.php';

// Проверка авторизации
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$pdo = getDbConnection();
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// Двойная проверка в БД
$stmt = $pdo->prepare("SELECT role, is_active FROM players WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || $admin['role'] !== 'admin' || $admin['is_active'] != 1) {
    session_destroy();
    header('Location: admin_login.php?error=access_revoked');
    exit;
}

// Определение текущего раздела
$action = $_GET['action'] ?? 'dashboard';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// ════════════ ОБРАБОТКА POST-ЗАПРОСОВ ════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            // --- МОНСТРЫ ---
            case 'monsters':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM monsters WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    logAction('DELETE_MONSTER', 'monsters', (int)$_POST['id']);
                    $success = 'Монстр удалён';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'monster_key' => trim($_POST['monster_key'] ?? ''),
                        'name' => trim($_POST['name'] ?? ''),
                        'level' => (int)($_POST['level'] ?? 1),
                        'speed' => (int)($_POST['speed'] ?? 5),
                        'status' => $_POST['status'] ?? 'wandering',
                        'base_hp' => (int)($_POST['base_hp'] ?? 10),
                        'base_armor' => (int)($_POST['base_armor'] ?? 0),
                        'base_dmg' => (int)($_POST['base_dmg'] ?? 1),
                        'xp_reward' => (int)($_POST['xp_reward'] ?? 5),
                        'spawn_weight' => (int)($_POST['spawn_weight'] ?? 10),
                        'habitat' => trim($_POST['habitat'] ?? 'wasteland'),
                        'is_active' => (int)(!!$_POST['is_active']),
                        'loot_table' => $_POST['loot_table'] ?? null
                    ];
                    
                    if (empty($fields['monster_key']) || empty($fields['name'])) {
                        $error = 'Заполните ключ и название';
                    } else {
                        if ((int)$_POST['id'] > 0) {
                            $fields['id'] = (int)$_POST['id'];
                            $stmt = $pdo->prepare("UPDATE monsters SET monster_key=?, name=?, level=?, speed=?, status=?, base_hp=?, base_armor=?, base_dmg=?, xp_reward=?, spawn_weight=?, habitat=?, is_active=?, loot_table=? WHERE id=?");
                            $stmt->execute([$fields['monster_key'], $fields['name'], $fields['level'], $fields['speed'], $fields['status'], $fields['base_hp'], $fields['base_armor'], $fields['base_dmg'], $fields['xp_reward'], $fields['spawn_weight'], $fields['habitat'], $fields['is_active'], $fields['loot_table'], $fields['id']]);
                            logAction('UPDATE_MONSTER', 'monsters', $fields['id']);
                            $success = 'Монстр обновлён';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO monsters (monster_key, name, level, speed, status, base_hp, base_armor, base_dmg, xp_reward, spawn_weight, habitat, is_active, loot_table) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([$fields['monster_key'], $fields['name'], $fields['level'], $fields['speed'], $fields['status'], $fields['base_hp'], $fields['base_armor'], $fields['base_dmg'], $fields['xp_reward'], $fields['spawn_weight'], $fields['habitat'], $fields['is_active'], $fields['loot_table']]);
                            logAction('CREATE_MONSTER', 'monsters', (int)$pdo->lastInsertId());
                            $success = 'Монстр добавлен';
                        }
                    }
                }
                break;

            // --- ОРУЖИЕ ---
            case 'weapons':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM weapons WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    logAction('DELETE_WEAPON', 'weapons', (int)$_POST['id']);
                    $success = 'Оружие удалено';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'item_key' => trim($_POST['item_key'] ?? ''),
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'icon' => trim($_POST['icon'] ?? '🔫'),
                        'weight' => (float)($_POST['weight'] ?? 0),
                        'value' => (int)($_POST['value'] ?? 0),
                        'dmg_dice' => (int)($_POST['dmg_dice'] ?? 4),
                        'dmg_mod' => (int)($_POST['dmg_mod'] ?? 0),
                        'crit_chance' => (float)($_POST['crit_chance'] ?? 5.0),
                        'crit_mult' => (float)($_POST['crit_mult'] ?? 1.5),
                        'range_type' => $_POST['range_type'] ?? 'melee',
                        'min_str' => (int)($_POST['min_str'] ?? 0),
                        'is_active' => (int)(!!$_POST['is_active'])
                    ];
                    
                    if (empty($fields['item_key']) || empty($fields['name'])) {
                        $error = 'Заполните ключ и название';
                    } else {
                        if ((int)$_POST['id'] > 0) {
                            $fields['id'] = (int)$_POST['id'];
                            $stmt = $pdo->prepare("UPDATE weapons SET item_key=?, name=?, description=?, icon=?, weight=?, value=?, dmg_dice=?, dmg_mod=?, crit_chance=?, crit_mult=?, range_type=?, min_str=?, is_active=? WHERE id=?");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['dmg_dice'], $fields['dmg_mod'], $fields['crit_chance'], $fields['crit_mult'], $fields['range_type'], $fields['min_str'], $fields['is_active'], $fields['id']]);
                            logAction('UPDATE_WEAPON', 'weapons', $fields['id']);
                            $success = 'Оружие обновлено';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO weapons (item_key, name, description, icon, weight, value, dmg_dice, dmg_mod, crit_chance, crit_mult, range_type, min_str, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['dmg_dice'], $fields['dmg_mod'], $fields['crit_chance'], $fields['crit_mult'], $fields['range_type'], $fields['min_str'], $fields['is_active']]);
                            logAction('CREATE_WEAPON', 'weapons', (int)$pdo->lastInsertId());
                            $success = 'Оружие добавлено';
                        }
                    }
                }
                break;

            // --- БРОНЯ ---
            case 'armors':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM armors WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Броня удалена';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'item_key' => trim($_POST['item_key'] ?? ''),
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'icon' => trim($_POST['icon'] ?? '🛡️'),
                        'weight' => (float)($_POST['weight'] ?? 0),
                        'value' => (int)($_POST['value'] ?? 0),
                        'defense' => (int)($_POST['defense'] ?? 0),
                        'rad_resistance' => (int)($_POST['rad_resistance'] ?? 0),
                        'slot_type' => $_POST['slot_type'] ?? 'tors',
                        'min_str' => (int)($_POST['min_str'] ?? 0),
                        'is_active' => (int)(!!$_POST['is_active'])
                    ];
                    if (empty($fields['item_key'])) { $error = 'Заполните ключ'; }
                    else {
                        if ((int)$_POST['id'] > 0) {
                            $fields['id'] = (int)$_POST['id'];
                            $stmt = $pdo->prepare("UPDATE armors SET item_key=?, name=?, description=?, icon=?, weight=?, value=?, defense=?, rad_resistance=?, slot_type=?, min_str=?, is_active=? WHERE id=?");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['defense'], $fields['rad_resistance'], $fields['slot_type'], $fields['min_str'], $fields['is_active'], $fields['id']]);
                            $success = 'Броня обновлена';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO armors (item_key, name, description, icon, weight, value, defense, rad_resistance, slot_type, min_str, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['defense'], $fields['rad_resistance'], $fields['slot_type'], $fields['min_str'], $fields['is_active']]);
                            $success = 'Броня добавлена';
                        }
                    }
                }
                break;

            // --- РАСХОДНИКИ ---
            case 'consumables':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM consumables WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Расходник удалён';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'item_key' => trim($_POST['item_key'] ?? ''),
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'icon' => trim($_POST['icon'] ?? '💊'),
                        'weight' => (float)($_POST['weight'] ?? 0),
                        'value' => (int)($_POST['value'] ?? 0),
                        'heal_amount' => (int)($_POST['heal_amount'] ?? 0),
                        'rad_heal' => (int)($_POST['rad_heal'] ?? 0),
                        'addiction_chance' => (float)($_POST['addiction_chance'] ?? 0.0),
                        'boost_type' => $_POST['boost_type'] ?? null,
                        'boost_value' => (int)($_POST['boost_value'] ?? 0),
                        'boost_duration' => (int)($_POST['boost_duration'] ?? 0),
                        'is_active' => (int)(!!$_POST['is_active'])
                    ];
                    if (empty($fields['item_key'])) { $error = 'Заполните ключ'; }
                    else {
                        if ((int)$_POST['id'] > 0) {
                            $fields['id'] = (int)$_POST['id'];
                            $stmt = $pdo->prepare("UPDATE consumables SET item_key=?, name=?, description=?, icon=?, weight=?, value=?, heal_amount=?, rad_heal=?, addiction_chance=?, boost_type=?, boost_value=?, boost_duration=?, is_active=? WHERE id=?");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['heal_amount'], $fields['rad_heal'], $fields['addiction_chance'], $fields['boost_type'], $fields['boost_value'], $fields['boost_duration'], $fields['is_active'], $fields['id']]);
                            $success = 'Расходник обновлён';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO consumables (item_key, name, description, icon, weight, value, heal_amount, rad_heal, addiction_chance, boost_type, boost_value, boost_duration, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['heal_amount'], $fields['rad_heal'], $fields['addiction_chance'], $fields['boost_type'], $fields['boost_value'], $fields['boost_duration'], $fields['is_active']]);
                            $success = 'Расходник добавлен';
                        }
                    }
                }
                break;

            // --- ЛУТ ---
            case 'loot':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM loot WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Предмет удалён';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'item_key' => trim($_POST['item_key'] ?? ''),
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'icon' => trim($_POST['icon'] ?? '📦'),
                        'weight' => (float)($_POST['weight'] ?? 0),
                        'value' => (int)($_POST['value'] ?? 0),
                        'category' => $_POST['category'] ?? 'junk',
                        'stackable' => (int)(!!$_POST['stackable']),
                        'max_stack' => (int)($_POST['max_stack'] ?? 99),
                        'is_active' => (int)(!!$_POST['is_active'])
                    ];
                    if (empty($fields['item_key'])) { $error = 'Заполните ключ'; }
                    else {
                        if ((int)$_POST['id'] > 0) {
                            $fields['id'] = (int)$_POST['id'];
                            $stmt = $pdo->prepare("UPDATE loot SET item_key=?, name=?, description=?, icon=?, weight=?, value=?, category=?, stackable=?, max_stack=?, is_active=? WHERE id=?");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['category'], $fields['stackable'], $fields['max_stack'], $fields['is_active'], $fields['id']]);
                            $success = 'Предмет обновлён';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO loot (item_key, name, description, icon, weight, value, category, stackable, max_stack, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([$fields['item_key'], $fields['name'], $fields['description'], $fields['icon'], $fields['weight'], $fields['value'], $fields['category'], $fields['stackable'], $fields['max_stack'], $fields['is_active']]);
                            $success = 'Предмет добавлен';
                        }
                    }
                }
                break;

            // --- ЛОКАЦИИ ---
            case 'locations':
                if (isset($_POST['delete'])) {
                    $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Локация удалена';
                } elseif (isset($_POST['save'])) {
                    $fields = [
                        'pos_x' => (int)($_POST['pos_x'] ?? 0),
                        'pos_y' => (int)($_POST['pos_y'] ?? 0),
                        'tile_type' => $_POST['tile_type'] ?? 'wasteland',
                        'tile_name' => trim($_POST['tile_name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'danger_level' => (int)($_POST['danger_level'] ?? 1),
                        'radiation_level' => (int)($_POST['radiation_level'] ?? 0),
                        'loot_quality' => (int)($_POST['loot_quality'] ?? 1),
                        'is_vault' => (int)(!!$_POST['is_vault']),
                        'is_dungeon' => (int)(!!$_POST['is_dungeon']),
                        'dungeon_size' => (int)($_POST['dungeon_size'] ?? 0),
                        'is_border' => (int)(!!$_POST['is_border']),
                        'border_direction' => $_POST['border_direction'] ?? null,
                        'border_message' => trim($_POST['border_message'] ?? ''),
                        'weather_resistant' => (int)(!!$_POST['weather_resistant']),
                        'scene_key' => trim($_POST['scene_key'] ?? '')
                    ];
                    if ((int)$_POST['id'] > 0) {
                        $fields['id'] = (int)$_POST['id'];
                        $stmt = $pdo->prepare("UPDATE locations SET pos_x=?, pos_y=?, tile_type=?, tile_name=?, description=?, danger_level=?, radiation_level=?, loot_quality=?, is_vault=?, is_dungeon=?, dungeon_size=?, is_border=?, border_direction=?, border_message=?, weather_resistant=?, scene_key=? WHERE id=?");
                        $stmt->execute([$fields['pos_x'], $fields['pos_y'], $fields['tile_type'], $fields['tile_name'], $fields['description'], $fields['danger_level'], $fields['radiation_level'], $fields['loot_quality'], $fields['is_vault'], $fields['is_dungeon'], $fields['dungeon_size'], $fields['is_border'], $fields['border_direction'], $fields['border_message'], $fields['weather_resistant'], $fields['scene_key'], $fields['id']]);
                        $success = 'Локация обновлена';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO locations (pos_x, pos_y, tile_type, tile_name, description, danger_level, radiation_level, loot_quality, is_vault, is_dungeon, dungeon_size, is_border, border_direction, border_message, weather_resistant, scene_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute([$fields['pos_x'], $fields['pos_y'], $fields['tile_type'], $fields['tile_name'], $fields['description'], $fields['danger_level'], $fields['radiation_level'], $fields['loot_quality'], $fields['is_vault'], $fields['is_dungeon'], $fields['dungeon_size'], $fields['is_border'], $fields['border_direction'], $fields['border_message'], $fields['weather_resistant'], $fields['scene_key']]);
                        $success = 'Локация добавлена';
                    }
                }
                break;

            // --- ПОЛЬЗОВАТЕЛИ ---
            case 'users':
                if (isset($_POST['toggle_ban'])) {
                    $stmt = $pdo->prepare("UPDATE players SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Статус пользователя изменён';
                } elseif (isset($_POST['change_role'])) {
                    $newRole = $_POST['role'] === 'admin' ? 'admin' : 'player';
                    $stmt = $pdo->prepare("UPDATE players SET role = ? WHERE id = ?");
                    $stmt->execute([$newRole, (int)$_POST['id']]);
                    $success = 'Роль изменена на ' . $newRole;
                } elseif (isset($_POST['reset_password'])) {
                    $newPass = bin2hex(random_bytes(4));
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE players SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, (int)$_POST['id']]);
                    $success = 'Пароль сброшен. Новый: ' . $newPass;
                } elseif (isset($_POST['delete_user'])) {
                    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ? AND id != ?");
                    $stmt->execute([(int)$_POST['id'], $adminId]);
                    $success = 'Пользователь удалён';
                }
                break;

            // --- НАСТРОЙКИ ---
            case 'settings':
                if (isset($_POST['save_setting'])) {
                    $key = trim($_POST['setting_key'] ?? '');
                    $val = trim($_POST['setting_value'] ?? '');
                    $cat = trim($_POST['setting_category'] ?? 'general');
                    $desc = trim($_POST['setting_desc'] ?? '');
                    if (!empty($key)) {
                        $stmt = $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value, category, description) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=?, category=?, description=?");
                        $stmt->execute([$key, $val, $cat, $desc, $val, $cat, $desc]);
                        $success = 'Настройка сохранена';
                    }
                } elseif (isset($_POST['delete_setting'])) {
                    $stmt = $pdo->prepare("DELETE FROM game_settings WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $success = 'Настройка удалена';
                }
                break;

            // --- ВЫХОД ---
            case 'logout':
                session_destroy();
                header('Location: admin_login.php');
                exit;
        }
    } catch (Exception $e) {
        $error = 'Ошибка: ' . $e->getMessage();
    }
}

// Логирование действий
function logAction($action, $table, $recordId) {
    global $pdo, $adminId;
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $action, $table, $recordId, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    } catch (Exception $e) {}
}

// Получение данных для редактирования
$editData = null;
if ($id > 0 && in_array($action, ['monsters', 'weapons', 'armors', 'consumables', 'loot', 'locations'])) {
    $table = rtrim($action, 's'); // monsters -> monster... нет, лучше явно
    $map = [
        'monsters' => 'monsters', 'weapons' => 'weapons', 'armors' => 'armors',
        'consumables' => 'consumables', 'loot' => 'loot', 'locations' => 'locations'
    ];
    $tbl = $map[$action] ?? $action;
    $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

// Получение списков для таблиц
$items = [];
switch ($action) {
    case 'monsters': $items = $pdo->query("SELECT * FROM monsters ORDER BY id DESC LIMIT 50")->fetchAll(); break;
    case 'weapons': $items = $pdo->query("SELECT * FROM weapons ORDER BY id DESC LIMIT 50")->fetchAll(); break;
    case 'armors': $items = $pdo->query("SELECT * FROM armors ORDER BY id DESC LIMIT 50")->fetchAll(); break;
    case 'consumables': $items = $pdo->query("SELECT * FROM consumables ORDER BY id DESC LIMIT 50")->fetchAll(); break;
    case 'loot': $items = $pdo->query("SELECT * FROM loot ORDER BY id DESC LIMIT 50")->fetchAll(); break;
    case 'locations': $items = $pdo->query("SELECT * FROM locations ORDER BY id DESC LIMIT 100")->fetchAll(); break;
    case 'users': $items = $pdo->query("SELECT id, username, email, role, is_active, created_at, updated_at FROM players ORDER BY id DESC LIMIT 100")->fetchAll(); break;
    case 'settings': $items = $pdo->query("SELECT * FROM game_settings ORDER BY category, setting_key")->fetchAll(); break;
    case 'logs': $items = $pdo->query("SELECT l.*, p.username FROM admin_logs l LEFT JOIN players p ON l.admin_id = p.id ORDER BY l.created_at DESC LIMIT 100")->fetchAll(); break;
}

// Статистика для дашборда
$stats = [];
if ($action === 'dashboard') {
    $stats['players'] = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
    $stats['players_online'] = 0; // Можно добавить таблицу сессий позже
    $stats['monsters'] = $pdo->query("SELECT COUNT(*) FROM monsters WHERE is_active=1")->fetchColumn();
    $stats['weapons'] = $pdo->query("SELECT COUNT(*) FROM weapons WHERE is_active=1")->fetchColumn();
    $stats['armors'] = $pdo->query("SELECT COUNT(*) FROM armors WHERE is_active=1")->fetchColumn();
    $stats['consumables'] = $pdo->query("SELECT COUNT(*) FROM consumables WHERE is_active=1")->fetchColumn();
    $stats['loot'] = $pdo->query("SELECT COUNT(*) FROM loot WHERE is_active=1")->fetchColumn();
    $stats['locations'] = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    $stats['logs'] = $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Fallout: Пустоши</title>
    <style>
        :root {
            --ios-bg: #F2F2F7;
            --ios-card: #FFFFFF;
            --ios-blue: #007AFF;
            --ios-blue-hover: #0056CC;
            --ios-green: #34C759;
            --ios-red: #FF3B30;
            --ios-orange: #FF9500;
            --ios-purple: #AF52DE;
            --ios-text: #1C1C1E;
            --ios-gray: #8E8E93;
            --ios-border: #E5E5EA;
            --ios-input-bg: #F2F2F7;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', Roboto, sans-serif;
            background: var(--ios-bg); color: var(--ios-text); display: flex; min-height: 100vh;
        }
        
        /* Боковое меню */
        .sidebar {
            width: var(--sidebar-width); background: var(--ios-card); border-right: 1px solid var(--ios-border);
            display: flex; flex-direction: column; position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header {
            padding: 24px 20px; border-bottom: 1px solid var(--ios-border);
        }
        .sidebar-header h2 {
            font-size: 22px; font-weight: 700; color: var(--ios-text); display: flex; align-items: center; gap: 10px;
        }
        .sidebar-header .admin-name {
            font-size: 13px; color: var(--ios-gray); margin-top: 4px; font-weight: 500;
        }
        .nav-section { padding: 16px 0; }
        .nav-label {
            padding: 0 20px; font-size: 11px; font-weight: 600; color: var(--ios-gray);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .nav-item {
            display: flex; align-items: center; padding: 12px 20px; color: var(--ios-text);
            text-decoration: none; font-size: 15px; font-weight: 500; transition: background 0.15s; gap: 12px;
        }
        .nav-item:hover { background: var(--ios-input-bg); }
        .nav-item.active {
            background: var(--ios-blue); color: white; font-weight: 600;
        }
        .nav-item .icon { font-size: 20px; width: 24px; text-align: center; }
        .nav-item .badge {
            margin-left: auto; background: var(--ios-gray); color: white;
            font-size: 11px; padding: 2px 6px; border-radius: 10px; font-weight: 600;
        }
        .nav-item.active .badge { background: rgba(255,255,255,0.3); }
        .sidebar-footer {
            margin-top: auto; padding: 16px 20px; border-top: 1px solid var(--ios-border);
        }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 12px;
            background: var(--ios-red); color: white; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600; cursor: pointer; width: 100%;
        }
        .logout-btn:hover { background: #D70015; }

        /* Основной контент */
        .main {
            flex: 1; margin-left: var(--sidebar-width); padding: 32px; max-width: 1200px;
        }
        .page-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
        }
        .page-header h1 { font-size: 28px; font-weight: 700; }
        .page-header .subtitle { color: var(--ios-gray); font-size: 15px; margin-top: 4px; }
        
        /* Карточки */
        .card {
            background: var(--ios-card); border-radius: 16px; padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 24px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;
        }
        .card-header h3 { font-size: 18px; font-weight: 600; }
        
        /* Сетка дашборда */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--ios-card); border-radius: 16px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card .label { font-size: 13px; color: var(--ios-gray); font-weight: 500; margin-bottom: 8px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: var(--ios-text); }
        .stat-card.blue .value { color: var(--ios-blue); }
        .stat-card.green .value { color: var(--ios-green); }
        .stat-card.red .value { color: var(--ios-red); }
        .stat-card.orange .value { color: var(--ios-orange); }

        /* Таблицы */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 12px; font-weight: 600; color: var(--ios-gray); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--ios-border); }
        td { padding: 14px 12px; font-size: 14px; border-bottom: 1px solid var(--ios-border); vertical-align: middle; }
        tr:hover td { background: var(--ios-input-bg); }
        .actions { display: flex; gap: 8px; }
        .btn {
            padding: 8px 14px; font-size: 13px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-blue { background: var(--ios-blue); color: white; }
        .btn-green { background: var(--ios-green); color: white; }
        .btn-red { background: var(--ios-red); color: white; }
        .btn-orange { background: var(--ios-orange); color: white; }
        .btn-ghost { background: var(--ios-input-bg); color: var(--ios-text); }
        .btn-ghost:hover { background: var(--ios-border); }
        .btn-sm { padding: 6px 10px; font-size: 12px; }
        
        /* Формы */
        .form-group { margin-bottom: 16px; }
        label.form-label { display: block; font-size: 13px; font-weight: 600; color: var(--ios-gray); margin-bottom: 6px; }
        input.form-input, select.form-select, textarea.form-textarea {
            width: 100%; padding: 12px 16px; font-size: 15px; border: 1px solid var(--ios-border);
            border-radius: 12px; background: var(--ios-input-bg); color: var(--ios-text); transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 4px rgba(0,122,255,0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--ios-blue); }
        
        /* Уведомления */
        .alert {
            padding: 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500;
            display: <?= ($error || $success) ? 'block' : 'none' ?>;
        }
        .alert-error { background: rgba(255,59,48,0.08); color: var(--ios-red); border: 1px solid rgba(255,59,48,0.2); }
        .alert-success { background: rgba(52,199,89,0.08); color: var(--ios-green); border: 1px solid rgba(52,199,89,0.2); }
        
        /* Статус-бейджи */
        .badge-status { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-active { background: rgba(52,199,89,0.1); color: var(--ios-green); }
        .badge-inactive { background: rgba(255,59,48,0.1); color: var(--ios-red); }
        .badge-admin { background: rgba(0,122,255,0.1); color: var(--ios-blue); }
        .badge-player { background: rgba(142,142,147,0.1); color: var(--ios-gray); }
        
        /* Адаптив */
        @media (max-width: 900px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header .admin-name, .nav-label, .nav-item span, .badge { display: none; }
            .nav-item { justify-content: center; padding: 16px; }
            .main { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>

<!-- БОКОВОЕ МЕНЮ -->
<nav class="sidebar">
    <div class="sidebar-header">
        <h2>☢️ Админ</h2>
        <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
    </div>
    
    <div class="nav-section">
        <div class="nav-label">Обзор</div>
        <a href="?action=dashboard" class="nav-item <?= $action === 'dashboard' ? 'active' : '' ?>">
            <span class="icon">📊</span> <span>Дашборд</span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-label">Контент</div>
        <a href="?action=monsters" class="nav-item <?= $action === 'monsters' ? 'active' : '' ?>">
            <span class="icon">👹</span> <span>Монстры</span>
            <span class="badge"><?= $stats['monsters'] ?? '' ?></span>
        </a>
        <a href="?action=weapons" class="nav-item <?= $action === 'weapons' ? 'active' : '' ?>">
            <span class="icon">🔫</span> <span>Оружие</span>
        </a>
        <a href="?action=armors" class="nav-item <?= $action === 'armors' ? 'active' : '' ?>">
            <span class="icon">🛡️</span> <span>Броня</span>
        </a>
        <a href="?action=consumables" class="nav-item <?= $action === 'consumables' ? 'active' : '' ?>">
            <span class="icon">💊</span> <span>Расходники</span>
        </a>
        <a href="?action=loot" class="nav-item <?= $action === 'loot' ? 'active' : '' ?>">
            <span class="icon">📦</span> <span>Лут</span>
        </a>
        <a href="?action=locations" class="nav-item <?= $action === 'locations' ? 'active' : '' ?>">
            <span class="icon">🗺️</span> <span>Локации</span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-label">Система</div>
        <a href="?action=users" class="nav-item <?= $action === 'users' ? 'active' : '' ?>">
            <span class="icon">👥</span> <span>Пользователи</span>
        </a>
        <a href="?action=settings" class="nav-item <?= $action === 'settings' ? 'active' : '' ?>">
            <span class="icon">⚙️</span> <span>Настройки</span>
        </a>
        <a href="?action=logs" class="nav-item <?= $action === 'logs' ? 'active' : '' ?>">
            <span class="icon">📜</span> <span>Логи</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <form method="POST" action="?action=logout" onsubmit="return confirm('Выйти из админ-панели?')">
            <button type="submit" class="logout-btn">🚪 Выйти</button>
        </form>
    </div>
</nav>

<!-- ОСНОВНОЙ КОНТЕНТ -->
<main class="main">
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- ДАШБОРД -->
    <?php if ($action === 'dashboard'): ?>
        <div class="page-header">
            <div>
                <h1>Панель управления</h1>
                <div class="subtitle">Обзор состояния игрового мира</div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="label">👥 Игроки</div>
                <div class="value"><?= $stats['players'] ?></div>
            </div>
            <div class="stat-card green">
                <div class="label">👹 Монстры</div>
                <div class="value"><?= $stats['monsters'] ?></div>
            </div>
            <div class="stat-card orange">
                <div class="label">🔫 Оружие</div>
                <div class="value"><?= $stats['weapons'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">🛡️ Броня</div>
                <div class="value"><?= $stats['armors'] ?></div>
            </div>
            <div class="stat-card red">
                <div class="label">💊 Расходники</div>
                <div class="value"><?= $stats['consumables'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">📦 Лут</div>
                <div class="value"><?= $stats['loot'] ?></div>
            </div>
            <div class="stat-card blue">
                <div class="label">🗺️ Локации</div>
                <div class="value"><?= $stats['locations'] ?></div>
            </div>
            <div class="stat-card green">
                <div class="label">📜 Логи</div>
                <div class="value"><?= $stats['logs'] ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>🚀 Быстрые действия</h3>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="?action=monsters" class="btn btn-blue">+ Добавить монстра</a>
                <a href="?action=weapons" class="btn btn-green">+ Добавить оружие</a>
                <a href="?action=locations" class="btn btn-orange">+ Создать локацию</a>
                <a href="?action=users" class="btn btn-ghost">👥 Управление игроками</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>📜 Последние действия</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Время</th><th>Админ</th><th>Действие</th><th>Таблица</th><th>ID</th></tr></thead>
                    <tbody>
                        <?php
                        $logs = $pdo->query("SELECT l.created_at, p.username, l.action, l.table_name, l.record_id FROM admin_logs l LEFT JOIN players p ON l.admin_id = p.id ORDER BY l.created_at DESC LIMIT 10")->fetchAll();
                        foreach ($logs as $l): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
                                <td><?= htmlspecialchars($l['username'] ?: 'Система') ?></td>
                                <td><?= htmlspecialchars($l['action']) ?></td>
                                <td><?= htmlspecialchars($l['table_name']) ?></td>
                                <td><?= $l['record_id'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- МОНСТРЫ / ОРУЖИЕ / БРОНЯ / РАСХОДНИКИ / ЛУТ -->
    <?php elseif (in_array($action, ['monsters', 'weapons', 'armors', 'consumables', 'loot'])): ?>
        <div class="page-header">
            <div>
                <h1><?= ['monsters'=>'👹 Монстры','weapons'=>'🔫 Оружие','armors'=>'🛡️ Броня','consumables'=>'💊 Расходники','loot'=>'📦 Лут'][$action] ?></h1>
                <div class="subtitle"><?= $editData ? 'Редактирование' : 'Управление контентом' ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><?= $editData ? 'Редактировать запись' : 'Добавить новую запись' ?></h3>
                <?php if ($editData): ?><a href="?action=<?= $action ?>" class="btn btn-ghost btn-sm">← Назад к списку</a><?php endif; ?>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $editData['id'] ?? 0 ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ключ (item_key / monster_key) *</label>
                        <input class="form-input" name="<?= $action === 'monsters' ? 'monster_key' : 'item_key' ?>" value="<?= $editData['monster_key'] ?? $editData['item_key'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Название *</label>
                        <input class="form-input" name="name" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" required>
                    </div>
                </div>

                <?php if ($action === 'monsters'): ?>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Уровень</label><input class="form-input" type="number" name="level" value="<?= $editData['level'] ?? 1 ?>"></div>
                        <div class="form-group"><label class="form-label">Скорость</label><input class="form-input" type="number" name="speed" value="<?= $editData['speed'] ?? 5 ?>"></div>
                        <div class="form-group">
                            <label class="form-label">Статус</label>
                            <select class="form-select" name="status">
                                <?php foreach(['wandering'=>'Бродячий','guarding'=>'Охранник','patrol'=>'Патруль','boss'=>'Босс'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= ($editData['status'] ?? 'wandering') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">HP</label><input class="form-input" type="number" name="base_hp" value="<?= $editData['base_hp'] ?? 10 ?>"></div>
                        <div class="form-group"><label class="form-label">Броня</label><input class="form-input" type="number" name="base_armor" value="<?= $editData['base_armor'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Урон</label><input class="form-input" type="number" name="base_dmg" value="<?= $editData['base_dmg'] ?? 1 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">XP награда</label><input class="form-input" type="number" name="xp_reward" value="<?= $editData['xp_reward'] ?? 5 ?>"></div>
                        <div class="form-group"><label class="form-label">Вес спавна</label><input class="form-input" type="number" name="spawn_weight" value="<?= $editData['spawn_weight'] ?? 10 ?>"></div>
                        <div class="form-group"><label class="form-label">Среда обитания</label><input class="form-input" name="habitat" value="<?= htmlspecialchars($editData['habitat'] ?? 'wasteland') ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Таблица лута (JSON)</label><textarea class="form-textarea" name="loot_table" rows="2"><?= htmlspecialchars($editData['loot_table'] ?? '') ?></textarea></div>
                <?php elseif ($action === 'weapons'): ?>
                    <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" name="description"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Иконка</label><input class="form-input" name="icon" value="<?= htmlspecialchars($editData['icon'] ?? '🔫') ?>"></div>
                        <div class="form-group"><label class="form-label">Вес</label><input class="form-input" type="number" step="0.1" name="weight" value="<?= $editData['weight'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Цена</label><input class="form-input" type="number" name="value" value="<?= $editData['value'] ?? 0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Кость урона (dX)</label><input class="form-input" type="number" name="dmg_dice" value="<?= $editData['dmg_dice'] ?? 4 ?>"></div>
                        <div class="form-group"><label class="form-label">Модификатор урона</label><input class="form-input" type="number" name="dmg_mod" value="<?= $editData['dmg_mod'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Шанс крита %</label><input class="form-input" type="number" step="0.1" name="crit_chance" value="<?= $editData['crit_chance'] ?? 5.0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Тип дальности</label>
                            <select class="form-select" name="range_type">
                                <?php foreach(['melee'=>'Ближний','short','medium','long'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= ($editData['range_type'] ?? 'melee') === $k ? 'selected' : '' ?>><?= is_int($k) ? $v : ($k === 'melee' ? 'Ближний' : $k) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Мин. Сила</label><input class="form-input" type="number" name="min_str" value="<?= $editData['min_str'] ?? 0 ?>"></div>
                    </div>
                <?php elseif ($action === 'armors'): ?>
                    <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" name="description"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Иконка</label><input class="form-input" name="icon" value="<?= htmlspecialchars($editData['icon'] ?? '🛡️') ?>"></div>
                        <div class="form-group"><label class="form-label">Вес</label><input class="form-input" type="number" step="0.1" name="weight" value="<?= $editData['weight'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Цена</label><input class="form-input" type="number" name="value" value="<?= $editData['value'] ?? 0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Защита</label><input class="form-input" type="number" name="defense" value="<?= $editData['defense'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Сопр. радиации</label><input class="form-input" type="number" name="rad_resistance" value="<?= $editData['rad_resistance'] ?? 0 ?>"></div>
                        <div class="form-group">
                            <label class="form-label">Слот</label>
                            <select class="form-select" name="slot_type">
                                <?php foreach(['head'=>'Голова','tors'=>'Торс','arms'=>'Руки','legs'=>'Ноги','full_body'=>'Всё тело'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= ($editData['slot_type'] ?? 'tors') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Мин. Сила</label><input class="form-input" type="number" name="min_str" value="<?= $editData['min_str'] ?? 0 ?>"></div>
                <?php elseif ($action === 'consumables'): ?>
                    <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" name="description"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Иконка</label><input class="form-input" name="icon" value="<?= htmlspecialchars($editData['icon'] ?? '💊') ?>"></div>
                        <div class="form-group"><label class="form-label">Вес</label><input class="form-input" type="number" step="0.1" name="weight" value="<?= $editData['weight'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Цена</label><input class="form-input" type="number" name="value" value="<?= $editData['value'] ?? 0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Лечение HP</label><input class="form-input" type="number" name="heal_amount" value="<?= $editData['heal_amount'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Лечение RAD</label><input class="form-input" type="number" name="rad_heal" value="<?= $editData['rad_heal'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Шанс зависимости %</label><input class="form-input" type="number" step="0.1" name="addiction_chance" value="<?= $editData['addiction_chance'] ?? 0.0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Тип буста</label><input class="form-input" name="boost_type" value="<?= htmlspecialchars($editData['boost_type'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">Значение буста</label><input class="form-input" type="number" name="boost_value" value="<?= $editData['boost_value'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Длительность</label><input class="form-input" type="number" name="boost_duration" value="<?= $editData['boost_duration'] ?? 0 ?>"></div>
                    </div>
                <?php elseif ($action === 'loot'): ?>
                    <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" name="description"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Иконка</label><input class="form-input" name="icon" value="<?= htmlspecialchars($editData['icon'] ?? '📦') ?>"></div>
                        <div class="form-group"><label class="form-label">Вес</label><input class="form-input" type="number" step="0.1" name="weight" value="<?= $editData['weight'] ?? 0 ?>"></div>
                        <div class="form-group"><label class="form-label">Цена</label><input class="form-input" type="number" name="value" value="<?= $editData['value'] ?? 0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Категория</label>
                            <select class="form-select" name="category">
                                <?php foreach(['junk'=>'Хлам','key_item'=>'Ключевой','quest'=>'Квестовый','component','currency'=>'Валюта'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= ($editData['category'] ?? 'junk') === $k ? 'selected' : '' ?>><?= is_int($k) ? $v : $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Макс. стак</label><input class="form-input" type="number" name="max_stack" value="<?= $editData['max_stack'] ?? 99 ?>"></div>
                    </div>
                    <div class="checkbox-group"><input type="checkbox" name="stackable" <?= ($editData['stackable'] ?? 1) ? 'checked' : '' ?>> <label>Стакается</label></div>
                <?php endif; ?>

                <div class="checkbox-group"><input type="checkbox" name="is_active" <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?>> <label>Активно</label></div>

                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" name="save" class="btn btn-blue"><?= $editData ? '💾 Сохранить' : '➕ Добавить' ?></button>
                    <?php if ($editData): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить запись?')">
                            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                            <button type="submit" name="delete" class="btn btn-red">🗑️ Удалить</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!$editData): ?>
        <div class="card">
            <div class="card-header"><h3>Список записей</h3></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Ключ</th><th>Название</th>
                            <?php if ($action === 'monsters'): ?><th>Ур.</th><th>HP</th><th>Урон</th><th>Вес</th><?php endif; ?>
                            <?php if ($action === 'weapons'): ?><th>Урон</th><th>Тип</th><?php endif; ?>
                            <?php if ($action === 'armors'): ?><th>Защита</th><th>Слот</th><?php endif; ?>
                            <?php if ($action === 'consumables'): ?><th>Лечение</th><?php endif; ?>
                            <?php if ($action === 'loot'): ?><th>Категория</th><th>Стак</th><?php endif; ?>
                            <th>Статус</th><th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><code><?= htmlspecialchars($item['monster_key'] ?? $item['item_key'] ?? '') ?></code></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <?php if ($action === 'monsters'): ?><td><?= $item['level'] ?></td><td><?= $item['base_hp'] ?></td><td><?= $item['base_dmg'] ?></td><td><?= $item['spawn_weight'] ?></td><?php endif; ?>
                                <?php if ($action === 'weapons'): ?><td><?= $item['dmg_dice'] ?>d<?= $item['dmg_mod'] ?></td><td><?= $item['range_type'] ?></td><?php endif; ?>
                                <?php if ($action === 'armors'): ?><td><?= $item['defense'] ?></td><td><?= $item['slot_type'] ?></td><?php endif; ?>
                                <?php if ($action === 'consumables'): ?><td><?= $item['heal_amount'] ?> HP / <?= $item['rad_heal'] ?> RAD</td><?php endif; ?>
                                <?php if ($action === 'loot'): ?><td><?= $item['category'] ?></td><td><?= $item['max_stack'] ?></td><?php endif; ?>
                                <td><span class="badge-status <?= $item['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $item['is_active'] ? 'Активно' : 'Откл.' ?></span></td>
                                <td class="actions">
                                    <a href="?action=<?= $action ?>&id=<?= $item['id'] ?>" class="btn btn-blue btn-sm">✏️</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?')">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="delete" class="btn btn-red btn-sm">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <!-- ЛОКАЦИИ -->
    <?php elseif ($action === 'locations'): ?>
        <div class="page-header">
            <div><h1>🗺️ Локации</h1><div class="subtitle"><?= $editData ? 'Редактирование' : 'Управление картой мира' ?></div></div>
        </div>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="id" value="<?= $editData['id'] ?? 0 ?>">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">X</label><input class="form-input" type="number" name="pos_x" value="<?= $editData['pos_x'] ?? 0 ?>"></div>
                    <div class="form-group"><label class="form-label">Y</label><input class="form-input" type="number" name="pos_y" value="<?= $editData['pos_y'] ?? 0 ?>"></div>
                    <div class="form-group">
                        <label class="form-label">Тип тайла</label>
                        <select class="form-select" name="tile_type">
                            <?php foreach(['wasteland','city','dungeon','radzone','vault','mountain'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($editData['tile_type'] ?? 'wasteland') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Название</label><input class="form-input" name="tile_name" value="<?= htmlspecialchars($editData['tile_name'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Описание</label><input class="form-input" name="description" value="<?= htmlspecialchars($editData['description'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Опасность (1-10)</label><input class="form-input" type="number" name="danger_level" min="1" max="10" value="<?= $editData['danger_level'] ?? 1 ?>"></div>
                    <div class="form-group"><label class="form-label">Радиация (0-100)</label><input class="form-input" type="number" name="radiation_level" min="0" max="100" value="<?= $editData['radiation_level'] ?? 0 ?>"></div>
                    <div class="form-group"><label class="form-label">Качество лута (1-5)</label><input class="form-input" type="number" name="loot_quality" min="1" max="5" value="<?= $editData['loot_quality'] ?? 1 ?>"></div>
                </div>
                <div class="form-row">
                    <div class="checkbox-group"><input type="checkbox" name="is_vault" <?= ($editData['is_vault'] ?? 0) ? 'checked' : '' ?>> <label>Убежище</label></div>
                    <div class="checkbox-group"><input type="checkbox" name="is_dungeon" <?= ($editData['is_dungeon'] ?? 0) ? 'checked' : '' ?>> <label>Данж</label></div>
                    <div class="form-group"><label class="form-label">Размер данжа</label><input class="form-input" type="number" name="dungeon_size" value="<?= $editData['dungeon_size'] ?? 0 ?>"></div>
                </div>
                <div class="form-row">
                    <div class="checkbox-group"><input type="checkbox" name="is_border" <?= ($editData['is_border'] ?? 0) ? 'checked' : '' ?>> <label>Граница мира</label></div>
                    <div class="form-group"><label class="form-label">Направление границы</label><select class="form-select" name="border_direction"><option value="">Нет</option><?php foreach(['north','south','east','west'] as $d): ?><option value="<?= $d ?>" <?= ($editData['border_direction'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Сообщение границы</label><input class="form-input" name="border_message" value="<?= htmlspecialchars($editData['border_message'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="checkbox-group"><input type="checkbox" name="weather_resistant" <?= ($editData['weather_resistant'] ?? 0) ? 'checked' : '' ?>> <label>Защита от погоды</label></div>
                    <div class="form-group"><label class="form-label">Ключ сцены</label><input class="form-input" name="scene_key" value="<?= htmlspecialchars($editData['scene_key'] ?? '') ?>"></div>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" name="save" class="btn btn-blue"><?= $editData ? '💾 Сохранить' : '➕ Добавить локацию' ?></button>
                    <?php if ($editData): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?')">
                            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                            <button type="submit" name="delete" class="btn btn-red">🗑️ Удалить</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($editData): ?><a href="?action=locations" class="btn btn-ghost">← Назад</a><?php endif; ?>
                </div>
            </form>
        </div>
        <?php if (!$editData): ?>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Координаты</th><th>Тип</th><th>Название</th><th>Опасн.</th><th>Рад.</th><th>Флаги</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $loc): ?>
                            <tr>
                                <td><?= $loc['id'] ?></td>
                                <td><?= $loc['pos_x'] ?>, <?= $loc['pos_y'] ?></td>
                                <td><code><?= $loc['tile_type'] ?></code></td>
                                <td><?= htmlspecialchars($loc['tile_name'] ?: '—') ?></td>
                                <td><?= $loc['danger_level'] ?></td>
                                <td><?= $loc['radiation_level'] ?></td>
                                <td>
                                    <?php if ($loc['is_vault']): ?>🏠<?php endif; ?>
                                    <?php if ($loc['is_dungeon']): ?>⚔️<?php endif; ?>
                                    <?php if ($loc['is_border']): ?>🚫<?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="?action=locations&id=<?= $loc['id'] ?>" class="btn btn-blue btn-sm">✏️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <!-- ПОЛЬЗОВАТЕЛИ -->
    <?php elseif ($action === 'users'): ?>
        <div class="page-header">
            <div><h1>👥 Пользователи</h1><div class="subtitle">Управление аккаунтами и правами</div></div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Статус</th><th>Создан</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                <td><span class="badge-status <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-player' ?>"><?= $u['role'] ?></span></td>
                                <td><span class="badge-status <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $u['is_active'] ? 'Активен' : 'Забанен' ?></span></td>
                                <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                                <td class="actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="toggle_ban" class="btn <?= $u['is_active'] ? 'btn-orange' : 'btn-green' ?> btn-sm"><?= $u['is_active'] ? '🚫 Бан' : '✅ Разбан' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'player' : 'admin' ?>">
                                        <button type="submit" name="change_role" class="btn btn-blue btn-sm"><?= $u['role'] === 'admin' ? '👤 В игроки' : '👑 В админы' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Сбросить пароль?')">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="reset_password" class="btn btn-ghost btn-sm">🔑 Сброс пароля</button>
                                    </form>
                                    <?php if ($u['id'] != $adminId): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить пользователя навсегда?')">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-red btn-sm">🗑️ Удалить</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- НАСТРОЙКИ -->
    <?php elseif ($action === 'settings'): ?>
        <div class="page-header">
            <div><h1>⚙️ Настройки игры</h1><div class="subtitle">Ключ-значение для баланса и конфигурации</div></div>
        </div>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="save_setting" value="1">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Ключ</label><input class="form-input" name="setting_key" placeholder="например: xp_multiplier"></div>
                    <div class="form-group"><label class="form-label">Значение</label><input class="form-input" name="setting_value" placeholder="1.5"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Категория</label><input class="form-input" name="setting_category" placeholder="combat"></div>
                    <div class="form-group"><label class="form-label">Описание</label><input class="form-input" name="setting_desc" placeholder="Множитель опыта"></div>
                </div>
                <button type="submit" class="btn btn-blue">➕ Добавить/Обновить</button>
            </form>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ключ</th><th>Значение</th><th>Категория</th><th>Описание</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $s): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($s['setting_key']) ?></code></td>
                                <td><?= htmlspecialchars($s['setting_value']) ?></td>
                                <td><?= $s['category'] ?></td>
                                <td><?= htmlspecialchars($s['description'] ?: '—') ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?')">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" name="delete_setting" class="btn btn-red btn-sm">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ЛОГИ -->
    <?php elseif ($action === 'logs'): ?>
        <div class="page-header">
            <div><h1>📜 Логи администратора</h1><div class="subtitle">История всех действий в панели</div></div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Время</th><th>Админ</th><th>Действие</th><th>Таблица</th><th>Запись ID</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $l): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td><?= htmlspecialchars($l['username'] ?: '—') ?></td>
                                <td><code><?= htmlspecialchars($l['action']) ?></code></td>
                                <td><?= htmlspecialchars($l['table_name']) ?></td>
                                <td><?= $l['record_id'] ?></td>
                                <td><?= htmlspecialchars($l['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
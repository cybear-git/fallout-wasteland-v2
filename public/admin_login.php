<?php
session_name('fw_adm_ssid');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true
]);

require_once __DIR__ . '/../config/database.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT p.id, p.username, p.password_hash 
                FROM players p
                JOIN roles r ON r.id = p.role_id
                WHERE p.username = ? AND r.role_name = 'admin' AND p.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$user['id'];
                $_SESSION['admin_name'] = $user['username'];
                header('Location: admin.php');
                exit;
            } else {
                $error = 'Неверные учётные данные или недостаточно прав';
            }
        } catch (Exception $e) {
            $error = 'Ошибка подключения к базе данных';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в Админ-панель</title>
    <style>
        :root {
            --ios-bg: #F2F2F7;
            --ios-card: #FFFFFF;
            --ios-blue: #007AFF;
            --ios-blue-hover: #0056CC;
            --ios-red: #FF3B30;
            --ios-text: #1C1C1E;
            --ios-gray: #8E8E93;
            --ios-border: #E5E5EA;
            --ios-input-bg: #F2F2F7;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', Roboto, sans-serif;
            background: var(--ios-bg);
            color: var(--ios-text);
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px;
        }
        .login-card {
            background: var(--ios-card);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            width: 100%; max-width: 400px;
            padding: 40px 30px;
        }
        h1 {
            text-align: center; font-size: 24px; font-weight: 700;
            margin-bottom: 8px; color: var(--ios-text);
        }
        .subtitle { text-align: center; color: var(--ios-gray); font-size: 15px; margin-bottom: 30px; }
        .input-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: var(--ios-gray); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        input {
            width: 100%; padding: 16px; font-size: 17px; border: 1px solid var(--ios-border);
            border-radius: 12px; background: var(--ios-input-bg); color: var(--ios-text);
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 4px rgba(0,122,255,0.1); }
        .btn {
            width: 100%; padding: 16px; font-size: 17px; font-weight: 600;
            background: var(--ios-blue); color: white; border: none;
            border-radius: 14px; cursor: pointer; transition: background 0.2s; margin-top: 10px;
        }
        .btn:hover { background: var(--ios-blue-hover); }
        .btn:active { transform: scale(0.98); }
        .error {
            color: var(--ios-red); font-size: 14px; text-align: center;
            margin-bottom: 20px; padding: 12px; background: rgba(255,59,48,0.08);
            border-radius: 12px; display: <?= $error ? 'block' : 'none' ?>;
        }
        .back { display: block; text-align: center; margin-top: 20px; color: var(--ios-blue); text-decoration: none; font-size: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align:center;font-size:48px;margin-bottom:10px;">🛡️</div>
        <h1>Админ-панель</h1>
        <p class="subtitle">Управление миром Fallout</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="input-group">
                <label>Логин администратора</label>
                <input type="text" name="username" placeholder="admin" required autocomplete="off">
            </div>
            <div class="input-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Войти в систему</button>
        </form>
        <a href="/index.php" class="back">← Вернуться к игре</a>
    </div>
</body>
</html>
<?php
/**
 * LOGOUT.PHP — Выход из игры
 */
declare(strict_types=1);
session_start();

// Уничтожаем сессию
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выход - Fallout RPG</title>
    <link rel="stylesheet" href="assets/css/game.css">
    <style>
        .logout-message {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: #0a0f0a;
            border: 3px solid #3d5c3d;
            border-radius: 10px;
            text-align: center;
            font-family: 'Courier New', monospace;
        }
        .logout-icon { font-size: 64px; margin-bottom: 20px; }
        .logout-text { color: #66ff66; font-size: 18px; line-height: 1.6; margin-bottom: 30px; }
        .btn-login {
            background: #2d5a2d;
            color: #66ff66;
            border: 2px solid #4caf50;
            padding: 12px 30px;
            text-decoration: none;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s;
        }
        .btn-login:hover { background: #3d7a3d; }
    </style>
</head>
<body class="pipboy-body">
    <div class="container">
        <div class="logout-message">
            <div class="logout-icon">🚪</div>
            <div class="logout-text">
                <p>Вы покинули Пустошь.</p>
                <p style="margin-top: 15px;">До скорой встречи, Избранный!</p>
            </div>
            <a href="login.php" class="btn-login">Вернуться на главную</a>
        </div>
    </div>
</body>
</html>

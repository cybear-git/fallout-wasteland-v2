<?php
/**
 * AUTH_FORM.PHP — Форма входа/регистрации
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в Пустошь - Fallout RPG</title>
    <link rel="stylesheet" href="assets/css/game.css">
    <style>
        .auth-container {
            max-width: 500px;
            margin: 80px auto;
            padding: 40px;
            background: #0a0f0a;
            border: 3px solid #3d5c3d;
            border-radius: 10px;
            box-shadow: 0 0 30px rgba(61, 92, 61, 0.5);
        }
        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #3d5c3d;
        }
        .auth-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background: #1a2f1a;
            color: #66ff66;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s;
        }
        .auth-tab:first-child {
            border-right: 1px solid #3d5c3d;
        }
        .auth-tab:hover, .auth-tab.active {
            background: #2d5a2d;
        }
        .auth-form {
            display: none;
        }
        .auth-form.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #66ff66;
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background: #0a0f0a;
            border: 2px solid #3d5c3d;
            color: #66ff66;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4caf50;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: #2d5a2d;
            color: #66ff66;
            border: 2px solid #4caf50;
            font-family: 'Courier New', monospace;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: #3d7a3d;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.6);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }
        .alert-error {
            background: #2f1a1a;
            border: 1px solid #ff6666;
            color: #ff9999;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-area h1 {
            color: #4caf50;
            font-family: 'Courier New', monospace;
            font-size: 32px;
            text-shadow: 0 0 20px rgba(76, 175, 80, 0.8);
        }
        .logo-area p {
            color: #66ff66;
            font-size: 14px;
        }
    </style>
</head>
<body class="pipboy-body">
    <div class="container">
        <div class="auth-container">
            <div class="logo-area">
                <h1>☢️ FALLOUT RPG</h1>
                <p>Добро пожаловать в Пустошь, Избранный</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="auth-tabs">
                <div class="auth-tab active" onclick="switchTab('login')">🔐 Вход</div>
                <div class="auth-tab" onclick="switchTab('register')">📝 Регистрация</div>
            </div>

            <!-- Форма входа -->
            <form method="POST" class="auth-form active" id="login-form">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Имя пользователя:</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Пароль:</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit">Войти в Пустошь</button>
            </form>

            <!-- Форма регистрации -->
            <form method="POST" class="auth-form" id="register-form">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Придумайте имя:</label>
                    <input type="text" name="username" required autocomplete="username" minlength="3" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Пароль:</label>
                    <input type="password" name="password" required autocomplete="new-password" minlength="6">
                </div>
                <div class="form-group">
                    <label>Подтверждение пароля:</label>
                    <input type="password" name="password_confirm" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-submit">Создать персонажа</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Переключаем табы
            document.querySelectorAll('.auth-tab').forEach((tab, index) => {
                tab.classList.toggle('active', (tabName === 'login' && index === 0) || (tabName === 'register' && index === 1));
            });
            
            // Переключаем формы
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(tabName + '-form').classList.add('active');
        }

        // Валидация паролей при регистрации
        document.getElementById('register-form').addEventListener('submit', function(e) {
            const pwd = this.querySelector('[name="password"]').value;
            const confirm = this.querySelector('[name="password_confirm"]').value;
            
            if (pwd !== confirm) {
                e.preventDefault();
                alert('❌ Пароли не совпадают!');
            }
        });

        console.log('🟢 Терминал авторизации загружен');
    </script>
</body>
</html>

<?php
/**
 * LOGIN.PHP — Обработчик входа пользователей
 * Fallout RPG Wasteland
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';

// Если уже авторизован — редирект в игру
if (!empty($_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Базовая валидация
    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        try {
            $pdo = getDbConnection();
            
            // Поиск пользователя (включая админов)
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, is_active, role
                FROM players
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Проверка активности аккаунта
                if ($user['is_active'] == 0) {
                    $error = 'Аккаунт заблокирован. Обратитесь к администратору.';
                } else {
                    // Регенерация сессии для защиты от session fixation
                    session_regenerate_id(true);
                    
                    // Установка сессионных переменных
                    $_SESSION['player_id'] = (int)$user['id'];
                    $_SESSION['player_name'] = $user['username'];
                    $_SESSION['player_role'] = $user['role'];
                    
                    // Логирование входа (опционально)
                    error_log("Player login: {$user['username']} (ID: {$user['id']})");
                    
                    // Редирект в игру
                    header('Location: index.php');
                    exit;
                }
            } else {
                // Общая ошибка для безопасности (не раскрывать, что именно неверно)
                $error = 'Неверное имя пользователя или пароль';
            }
        } catch (PDOException $e) {
            error_log("Login DB error: " . $e->getMessage());
            $error = 'Ошибка подключения к базе данных';
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Произошла непредвиденная ошибка';
        }
    }
}

// Включаем форму авторизации с ошибкой
include __DIR__ . '/includes/auth_form.php';
?>

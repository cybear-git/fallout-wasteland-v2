<?php
/**
 * REGISTER.PHP — Обработчик регистрации пользователей
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

$errors = [];

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Валидация данных
    if (strlen($username) < 3) {
        $errors[] = 'Имя пользователя должно быть не менее 3 символов';
    }
    if (strlen($username) > 50) {
        $errors[] = 'Имя пользователя не может превышать 50 символов';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Имя может содержать только буквы, цифры и подчёркивание';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не менее 6 символов';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Пароли не совпадают';
    }
    
    // Если нет ошибок валидации — создаём пользователя
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            
            // Проверка существования пользователя с таким именем
            $stmt = $pdo->prepare("SELECT id FROM players WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Пользователь с таким именем уже существует';
            } else {
                // Хеширование пароля (современный алгоритм bcrypt)
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Создание записи в БД
                $stmt = $pdo->prepare("
                    INSERT INTO players (username, password_hash, role, is_active, created_at, updated_at)
                    VALUES (?, ?, 'player', 1, NOW(), NOW())
                ");
                $stmt->execute([$username, $passwordHash]);
                
                $playerId = (int)$pdo->lastInsertId();
                
                // Автоматический вход после регистрации
                session_regenerate_id(true);
                $_SESSION['player_id'] = $playerId;
                $_SESSION['player_name'] = $username;
                $_SESSION['player_role'] = 'player';
                
                // Логирование регистрации
                error_log("New player registered: {$username} (ID: {$playerId})");
                
                // Редирект в игру
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Register DB error: " . $e->getMessage());
            $errors[] = 'Ошибка подключения к базе данных';
        } catch (Exception $e) {
            error_log("Register error: " . $e->getMessage());
            $errors[] = 'Произошла непредвиденная ошибка';
        }
    }
}

// Передаём ошибки в форму
include __DIR__ . '/includes/auth_form.php';
?>

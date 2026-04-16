<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/../config/database.php';

if (!empty($_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'Ошибка безопасности: неверный CSRF-токен.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Заполните все поля';
        } else {
            try {
                $pdo = getDbConnection();

                $stmt = $pdo->prepare("
                    SELECT p.id, p.username, p.password_hash, p.is_active
                    FROM players p
                    WHERE p.username = ?
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['is_active'] == 0) {
                        $errors[] = 'Аккаунт заблокирован.';
                    } else {
                        session_regenerate_id(true);
                        regenerateCsrfToken();

                        $_SESSION['player_id'] = (int)$user['id'];
                        $_SESSION['player_name'] = $user['username'];
                        $_SESSION['player_role'] = 'player';

                        error_log("Player login: {$user['username']} (ID: {$user['id']})");

                        header('Location: index.php');
                        exit;
                    }
                } else {
                    $errors[] = 'Неверное имя пользователя или пароль';
                }
            } catch (PDOException $e) {
                error_log("Login DB error: " . $e->getMessage());
                $errors[] = 'Ошибка подключения к базе данных. Проверьте настройки.';
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = 'Произошла непредвиденная ошибка.';
            }
        }
    }
}

include __DIR__ . '/../includes/auth_form.php';

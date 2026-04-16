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
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (strlen($username) < 3) $errors[] = 'Имя пользователя от 3 символов';
        if (strlen($username) > 50) $errors[] = 'Имя не более 50 символов';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'Только буквы, цифры и _';
        if (strlen($password) < 6) $errors[] = 'Пароль от 6 символов';
        if ($password !== $passwordConfirm) $errors[] = 'Пароли не совпадают';

        if (empty($errors)) {
            try {
                $pdo = getDbConnection();

                $stmt = $pdo->prepare("SELECT id FROM players WHERE username = ?");
                $stmt->execute([$username]);

                if ($stmt->fetch()) {
                    $errors[] = 'Пользователь уже существует';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO players (username, password_hash, is_active, created_at, updated_at)
                        VALUES (?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([$username, $passwordHash]);
                    $playerId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare("
                        INSERT INTO characters (player_id, name, status, level, hp, max_hp, caps, pos_x, pos_y)
                        VALUES (?, ?, 'alive', 1, 100, 100, 50, 0, 0)
                    ");
                    $stmt->execute([$playerId, htmlspecialchars($username)]);

                    $spawnX = 0;
                    $spawnY = 0;
                    $stmtNode = $pdo->prepare("SELECT id FROM map_nodes WHERE pos_x = ? AND pos_y = ?");
                    $stmtNode->execute([$spawnX, $spawnY]);
                    if (!$stmtNode->fetch()) {
                        $stmtInsertNode = $pdo->prepare("
                            INSERT INTO map_nodes (pos_x, pos_y, location_type_id) 
                            SELECT ?, ?, lt.id 
                            FROM location_types lt WHERE lt.type_name = 'Пустошь' LIMIT 1
                        ");
                        $stmtInsertNode->execute([$spawnX, $spawnY]);
                    }

                    session_regenerate_id(true);
                    regenerateCsrfToken();
                    $_SESSION['player_id'] = $playerId;
                    $_SESSION['player_name'] = $username;
                    $_SESSION['player_role'] = 'player';

                    error_log("New player registered: {$username} (ID: {$playerId})");

                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Register DB error: " . $e->getMessage());
                $errors[] = 'Ошибка базы данных при регистрации.';
            } catch (Exception $e) {
                error_log("Register error: " . $e->getMessage());
                $errors[] = 'Произошла непредвиденная ошибка.';
            }
        }
    }
}

include __DIR__ . '/../includes/auth_form.php';

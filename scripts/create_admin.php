<?php
/**
 * Скрипт создания администратора
 * Запуск: php scripts/create_admin.php
 */

require_once __DIR__ . '/../config/database.php';

$username = 'admin';
$password = 'admin123';

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = getDbConnection();
    
    // Проверяем существование роли admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'admin' LIMIT 1");
    $stmt->execute();
    $adminRole = $stmt->fetch();
    
    if (!$adminRole) {
        // Создаем роль admin если её нет
        $stmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES ('admin')");
        $stmt->execute();
        $adminRoleId = (int)$pdo->lastInsertId();
    } else {
        $adminRoleId = (int)$adminRole['id'];
    }
    
    // Проверяем существование пользователя
    $stmt = $pdo->prepare("SELECT id FROM players WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Обновляем пароль и роль
        $stmt = $pdo->prepare("UPDATE players SET password_hash = ?, role_id = ? WHERE username = ?");
        $stmt->execute([$hash, $adminRoleId, $username]);
        echo "Админ обновлен!\n";
    } else {
        // Создаем нового админа
        $stmt = $pdo->prepare("INSERT INTO players (username, password_hash, role_id, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$username, $hash, $adminRoleId]);
        
        $playerId = (int)$pdo->lastInsertId();
        
        // Создаем персонажа для админа
        $stmt = $pdo->prepare("INSERT INTO characters (player_id, name, level, hp, max_hp, pos_x, pos_y) VALUES (?, ?, 1, 100, 100, 0, 0)");
        $stmt->execute([$playerId, $username]);
        
        echo "Админ создан!\n";
    }
    
    echo "Логин: $username\n";
    echo "Пароль: $password\n";
    echo "\nURL входа в админку: http://localhost:1317/admin_login.php\n";
    
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

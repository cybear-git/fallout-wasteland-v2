<?php
require_once __DIR__ . '/../config/db.php'; // Подключение к БД

$username = 'admin';
$email = 'admin@fallout.local';
$password = 'admin123';
$role_id = 2; // Admin

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO players (username, email, password_hash, role_id, hp_max, hp_current, ap_max, ap_current) 
        VALUES (:username, :email, :hash, :role, 100, 100, 100, 100)
        ON DUPLICATE KEY UPDATE password_hash = :hash_up
    ");
    
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':hash' => $hash,
        ':hash_up' => $hash,
        ':role' => $role_id
    ]);

    echo "Админ создан/обновлен!\nЛогин: $username\nПароль: $password\n";
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
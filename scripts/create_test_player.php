<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

// Проверим, есть ли тестовый игрок
$stmt = $pdo->prepare("SELECT * FROM players WHERE username = ?");
$stmt->execute(['testuser']);
$existing = $stmt->fetch();

if ($existing) {
    echo "Тестовый игрок уже существует (ID: {$existing['id']})\n";
} else {
    // Создаем тестового игрока
    $stmt = $pdo->prepare("
        INSERT INTO players (username, password_hash, role_id, is_active, created_at) 
        VALUES (?, ?, 1, 1, NOW())
    ");
    $stmt->execute(['testuser', password_hash('test123', PASSWORD_DEFAULT)]);
    $playerId = $pdo->lastInsertId();
    echo "Создан игрок: testuser (ID: $playerId)\n";

    // Создаем персонажа
    $stmt = $pdo->prepare("
        INSERT INTO characters (player_id, name, status, level, hp, max_hp, caps, strength, perception, endurance, charisma, intelligence, agility, luck, pos_x, pos_y)
        VALUES (?, ?, 'alive', 1, 100, 100, 50, 6, 5, 5, 5, 5, 6, 5, 40, 25)
    ");
    $stmt->execute([$playerId, 'Тестовый Выживший']);
    echo "Создан персонаж: Тестовый Выживший\n";
}

// Добавим снаряжение
$stmt = $pdo->prepare("SELECT id FROM characters WHERE player_id = ?");
$stmt->execute([$playerId ?? $existing['id']]);
$charId = $stmt->fetchColumn();

if ($charId) {
    // Проверим, есть ли уже снаряжение
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE character_id = $charId");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO inventory (character_id, item_type, item_key, quantity, condition_pct) VALUES (?, 'consumable', 'stimpak', 3, 100.0)");
        $stmt->execute([$charId]);
        echo "Добавлены стимпаки\n";
    }
}

echo "\nДанные для входа:\n";
echo "Логин: testuser\n";
echo "Пароль: test123\n";
echo "URL: http://localhost:1317/\n";

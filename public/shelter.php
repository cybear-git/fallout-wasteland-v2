<?php
/**
 * SHELTER.PHP — Убежище, диалог с Хранителем, старт игры
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';

// Проверка авторизации
if (!isset($_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$playerId = $_SESSION['player_id'];
$errors = [];
$success = false;
$keeperData = null;
$playerData = null;

try {
    // Получаем данные игрока
    $stmt = $pdo->prepare("
        SELECT p.*, mn.x, mn.y, mn.tile_type, mn.id as current_node_id
        FROM players p
        LEFT JOIN map_nodes mn ON p.current_map_node_id = mn.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $playerId]);
    $playerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playerData) {
        throw new Exception('Игрок не найден');
    }

    // Проверяем, находится ли игрок в убежище
    if ($playerData['tile_type'] !== 'vault_ext') {
        // Игрок уже вышел из убежища
        header('Location: index.php');
        exit;
    }

    // Получаем данные Хранителя этого убежища
    $stmt = $pdo->prepare("
        SELECT vk.*, mn.name as vault_name
        FROM vault_keepers vk
        JOIN map_nodes mn ON vk.vault_id = mn.id
        WHERE vk.vault_id = :node_id
    ");
    $stmt->execute([':node_id' => $playerData['current_node_id']]);
    $keeperData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Обработка кнопки "Выйти в Пустошь"
    if (isset($_POST['exit_vault']) && $keeperData) {
        $pdo->beginTransaction();

        // 1. Выдаем снаряжение
        $starterItems = [
            ['name' => 'Комбинезон Убежища', 'type' => 'armor', 'value' => 0, 'weight' => 2, 'description' => 'Сине-желтый комбинезон с номером твоего убежища.', 'armor_class' => 5],
            ['name' => 'Стимпак', 'type' => 'consumable', 'value' => 25, 'weight' => 0.5, 'description' => 'Восстанавливает здоровье.', 'effect_value' => 30],
            ['name' => 'Стимпак', 'type' => 'consumable', 'value' => 25, 'weight' => 0.5, 'description' => 'Восстанавливает здоровье.', 'effect_value' => 30],
            ['name' => 'Стимпак', 'type' => 'consumable', 'value' => 25, 'weight' => 0.5, 'description' => 'Восстанавливает здоровье.', 'effect_value' => 30],
            ['name' => 'Антирадин', 'type' => 'consumable', 'value' => 15, 'weight' => 0.3, 'description' => 'Снижает уровень радиации.', 'effect_value' => -20],
        ];

        $stmtInsertItem = $pdo->prepare("
            INSERT INTO player_items (player_id, item_id, quantity)
            SELECT :player_id, id, :qty FROM items WHERE name = :name
            ON DUPLICATE KEY UPDATE quantity = quantity + :qty2
        ");

        foreach ($starterItems as $item) {
            // Проверяем, существует ли предмет в базе items
            $stmtCheck = $pdo->prepare("SELECT id FROM items WHERE name = :name");
            $stmtCheck->execute([':name' => $item['name']]);
            $existingItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$existingItem) {
                // Создаем предмет
                $stmtCreate = $pdo->prepare("
                    INSERT INTO items (name, type, value, weight, description, armor_class, effect_value)
                    VALUES (:name, :type, :value, :weight, :desc, :ac, :eff)
                ");
                $stmtCreate->execute([
                    ':name' => $item['name'],
                    ':type' => $item['type'],
                    ':value' => $item['value'],
                    ':weight' => $item['weight'],
                    ':desc' => $item['description'],
                    ':ac' => $item['armor_class'] ?? null,
                    ':eff' => $item['effect_value'] ?? null
                ]);
                $itemId = $pdo->lastInsertId();
            } else {
                $itemId = $existingItem['id'];
            }

            $qty = ($item['name'] === 'Комбинезон Убежища') ? 1 : 
                   (($item['name'] === 'Антирадин') ? 1 : 3);
            
            $stmtInsertItem->execute([
                ':player_id' => $playerId,
                ':name' => $item['name'],
                ':qty' => $qty,
                ':qty2' => $qty
            ]);
        }

        // 2. Добавляем крышки
        $stmtCaps = $pdo->prepare("UPDATE players SET caps = caps + :caps WHERE id = :id");
        $stmtCaps->execute([':caps' => $keeperData['starter_caps'], ':id' => $playerId]);

        // 3. Применяем бонусы Хранителя (броня + харизма)
        $stmtStats = $pdo->prepare("
            UPDATE players 
            SET base_armor = base_armor + :armor, 
                charisma = charisma + :cha,
                current_mission = :mission,
                last_safe_node_id = :safe_node
            WHERE id = :id
        ");
        $stmtStats->execute([
            ':armor' => $keeperData['bonus_armor'],
            ':cha' => $keeperData['bonus_charisma'],
            ':mission' => $keeperData['mission_text'],
            ':safe_node' => $playerData['current_node_id'],
            ':id' => $playerId
        ]);

        // 4. Активируем Пип-бой (флаг)
        $stmtPipboy = $pdo->prepare("UPDATE players SET pipboy_active = 1 WHERE id = :id");
        $stmtPipboy->execute([':id' => $playerId]);

        // 5. Записываем лог
        $stmtLog = $pdo->prepare("
            INSERT INTO admin_logs (action, details, created_at) 
            VALUES ('player_exit_vault', :details, NOW())
        ");
        $stmtLog->execute([':details' => json_encode([
            'player_id' => $playerId,
            'vault_id' => $playerData['current_node_id'],
            'keeper_id' => $keeperData['id']
        ])]);

        $pdo->commit();
        $success = true;

        // Перенаправление в игру через 2 секунды
        header('Refresh: 2; URL=index.php');
    }

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Убежище - Fallout RPG</title>
    <link rel="stylesheet" href="assets/css/game.css">
    <style>
        .vault-terminal {
            background: #0a0f0a;
            border: 3px solid #3d5c3d;
            border-radius: 10px;
            padding: 30px;
            max-width: 800px;
            margin: 40px auto;
            font-family: 'Courier New', monospace;
            box-shadow: 0 0 30px rgba(61, 92, 61, 0.5);
        }
        .terminal-header {
            color: #4caf50;
            border-bottom: 2px solid #3d5c3d;
            padding-bottom: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .keeper-dialogue {
            color: #66ff66;
            line-height: 1.8;
            margin-bottom: 30px;
            min-height: 150px;
        }
        .keeper-name {
            color: #fff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .starter-gear {
            background: #1a2f1a;
            border: 1px solid #3d5c3d;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .gear-list {
            color: #9ccc9c;
            list-style: none;
            padding: 0;
        }
        .gear-list li {
            padding: 5px 0;
            border-bottom: 1px dashed #3d5c3d;
        }
        .gear-list li:last-child {
            border-bottom: none;
        }
        .btn-exit {
            background: #2d5a2d;
            color: #66ff66;
            border: 2px solid #4caf50;
            padding: 15px 40px;
            font-size: 18px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s;
            display: block;
            margin: 30px auto;
        }
        .btn-exit:hover {
            background: #3d7a3d;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.6);
        }
        .btn-exit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .success-message {
            color: #66ff66;
            text-align: center;
            font-size: 20px;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .mission-text {
            color: #ffcc00;
            font-style: italic;
            margin-top: 15px;
            padding: 10px;
            border-left: 3px solid #ffcc00;
        }
    </style>
</head>
<body class="pipboy-body">
    <div class="container">
        <?php if ($success): ?>
            <!-- Успешный выход -->
            <div class="vault-terminal">
                <div class="terminal-header">
                    <h1>🟢 СИСТЕМА АКТИВИРОВАНА</h1>
                </div>
                <div class="success-message">
                    <p>Пип-бой активирован.</p>
                    <p>Снаряжение получено.</p>
                    <p>Добро пожаловать в Пустошь, Избранный!</p>
                    <p style="margin-top: 20px; font-size: 14px;">Перенаправление...</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Диалог с Хранителем -->
            <div class="vault-terminal">
                <div class="terminal-header">
                    <h1>🏠 ТЕРМИНАЛ УБЕЖИЩА</h1>
                    <p><?= htmlspecialchars($keeperData['vault_name'] ?? 'Неизвестно') ?></p>
                </div>

                <?php if ($keeperData): ?>
                    <div class="keeper-dialogue">
                        <div class="keeper-name">👤 <?= htmlspecialchars($keeperData['keeper_name']) ?>:</div>
                        <p><?= nl2br(htmlspecialchars($keeperData['greeting_text'])) ?></p>
                        
                        <div class="mission-text">
                            <strong>📜 ЗАДАНИЕ:</strong><br>
                            <?= nl2br(htmlspecialchars($keeperData['mission_text'])) ?>
                        </div>

                        <div class="starter-gear">
                            <h3 style="color: #fff; margin-top: 0;">📦 ВЫДАЧА СНАРЯЖЕНИЯ:</h3>
                            <ul class="gear-list">
                                <li>✅ Комбинезон Убежища (Броня +5)</li>
                                <li>✅ Стимпак x3</li>
                                <li>✅ Антирадин x1</li>
                                <li>✅ Крышки: <?= $keeperData['starter_caps'] ?></li>
                                <li>✅ Бонус Хранителя: Броня +2, Харизма +1</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <button type="submit" name="exit_vault" class="btn-exit">
                                🚪 Выйти в Пустошь
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="keeper-dialogue">
                        <p style="color: #ff6666;">Ошибка: Хранитель не найден. Обратитесь к администратору.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Звуковые эффекты (опционально)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🏠 Терминал убежища загружен');
        });
    </script>
</body>
</html>

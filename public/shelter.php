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
    // Получаем данные персонажа
    $stmt = $pdo->prepare("
        SELECT c.*, p.username,
               mn.id as map_node_id, mn.pos_x, mn.pos_y,
               lt.type_name as tile_type
        FROM characters c
        JOIN players p ON p.id = c.player_id
        LEFT JOIN map_nodes mn ON mn.pos_x = c.pos_x AND mn.pos_y = c.pos_y
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        WHERE c.player_id = :id
    ");
    $stmt->execute([':id' => $playerId]);
    $playerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playerData) {
        throw new Exception('Персонаж не найден');
    }

    // Проверяем, находится ли игрок в убежище
    if ($playerData['tile_type'] !== 'vault_ext' && $playerData['tile_type'] !== 'vault') {
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
    $stmt->execute([':node_id' => $playerData['map_node_id']]);
    $keeperData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Обработка кнопки "Выйти в Пустошь"
    if (isset($_POST['exit_vault']) && $keeperData) {
        $pdo->beginTransaction();

        $characterId = $playerData['id'];

        // 1. Выдаем снаряжение в инвентарь
        $starterItems = [
            ['name' => 'Комбинезон Убежища', 'type' => 'armor'],
            ['name' => 'Стимпак', 'type' => 'consumable'],
            ['name' => 'Стимпак', 'type' => 'consumable'],
            ['name' => 'Стимпак', 'type' => 'consumable'],
            ['name' => 'Антирадин', 'type' => 'consumable'],
        ];

        foreach ($starterItems as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO inventory (character_id, item_type, item_key, quantity)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $qty = ($item['name'] === 'Комбинезон Убежища') ? 1 : 
                   (($item['name'] === 'Антирадин') ? 1 : 3);
            $stmt->execute([$characterId, $item['type'], $item['name'], $qty]);
        }

        // 2. Добавляем крышки персонажу
        $starterCaps = $keeperData['starter_caps'] ?? 50;
        $stmtCaps = $pdo->prepare("UPDATE characters SET caps = caps + ? WHERE id = ?");
        $stmtCaps->execute([$starterCaps, $characterId]);

        // 3. Применяем бонусы Хранителя (харизма)
        $bonusCha = $keeperData['bonus_charisma'] ?? 0;
        if ($bonusCha > 0) {
            $stmtStats = $pdo->prepare("UPDATE characters SET charisma = charisma + ? WHERE id = ?");
            $stmtStats->execute([$bonusCha, $characterId]);
        }

        // 4. Записываем лог
        $stmtLog = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, table_name, details, ip_address) 
            VALUES (0, 'player_exit_vault', 'characters', ?, '127.0.0.1')
        ");
        $stmtLog->execute([json_encode([
            'player_id' => $playerId,
            'character_id' => $characterId,
            'vault_id' => $playerData['map_node_id'],
            'caps_given' => $starterCaps
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

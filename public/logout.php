<?php
/**
 * LOGOUT.PHP — Выход из игры с проверкой боя и данжа
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$playerId = $_SESSION['player_id'];
$logoutReason = 'manual';
$survivalCheckSuccess = null;
$wasInCombat = 0;
$wasInDungeon = 0;
$message = '';
$messageType = 'info';

try {
    $pdo->beginTransaction();

    // Получаем текущее состояние игрока
    $stmt = $pdo->prepare("
        SELECT p.*, 
               cb.id as combat_id,
               dn.id as dungeon_id
        FROM players p
        LEFT JOIN combats cb ON p.id = cb.player_id AND cb.status = 'active'
        LEFT JOIN dungeons dn ON p.current_dungeon_id = dn.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        throw new Exception('Игрок не найден');
    }

    $wasInCombat = $player['combat_id'] ? 1 : 0;
    $wasInDungeon = $player['current_dungeon_id'] ? 1 : 0;

    // ЛОГИКА ПОБЕГА ИЗ БОЯ
    if ($wasInCombat) {
        // Проверка на побег (50% шанс + модификаторы от Удачи и Харизмы)
        $luck = $player['luck'] ?? 5;
        $charisma = $player['charisma'] ?? 5;
        $escapeChance = 50 + ($luck * 3) + ($charisma * 2);
        $roll = rand(1, 100);
        
        $survivalCheckSuccess = ($roll <= $escapeChance) ? 1 : 0;

        if ($survivalCheckSuccess) {
            // Успешный побег
            $message = "🏃 Вам удалось сбежать из боя! Вы затаились в руинах и пережили опасность.";
            $messageType = 'success';
            
            // Завершаем бой без наград/потерь
            $stmtEndCombat = $pdo->prepare("UPDATE combats SET status = 'escaped' WHERE id = :combat_id");
            $stmtEndCombat->execute([':combat_id' => $player['combat_id']]);
        } else {
            // Проваленный побег - игрок теряет почти всё HP
            $message = "💀 Бой прерван! Вы получили критические ранения и потеряли сознание. Чудом выжив, вы ползете в укрытие...";
            $messageType = 'error';
            
            // Оставляем 1 HP
            $stmtHP = $pdo->prepare("UPDATE players SET current_hp = 1 WHERE id = :id");
            $stmtHP->execute([':id' => $playerId]);
            
            // Завершаем бой как поражение
            $stmtEndCombat = $pdo->prepare("UPDATE combats SET status = 'fled_defeat' WHERE id = :combat_id");
            $stmtEndCombat->execute([':combat_id' => $player['combat_id']]);
        }
    }

    // ЛОГИКА ВЫХОДА ИЗ ДАНЖА
    if ($wasInDungeon && !$wasInCombat) {
        // Если игрок в данже, но не в бою - просто выходим
        $message = "🚪 Вы покинули подземелье и вернулись на поверхность.";
        $messageType = 'info';
        
        $stmtExitDungeon = $pdo->prepare("
            UPDATE players 
            SET current_dungeon_id = NULL,
                current_map_node_id = (
                    SELECT mn.entrance_node_id 
                    FROM dungeons d 
                    JOIN map_nodes mn ON d.entrance_node_id = mn.id 
                    WHERE d.id = :dungeon_id
                )
            WHERE id = :id
        ");
        $stmtExitDungeon->execute([
            ':dungeon_id' => $player['current_dungeon_id'],
            ':id' => $playerId
        ]);
    }

    // Если игрок был в бою И в данже одновременно (редкий случай)
    if ($wasInCombat && $wasInDungeon) {
        if (!$survivalCheckSuccess) {
            // При провале - телепорт на вход в данж с 1 HP
            $stmtTeleport = $pdo->prepare("
                UPDATE players 
                SET current_dungeon_id = NULL,
                    current_map_node_id = (
                        SELECT entrance_node_id 
                        FROM dungeons 
                        WHERE id = :dungeon_id
                    ),
                    current_hp = 1
                WHERE id = :id
            ");
            $stmtTeleport->execute([
                ':dungeon_id' => $player['current_dungeon_id'],
                ':id' => $playerId
            ]);
        } else {
            // При успехе - просто выход из данжа
            $stmtExitDungeon = $pdo->prepare("
                UPDATE players 
                SET current_dungeon_id = NULL,
                    current_map_node_id = (
                        SELECT entrance_node_id 
                        FROM dungeons 
                        WHERE id = :dungeon_id
                    )
                WHERE id = :id
            ");
            $stmtExitDungeon->execute([
                ':dungeon_id' => $player['current_dungeon_id'],
                ':id' => $playerId
            ]);
        }
    }

    // Устанавливаем оффлайн статус
    $stmtOffline = $pdo->prepare("UPDATE players SET is_online = 0 WHERE id = :id");
    $stmtOffline->execute([':id' => $playerId]);

    // Логируем сессию
    $stmtLogSession = $pdo->prepare("
        INSERT INTO player_sessions 
        (player_id, logout_time, logout_reason, was_in_combat, was_in_dungeon, survival_check_success)
        VALUES (:player_id, NOW(), :reason, :combat, :dungeon, :survival)
    ");
    $stmtLogSession->execute([
        ':player_id' => $playerId,
        ':reason' => $logoutReason,
        ':combat' => $wasInCombat,
        ':dungeon' => $wasInDungeon,
        ':survival' => $survivalCheckSuccess
    ]);

    $pdo->commit();

    // Уничтожаем сессию
    session_destroy();

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    $message = "⚠️ Ошибка при выходе: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выход - Fallout RPG</title>
    <link rel="stylesheet" href="assets/css/game.css">
    <style>
        .logout-message {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: #0a0f0a;
            border: 3px solid #3d5c3d;
            border-radius: 10px;
            text-align: center;
            font-family: 'Courier New', monospace;
        }
        .logout-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .logout-text {
            color: #66ff66;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-login {
            background: #2d5a2d;
            color: #66ff66;
            border: 2px solid #4caf50;
            padding: 12px 30px;
            text-decoration: none;
            display: inline-block;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #3d7a3d;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.6);
        }
        .status-success { color: #66ff66; }
        .status-error { color: #ff6666; }
        .status-info { color: #ffcc00; }
    </style>
</head>
<body class="pipboy-body">
    <div class="container">
        <div class="logout-message">
            <div class="logout-icon">
                <?php if ($messageType === 'success'): ?>
                    🏃
                <?php elseif ($messageType === 'error'): ?>
                    💀
                <?php else: ?>
                    🚪
                <?php endif; ?>
            </div>
            <div class="logout-text status-<?= $messageType ?>">
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
            <p style="color: #9ccc9c; font-size: 14px; margin-top: 20px;">
                Персонаж отправился на отдых. Он невидим для других игроков и монстров.
            </p>
            <a href="index.php" class="btn-login">Вернуться в игру</a>
        </div>
    </div>
</body>
</html>

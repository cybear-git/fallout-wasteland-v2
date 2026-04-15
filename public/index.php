<?php
/**
 * INDEX.PHP — Основной игровой интерфейс (Pip-Boy)
 * Движение, поиск, инвентарь, статус персонажа
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';

// Если не авторизован — редирект на форму входа
if (!isset($_SESSION['player_id'])) {
    header('Location: includes/auth_form.php');
    exit;
}

$playerId = $_SESSION['player_id'];
$message = '';
$error = '';

try {
    // Получаем данные игрока
    $stmt = $pdo->prepare("
        SELECT p.*, 
               mn.x, mn.y, mn.tile_type, mn.id as current_node_id,
               lt.name as location_name
        FROM players p
        LEFT JOIN map_nodes mn ON p.current_map_node_id = mn.id
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        session_destroy();
        header('Location: includes/auth_form.php');
        exit;
    }

    // Проверка состояния шока
    $isShocked = false;
    if ($player['shock_until'] && strtotime($player['shock_until']) > time()) {
        $isShocked = true;
        $remainingShock = ceil((strtotime($player['shock_until']) - time()) / 60);
        $message = "⚠️ СОСТОЯНИЕ ШОК! Характеристики снижены. Осталось минут: {$remainingShock}";
    }

    // Обработка действий (движение)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isShocked) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'move') {
            $dx = (int)($_POST['dx'] ?? 0);
            $dy = (int)($_POST['dy'] ?? 0);
            
            if ($dx !== 0 || $dy !== 0) {
                $newX = $player['x'] + $dx;
                $newY = $player['y'] + $dy;
                
                // Проверка границ карты (160x90)
                if ($newX < 0 || $newX >= 160 || $newY < 0 || $newY >= 90) {
                    $error = "❌ Дальше идти некуда. Там только смерть.";
                } else {
                    // Проверка непроходимых зон
                    $stmt = $pdo->prepare("
                        SELECT id, tile_type, is_passable 
                        FROM map_nodes 
                        WHERE x = :x AND y = :y
                    ");
                    $stmt->execute([':x' => $newX, ':y' => $newY]);
                    $targetNode = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$targetNode) {
                        $error = "❌ Локация не найдена.";
                    } elseif ($targetNode['tile_type'] === 'mountain' || 
                              $targetNode['tile_type'] === 'military_base' ||
                              $targetNode['is_passable'] == 0) {
                        $error = "❌ Проход заблокирован. Нужно свернуть.";
                    } else {
                        // Перемещение
                        $stmt = $pdo->prepare("
                            UPDATE players 
                            SET current_map_node_id = :node_id,
                                last_move_at = NOW(),
                                inactivity_count = 0
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            ':node_id' => $targetNode['id'],
                            ':id' => $playerId
                        ]);
                        
                        // Получение фразы
                        $stmt = $pdo->prepare("
                            SELECT text FROM location_quotes 
                            WHERE location_type = :type OR location_type IS NULL
                            ORDER BY RAND() LIMIT 1
                        ");
                        $stmt->execute([':type' => $targetNode['tile_type']]);
                        $quoteRow = $stmt->fetch(PDO::FETCH_ASSOC);
                        $message = "👣 Вы переместились. " . ($quoteRow['text'] ?? '');
                        
                        // Обновление данных игрока
                        $player['x'] = $newX;
                        $player['y'] = $newY;
                        $player['current_node_id'] = $targetNode['id'];
                        $player['tile_type'] = $targetNode['tile_type'];
                    }
                }
            }
        }
    }

    // Проверка nearby игроков (радиус 2 клетки)
    $stmt = $pdo->prepare("
        SELECT p.username, p.level, mn.x, mn.y,
               SQRT(POW(mn.x - :x, 2) + POW(mn.y - :y, 2)) as distance
        FROM players p
        JOIN map_nodes mn ON p.current_map_node_id = mn.id
        WHERE p.id != :id
          AND p.is_online = 1
          AND p.shock_until IS NULL
          AND ABS(mn.x - :x) <= 2
          AND ABS(mn.y - :y) <= 2
        ORDER BY distance
        LIMIT 5
    ");
    $stmt->execute([
        ':x' => $player['x'],
        ':y' => $player['y'],
        ':id' => $playerId
    ]);
    $nearbyPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Ошибка: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pip-Boy 3000 | Fallout Wasteland</title>
    <link rel="stylesheet" href="/assets/css/game.css">
</head>
<body class="pipboy-interface">
    <div class="crt-overlay"></div>
    
    <div class="pipboy-container">
        <!-- ВЕРХНЯЯ ПАНЕЛЬ: СТАТУС -->
        <header class="pipboy-header">
            <div class="header-left">
                <h1>📟 Pip-Boy 3000</h1>
                <span class="location-badge">
                    📍 <?= htmlspecialchars($player['location_name'] ?? 'Неизвестно') ?>
                </span>
            </div>
            <div class="header-right">
                <span class="player-name"><?= htmlspecialchars($player['username']) ?></span>
                <span class="player-level">LVL <?= $player['level'] ?></span>
                <a href="/logout.php" class="btn-logout">🚪 ВЫХОД</a>
            </div>
        </header>

        <!-- СООБЩЕНИЯ -->
        <?php if ($message): ?>
            <div class="message-box info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message-box error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($isShocked): ?>
            <div class="message-box shock">⚠️ ШОК: <?= $remainingShock ?> мин</div>
        <?php endif; ?>

        <div class="pipboy-content">
            <!-- ЛЕВАЯ КОЛОНКА: СТАТЫ И ИНФО -->
            <aside class="sidebar">
                <div class="stat-block">
                    <h3>❤️ ЗДОРОВЬЕ</h3>
                    <div class="stat-bar">
                        <div class="stat-fill hp" style="width: <?= ($player['current_hp'] / $player['max_hp']) * 100 ?>%"></div>
                        <span><?= $player['current_hp'] ?> / <?= $player['max_hp'] ?></span>
                    </div>
                </div>

                <div class="stat-block">
                    <h3>☢️ РАДИАЦИЯ</h3>
                    <div class="stat-bar">
                        <div class="stat-fill rad" style="width: <?= min(100, $player['radiation']) ?>%"></div>
                        <span><?= $player['radiation'] ?> / 1000</span>
                    </div>
                </div>

                <div class="stat-block">
                    <h3>💰 КРЫШКИ</h3>
                    <p class="stat-value"><?= $player['caps'] ?></p>
                </div>

                <div class="stat-block">
                    <h3>🎒 XP</h3>
                    <p class="stat-value"><?= $player['xp'] ?> / <?= $player['next_level_xp'] ?></p>
                </div>

                <div class="stats-grid">
                    <div class="mini-stat">
                        <span class="label">STR</span>
                        <span class="value"><?= $player['strength'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">PER</span>
                        <span class="value"><?= $player['perception'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">END</span>
                        <span class="value"><?= $player['endurance'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">CHR</span>
                        <span class="value"><?= $player['charisma'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">INT</span>
                        <span class="value"><?= $player['intelligence'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">AGI</span>
                        <span class="value"><?= $player['agility'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label">LCK</span>
                        <span class="value"><?= $player['luck'] ?></span>
                    </div>
                </div>

                <!-- ХЛАМОТРОН -->
                <div class="stat-block junk-jet-status">
                    <h3>🔫 ХЛАМОТРОН</h3>
                    <p>
                        <?php if ($player['has_junk_jet']): ?>
                            ✅ Есть | Хлам: <strong><?= $player['junk_jet_ammo'] ?></strong>
                        <?php else: ?>
                            ❌ Нет в наличии
                        <?php endif; ?>
                    </p>
                </div>
            </aside>

            <!-- ЦЕНТРАЛЬНАЯ ЧАСТЬ: КАРТА И ДЕЙСТВИЯ -->
            <main class="main-view">
                <!-- КАРТА/КОМПАС -->
                <section class="compass-section">
                    <div class="coordinates">
                        X: <strong><?= $player['x'] ?></strong> | 
                        Y: <strong><?= $player['y'] ?></strong>
                    </div>
                    
                    <div class="terrain-info">
                        <strong>Местность:</strong> <?= htmlspecialchars($player['tile_type'] ?? 'Пустошь') ?>
                    </div>

                    <!-- НАВИГАЦИЯ -->
                    <?php if (!$isShocked): ?>
                    <form method="POST" class="movement-grid">
                        <input type="hidden" name="action" value="move">
                        
                        <button type="submit" name="dx" value="0" data-dy="-1" class="btn-move">⬆️ С</button>
                        
                        <div class="move-row">
                            <button type="submit" name="dx" value="-1" data-dy="-1" class="btn-move">↖️ СВ</button>
                            <button type="submit" name="dx" value="1" data-dy="-1" class="btn-move">↗️ СЗ</button>
                        </div>
                        
                        <div class="move-row">
                            <button type="submit" name="dx" value="-1" data-dy="0" class="btn-move">⬅️ З</button>
                            <button type="submit" name="dx" value="1" data-dy="0" class="btn-move">➡️ В</button>
                        </div>
                        
                        <div class="move-row">
                            <button type="submit" name="dx" value="-1" data-dy="1" class="btn-move">↙️ ЮЗ</button>
                            <button type="submit" name="dx" value="1" data-dy="1" class="btn-move">↘️ ЮВ</button>
                        </div>
                        
                        <button type="submit" name="dx" value="0" data-dy="1" class="btn-move">⬇️ Ю</button>
                    </form>
                    <?php else: ?>
                    <div class="movement-disabled">
                        ⚠️ Движение заблокировано из-за шока
                    </div>
                    <?php endif; ?>
                </section>

                <!-- ДЕЙСТВИЯ -->
                <section class="actions-section">
                    <h3>⚡ ДЕЙСТВИЯ</h3>
                    <div class="actions-grid">
                        <button onclick="performSearch()" class="btn-action">🔍 ИСКАТЬ</button>
                        <button onclick="showInventory()" class="btn-action">🎒 ИНВЕНТАРЬ</button>
                        <button class="btn-action" disabled>⚔️ БОЙ</button>
                        <button class="btn-action" disabled>💊 ХИМИЯ</button>
                    </div>
                </section>

                <!-- NEARBY PLAYERS -->
                <?php if (!empty($nearbyPlayers)): ?>
                <section class="nearby-section">
                    <h3>👀 ЗАМЕЧЕНЫ СУЩНОСТИ</h3>
                    <ul class="nearby-list">
                        <?php foreach ($nearbyPlayers as $nearby): ?>
                            <li>
                                👤 <?= htmlspecialchars($nearby['username']) ?> 
                                (LVL <?= $nearby['level'] ?>) — 
                                <em>на расстоянии <?= round($nearby['distance'], 1) ?> кл.</em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </main>

            <!-- ПРАВАЯ КОЛОНКА: ЛОГ -->
            <aside class="log-panel">
                <h3>📜 ЖУРНАЛ</h3>
                <div class="log-entries" id="gameLog">
                    <div class="log-entry">
                        <small>[<?= date('H:i') ?>]</small>
                        Добро пожаловать в Пустошь, Странник.
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО ИНВЕНТАРЯ -->
    <div id="inventoryModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeInventory()">&times;</span>
            <h2>🎒 ИНВЕНТАРЬ</h2>
            <div id="inventoryContent">Загрузка...</div>
        </div>
    </div>

    <script>
        // Функция поиска
        function performSearch() {
            fetch('/api/search.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    logMessage(data.message);
                    if (data.quote) logMessage('📝 "' + data.quote + '"');
                    if (data.found_item) {
                        logMessage('✅ Найдено: ' + data.found_item.name + ' x' + data.found_item.quantity);
                    }
                    if (data.monster_encounter) {
                        logMessage('⚠️ ВНИМАНИЕ: Враг рядом!');
                    }
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(e => alert('Ошибка сети: ' + e));
        }

        // Показать инвентарь
        function showInventory() {
            document.getElementById('inventoryModal').style.display = 'block';
            fetch('/api/inventory.php?action=list')
                .then(r => r.json())
                .then(data => {
                    const div = document.getElementById('inventoryContent');
                    if (data.success && data.inventory.length > 0) {
                        let html = '<ul>';
                        data.inventory.forEach(item => {
                            html += '<li>' + item.name + ' x' + item.quantity;
                            if (item.equipped) html += ' [ЭКИП]';
                            html += '</li>';
                        });
                        html += '</ul>';
                        html += '<p>💰 Крышки: ' + data.caps + '</p>';
                        if (data.has_junk_jet) {
                            html += '<p>🔫 Хламотрон: хлам=' + data.junk_jet_ammo + '</p>';
                        }
                        div.innerHTML = html;
                    } else {
                        div.innerHTML = '<p>Инвентарь пуст.</p>';
                    }
                });
        }

        function closeInventory() {
            document.getElementById('inventoryModal').style.display = 'none';
        }

        function logMessage(msg) {
            const log = document.getElementById('gameLog');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = '<small>[' + new Date().toLocaleTimeString('ru-RU', {hour:'2-digit',minute:'2-digit'}) + ']</small> ' + msg;
            log.insertBefore(entry, log.firstChild);
        }

        // Закрытие по клику вне модалки
        window.onclick = function(event) {
            const modal = document.getElementById('inventoryModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>

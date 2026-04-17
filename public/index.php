<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$playerId = $_SESSION['player_id'];
$error = '';

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT p.id as player_id, p.username,
               c.id as character_id, c.name as character_name,
               c.status, c.level, c.xp, c.hp, c.max_hp, c.radiation,
               c.strength, c.perception, c.endurance, c.charisma,
               c.intelligence, c.agility, c.luck, c.caps,
               c.pos_x, c.pos_y
        FROM players p
        INNER JOIN characters c ON c.player_id = p.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $stmtLoc = $pdo->prepare("
        SELECT mn.id, l.location_key, lt.type_key, lt.type_name,
               l.danger_level, l.radiation_level, l.loot_quality
        FROM map_nodes mn
        LEFT JOIN locations l ON l.id = mn.location_id
        LEFT JOIN location_types lt ON lt.id = mn.location_type_id
        WHERE mn.pos_x = :x AND mn.pos_y = :y
        LIMIT 1
    ");
    $stmtLoc->execute([':x' => $player['pos_x'], ':y' => $player['pos_y']]);
    $node = $stmtLoc->fetch();

} catch (PDOException $e) {
    error_log("index.php PDO error: " . $e->getMessage());
    $error = "Ошибка базы данных.";
    $player = ['username' => 'Unknown', 'level' => 1, 'hp' => 0, 'max_hp' => 100,
               'caps' => 0, 'strength' => 1, 'perception' => 1, 'endurance' => 1,
               'charisma' => 1, 'intelligence' => 1, 'agility' => 1, 'luck' => 1,
               'pos_x' => 0, 'pos_y' => 0, 'radiation' => 0];
    $node = null;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>PIP-BOY 3000 | Fallout Wasteland</title>
    <link rel="stylesheet" href="/assets/css/pipboy.css">
</head>
<body class="pipboy-body">
    <div class="pipboy-container">
        <!-- ВЕРХНИЙ БАР -->
        <header class="pipboy-header">
            <div class="header-section">
                <span class="pipboy-logo">📟 PIP-BOY 3000</span>
                <span class="player-name"><?= htmlspecialchars($player['username'] ?? '???') ?></span>
            </div>
            <div class="header-section">
                <div class="stat-item">
                    <span class="stat-label">LVL</span>
                    <span class="stat-value"><?= (int)($player['level'] ?? 1) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">💰</span>
                    <span class="stat-value"><?= (int)($player['caps'] ?? 0) ?></span>
                </div>
                <div class="stat-item">
                    <a href="/logout.php" class="logout-btn" title="Выйти из сессии">🚪 ВЫХОД</a>
                </div>
            </div>
        </header>

        <!-- ОСНОВНОЙ ЭКРАН -->
        <main class="pipboy-main">
            <!-- ЛЕВАЯ ПАНЕЛЬ - НАВИГАЦИЯ -->
            <nav class="pipboy-nav">
                <button class="nav-btn" onclick="showPanel('status')" id="btn-status">
                    📊
                </button>
                <button class="nav-btn" onclick="showPanel('special')" id="btn-special">
                    🎯
                </button>
                <button class="nav-btn" onclick="showPanel('inventory')" id="btn-inventory">
                    🎒
                </button>
                <button class="nav-btn" onclick="showPanel('equip')" id="btn-equip">
                    ⚔️
                </button>
                <button class="nav-btn" onclick="showPanel('map')" id="btn-map">
                    🗺️
                </button>
                <button class="nav-btn" onclick="showPanel('quests')" id="btn-quests">
                    📋
                </button>
                <button class="nav-btn" onclick="showPanel('vendors')" id="btn-vendors">
                    💰
                </button>
                <button class="nav-btn" onclick="showPanel('crafting')" id="btn-crafting">
                    🔨
                </button>
                <button class="nav-btn" onclick="showPanel('history')" id="btn-history">
                    📜
                </button>
            </nav>

            <!-- ЦЕНТРАЛЬНАЯ ОБЛАСТЬ -->
            <div class="pipboy-center">
                <!-- ПАНЕЛИ КОНТЕНТА -->
                <div class="panel-content" id="panel-status">
                    <h2 class="panel-title">📊 СТАТУС</h2>
                    <div class="stats-grid">
                        <div class="stat-block">
                            <span class="stat-name">S</span>
                            <span class="stat-num"><?= (int)($player['strength'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">P</span>
                            <span class="stat-num"><?= (int)($player['perception'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">E</span>
                            <span class="stat-num"><?= (int)($player['endurance'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">C</span>
                            <span class="stat-num"><?= (int)($player['charisma'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">I</span>
                            <span class="stat-num"><?= (int)($player['intelligence'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">A</span>
                            <span class="stat-num"><?= (int)($player['agility'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block">
                            <span class="stat-name">L</span>
                            <span class="stat-num"><?= (int)($player['luck'] ?? 0) ?></span>
                        </div>
                        <div class="stat-block highlight">
                            <span class="stat-name">XP</span>
                            <span class="stat-num"><?= (int)($player['xp'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div class="location-info">
                        <div class="location-name" id="loc-name"><?= htmlspecialchars($node['type_name'] ?? 'Пустошь') ?></div>
                        <div class="location-coords">(<?= (int)($player['pos_x'] ?? 0) ?>, <?= (int)($player['pos_y'] ?? 0) ?>)</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-special">
                    <h2 class="panel-title">🎯 S.P.E.C.I.A.L.</h2>
                    <div class="special-list">
                        <div class="special-item"><span>S</span> Сила: <strong><?= (int)($player['strength'] ?? 0) ?></strong> — Грубая сила, переносимый вес</div>
                        <div class="special-item"><span>P</span> Восприятие: <strong><?= (int)($player['perception'] ?? 0) ?></strong> — Поиск, меткость</div>
                        <div class="special-item"><span>E</span> Выносливость: <strong><?= (int)($player['endurance'] ?? 0) ?></strong> — HP, сопротивление</div>
                        <div class="special-item"><span>C</span> Харизма: <strong><?= (int)($player['charisma'] ?? 0) ?></strong> — Торговля, диалоги</div>
                        <div class="special-item"><span>I</span> Интеллект: <strong><?= (int)($player['intelligence'] ?? 0) ?></strong> — Очки опыта, ремонт</div>
                        <div class="special-item"><span>A</span> Ловкость: <strong><?= (int)($player['agility'] ?? 0) ?></strong> — Очки действий, скрытность</div>
                        <div class="special-item"><span>L</span> Удача: <strong><?= (int)($player['luck'] ?? 0) ?></strong> — Шанс крита, находки</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-inventory">
                    <h2 class="panel-title">🎒 ИНВЕНТАРЬ</h2>
                    <div class="inventory-list" id="inventory-list">
                        <div class="loading">Загрузка...</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-equip">
                    <h2 class="panel-title">⚔️ ЭКИПИРОВКА</h2>
                    <div class="equip-slots">
                        <div class="equip-slot">
                            <span class="slot-name">🗡️ Оружие</span>
                            <span class="slot-item" id="equip-weapon">—</span>
                        </div>
                        <div class="equip-slot">
                            <span class="slot-name">🛡️ Броня</span>
                            <span class="slot-item" id="equip-armor">—</span>
                        </div>
                        <div class="equip-slot">
                            <span class="slot-name">💊 Снадобья</span>
                            <span class="slot-item" id="equip-consumable">—</span>
                        </div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-map">
                    <h2 class="panel-title">🗺️ КАРТА</h2>
                    <div class="map-grid" id="map-grid">
                        <!-- 9x9 grid will be generated here -->
                    </div>
                    <div class="map-legend">
                        <span class="legend-item">🧭 = Вы</span>
                        <span class="legend-item">💀 = Монстр</span>
                        <span class="legend-item">📦 = Лут</span>
                        <span class="legend-item">☢️ = Радиация</span>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-quests">
                    <h2 class="panel-title">📋 КВЕСТЫ</h2>
                    <div class="quests-list" id="quests-list">
                        <div class="loading">Загрузка квестов...</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-vendors">
                    <h2 class="panel-title">💰 ТОРГОВЦЫ</h2>
                    <div class="vendors-list" id="vendors-list">
                        <div class="loading">Загрузка торговцев...</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-crafting">
                    <h2 class="panel-title">🔨 КРАФТ</h2>
                    <div class="crafting-list" id="crafting-list">
                        <div class="loading">Загрузка рецептов...</div>
                    </div>
                </div>

                <div class="panel-content hidden" id="panel-history">
                    <h2 class="panel-title">📜 ЖУРНАЛ</h2>
                    <div class="history-log" id="history-log">
                        <div class="log-entry">
                            <small>[<?= date('H:i') ?>]</small> Добро пожаловать в Пустошь, Избранный.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ПРАВАЯ ПАНЕЛЬ - КРОССОВИДНОЕ УПРАВЛЕНИЕ -->
            <div class="pipboy-controls">
                <div class="crosshair">
                    <button class="cross-btn up" onclick="move(0, -1)">▲</button>
                    <div class="cross-row">
                        <button class="cross-btn left" onclick="move(-1, 0)">◄</button>
                        <button class="cross-btn center" onclick="performSearch()" title="Искать">🔍</button>
                        <button class="cross-btn right" onclick="move(1, 0)">►</button>
                    </div>
                    <button class="cross-btn down" onclick="move(0, 1)">▼</button>
                </div>
            </div>
        </main>

        <!-- НИЖНИЙ БАР СОСТОЯНИЯ -->
        <footer class="pipboy-statusbar">
            <div class="hp-container">
                <span class="bar-label">HP</span>
                <div class="hp-bar">
                    <div class="hp-fill" id="hp-fill" style="width: <?= max(0, min(100, (($player['hp'] ?? 0) / max(1, ($player['max_hp'] ?? 100))) * 100)) ?>%"></div>
                </div>
                <span class="bar-value"><?= (int)($player['hp'] ?? 0) ?>/<?= (int)($player['max_hp'] ?? 100) ?></span>
            </div>
            <div class="radiation-container">
                <span class="bar-label">☢️ RAD</span>
                <div class="rad-bar">
                    <div class="rad-fill" id="rad-fill" style="width: <?= max(0, min(100, ($player['radiation'] ?? 0))) ?>%"></div>
                </div>
                <span class="bar-value"><?= (int)($player['radiation'] ?? 0) ?></span>
            </div>
            <div class="caps-display">
                💰 <?= (int)($player['caps'] ?? 0) ?>
            </div>
        </footer>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО БОЯ -->
    <div id="combat-modal" class="modal hidden">
        <div class="combat-container">
            <div class="combat-header">
                <h2>⚔️ БОЙ</h2>
                <span id="combat-monster-name">Враг</span>
            </div>
            <div class="combat-area">
                <div class="combat-monster">
                    <div class="monster-sprite" id="monster-sprite">
                        <svg viewBox="0 0 100 100" class="monster-svg">
                            <text x="50" y="50" text-anchor="middle" dominant-baseline="middle" font-size="40">👹</text>
                        </svg>
                    </div>
                    <div class="monster-hp-bar">
                        <div class="monster-hp-fill" id="monster-hp-fill" style="width: 100%"></div>
                    </div>
                    <span class="monster-hp-text" id="monster-hp-text">100/100</span>
                </div>
                <div class="combat-log" id="combat-log">
                    <div class="combat-log-entry">Бой начался!</div>
                </div>
            </div>
            <div class="combat-actions">
                <button class="combat-btn attack" onclick="attackMonster()">💥 АТАКА</button>
                <button class="combat-btn flee" onclick="fleeCombat()">🏃 БЕЖАТЬ</button>
            </div>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО НАХОДКИ -->
    <div id="found-modal" class="modal hidden">
        <div class="found-container">
            <h2 id="found-title">📦 Находка!</h2>
            <p id="found-message"></p>
            <button class="modal-close" onclick="closeFoundModal()">ЗАКРЫТЬ</button>
        </div>
    </div>

    <script>
        let currentPanel = 'status';
        let currentCombatId = null;
        let posX = <?= (int)($player['pos_x'] ?? 0) ?>;
        let posY = <?= (int)($player['pos_y'] ?? 0) ?>;
        let playerHP = <?= (int)($player['hp'] ?? 0) ?>;
        let playerMaxHP = <?= (int)($player['max_hp'] ?? 100) ?>;

        function showPanel(name) {
            document.querySelectorAll('.panel-content').forEach(p => p.classList.add('hidden'));
            document.getElementById('panel-' + name).classList.remove('hidden');
            currentPanel = name;

            if (name === 'inventory') loadInventory();
            if (name === 'map') loadMap();
            if (name === 'quests') loadQuests();
            if (name === 'vendors') loadVendors();
            if (name === 'crafting') loadCrafting();
        }

        // Глобальное состояние боя - нельзя двигаться во время боя
        let inCombat = false;

        function move(dx, dy) {
            if (inCombat) {
                showAlert('⚠️ Нельзя уйти во время боя!');
                return;
            }
            fetch('/api/move.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ dx, dy })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    posX = data.player.pos_x;
                    posY = data.player.pos_y;
                    playerHP = data.player.hp || playerHP;
                    playerMaxHP = data.player.max_hp || playerMaxHP;
                    updateLocation(data.player);
                    addLog(data.message);
                    if (data.quote) addLog('<i>' + data.quote + '</i>');
                    if (data.monster_encounter) startCombat(data.monster_encounter);
                } else {
                    showAlert(data.error || 'Ошибка');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        function updateLocation(player) {
            document.getElementById('loc-name').textContent = player.location_name || 'Пустошь';
            document.querySelector('.location-coords').textContent = `(${player.pos_x}, ${player.pos_y})`;
            const hpPct = Math.max(0, Math.min(100, (playerHP / playerMaxHP) * 100));
            document.getElementById('hp-fill').style.width = hpPct + '%';
            document.querySelector('.bar-value').textContent = `${playerHP}/${playerMaxHP}`;
        }

        function performSearch() {
            if (inCombat) {
                showAlert('⚠️ Нельзя искать во время боя!');
                return;
            }
            fetch('/api/search.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog(data.message || 'Ничего не найдено');
                    if (data.quote) addLog('<i>' + data.quote + '</i>');
                    if (data.found_item) showFound(data.found_item);
                    if (data.monster_encounter) startCombat(data.monster_encounter);
                    if (data.xp_gained) addLog('+' + data.xp_gained + ' XP');
                } else {
                    showAlert(data.error || 'Ошибка поиска');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        function loadInventory() {
            fetch('/api/inventory.php?action=list')
                .then(r => r.json())
                .then(data => {
                    const div = document.getElementById('inventory-list');
                    if (data.success && data.inventory && data.inventory.length > 0) {
                        let html = '<ul class="inv-items">';
                        data.inventory.forEach(item => {
                            const scrapBtn = item.item_type === 'loot' || item.item_type === 'weapon' || item.item_type === 'armor' 
                                ? `<button class="scrap-btn" onclick="scrapItem(${item.id}, '${item.item_key.replace(/'/g, "\\'")}')">🗑️</button>` 
                                : '';
                            html += `<li class="inv-item">${item.name || item.item_key} <span class="inv-qty">x${item.quantity}</span> ${scrapBtn}</li>`;
                        });
                        html += '</ul>';
                        div.innerHTML = html;
                    } else {
                        div.innerHTML = '<p class="empty">Инвентарь пуст</p>';
                    }
                });
        }

        function scrapItem(itemId, itemKey) {
            if (!confirm('Удалить этот предмет?')) return;
            const formData = new FormData();
            formData.append('action', 'scrap');
            formData.append('item_id', itemId);
            fetch('/api/inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog(data.message);
                    loadInventory();
                } else {
                    showAlert(data.error || 'Ошибка удаления');
                }
            });
        }

        function loadMap() {
            fetch(`/api/map.php?x=${posX}&y=${posY}`)
                .then(r => r.json())
                .then(data => {
                    const grid = document.getElementById('map-grid');
                    if (data.nodes) {
                        let html = '';
                        for (let y = 4; y >= -4; y--) {
                            for (let x = -4; x <= 4; x++) {
                                const node = data.nodes.find(n => n.dx === x && n.dy === y);
                                const isPlayer = x === 0 && y === 0;
                                const cls = isPlayer ? 'cell-player' : (node ? 'cell-' + (node.type_key || 'wasteland') : 'cell-empty');
                                const icon = isPlayer ? '🧭' : (node ? getNodeIcon(node) : '+');
                                html += `<div class="map-cell ${cls}" title="${node ? (node.type_name || 'Пустошь') : 'Неизвестно'}">${icon}</div>`;
                            }
                        }
                        grid.innerHTML = html;
                    }
                })
                .catch(() => { grid.innerHTML = '<p>Ошибка загрузки карты</p>'; });
        }

        function getNodeIcon(node) {
            if (node.rad_level > 20) return '☢️';
            if (node.danger_level > 3) return '💀';
            if (node.is_vault) return '🏠';
            if (node.is_dungeon) return '⚔️';
            return '·';
        }

        function startCombat(monster) {
            inCombat = true;
            currentCombatId = null;
            document.getElementById('combat-monster-name').textContent = monster.name || 'Враг';
            document.getElementById('monster-hp-text').textContent = `${monster.hp}/${monster.max_hp}`;
            document.getElementById('monster-hp-fill').style.width = '100%';
            document.getElementById('combat-log').innerHTML = '<div class="combat-log-entry">⚔️ ' + (monster.name || 'Враг') + ' появился!</div>';
            document.getElementById('combat-modal').classList.remove('hidden');

            fetch('/api/combat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'start', monster_id: monster.id, location_id: 0 })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) currentCombatId = data.combat_id;
            });
        }

        function attackMonster() {
            if (!currentCombatId) return;
            fetch('/api/combat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'attack', combat_id: currentCombatId, target_index: 0 })
            })
            .then(r => r.json())
            .then(data => {
                const log = document.getElementById('combat-log');
                log.innerHTML += '<div class="combat-log-entry">' + (data.message || '') + '</div>';
                log.scrollTop = log.scrollHeight;

                if (data.enemy_hp !== undefined) {
                    const pct = Math.max(0, (data.enemy_hp / data.enemy_max_hp) * 100);
                    document.getElementById('monster-hp-fill').style.width = pct + '%';
                    document.getElementById('monster-hp-text').textContent = `${data.enemy_hp}/${data.enemy_max_hp}`;
                }

                if (data.killed) {
                    log.innerHTML += '<div class="combat-log-entry victory">🏆 Победа! ' + (data.xp_gained || 0) + ' XP</div>';
                    addLog('🏆 Победа над врагом! +' + (data.xp_gained || 0) + ' XP');
                    setTimeout(() => closeCombat(), 2000);
                } else if (data.enemy_response) {
                    log.innerHTML += '<div class="combat-log-entry damage">' + (data.enemy_response.message || '') + '</div>';
                    // Обновляем HP игрока после атаки врага
                    if (data.player_hp !== undefined) {
                        playerHP = data.player_hp;
                        playerMaxHP = data.player_max_hp || playerMaxHP;
                        const hpPct = Math.max(0, Math.min(100, (playerHP / playerMaxHP) * 100));
                        document.getElementById('hp-fill').style.width = hpPct + '%';
                        document.querySelector('.bar-value').textContent = `${playerHP}/${playerMaxHP}`;
                    }
                    if (data.enemy_response.player_dead) {
                        log.innerHTML += '<div class="combat-log-entry death">💀 Поражение...</div>';
                        addLog('💀 Вы погибли...');
                        setTimeout(() => location.reload(), 3000);
                    }
                }
            });
        }

        function fleeCombat() {
            if (!currentCombatId) return;
            fetch('/api/combat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'flee', combat_id: currentCombatId })
            })
            .then(r => r.json())
            .then(data => {
                const log = document.getElementById('combat-log');
                if (data.escaped) {
                    log.innerHTML += '<div class="combat-log-entry">🏃 Вы сбежали!</div>';
                    addLog('🏃 Вы сбежали от врага');
                    setTimeout(() => closeCombat(), 1000);
                } else {
                    log.innerHTML += '<div class="combat-log-entry damage">' + (data.message || 'Не удалось сбежать!') + '</div>';
                    // Обновляем HP игрока после неудачного побега
                    if (data.player_hp !== undefined) {
                        playerHP = data.player_hp;
                        playerMaxHP = data.player_max_hp || playerMaxHP;
                        const hpPct = Math.max(0, Math.min(100, (playerHP / playerMaxHP) * 100));
                        document.getElementById('hp-fill').style.width = hpPct + '%';
                        document.querySelector('.bar-value').textContent = `${playerHP}/${playerMaxHP}`;
                    }
                    // Проверяем смерть игрока
                    if (data.player_dead) {
                        log.innerHTML += '<div class="combat-log-entry death">💀 Вас убили при попытке побега...</div>';
                        addLog('💀 Вы погибли...');
                        setTimeout(() => location.reload(), 3000);
                    }
                }
            });
        }

        function closeCombat() {
            document.getElementById('combat-modal').classList.add('hidden');
            currentCombatId = null;
            inCombat = false;
        }

        function showFound(item) {
            const title = item.rarity ? item.rarity + ': ' + item.name : '📦 ' + item.name;
            document.getElementById('found-title').textContent = title;
            document.getElementById('found-message').textContent = 'Найдено: ' + item.name + ' x' + item.quantity;
            document.getElementById('found-modal').classList.remove('hidden');
        }

        function closeFoundModal() {
            document.getElementById('found-modal').classList.add('hidden');
        }

        function addLog(msg) {
            const log = document.getElementById('history-log');
            if (!log) return;
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = '<small>[' + new Date().toLocaleTimeString('ru-RU', {hour:'2-digit', minute:'2-digit'}) + ']</small> ' + msg;
            log.insertBefore(entry, log.firstChild);
        }

        function showAlert(msg) {
            addLog('<span style="color:#ff6666">⚠️ ' + msg + '</span>');
        }

        // Загрузка квестов
        function loadQuests() {
            fetch('/api/quests.php?action=list')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('quests-list');
                    if (!container) return;
                    
                    if (data.error) {
                        container.innerHTML = '<div class="error">' + data.error + '</div>';
                        return;
                    }
                    
                    if (!data.quests || data.quests.length === 0) {
                        container.innerHTML = '<div class="empty">Нет активных квестов</div>';
                        return;
                    }
                    
                    let html = '';
                    data.quests.forEach(q => {
                        const statusClass = q.status === 'completed' ? 'quest-completed' : (q.status === 'failed' ? 'quest-failed' : 'quest-active');
                        const statusText = q.status === 'completed' ? '✅ Завершен' : (q.status === 'failed' ? '❌ Провален' : '📝 В процессе');
                        html += `<div class="quest-item ${statusClass}">
                            <div class="quest-title">${q.title}</div>
                            <div class="quest-desc">${q.description}</div>
                            <div class="quest-status">${statusText}</div>
                            <div class="quest-progress">Прогресс: ${q.progress}/${q.target}</div>`;
                        
                        if (q.status === 'active' && q.can_complete) {
                            html += `<button class="quest-btn" onclick="completeQuest(${q.id})">🎁 Завершить</button>`;
                        }
                        if (q.status === 'available' && !q.started) {
                            html += `<button class="quest-btn" onclick="startQuest(${q.id})">▶️ Начать</button>`;
                        }
                        html += '</div>';
                    });
                    container.innerHTML = html;
                })
                .catch(e => {
                    document.getElementById('quests-list').innerHTML = '<div class="error">Ошибка загрузки квестов</div>';
                });
        }

        function startQuest(questId) {
            fetch('/api/quests.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'start', quest_id: questId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog('📋 Квест получен: ' + data.message);
                    loadQuests();
                } else {
                    showAlert(data.error || 'Ошибка начала квеста');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        function completeQuest(questId) {
            fetch('/api/quests.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'complete', quest_id: questId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog('🎉 Квест завершен! ' + data.message);
                    if (data.caps) addLog('💰 Получено крышек: ' + data.caps);
                    if (data.xp) addLog('⭐ Получено опыта: ' + data.xp);
                    loadQuests();
                } else {
                    showAlert(data.error || 'Ошибка завершения квеста');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        // Загрузка торговцев
        function loadVendors() {
            fetch('/api/vendor.php?action=list')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('vendors-list');
                    if (!container) return;
                    
                    if (data.error) {
                        container.innerHTML = '<div class="error">' + data.error + '</div>';
                        return;
                    }
                    
                    if (!data.vendors || data.vendors.length === 0) {
                        container.innerHTML = '<div class="empty">Нет доступных торговцев</div>';
                        return;
                    }
                    
                    let html = '';
                    data.vendors.forEach(v => {
                        html += `<div class="vendor-item">
                            <div class="vendor-name">${v.name}</div>
                            <div class="vendor-location">📍 ${v.location}</div>
                            <div class="vendor-gold">💰 У него: ${v.caps} крышек</div>
                            <button class="vendor-btn" onclick="openVendorTrade(${v.id})">💬 Торговать</button>
                        </div>`;
                    });
                    container.innerHTML = html;
                })
                .catch(e => {
                    document.getElementById('vendors-list').innerHTML = '<div class="error">Ошибка загрузки торговцев</div>';
                });
        }

        function openVendorTrade(vendorId) {
            Promise.all([
                fetch('/api/vendor.php?action=inventory&vendor_id=' + vendorId).then(r => r.json()),
                fetch('/api/inventory.php').then(r => r.json())
            ])
            .then(([vendorData, playerData]) => {
                if (vendorData.error) {
                    showAlert(vendorData.error);
                    return;
                }
                
                let html = '<div class="trade-container">';
                html += '<div class="trade-section"><h3>📦 Товары торговца</h3><div class="trade-items">';
                
                if (vendorData.items && vendorData.items.length > 0) {
                    vendorData.items.forEach(item => {
                        html += `<div class="trade-item">
                            <span>${item.name}</span>
                            <span class="trade-price">💰 ${item.price}</span>
                            <button onclick="buyItem(${vendorId}, ${item.item_id}, ${item.price})">Купить</button>
                        </div>`;
                    });
                } else {
                    html += '<div class="empty">Нет товаров</div>';
                }
                
                html += '</div></div><div class="trade-section"><h3>🎒 Ваш инвентарь</h3><div class="trade-items">';
                
                if (playerData.items && playerData.items.length > 0) {
                    playerData.items.filter(i => i.type_id !== 5).forEach(item => {
                        const sellPrice = Math.floor((item.base_value || 1) * 0.5);
                        html += `<div class="trade-item">
                            <span>${item.name}</span>
                            <span class="trade-price">💰 ${sellPrice}</span>
                            <button onclick="sellItem(${vendorId}, ${item.id}, ${sellPrice})">Продать</button>
                        </div>`;
                    });
                } else {
                    html += '<div class="empty">Пусто</div>';
                }
                
                html += '</div></div></div>';
                
                showTradeModal(html);
            })
            .catch(e => showAlert('Ошибка загрузки торговли'));
        }

        function buyItem(vendorId, itemId, price) {
            fetch('/api/vendor.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'buy', vendor_id: vendorId, item_id: itemId, price: price })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog('🛒 Куплено: ' + data.item_name + ' за ' + price + ' крышек');
                    openVendorTrade(vendorId);
                    updatePlayerStats(data.player);
                } else {
                    showAlert(data.error || 'Ошибка покупки');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        function sellItem(vendorId, itemId, price) {
            fetch('/api/vendor.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'sell', vendor_id: vendorId, item_id: itemId, price: price })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog('💰 Продано: ' + data.item_name + ' за ' + price + ' крышек');
                    openVendorTrade(vendorId);
                    updatePlayerStats(data.player);
                } else {
                    showAlert(data.error || 'Ошибка продажи');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        function showTradeModal(content) {
            const modal = document.createElement('div');
            modal.id = 'trade-modal';
            modal.className = 'modal';
            modal.innerHTML = `<div class="found-container">
                <h2>💱 ТОРГОВЛЯ</h2>
                ${content}
                <button class="modal-close" onclick="this.closest('.modal').remove()">ЗАКРЫТЬ</button>
            </div>`;
            document.body.appendChild(modal);
        }

        function updatePlayerStats(player) {
            if (player.caps !== undefined) {
                document.querySelectorAll('.stat-value').forEach(el => {
                    if (el.previousElementSibling?.textContent === '💰') {
                        el.textContent = player.caps;
                    }
                });
                document.querySelectorAll('.caps-display').forEach(el => {
                    el.textContent = '💰 ' + player.caps;
                });
            }
            if (player.hp !== undefined) {
                playerHP = player.hp;
                playerMaxHP = player.max_hp || playerMaxHP;
                const hpPercent = Math.max(0, Math.min(100, (playerHP / playerMaxHP) * 100));
                document.getElementById('hp-fill').style.width = hpPercent + '%';
                document.querySelector('.bar-value').textContent = playerHP + '/' + playerMaxHP;
            }
        }

        // Загрузка крафта
        function loadCrafting() {
            fetch('/api/crafting.php?action=recipes')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('crafting-list');
                    if (!container) return;
                    
                    if (data.error) {
                        container.innerHTML = '<div class="error">' + data.error + '</div>';
                        return;
                    }
                    
                    if (!data.recipes || data.recipes.length === 0) {
                        container.innerHTML = '<div class="empty">Нет доступных рецептов</div>';
                        return;
                    }
                    
                    let html = '';
                    data.recipes.forEach(recipe => {
                        const canCraft = recipe.can_craft ? '' : 'disabled';
                        const missingIngredients = recipe.missing ? ` <small class="missing">(${recipe.missing})</small>` : '';
                        html += `<div class="crafting-item ${recipe.can_craft ? 'can-craft' : 'cant-craft'}">
                            <div class="crafting-name">${recipe.output_name}</div>
                            <div class="crafting-reqs">Требуется: ${recipe.ingredients}${missingIngredients}</div>
                            <div class="crafting-result">Результат: ${recipe.result_desc}</div>
                            <button class="craft-btn" ${canCraft} onclick="craftItem(${recipe.id})">🔨 Создать</button>
                        </div>`;
                    });
                    container.innerHTML = html;
                })
                .catch(e => {
                    document.getElementById('crafting-list').innerHTML = '<div class="error">Ошибка загрузки рецептов</div>';
                });
        }

        function craftItem(recipeId) {
            fetch('/api/crafting.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'craft', recipe_id: recipeId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog('🔨 Создано: ' + data.output_name);
                    loadCrafting();
                    loadInventory();
                } else {
                    showAlert(data.error || 'Ошибка крафта');
                }
            })
            .catch(e => showAlert('Ошибка сети'));
        }

        showPanel('status');
    </script>
</body>
</html>

<?php
declare(strict_types=1);

/**
 * Combat System - Боевая система Fallout RPG
 * Пошаговый бой с расчетом урона, критов и дропа лута
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Начать бой с монстром
 */
function startCombat(int $playerId, int $monsterId, ?int $locationId = null, ?int $dungeonNodeId = null): array {
    global $pdo;
    
    try {
        $player = getPlayerStats($playerId);
        if (!$player) {
            return ['success' => false, 'error' => 'Игрок не найден'];
        }
        
        $monster = getMonsterData($monsterId);
        if (!$monster) {
            return ['success' => false, 'error' => 'Монстр не найден'];
        }
        
        $enemyJson = json_encode([
            [
                'monster_id' => $monsterId,
                'monster_key' => $monster['monster_key'],
                'name' => $monster['name'],
                'level' => $monster['level'],
                'hp' => $monster['base_hp'],
                'max_hp' => $monster['base_hp'],
                'armor' => $monster['base_armor'],
                'damage' => $monster['base_dmg'],
                'xp_reward' => $monster['xp_reward'],
                'loot_table' => json_decode($monster['loot_table'], true)
            ]
        ]);
        
        $initiativeOrder = json_encode([$playerId, -1]);
        
        $stmt = $pdo->prepare("
            INSERT INTO combats (player_id, location_id, dungeon_node_id, enemy_json, initiative_order, current_turn_index)
            VALUES (:player_id, :location_id, :dungeon_node_id, :enemy_json, :initiative_order, 0)
        ");
        
        $stmt->execute([
            ':player_id' => $playerId,
            ':location_id' => $locationId,
            ':dungeon_node_id' => $dungeonNodeId,
            ':enemy_json' => $enemyJson,
            ':initiative_order' => $initiativeOrder
        ]);
        
        $combatId = (int)$pdo->lastInsertId();
        
        logCombatAction($combatId, 'system', 0, 'start', null, null, 0, 0, null, null, 
            "Бой начался! {$player['name']} против {$monster['name']}");
        
        return [
            'success' => true,
            'combat_id' => $combatId,
            'player' => $player,
            'enemies' => json_decode($enemyJson, true),
            'message' => "На вас напал {$monster['name']} (ур. {$monster['level']})!"
        ];
        
    } catch (PDOException $e) {
        error_log("Combat start error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка начала боя'];
    }
}

/**
 * Получить данные игрока с экипировкой
 */
function getPlayerStats(int $playerId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(SUM(CASE WHEN i.equipped_slot IS NOT NULL THEN w.dmg_mod ELSE 0 END), 0) as weapon_dmg,
               COALESCE(SUM(CASE WHEN i.equipped_slot IS NOT NULL THEN a.defense ELSE 0 END), 0) as armor_def
        FROM players p
        LEFT JOIN inventory i ON p.id = i.player_id AND i.equipped_slot IS NOT NULL
        LEFT JOIN weapons w ON i.item_type = 'weapon' AND i.item_key = w.item_key
        LEFT JOIN armors a ON i.item_type = 'armor' AND i.item_key = a.item_key
        WHERE p.id = :player_id
        GROUP BY p.id
    ");
    
    $stmt->execute([':player_id' => $playerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return null;
    }
    
    $effects = getPlayerEffects($playerId);
    $result['effects'] = $effects;
    
    return $result;
}

/**
 * Получить данные монстра
 */
function getMonsterData(int $monsterId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM monsters WHERE id = :id");
    $stmt->execute([':id' => $monsterId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * Получить активные эффекты игрока
 */
function getPlayerEffects(int $playerId): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT effect_key, effect_name, stat_modifier, expires_at
        FROM player_effects
        WHERE player_id = :player_id AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->execute([':player_id' => $playerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Атака в бою
 */
function combatAttack(int $combatId, int $playerId, int $targetIndex): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM combats WHERE id = :id AND combat_state = 'active'");
        $stmt->execute([':id' => $combatId]);
        $combat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$combat) {
            return ['success' => false, 'error' => 'Бой не найден или завершен'];
        }
        
        $enemies = json_decode($combat['enemy_json'], true);
        
        if (!isset($enemies[$targetIndex])) {
            return ['success' => false, 'error' => 'Цель не найдена'];
        }
        
        $enemy = &$enemies[$targetIndex];
        $player = getPlayerStats($playerId);
        
        $weaponDamage = getEquippedWeaponDamage($playerId);
        $baseDamage = (int)($player['strength'] / 2) + $weaponDamage;
        
        $critChance = (float)getSetting('crit_chance_base', '0.05');
        $isCrit = (mt_rand(1, 100) <= ($critChance * 100));
        $critMultiplier = $isCrit ? 2.0 : 1.0;
        
        $armorReduction = max(0, $enemy['armor'] * 0.5);
        $finalDamage = max(1, (int)(($baseDamage * $critMultiplier) - $armorReduction));
        
        $hpBefore = $enemy['hp'];
        $enemy['hp'] = max(0, $enemy['hp'] - $finalDamage);
        $hpAfter = $enemy['hp'];
        
        $stmt = $pdo->prepare("UPDATE combats SET enemy_json = :enemy_json WHERE id = :id");
        $stmt->execute([
            ':enemy_json' => json_encode($enemies),
            ':id' => $combatId
        ]);
        
        $actionType = $isCrit ? 'crit' : 'attack';
        $description = $isCrit 
            ? "КРИТИЧЕСКИЙ УДАР! Вы нанесли {$finalDamage} урона {$enemy['name']}"
            : "Вы атаковали {$enemy['name']} и нанесли {$finalDamage} урона";
        
        logCombatAction($combatId, 'player', $playerId, $actionType, 'monster', $targetIndex, 
            $finalDamage, 0, $hpBefore, $hpAfter, $description);
        
        $killed = false;
        $xpGained = 0;
        $lootDropped = [];
        
        if ($enemy['hp'] <= 0) {
            $killed = true;
            $xpGained = $enemy['xp_reward'];
            
            grantXp($playerId, $xpGained);
            $lootDropped = generateLoot($enemy['loot_table']);
            
            $allDead = true;
            foreach ($enemies as $e) {
                if ($e['hp'] > 0) {
                    $allDead = false;
                    break;
                }
            }
            
            if ($allDead) {
                endCombat($combatId, 'won');
                
                return [
                    'success' => true,
                    'killed' => true,
                    'xp_gained' => $xpGained,
                    'loot' => $lootDropped,
                    'combat_won' => true,
                    'message' => "{$enemy['name']} погиб! Вы получили {$xpGained} XP." . 
                                (!empty($lootDropped) ? " Найден лут!" : "")
                ];
            }
        }
        
        $enemyResponse = null;
        if (!$killed && $enemy['hp'] > 0) {
            $enemyResponse = monsterTurn($combatId, $playerId, $targetIndex, $enemy);
        }
        
        return [
            'success' => true,
            'killed' => $killed,
            'damage_dealt' => $finalDamage,
            'is_crit' => $isCrit,
            'enemy_hp' => $enemy['hp'],
            'enemy_max_hp' => $enemy['max_hp'],
            'xp_gained' => $xpGained,
            'loot' => $lootDropped,
            'enemy_response' => $enemyResponse,
            'message' => $description
        ];
        
    } catch (PDOException $e) {
        error_log("Combat attack error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка атаки'];
    }
}

/**
 * Получить урон экипированного оружия
 */
function getEquippedWeaponDamage(int $playerId): int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(w.dmg_mod, 0) as damage
        FROM inventory i
        JOIN weapons w ON i.item_key = w.item_key
        WHERE i.player_id = :player_id AND i.equipped_slot = 'main_hand'
        LIMIT 1
    ");
    
    $stmt->execute([':player_id' => $playerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (int)$result['damage'] : 0;
}

/**
 * Ход монстра
 */
function monsterTurn(int $combatId, int $playerId, int $monsterIndex, array &$monster): ?array {
    global $pdo;
    
    $player = getPlayerStats($playerId);
    $playerHp = $player['current_hp'];
    
    $baseDamage = $monster['damage'];
    $armorReduction = getPlayerArmor($playerId);
    $finalDamage = max(1, $baseDamage - $armorReduction);
    
    $newHp = max(0, $playerHp - $finalDamage);
    
    $stmt = $pdo->prepare("UPDATE players SET current_hp = :hp WHERE id = :id");
    $stmt->execute([':hp' => $newHp, ':id' => $playerId]);
    
    $description = "{$monster['name']} атакует вас и наносит {$finalDamage} урона!";
    
    logCombatAction($combatId, 'monster', $monsterIndex, 'attack', 'player', $playerId, 
        $finalDamage, $finalDamage, $playerHp, $newHp, $description);
    
    if ($newHp <= 0) {
        endCombat($combatId, 'lost');
        applyDeathPenalty($playerId);
        
        return [
            'player_hit' => true,
            'damage_taken' => $finalDamage,
            'player_hp' => 0,
            'player_dead' => true,
            'message' => "Вас убили! Вы потеряли часть опыта."
        ];
    }
    
    return [
        'player_hit' => true,
        'damage_taken' => $finalDamage,
        'player_hp' => $newHp,
        'player_max_hp' => $player['max_hp'],
        'message' => $description
    ];
}

/**
 * Получить защиту брони игрока
 */
function getPlayerArmor(int $playerId): int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(a.defense), 0) as total_armor
        FROM inventory i
        JOIN armors a ON i.item_key = a.item_key
        WHERE i.player_id = :player_id AND i.equipped_slot IS NOT NULL
    ");
    
    $stmt->execute([':player_id' => $playerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)($result['total_armor'] ?? 0);
}

/**
 * Выдать опыт игроку
 */
function grantXp(int $playerId, int $amount): void {
    global $pdo;
    
    $multiplier = (float)getSetting('xp_multiplier', '1.0');
    $finalXp = (int)($amount * $multiplier);
    
    $stmt = $pdo->prepare("
        UPDATE players 
        SET experience = experience + :xp, 
            level = FLOOR((experience + :xp) / 100) + 1
        WHERE id = :id
    ");
    
    $stmt->execute([':xp' => $finalXp, ':id' => $playerId]);
}

/**
 * Сгенерировать лут из loot_table
 */
function generateLoot(array $lootTable): array {
    $droppedItems = [];
    $dropChance = (float)getSetting('loot_drop_chance', '0.75');
    
    foreach ($lootTable as $item) {
        if (mt_rand(1, 100) > ($item['chance'] * 100 * $dropChance)) {
            continue;
        }
        
        $qty = mt_rand($item['min_qty'] ?? 1, $item['max_qty'] ?? 1);
        
        $droppedItems[] = [
            'type' => $item['type'],
            'key' => $item['key'],
            'quantity' => $qty
        ];
    }
    
    return $droppedItems;
}

/**
 * Добавить лут в инвентарь игрока
 */
function addLootToInventory(int $playerId, array $lootItems): array {
    global $pdo;
    $added = [];
    
    foreach ($lootItems as $item) {
        $tableMap = [
            'loot' => 'loot',
            'weapon' => 'weapons',
            'armor' => 'armors',
            'consumable' => 'consumables'
        ];
        
        $tableName = $tableMap[$item['type']] ?? 'loot';
        
        $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE item_key = :key");
        $stmt->execute([':key' => $item['key']]);
        $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemData) {
            continue;
        }
        
        addInventoryItem($playerId, $item['type'], $item['key'], $item['quantity']);
        
        $added[] = [
            'name' => $itemData['name'],
            'quantity' => $item['quantity'],
            'type' => $item['type']
        ];
    }
    
    return $added;
}

/**
 * Добавить предмет в инвентарь
 */
function addInventoryItem(int $playerId, string $itemType, string $itemKey, int $quantity = 1): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO inventory (player_id, item_type, item_key, quantity, condition_pct)
        VALUES (:player_id, :item_type, :item_key, :quantity, 100.00)
        ON DUPLICATE KEY UPDATE quantity = quantity + :quantity
    ");
    
    return $stmt->execute([
        ':player_id' => $playerId,
        ':item_type' => $itemType,
        ':item_key' => $itemKey,
        ':quantity' => $quantity
    ]);
}

/**
 * Завершить бой
 */
function endCombat(int $combatId, string $state): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE combats 
        SET combat_state = :state, ended_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':state' => $state,
        ':id' => $combatId
    ]);
}

/**
 * Применить штраф смерти
 */
function applyDeathPenalty(int $playerId): void {
    global $pdo;
    
    $penalty = (float)getSetting('xp_death_penalty', '0.1');
    
    $stmt = $pdo->prepare("
        UPDATE players 
        SET experience = GREATEST(0, FLOOR(experience - (experience * :penalty))),
            current_hp = FLOOR(max_hp * 0.5)
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':penalty' => $penalty,
        ':id' => $playerId
    ]);
}

/**
 * Логирование действий в бою
 */
function logCombatAction(int $combatId, string $actorType, int $actorId, string $actionType, 
                         ?string $targetType, ?int $targetId, int $damageDealt, int $damageTaken,
                         ?int $hpBefore, ?int $hpAfter, string $description): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO combat_logs (combat_id, actor_type, actor_id, action_type, target_type, target_id,
                                 damage_dealt, damage_taken, hp_before, hp_after, description)
        VALUES (:combat_id, :actor_type, :actor_id, :action_type, :target_type, :target_id,
                :damage_dealt, :damage_taken, :hp_before, :hp_after, :description)
    ");
    
    $stmt->execute([
        ':combat_id' => $combatId,
        ':actor_type' => $actorType,
        ':actor_id' => $actorId,
        ':action_type' => $actionType,
        ':target_type' => $targetType,
        ':target_id' => $targetId,
        ':damage_dealt' => $damageDealt,
        ':damage_taken' => $damageTaken,
        ':hp_before' => $hpBefore,
        ':hp_after' => $hpAfter,
        ':description' => $description
    ]);
}

/**
 * Получить настройку из БД
 */
function getSetting(string $key, string $default = ''): string {
    global $pdo;
    
    static $cache = [];
    
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("SELECT setting_value FROM game_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetchColumn();
        $cache[$key] = $result ?: $default;
    }
    
    return $cache[$key];
}

/**
 * Побег из боя
 */
function fleeCombat(int $combatId, int $playerId): array {
    global $pdo;
    
    $success = mt_rand(1, 100) <= 50;
    
    if ($success) {
        endCombat($combatId, 'fled');
        
        return [
            'success' => true,
            'escaped' => true,
            'message' => 'Вам удалось сбежать!'
        ];
    } else {
        $stmt = $pdo->prepare("
            UPDATE combats SET current_turn_index = current_turn_index + 1 WHERE id = :id
        ");
        $stmt->execute([':id' => $combatId]);
        
        return [
            'success' => true,
            'escaped' => false,
            'message' => 'Побег не удался! Враг атакует.'
        ];
    }
}

/**
 * Использование предмета в бою
 */
function useItemInCombat(int $combatId, int $playerId, int $inventoryItemId): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT i.*, c.heal_amount, c.rad_heal, c.boost_type, c.boost_value
        FROM inventory i
        LEFT JOIN consumables c ON i.item_key = c.item_key
        WHERE i.id = :id AND i.player_id = :player_id
    ");
    
    $stmt->execute([
        ':id' => $inventoryItemId,
        ':player_id' => $playerId
    ]);
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item || $item['item_type'] !== 'consumable') {
        return ['success' => false, 'error' => 'Нельзя использовать этот предмет'];
    }
    
    $player = getPlayerStats($playerId);
    $effectApplied = false;
    $message = '';
    
    if ($item['heal_amount'] > 0) {
        $newHp = min($player['max_hp'], $player['current_hp'] + $item['heal_amount']);
        
        $stmt = $pdo->prepare("UPDATE players SET current_hp = :hp WHERE id = :id");
        $stmt->execute([':hp' => $newHp, ':id' => $playerId]);
        
        $message = "Вы использовали {$item['name']} и восстановили " . ($newHp - $player['current_hp']) . " HP.";
        $effectApplied = true;
    }
    
    if ($item['quantity'] > 1) {
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = :id");
        $stmt->execute([':id' => $inventoryItemId]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
        $stmt->execute([':id' => $inventoryItemId]);
    }
    
    logCombatAction($combatId, 'player', $playerId, 'use_item', null, null, 0, 0, null, null, $message);
    
    return [
        'success' => true,
        'effect_applied' => $effectApplied,
        'message' => $message
    ];
}

<?php
declare(strict_types=1);

/**
 * Combat System - Боевая система Fallout RPG
 * Пошаговый бой с расчетом урона, критов и дропа лута
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Проверка и расход боеприпасов
 */
function consumeAmmo(int $characterId, string $ammoTypeName): bool {
    global $pdo;
    if ($ammoTypeName === 'none' || empty($ammoTypeName)) return true;
    
    $stmt = $pdo->prepare("
        SELECT pa.quantity 
        FROM player_ammo pa
        JOIN ammo_types at ON at.id = pa.ammo_type_id
        JOIN characters c ON c.player_id = pa.player_id
        WHERE c.id = ? AND at.type_name = ?
    ");
    $stmt->execute([$characterId, $ammoTypeName]);
    $qty = $stmt->fetchColumn();
    
    if ($qty && $qty > 0) {
        $stmt = $pdo->prepare("
            UPDATE player_ammo pa
            JOIN characters c ON c.player_id = pa.player_id
            JOIN ammo_types at ON at.id = pa.ammo_type_id
            SET pa.quantity = pa.quantity - 1 
            WHERE c.id = ? AND at.type_name = ?
        ");
        $stmt->execute([$characterId, $ammoTypeName]);
        return true;
    }
    return false;
}

/**
 * Получить тип патронов экипированного оружия
 */
function getEquippedAmmoType(int $characterId): string {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT at.type_name 
        FROM inventory i 
        JOIN weapons w ON i.item_key = w.item_key
        JOIN ammo_types at ON at.id = w.ammo_type_id
        WHERE i.character_id = ? AND i.equipped = 1 AND i.item_type = 'weapon'
        LIMIT 1
    ");
    $stmt->execute([$characterId]);
    return $stmt->fetchColumn() ?: 'none';
}

/**
 * Получить ID игрока по ID персонажа
 */
function getPlayerIdByCharacterId(int $characterId): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT player_id FROM characters WHERE id = ?");
    $stmt->execute([$characterId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Начать бой с монстром
 */
function startCombat(int $characterId, int $monsterId, ?int $locationId = null, ?int $dungeonNodeId = null): array {
    global $pdo;
    
    try {
        $player = getCharacterStats($characterId);
        if (!$player) {
            return ['success' => false, 'error' => 'Персонаж не найден'];
        }
        
        $monster = getMonsterData($monsterId);
        if (!$monster) {
            return ['success' => false, 'error' => 'Монстр не найден'];
        }
        
        $playerId = getPlayerIdByCharacterId($characterId);
        
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
                'loot_table' => json_decode($monster['loot_table'] ?? '[]', true)
            ]
        ]);
        
        $initiativeOrder = json_encode([$characterId, -1]);
        
        $stmt = $pdo->prepare("
            INSERT INTO combats (player_id, location_id, dungeon_node_id, enemy_json, initiative_order, current_turn_index)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        
        $stmt->execute([
            $playerId,
            $locationId,
            $dungeonNodeId,
            $enemyJson,
            $initiativeOrder
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
 * Получить данные персонажа с экипировкой
 */
function getCharacterStats(int $characterId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.*, p.username as player_name
        FROM characters c
        JOIN players p ON p.id = c.player_id
        WHERE c.id = ?
    ");
    
    $stmt->execute([$characterId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return null;
    }
    
    $effects = getPlayerEffects($result['player_id']);
    $result['effects'] = $effects;
    
    return $result;
}

function getPlayerStats(int $playerId): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM characters WHERE player_id = ?");
    $stmt->execute([$playerId]);
    $characterId = $stmt->fetchColumn();
    return $characterId ? getCharacterStats((int)$characterId) : null;
}

/**
 * Получить данные монстра
 */
function getMonsterData(int $monsterId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM monsters WHERE id = ?");
    $stmt->execute([$monsterId]);
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
        WHERE player_id = ? AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->execute([$playerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Атака в бою
 */
function combatAttack(int $combatId, int $characterId, int $targetIndex): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM combats WHERE id = ?");
        $stmt->execute([$combatId]);
        $combat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$combat || ($combat['state_id'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Бой не найден или завершен'];
        }
        
        $enemies = json_decode($combat['enemy_json'], true);
        
        if (!isset($enemies[$targetIndex])) {
            return ['success' => false, 'error' => 'Цель не найдена'];
        }
        
        $enemy = &$enemies[$targetIndex];
        $player = getCharacterStats($characterId);
        $playerId = getPlayerIdByCharacterId($characterId);
        
        $ammoType = getEquippedAmmoType($characterId);
        $hasAmmo = consumeAmmo($characterId, $ammoType);
        
        $weaponDamage = getEquippedWeaponDamage($characterId);
        if (!$hasAmmo) {
            $weaponDamage = 0;
            $description_prefix = "У вас закончились патроны! Вы бьете рукоятью: ";
        } else {
            $description_prefix = "";
        }
        
        $baseDamage = (int)($player['strength'] / 2) + $weaponDamage;
        
        $critChance = 0.05;
        $isCrit = (mt_rand(1, 100) <= ($critChance * 100));
        $critMultiplier = $isCrit ? 2.0 : 1.0;
        
        $armorReduction = max(0, $enemy['armor'] * 0.5);
        $finalDamage = max(1, (int)(($baseDamage * $critMultiplier) - $armorReduction));
        
        $hpBefore = $enemy['hp'];
        $enemy['hp'] = max(0, $enemy['hp'] - $finalDamage);
        $hpAfter = $enemy['hp'];
        
        $stmt = $pdo->prepare("UPDATE combats SET enemy_json = ? WHERE id = ?");
        $stmt->execute([json_encode($enemies), $combatId]);
        
        $actionType = $isCrit ? 'crit' : 'attack';
        $description = $description_prefix . ($isCrit 
            ? "КРИТИЧЕСКИЙ УДАР! Вы нанесли {$finalDamage} урона {$enemy['name']}"
            : "Вы атаковали {$enemy['name']} и нанесли {$finalDamage} урона");
        
        logCombatAction($combatId, 'player', $characterId, $actionType, 'monster', $targetIndex, 
            $finalDamage, 0, $hpBefore, $hpAfter, $description);
        
        $killed = false;
        $xpGained = 0;
        $lootDropped = [];
        
        if ($enemy['hp'] <= 0) {
            $killed = true;
            $xpGained = $enemy['xp_reward'];
            
            grantXp($characterId, $xpGained);
            
            if (!empty($enemy['loot_table'])) {
                $lootDropped = generateLoot($enemy['loot_table'], $characterId);
            }
            
            $ammoTypes = ['bullet', 'energy', 'junk'];
            $ammoType = $ammoTypes[array_rand($ammoTypes)];
            $ammoAmount = rand(1, 10);
            grantAmmo($playerId, $ammoType, $ammoAmount);
            
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
            $enemyResponse = monsterTurn($combatId, $characterId, $targetIndex, $enemy);
            // Обновляем данные игрока после ответа врага
            $updatedPlayer = getCharacterStats($characterId);
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
                'player_hp' => $updatedPlayer['hp'],
                'player_max_hp' => $updatedPlayer['max_hp'],
                'message' => $description
            ];
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
            'player_hp' => $player['hp'],
            'player_max_hp' => $player['max_hp'],
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
function getEquippedWeaponDamage(int $characterId): int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(w.dmg_mod, 0) as damage
        FROM inventory i
        JOIN weapons w ON i.item_key = w.item_key
        WHERE i.character_id = ? AND i.equipped = 1 AND i.item_type = 'weapon'
        LIMIT 1
    ");
    
    $stmt->execute([$characterId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (int)$result['damage'] : 0;
}

/**
 * Ход монстра
 */
function monsterTurn(int $combatId, int $characterId, int $monsterIndex, array &$monster): ?array {
    global $pdo;
    
    $player = getCharacterStats($characterId);
    $playerHp = $player['hp'];
    
    $baseDamage = $monster['damage'];
    $armorReduction = getPlayerArmor($characterId);
    $finalDamage = max(1, $baseDamage - $armorReduction);
    
    $newHp = max(0, $playerHp - $finalDamage);
    
    $stmt = $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?");
    $stmt->execute([$newHp, $characterId]);
    
    $description = "{$monster['name']} атакует вас и наносит {$finalDamage} урона!";
    
    logCombatAction($combatId, 'monster', $monsterIndex, 'attack', 'player', $characterId, 
        $finalDamage, $finalDamage, $playerHp, $newHp, $description);
    
    if ($newHp <= 0) {
        endCombat($combatId, 'lost');
        applyDeathPenalty($characterId);
        
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
 * Получить защиту брони персонажа
 */
function getPlayerArmor(int $characterId): int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(a.defense), 0) as total_armor
        FROM inventory i
        JOIN armors a ON i.item_key = a.item_key
        WHERE i.character_id = ? AND i.equipped = 1 AND i.item_type = 'armor'
    ");
    
    $stmt->execute([$characterId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)($result['total_armor'] ?? 0);
}

/**
 * Выдать опыт персонажу
 */
function grantXp(int $characterId, int $amount): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE characters 
        SET xp = xp + ?,
            level = FLOOR((xp + ?) / 100) + 1
        WHERE id = ?
    ");
    
    $stmt->execute([$amount, $amount, $characterId]);
}

/**
 * Сгенерировать лут из loot_table
 */
function generateLoot(array $lootTable, int $characterId): array {
    global $pdo;
    $droppedItems = [];
    
    foreach ($lootTable as $item) {
        $chance = $item['chance'] ?? 0.5;
        if (mt_rand(1, 100) > ($chance * 100)) {
            continue;
        }
        
        $qty = mt_rand($item['min_qty'] ?? 1, $item['max_qty'] ?? 1);
        
        $itemType = $item['type'] ?? 'loot';
        $itemKey = $item['key'] ?? '';
        
        addInventoryItem($characterId, $itemType, $itemKey, $qty);
        
        $droppedItems[] = [
            'type' => $itemType,
            'key' => $itemKey,
            'quantity' => $qty
        ];
    }
    
    return $droppedItems;
}

/**
 * Добавить предмет в инвентарь
 */
function addInventoryItem(int $characterId, string $itemType, string $itemKey, int $quantity = 1): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO inventory (character_id, item_type, item_key, quantity)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + ?
    ");
    
    return $stmt->execute([$characterId, $itemType, $itemKey, $quantity, $quantity]);
}

/**
 * Выдать боеприпасы игроку
 */
function grantAmmo(int $playerId, string $ammoTypeName, int $amount): void {
    global $pdo;
    if ($ammoTypeName === 'none') return;
    
    $stmt = $pdo->prepare("
        SELECT id FROM ammo_types WHERE type_name = ?
    ");
    $stmt->execute([$ammoTypeName]);
    $ammoTypeId = $stmt->fetchColumn();
    
    if (!$ammoTypeId) return;
    
    $stmt = $pdo->prepare("
        INSERT INTO player_ammo (player_id, ammo_type_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + ?
    ");
    $stmt->execute([$playerId, $ammoTypeId, $amount, $amount]);
}

/**
 * Завершить бой
 */
function endCombat(int $combatId, string $state): void {
    global $pdo;
    
    $stateId = match($state) {
        'won' => 2,
        'lost' => 3,
        'fled' => 4,
        default => 1
    };
    
    $stmt = $pdo->prepare("UPDATE combats SET state_id = ?, ended_at = NOW() WHERE id = ?");
    $stmt->execute([$stateId, $combatId]);
}

/**
 * Применить штраф смерти
 */
function applyDeathPenalty(int $characterId): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE characters 
        SET xp = GREATEST(0, FLOOR(xp * 0.9)),
            hp = FLOOR(max_hp * 0.5)
        WHERE id = ?
    ");
    
    $stmt->execute([$characterId]);
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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $combatId,
        $actorType,
        $actorId,
        $actionType,
        $targetType,
        $targetId,
        $damageDealt,
        $damageTaken,
        $hpBefore,
        $hpAfter,
        $description
    ]);
}

/**
 * Побег из боя
 */
function fleeCombat(int $combatId, int $characterId): array {
    global $pdo;
    
    // Проверка - бой еще активен?
    $stmt = $pdo->prepare("SELECT * FROM combats WHERE id = ?");
    $stmt->execute([$combatId]);
    $combat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$combat || ($combat['state_id'] ?? 0) > 0) {
        return ['success' => false, 'error' => 'Бой уже завершен'];
    }
    
    $enemies = json_decode($combat['enemy_json'], true);
    
    // Получаем первого живого врага для атаки при неудачном побеге
    $aliveEnemy = null;
    foreach ($enemies as $idx => $enemy) {
        if ($enemy['hp'] > 0) {
            $aliveEnemy = ['index' => $idx, 'data' => $enemy];
            break;
        }
    }
    
    $success = mt_rand(1, 100) <= 50;
    
    if ($success) {
        endCombat($combatId, 'fled');
        
        return [
            'success' => true,
            'escaped' => true,
            'message' => 'Вам удалось сбежать!'
        ];
    } else {
        // Неудачный побег - враг атакует
        $stmt = $pdo->prepare("UPDATE combats SET current_turn_index = current_turn_index + 1 WHERE id = ?");
        $stmt->execute([$combatId]);
        
        $responseMsg = 'Побег не удался!';
        
        // Атака врага
        if ($aliveEnemy) {
            $player = getCharacterStats($characterId);
            $enemy = &$aliveEnemy['data'];
            
            $baseDamage = $enemy['damage'];
            $armorReduction = getPlayerArmor($characterId);
            $finalDamage = max(1, $baseDamage - $armorReduction);
            
            $newHp = max(0, $player['hp'] - $finalDamage);
            
            $stmt = $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?");
            $stmt->execute([$newHp, $characterId]);
            
            $responseMsg .= " {$enemy['name']} атакует вас и наносит {$finalDamage} урона!";
            
            // Проверяем смерть игрока
            if ($newHp <= 0) {
                endCombat($combatId, 'lost');
                applyDeathPenalty($characterId);
                
                return [
                    'success' => true,
                    'escaped' => false,
                    'message' => $responseMsg,
                    'player_hp' => 0,
                    'player_dead' => true
                ];
            }
            
            return [
                'success' => true,
                'escaped' => false,
                'message' => $responseMsg,
                'player_hp' => $newHp,
                'player_max_hp' => $player['max_hp']
            ];
        }
        
        return [
            'success' => true,
            'escaped' => false,
            'message' => $responseMsg
        ];
    }
}

/**
 * Использование предмета в бою
 */
function useItemInCombat(int $combatId, int $characterId, int $inventoryItemId): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT i.*, c.heal_amount, c.rad_heal, c.boost_type, c.boost_value, c.name as item_name
        FROM inventory i
        LEFT JOIN consumables c ON i.item_key = c.item_key
        WHERE i.id = ? AND i.character_id = ?
    ");
    
    $stmt->execute([$inventoryItemId, $characterId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item || $item['item_type'] !== 'consumable') {
        return ['success' => false, 'error' => 'Нельзя использовать этот предмет'];
    }
    
    $player = getCharacterStats($characterId);
    $effectApplied = false;
    $message = '';
    
    if ($item['heal_amount'] > 0) {
        $newHp = min($player['max_hp'], $player['hp'] + $item['heal_amount']);
        
        $stmt = $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?");
        $stmt->execute([$newHp, $characterId]);
        
        $message = "Вы использовали {$item['item_name']} и восстановили " . ($newHp - $player['hp']) . " HP.";
        $effectApplied = true;
    }
    
    if ($item['quantity'] > 1) {
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?");
        $stmt->execute([$inventoryItemId]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$inventoryItemId]);
    }
    
    logCombatAction($combatId, 'player', $characterId, 'use_item', null, null, 0, 0, null, null, $message);
    
    return [
        'success' => true,
        'effect_applied' => $effectApplied,
        'message' => $message
    ];
}

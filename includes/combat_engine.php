<?php
/**
 * Combat Engine v2.0
 * Полностью серверная логика боя с транзакциями и валидацией
 */

class CombatEngine {
    private $pdo;
    private $characterId;
    
    public function __construct($pdo, $characterId) {
        $this->pdo = $pdo;
        $this->characterId = $characterId;
    }

    /**
     * Начало боя с монстром
     */
    public function startCombat(int $monsterId): array {
        try {
            $this->pdo->beginTransaction();

            // Получаем данные персонажа
            $charStmt = $this->pdo->prepare("SELECT * FROM characters WHERE id = ? FOR UPDATE");
            $charStmt->execute([$this->characterId]);
            $character = $charStmt->fetch(PDO::FETCH_ASSOC);

            if (!$character) {
                throw new Exception("Персонаж не найден");
            }

            // Получаем данные монстра
            $monStmt = $this->pdo->prepare("SELECT * FROM monsters WHERE id = ?");
            $monStmt->execute([$monsterId]);
            $monster = $monStmt->fetch(PDO::FETCH_ASSOC);

            if (!$monster) {
                throw new Exception("Монстр не найден");
            }

            // Создаем сессию боя
            $sessionId = bin2hex(random_bytes(16));
            $insertStmt = $this->pdo->prepare("
                INSERT INTO combat_sessions 
                (session_id, character_id, monster_id, monster_current_hp, monster_max_hp, started_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $sessionId, 
                $this->characterId, 
                $monsterId, 
                $monster['hp'], 
                $monster['hp']
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'session_id' => $sessionId,
                'monster' => $monster,
                'player_hp' => $character['current_hp'],
                'player_max_hp' => $character['max_hp'],
                'message' => "⚔️ На вас напал {$monster['name']}!"
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Атака игрока
     */
    public function playerAttack(string $sessionId, int $actionType = 1): array {
        try {
            $this->pdo->beginTransaction();

            // Блокируем сессию боя
            $sessStmt = $this->pdo->prepare("SELECT * FROM combat_sessions WHERE session_id = ? AND character_id = ? FOR UPDATE");
            $sessStmt->execute([$sessionId, $this->characterId]);
            $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception("Бой не найден или уже завершен");
            }

            if ($session['is_finished']) {
                throw new Exception("Бой уже завершен");
            }

            // Получаем актуальные данные
            $charStmt = $this->pdo->prepare("SELECT * FROM characters WHERE id = ? FOR UPDATE");
            $charStmt->execute([$this->characterId]);
            $character = $charStmt->fetch(PDO::FETCH_ASSOC);

            $monStmt = $this->pdo->prepare("SELECT * FROM monsters WHERE id = ?");
            $monStmt->execute([$session['monster_id']]);
            $monster = $monStmt->fetch(PDO::FETCH_ASSOC);

            // Расчет урона игрока
            $damage = $this->calculateDamage($character, $monster, $actionType);
            $isCrit = (mt_rand(1, 100) <= $character['luck']); // Крит по удаче
            if ($isCrit) $damage = intval($damage * 1.5);

            $newMonsterHp = max(0, $session['monster_current_hp'] - $damage);

            // Обновляем HP монстра в сессии
            $updateStmt = $this->pdo->prepare("UPDATE combat_sessions SET monster_current_hp = ? WHERE session_id = ?");
            $updateStmt->execute([$newMonsterHp, $sessionId]);

            $log = ["💥 Вы нанесли {$damage} урона!" . ($isCrit ? " (КРИТ!)" : "")];

            // Проверка смерти монстра
            if ($newMonsterHp <= 0) {
                $this->resolveVictory($session, $character, $monster);
                $this->pdo->commit();
                return [
                    'success' => true,
                    'won' => true,
                    'log' => $log,
                    'message' => "🏆 Победа! {$monster['name']} повержен."
                ];
            }

            // Ход противника
            $enemyDamage = $this->calculateEnemyDamage($monster, $character);
            $newPlayerHp = max(0, $character['current_hp'] - $enemyDamage);

            // Обновляем HP игрока
            $charUpdate = $this->pdo->prepare("UPDATE characters SET current_hp = ? WHERE id = ?");
            $charUpdate->execute([$newPlayerHp, $this->characterId]);

            $log[] = "🛡️ {$monster['name']} атакует и наносит {$enemyDamage} урона!";

            // Проверка смерти игрока
            if ($newPlayerHp <= 0) {
                $this->resolveDefeat($character);
                $this->pdo->commit();
                return [
                    'success' => true,
                    'won' => false,
                    'dead' => true,
                    'player_hp' => 0,
                    'log' => $log,
                    'message' => "☠️ ВЫ ПОГИБЛИ..."
                ];
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'won' => false,
                'player_hp' => $newPlayerHp,
                'player_max_hp' => $character['max_hp'],
                'monster_hp' => $newMonsterHp,
                'monster_max_hp' => $session['monster_max_hp'],
                'log' => $log
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Побег из боя
     */
    public function flee(string $sessionId): array {
        try {
            $this->pdo->beginTransaction();

            $sessStmt = $this->pdo->prepare("SELECT * FROM combat_sessions WHERE session_id = ? AND character_id = ? FOR UPDATE");
            $sessStmt->execute([$sessionId, $this->characterId]);
            $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

            if (!$session || $session['is_finished']) {
                throw new Exception("Бой не активен");
            }

            // Шанс побега 50% + ловкость
            $charStmt = $this->pdo->prepare("SELECT agility FROM characters WHERE id = ?");
            $charStmt->execute([$this->characterId]);
            $agility = $charStmt->fetchColumn();
            
            $chance = 50 + ($agility * 2);
            $roll = mt_rand(1, 100);

            if ($roll <= $chance) {
                // Успешный побег
                $this->finishCombat($sessionId, false);
                $this->pdo->commit();
                return ['success' => true, 'escaped' => true, 'message' => "🏃 Вы успешно сбежали!"];
            } else {
                // Неудача - ответный удар
                $monStmt = $this->pdo->prepare("SELECT * FROM monsters WHERE id = ?");
                $monStmt->execute([$session['monster_id']]);
                $monster = $monStmt->fetch(PDO::FETCH_ASSOC);

                $charStmt = $this->pdo->prepare("SELECT * FROM characters WHERE id = ? FOR UPDATE");
                $charStmt->execute([$this->characterId]);
                $character = $charStmt->fetch(PDO::FETCH_ASSOC);

                $damage = $this->calculateEnemyDamage($monster, $character);
                $newHp = max(0, $character['current_hp'] - $damage);

                $charUpdate = $this->pdo->prepare("UPDATE characters SET current_hp = ? WHERE id = ?");
                $charUpdate->execute([$newHp, $this->characterId]);

                $this->pdo->commit();

                if ($newHp <= 0) {
                    return ['success' => true, 'escaped' => false, 'dead' => true, 'message' => "☠️ Побег не удался! Вы погибли."];
                }

                return [
                    'success' => true, 
                    'escaped' => false, 
                    'player_hp' => $newHp,
                    'message' => "❌ Побег не удался! {$monster['name']} наносит {$damage} урона!"
                ];
            }

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --- Вспомогательные методы ---

    private function calculateDamage($char, $mon, $type): int {
        // type: 1=обычная, 2=сильная (меньше точность), 3=прицельная
        $baseDmg = $char['strength'] * 2; // Упрощенная формула
        // TODO: Учесть оружие из инвентаря
        return max(1, $baseDmg);
    }

    private function calculateEnemyDamage($mon, $char): int {
        $baseDmg = $mon['damage'];
        $armor = $char['resistance'] ?? 0; // TODO: Учесть броню
        return max(1, $baseDmg - intval($armor / 2));
    }

    private function resolveVictory($session, $char, $mon) {
        $this->finishCombat($session['session_id'], true);
        
        // Начисление опыта
        $xp = $mon['experience'];
        $stmt = $this->pdo->prepare("UPDATE characters SET experience = experience + ?, level = level + FLOOR((experience + ?) / 100) - FLOOR(experience / 100) WHERE id = ?");
        $stmt->execute([$xp, $xp, $char['id']]);

        // Лут (упрощенно)
        // TODO: Вызвать функцию лута
    }

    private function resolveDefeat($char) {
        // Потеря крышек при смерти
        $loss = intval($char['caps'] * 0.1);
        $stmt = $this->pdo->prepare("UPDATE characters SET caps = caps - ?, current_hp = 1 WHERE id = ?");
        $stmt->execute([$loss, $char['id']]);
    }

    private function finishCombat($sessionId, $isVictory) {
        $stmt = $this->pdo->prepare("UPDATE combat_sessions SET is_finished = 1, ended_at = NOW() WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
}

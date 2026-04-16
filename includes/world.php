<?php
declare(strict_types=1);

/**
 * World Logic - Логика мира Fallout RPG
 */

/**
 * Выбрать случайного монстра в зависимости от уровня опасности
 */
function spawnMonster(PDO $pdo, int $dangerLevel): ?array {
    // Подбираем диапазон уровней монстров
    $minLvl = max(1, $dangerLevel - 1);
    $maxLvl = $dangerLevel + 1;
    
    $stmt = $pdo->prepare("
        SELECT id, name, level 
        FROM monsters 
        WHERE level BETWEEN :min AND :max AND is_active = 1 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->execute([':min' => $minLvl, ':max' => $maxLvl]);
    $monster = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $monster ?: null;
}

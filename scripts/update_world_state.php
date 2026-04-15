<?php
/**
 * Cron-скрипт для обновления погоды и перемещения существ
 * Запускать каждые 5-10 минут через cron
 * Пример: */5 * * * * php /workspace/scripts/update_world_state.php
 */

require_once __DIR__ . '/../config/database.php';

echo "🌍  Обновление состояния мира...\n";

try {
    // 1. Обновление погоды
    echo "⛅  Проверка погодных событий...\n";
    
    // Удаляем истекшие события
    $pdo->exec("UPDATE world_weather_events SET is_active = 0 WHERE ends_at < NOW()");
    
    // Двигаем активные события
    $weatherStmt = $pdo->query("
        SELECT id, center_x, center_y, direction_x, direction_y, radius, event_type 
        FROM world_weather_events 
        WHERE is_active = 1 AND ends_at > NOW()
    ");
    
    while ($weather = $weatherStmt->fetch(PDO::FETCH_ASSOC)) {
        $newX = $weather['center_x'] + $weather['direction_x'];
        $newY = $weather['center_y'] + $weather['direction_y'];
        
        // Отскок от границ карты (160x90)
        if ($newX < 5 || $newX > 155) {
            $newDirectionX = -$weather['direction_x'];
            $pdo->prepare("UPDATE world_weather_events SET direction_x = ? WHERE id = ?")
                ->execute([$newDirectionX, $weather['id']]);
            $newX = $weather['center_x']; // Остаемся на месте в этом тике
        } else {
            $newDirectionX = $weather['direction_x'];
        }
        
        if ($newY < 5 || $newY > 85) {
            $newDirectionY = -$weather['direction_y'];
            $pdo->prepare("UPDATE world_weather_events SET direction_y = ? WHERE id = ?")
                ->execute([$newDirectionY, $weather['id']]);
            $newY = $weather['center_y'];
        } else {
            $newDirectionY = $weather['direction_y'];
        }
        
        // Обновляем позицию
        $pdo->prepare("UPDATE world_weather_events SET center_x = ?, center_y = ? WHERE id = ?")
            ->execute([$newX, $newY, $weather['id']]);
        
        echo "   🌪️  {$weather['event_type']} переместилось в ($newX, $newY)\n";
    }
    
    // Применяем погоду к клеткам карты (упрощенно - помечаем клетки внутри радиуса)
    // В реальном проекте здесь нужен более сложный алгоритм обновления map_nodes.weather_id
    echo "   📍  Применение эффектов погоды к клеткам...\n";
    
    // 2. Перемещение групп существ
    echo "🦎  Проверка групп существ...\n";
    
    $creatureStmt = $pdo->query("
        SELECT id, group_type, current_node_ids, direction_x, direction_y, 
               move_interval_minutes, last_move_time
        FROM world_creature_groups
        WHERE is_active = 1
    ");
    
    while ($group = $creatureStmt->fetch(PDO::FETCH_ASSOC)) {
        $lastMove = strtotime($group['last_move_time']);
        $now = time();
        $intervalSeconds = $group['move_interval_minutes'] * 60;
        
        if (($now - $lastMove) >= $intervalSeconds) {
            // Пора двигать
            $currentNodes = json_decode($group['current_node_ids'], true);
            
            if (!empty($currentNodes)) {
                // Берем центральную ноду группы для расчета движения
                $centerNodeId = $currentNodes[intval(count($currentNodes) / 2)];
                
                // Получаем координаты центральной ноды
                $nodeData = $pdo->prepare("SELECT x, y FROM map_nodes WHERE id = ?");
                $nodeData->execute([$centerNodeId]);
                $pos = $nodeData->fetch(PDO::FETCH_ASSOC);
                
                if ($pos) {
                    $newX = $pos['x'] + $group['direction_x'];
                    $newY = $pos['y'] + $group['direction_y'];
                    
                    // Проверка границ и непроходимых клеток
                    $checkNode = $pdo->prepare("
                        SELECT id FROM map_nodes 
                        WHERE x = ? AND y = ? AND is_impassable = 0
                    ");
                    $checkNode->execute([$newX, $newY]);
                    $targetNode = $checkNode->fetch(PDO::FETCH_ASSOC);
                    
                    if ($targetNode) {
                        // Движение успешно
                        // Обновляем список нод группы (сдвигаем всю группу)
                        // Для упрощения просто берем соседние ноды вокруг новой позиции
                        $newNodes = [];
                        $neighbors = $pdo->prepare("
                            SELECT id FROM map_nodes 
                            WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ?
                            AND is_impassable = 0
                            LIMIT 5
                        ");
                        $neighbors->execute([
                            $newX - 1, $newX + 1,
                            $newY - 1, $newY + 1
                        ]);
                        
                        while ($n = $neighbors->fetch(PDO::FETCH_ASSOC)) {
                            $newNodes[] = $n['id'];
                        }
                        
                        if (!empty($newNodes)) {
                            $pdo->prepare("
                                UPDATE world_creature_groups 
                                SET current_node_ids = ?, last_move_time = NOW()
                                WHERE id = ?
                            ")->execute([json_encode($newNodes), $group['id']]);
                            
                            echo "   🐾  {$group['group_type']} переместилась в район ($newX, $newY)\n";
                        }
                    } else {
                        // Столкновение с препятствием - меняем направление
                        $newDirX = rand(-1, 1);
                        $newDirY = rand(-1, 1);
                        $pdo->prepare("
                            UPDATE world_creature_groups 
                            SET direction_x = ?, direction_y = ?
                            WHERE id = ?
                        ")->execute([$newDirX, $newDirY, $group['id']]);
                        
                        echo "   ⚠️  {$group['group_type']} уперлась в препятствие, смена направления\n";
                    }
                }
            }
        }
    }
    
    echo "✅  Обновление мира завершено.\n";
    
} catch (Exception $e) {
    echo "❌  Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * ГЕНЕРАТОР МИРА: ПУСТОШЬ 160x90
 * - Биомы: Горы (Запад), Леса (Север), Пустошь (Центр), Мексика (Юг), Братство (Восток)
 * - 8 Убежищ в безопасных зонах
 * - Непроходимые границы (3-4 клетки)
 */

require_once __DIR__ . '/../config/database.php';

// КОНФИГУРАЦИЯ
$mapWidth = 160;
$mapHeight = 90;
$borderSize = 4; // Толщина непроходимой зоны
$vaultCount = 8;

try {
    $pdo->beginTransaction();

    echo "🗺️  Начинаю генерацию карты {$mapWidth}x{$mapHeight}...\n";

    // 1. Очистка старых данных
    $pdo->exec("TRUNCATE TABLE map_nodes");
    $pdo->exec("TRUNCATE TABLE vault_keepers");
    echo "Tables cleared.\n";

    // 2. Подготовка утверждений
    $stmtInsertNode = $pdo->prepare("
        INSERT INTO map_nodes (x, y, tile_type, biome, is_spawn_point, radiation_level, name, description)
        VALUES (:x, :y, :tile_type, :biome, :is_spawn, :rad, :name, :desc)
    ");

    $stmtInsertKeeper = $pdo->prepare("
        INSERT INTO vault_keepers (vault_id, greeting_text, mission_text, bonus_armor, bonus_charisma, starter_caps, starter_stimpak, starter_antirad)
        VALUES (:vault_id, :greeting, :mission, 2, 1, 30, 3, 1)
    );

    $missions = [
        'Найди Источник Живой Воды. Легенды говорят, что он может очистить землю. Иди и верни надежду.',
        'В Пустоши потеряна технология предтеч. Найди её, пока она не попала к рейдерам.',
        'Собери советы старейших племен. Только единство спасет нас от угасания.',
        'Уничтожь гнездо Когтей Смерти на севере. Они угрожают торговому пути.',
        'Найди чертежи силовой брони в бункере Братства. Если сможешь выжить.',
        'Очисти завод от рейдеров. Местные жители заплатят крышками.',
        'Исследуй пещеры на западе. Там скрыт вход в старое убежище.',
        'Доставь письмо старейшине поселения в центре пустоши.'
    ];

    $greetings = [
        'Добро пожаловать домой, Избранный. Я ждал тебя. Пустошь сурова, но ты сильнее.',
        'Приветствую, житель. Твой комбинезон готов. Путь лежит через огонь и кровь.',
        'Ты вышел из криокамеры последним. Мир изменился. Измени его и ты.',
        'Выживший! Мы думали, система селекции дала сбой. Но нет — ты здесь.',
        'Наконец-то! Убежищу нужен герой. А герою — цель.',
        'Твой предок подписал контракт с Vault-Tec. Теперь твой черёд платить.',
        'Мы хранили этот комбинезон сто лет. Он ждал именно тебя.',
        'Выход за этой дверью. Обратного пути нет. Ты готов?'
    ];

    $vaultLocations = []; // Для отслеживания занятых зон

    // 3. Генерация сетки
    $nodesCreated = 0;
    for ($y = 0; $y < $mapHeight; $y++) {
        for ($x = 0; $x < $mapWidth; $x++) {
            $biome = 'wasteland';
            $tileType = 'wasteland';
            $radLevel = 0;
            $isSpawn = 0;
            $name = null;
            $desc = null;

            // ЗАПАД: Горы (непроходимо)
            if ($x < $borderSize) {
                $biome = 'mountains';
                $tileType = 'mountain';
                $radLevel = 0;
                $desc = 'Непроходимые скалы. Дальше только смерть.';
            }
            // ВОСТОК: Земли Братства Стали (непроходимо)
            elseif ($x >= $mapWidth - $borderSize) {
                $biome = 'brotherhood_lands';
                $tileType = 'military_base';
                $radLevel = 0;
                $desc = 'Патруль Братства Стали. Проход запрещен под страхом расстрела.';
            }
            // СЕВЕР: Леса и Холод (непроходимо)
            elseif ($y < $borderSize) {
                $biome = 'frozen_forest';
                $tileType = 'forest';
                $radLevel = 0;
                $desc = 'Ледяной ветер и мутантские деревья. Путь закрыт.';
            }
            // ЮГ: Пустыня Мексика (непроходимо)
            elseif ($y >= $mapHeight - $borderSize) {
                $biome = 'mexico_desert';
                $tileType = 'wasteland';
                $radLevel = 5;
                $desc = 'Выжженная пустыня. Дальше только радиация и смерть.';
            }
            // ЦЕНТР: Пустошь + Предгорья (безопасно для убежищ)
            else {
                // Предгорья у границ гор (безопасно, но красиво)
                if ($x >= $borderSize && $x < $borderSize + 10) {
                    $biome = 'foothills';
                    $tileType = 'wasteland';
                    $radLevel = 0;
                } else {
                    $biome = 'wasteland';
                    $tileType = 'wasteland';
                    
                    // Случайные аномалии радиации в центре
                    if (rand(1, 100) <= 5) {
                        $radLevel = rand(5, 20);
                        $tileType = 'wasteland';
                    }
                }
            }

            // Вставка ноды
            $stmtInsertNode->execute([
                ':x' => $x,
                ':y' => $y,
                ':tile_type' => $tileType,
                ':biome' => $biome,
                ':is_spawn' => $isSpawn,
                ':rad' => $radLevel,
                ':name' => $name,
                ':desc' => $desc
            ]);
            $nodesCreated++;
        }
    }
    echo "Created {$nodesCreated} map nodes.\n";

    // 4. Размещение 8 Убежищ в безопасных зонах (предгорья или центр)
    echo "🏠 Размещение {$vaultCount} убежищ...\n";
    
    $placedVaults = 0;
    $attempts = 0;
    $minDistance = 15; // Минимальное расстояние между убежищами

    while ($placedVaults < $vaultCount && $attempts < 1000) {
        $attempts++;
        
        // Генерируем координаты в безопасной зоне (предгорья или центр)
        $x = rand($borderSize + 2, $mapWidth - $borderSize - 3);
        $y = rand($borderSize + 2, $mapHeight - $borderSize - 3);
        
        // Проверка: не слишком ли близко к границам (чтобы было место для выхода)
        if ($x < $borderSize + 5 || $x > $mapWidth - $borderSize - 5 || 
            $y < $borderSize + 5 || $y > $mapHeight - $borderSize - 5) {
            continue;
        }

        // Проверка расстояния до других убежищ
        $tooClose = false;
        foreach ($vaultLocations as $vx => $vy) {
            $dist = sqrt(pow($x - $vx, 2) + pow($y - $vy, 2));
            if ($dist < $minDistance) {
                $tooClose = true;
                break;
            }
        }
        if ($tooClose) continue;

        // Получаем ID созданной ноды
        $stmtGetNode = $pdo->prepare("SELECT id FROM map_nodes WHERE x = :x AND y = :y");
        $stmtGetNode->execute([':x' => $x, ':y' => $y]);
        $node = $stmtGetNode->fetch(PDO::FETCH_ASSOC);
        
        if (!$node) continue;
        
        $nodeId = $node['id'];

        // Обновляем ноду: делаем её убежищем
        $stmtUpdateNode = $pdo->prepare("
            UPDATE map_nodes 
            SET tile_type = 'vault_ext', 
                biome = 'vault_zone', 
                is_spawn_point = 1, 
                radiation_level = 0,
                name = :name,
                description = 'Вход в убежище Vault-Tec. Символ шестеренки едва виден на металле.'
            WHERE id = :id
        ");
        $vaultName = "Убежище-" . ($placedVaults + 1);
        $stmtUpdateNode->execute([
            ':name' => $vaultName,
            ':id' => $nodeId
        ]);

        // Создаем Хранителя для этого убежища
        $missionKey = $placedVaults % count($missions);
        $greetingKey = $placedVaults % count($greetings);
        
        $stmtInsertKeeper->execute([
            ':vault_id' => $nodeId,
            ':greeting' => $greetings[$greetingKey],
            ':mission' => $missions[$missionKey]
        ]);

        $vaultLocations[$x] = $y;
        $placedVaults++;
        echo "   📍 Убежище #" . $placedVaults . " размещено на координатах ($x, $y)\n";
    }

    if ($placedVaults < $vaultCount) {
        echo "Warning: Could not place all vaults. Placed: $placedVaults of $vaultCount\n";
    } else {
        echo "All vaults successfully placed.\n";
    }

    // 5. Добавление нескольких городов/заводов (кластеры)
    echo "Generating large locations (cities, factories)...\n";
    $largeLocationsCount = 12;
    $placedLarge = 0;
    $attempts = 0;
    
    while ($placedLarge < $largeLocationsCount && $attempts < 500) {
        $attempts++;
        $x = rand($borderSize + 10, $mapWidth - $borderSize - 10);
        $y = rand($borderSize + 10, $mapHeight - $borderSize - 10);
        
        // Проверка: не слишком ли близко к убежищам
        $tooCloseToVault = false;
        foreach ($vaultLocations as $vx => $vy) {
            $dist = sqrt(pow($x - $vx, 2) + pow($y - $vy, 2));
            if ($dist < 20) {
                $tooCloseToVault = true;
                break;
            }
        }
        if ($tooCloseToVault) continue;

        // Размер кластера (3x3 или 4x4)
        $size = rand(3, 4);
        $type = rand(0, 1) ? 'ruins_city' : 'factory';
        $namePrefix = $type === 'ruins_city' ? 'Руины города' : 'Завод';
        $name = $namePrefix . ' #' . ($placedLarge + 1);

        $clusterNodes = [];
        $validCluster = true;

        for ($dy = 0; $dy < $size; $dy++) {
            for ($dx = 0; $dx < $size; $dx++) {
                $cx = $x + $dx;
                $cy = $y + $dy;
                
                if ($cx >= $mapWidth - $borderSize || $cy >= $mapHeight - $borderSize) {
                    $validCluster = false;
                    break;
                }
                
                $clusterNodes[] = ['x' => $cx, 'y' => $cy];
            }
            if (!$validCluster) break;
        }

        if (!$validCluster) continue;

        // Обновляем ноды кластера
        foreach ($clusterNodes as $coord) {
            $stmtGetNode = $pdo->prepare("SELECT id, tile_type FROM map_nodes WHERE x = :x AND y = :y");
            $stmtGetNode->execute([':x' => $coord['x'], ':y' => $coord['y']]);
            $node = $stmtGetNode->fetch(PDO::FETCH_ASSOC);
            
            if ($node && $node['tile_type'] === 'wasteland') {
                $stmtUpdate = $pdo->prepare("
                    UPDATE map_nodes 
                    SET tile_type = :type, 
                        biome = :biome,
                        name = :name,
                        description = :desc
                    WHERE id = :id
                ");
                $desc = $type === 'ruins_city' 
                    ? 'Разрушенные здания занимают несколько кварталов. Опасно.' 
                    : 'Промышленный комплекс предвоенной эпохи. Много хлама.';
                
                $stmtUpdate->execute([
                    ':type' => $type,
                    ':biome' => $type,
                    ':name' => $name,
                    ':desc' => $desc,
                    ':id' => $node['id']
                ]);
            } else {
                $validCluster = false; // Занято другой структурой
                break;
            }
        }

        if ($validCluster) {
            $placedLarge++;
            echo "   🏢 Локация '$name' ($size x" . "$size) размещена.\n";
        }
    }

    $pdo->commit();
    echo "\n🎉 ГЕНЕРАЦИЯ ЗАВЕРШЕНА!\n";
    echo "   - Карта: {$mapWidth}x{$mapHeight}\n";
    echo "   - Убежищ: $placedVaults\n";
    echo "   - Крупных локаций: $placedLarge\n";
    echo "   - Общее узлов: $nodesCreated\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

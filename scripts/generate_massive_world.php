<?php
/**
 * Генератор огромного мира (160x90)
 * - Биомы: Горы (Запад), Леса (Север), Пустошь (Центр), Мексика (Юг), Братство (Восток)
 * - 8 Убежищ в безопасных зонах
 * - Непроходимые границы (3-4 клетки)
 */

require_once __DIR__ . '/../config/database.php';

// КОНФИГУРАЦИЯ
$MAP_WIDTH = 160;
$MAP_HEIGHT = 90;
$BORDER_THICKNESS = 4; // 3-4 клетки непроходимой зоны
$SHELTER_COUNT = 8;

try {
    $pdo->beginTransaction();

    echo "🗺️  Начало генерации карты {$MAP_WIDTH}x{$MAP_HEIGHT}...\n";

    // 1. Очистка старой карты (если нужно)
    // $pdo->exec("TRUNCATE TABLE map_nodes"); 
    // Внимание: это удалит всех игроков с их позициями! Лучше удалять только если карта пуста.
    $countCheck = $pdo->query("SELECT COUNT(*) FROM map_nodes")->fetchColumn();
    if ($countCheck > 0) {
        echo "⚠️  Карта уже существует ({$countCheck} нод). Пропускаем полную пересоздание.\n";
        echo "💡  Для перегенерации выполните: TRUNCATE TABLE map_nodes; вручную.\n";
        // Для демо-целей мы просто добавим недостающее, но в реальном проекте нужна логика обновления
        // В данном скрипте предположим, что мы запускаем на чистой БД после миграции 021
        // Если таблица не пуста - выходим, чтобы не дублировать
        exit("❌  Таблица не пуста. Остановка во избежание дублирования ID.\n");
    }

    // 2. Подготовка данных для вставки (Batch Insert)
    $stmt = $pdo->prepare("
        INSERT INTO map_nodes (x, y, tile_type, biome, is_impassable, radiation_level) 
        VALUES (:x, :y, :tile_type, :biome, :is_impassable, :radiation)
    ");

    $batchSize = 2000;
    $counter = 0;

    echo "🏗️  Генерация ландшафта...\n";

    for ($y = 0; $y < $MAP_HEIGHT; $y++) {
        for ($x = 0; $x < $MAP_WIDTH; $x++) {
            
            $biome = 'wasteland';
            $tileType = 'plains';
            $impassable = 0;
            $radiation = 0;

            // ЗАПАД: Горы (непроходимые)
            if ($x < $BORDER_THICKNESS) {
                $biome = 'mountain';
                $tileType = 'mountain_peak';
                $impassable = 1;
            }
            // ВОСТОК: Граница Братства Стали (непроходимая)
            elseif ($x >= $MAP_WIDTH - $BORDER_THICKNESS) {
                $biome = 'brotherhood_border';
                $tileType = 'brotherhood_checkpoint';
                $impassable = 1;
            }
            // СЕВЕР: Леса и Холод (непроходимые)
            elseif ($y < $BORDER_THICKNESS) {
                $biome = 'forest_cold';
                $tileType = 'frozen_forest';
                $impassable = 1;
            }
            // ЮГ: Пустыня Мексики (непроходимая)
            elseif ($y >= $MAP_HEIGHT - $BORDER_THICKNESS) {
                $biome = 'mexico_desert';
                $tileType = 'hot_desert';
                $impassable = 1;
            }
            // ПРЕДГОРЬЯ (зона между горами и пустошью) - проходимо, но опасно
            elseif ($x < $BORDER_THICKNESS + 5) {
                $biome = 'foothills';
                $tileType = 'rocky_wasteland';
                $radiation = rand(5, 15);
            }
            // ЦЕНТР: Пустошь (основная зона)
            else {
                // Небольшие вариации внутри пустоши
                $rand = rand(1, 100);
                if ($rand < 5) {
                    $tileType = 'crater';
                    $radiation = rand(20, 50);
                } elseif ($rand < 15) {
                    $tileType = 'ruins';
                } elseif ($rand < 25) {
                    $tileType = 'dry_lake';
                } else {
                    $tileType = 'plains';
                }
            }

            $stmt->execute([
                ':x' => $x,
                ':y' => $y,
                ':tile_type' => $tileType,
                ':biome' => $biome,
                ':is_impassable' => $impassable,
                ':radiation' => $radiation
            ]);

            $counter++;
            if ($counter % $batchSize === 0) {
                echo "   ... обработано {$counter} из " . ($MAP_WIDTH * $MAP_HEIGHT) . "\n";
            }
        }
    }

    echo "✅  Карта сгенерирована. Всего нод: {$counter}\n";

    // 3. Создание 8 Убежищ
    echo "🚪  Размещение {$SHELTER_COUNT} убежищ...\n";

    $shelterStmt = $pdo->prepare("
        INSERT INTO shelters (name, map_node_id, is_active) 
        VALUES (:name, :node_id, 1)
    ");

    // Находим безопасные зоны (не горы, не границы, низкая радиация)
    // Формула: ширина / (ширина / 8) = шаг по X. Но нам нужно случайное размещение в безопасной зоне.
    // Безопасная зона: X от 10 до 150, Y от 10 до 80
    
    $safeNodesQuery = $pdo->query("
        SELECT id, x, y FROM map_nodes 
        WHERE is_impassable = 0 
          AND radiation_level < 10 
          AND x BETWEEN 10 AND ($MAP_WIDTH - 10)
          AND y BETWEEN 10 AND ($MAP_HEIGHT - 10)
        ORDER BY RAND()
        LIMIT $SHELTER_COUNT
    ");
    
    $safeNodes = $safeNodesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (count($safeNodes) < $SHELTER_COUNT) {
        throw new Exception("Недостаточно безопасных зон для размещения всех убежищ! Найдено: " . count($safeNodes));
    }

    $vaultNames = [
        "Убежище 101", "Убежище 76", "Убежище 11", "Убежище 34",
        "Убежище 88", "Убежище 95", "Убежище 112", "Убежище 118"
    ];

    foreach ($safeNodes as $index => $node) {
        $name = $vaultNames[$index] ?? "Убежище #" . ($index + 1);
        $shelterStmt->execute([
            ':name' => $name,
            ':node_id' => $node['id']
        ]);
        echo "   📍 {$name} размещено на координатах ({$node['x']}, {$node['y']})\n";
    }

    $pdo->commit();
    echo "🎉  Генерация мира успешно завершена!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌  Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

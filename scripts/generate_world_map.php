<?php
/**
 * ГЕНЕРАТОР МИРА: ПУСТОШЬ
 */

require_once __DIR__ . '/../config/database.php';

$WIDTH = 80;
$HEIGHT = 50;
$BORDER = 3;

function isInCluster(int $x, int $y, int $cx, int $cy, int $rx, int $ry): bool {
    return (abs($x - $cx) <= $rx && abs($y - $cy) <= $ry);
}

echo "🗺️ Генерация карты мира {$WIDTH}x{$HEIGHT}...\n";

try {
    $pdo = getDbConnection();

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE map_adjacency");
    $pdo->exec("TRUNCATE TABLE map_nodes");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Таблицы очищены\n";

    // Типы локаций
    $types = [];
    $stmt = $pdo->query("SELECT id, type_name FROM location_types");
    while ($row = $stmt->fetch()) {
        $types[$row['type_name']] = (int)$row['id'];
    }

    // Локации
    $vaultIds = [];
    $dungeonIds = [];
    $stmt = $pdo->query("SELECT id, is_vault, is_dungeon FROM locations");
    while ($row = $stmt->fetch()) {
        if ($row['is_vault']) $vaultIds[] = (int)$row['id'];
        if ($row['is_dungeon']) $dungeonIds[] = (int)$row['id'];
    }

    // ID типов
    $typeWasteland = $types['Пустошь'] ?? 16;
    $typeRuins = $types['Руины'] ?? 25;
    $typeRadzone = $types['Радиоактивная зона'] ?? 19;
    $typeForest = $types['Лес'] ?? 23;
    $typeMountain = $types['Горы'] ?? 22;
    $typeDesert = $types['Пустыня'] ?? 24;
    $typeDungeon = $types['Подземелье'] ?? 18;
    $typeVaultExt = $types['Вход в Убежище'] ?? 21;
    $typeMilitaryBase = $types['Комплекс Братства'] ?? 28;

    echo "   Тип Пустошь: {$typeWasteland}\n";
    echo "   Тип Горы: {$typeMountain}\n";

    $stmtInsert = $pdo->prepare("
        INSERT INTO map_nodes (pos_x, pos_y, location_id, is_border, border_direction, location_type_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $grid = [];
    for ($y = 0; $y < $HEIGHT; $y++) {
        for ($x = 0; $x < $WIDTH; $x++) {
            $isBorder = false;
            $borderDir = null;
            $typeId = $typeWasteland;

            // Границы
            if ($x < $BORDER) {
                $isBorder = true;
                $borderDir = 'w';
                $typeId = $typeMountain;
            } elseif ($x >= $WIDTH - $BORDER) {
                $isBorder = true;
                $borderDir = 'e';
                $typeId = $typeMilitaryBase;
            } elseif ($y < $BORDER) {
                $isBorder = true;
                $borderDir = 'n';
                $typeId = $typeMountain;
            } elseif ($y >= $HEIGHT - $BORDER) {
                $isBorder = true;
                $borderDir = 's';
                $typeId = $typeDesert;
            }
            // Кластеры
            elseif (isInCluster($x, $y, 20, 15, 8, 8) || isInCluster($x, $y, 55, 30, 6, 6)) {
                $typeId = $typeRuins;
            }
            elseif (isInCluster($x, $y, 40, 25, 5, 5) || isInCluster($x, $y, 10, 35, 4, 4)) {
                $typeId = $typeRadzone;
            }
            elseif ($x < 20 && $y > 20) {
                $typeId = $typeForest;
            }

            $stmtInsert->execute([$x, $y, null, $isBorder ? 1 : 0, $borderDir, $typeId]);
            $grid[$y][$x] = $typeId;
        }
    }

    echo "✅ Создано " . ($WIDTH * $HEIGHT) . " клеток\n";

    // Убежища
    $placed = 0;
    for ($i = 0; $i < 5 && $placed < 5; $i++) {
        $x = rand($BORDER + 5, $WIDTH - $BORDER - 5);
        $y = rand($BORDER + 5, $HEIGHT - $BORDER - 5);

        $tooClose = false;
        foreach ($grid as $gy => $row) {
            foreach ($row as $gx => $gt) {
                if ($gt == $typeVaultExt) {
                    $dist = sqrt(pow($x - $gx, 2) + pow($y - $gy, 2));
                    if ($dist < 15) { $tooClose = true; break 2; }
                }
            }
        }
        if ($tooClose) continue;

        $vId = !empty($vaultIds) ? $vaultIds[$placed % count($vaultIds)] : null;
        $pdo->prepare("UPDATE map_nodes SET location_type_id = ? WHERE pos_x = ? AND pos_y = ?")
            ->execute([$typeVaultExt, $x, $y]);
        if ($vId) {
            $pdo->prepare("UPDATE map_nodes SET location_id = ? WHERE pos_x = ? AND pos_y = ?")
                ->execute([$vId, $x, $y]);
        }
        $grid[$y][$x] = $typeVaultExt;
        $placed++;
        echo "   🏠 Убежище #{$placed} на ({$x}, {$y})\n";
    }

    // Данжи
    $placedD = 0;
    for ($i = 0; $i < 100 && $placedD < 4; $i++) {
        $x = rand($BORDER + 10, $WIDTH - $BORDER - 10);
        $y = rand($BORDER + 10, $HEIGHT - $BORDER - 10);
        $typeId = $grid[$y][$x] ?? 0;

        if ($typeId == $typeWasteland || $typeId == $typeRuins) {
            $dId = !empty($dungeonIds) ? $dungeonIds[$placedD % count($dungeonIds)] : null;
            $pdo->prepare("UPDATE map_nodes SET location_type_id = ? WHERE pos_x = ? AND pos_y = ?")
                ->execute([$typeDungeon, $x, $y]);
            if ($dId) {
                $pdo->prepare("UPDATE map_nodes SET location_id = ? WHERE pos_x = ? AND pos_y = ?")
                    ->execute([$dId, $x, $y]);
            }
            $grid[$y][$x] = $typeDungeon;
            $placedD++;
            echo "   ⚔️ Данж #{$placedD} на ({$x}, {$y})\n";
        }
    }

    // Персонажи в центр
    $pdo->prepare("UPDATE characters SET pos_x = ?, pos_y = ? WHERE pos_x = 0 AND pos_y = 0 LIMIT 10")
        ->execute([floor($WIDTH / 2), floor($HEIGHT / 2)]);

    echo "\n🎉 КАРТА СГЕНЕРИРОВАНА!\n";
    echo "   Размер: {$WIDTH}x{$HEIGHT}\n";
    echo "   Убежищ: {$placed}\n";
    echo "   Данжей: {$placedD}\n";

} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

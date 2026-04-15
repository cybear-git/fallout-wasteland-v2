<?php
// Увеличиваем лимиты памяти и времени для большой карты
ini_set('memory_limit', '512M');
set_time_limit(600);

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();

echo "🌍 Генерация карты мира (160×90, Эллипс 16:9)...\n";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE map_adjacency");
    $pdo->exec("TRUNCATE map_nodes");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 📐 ПАРАМЕТРЫ СЕТКИ
    // Ширина 160 (X от -79 до 80), Высота 90 (Y от -44 до 45)
    $gridW = 160;
    $gridH = 90;
    $a = $gridW / 2;  // 80 - полуось по X
    $b = $gridH / 2;  // 45 - полуось по Y
    
    $directions = [
        'n' => [0, -1], 's' => [0, 1], 'e' => [1, 0], 'w' => [-1, 0],
        'ne' => [1, -1], 'nw' => [-1, -1], 'se' => [1, 1], 'sw' => [-1, 1]
    ];

    // 1. Генерация эллипса с рваными краями
    echo "📐 Расчет эллипса...\n";
    $potentialNodes = [];
    $limitX = $a + 5; // Небольшой запас для шума
    $limitY = $b + 5;

    for ($y = -$limitY; $y <= $limitY; $y++) {
        for ($x = -$limitX; $x <= $limitX; $x++) {
            // Шум для рваных краев (±0.05)
            $noise = rand(-50, 50) / 1000;
            
            // Формула эллипса: (x/a)² + (y/b)² <= 1 + noise
            if ((($x * $x) / ($a * $a)) + (($y * $y) / ($b * $b)) <= 1.0 + $noise) {
                $potentialNodes["$x,$y"] = ['x' => $x, 'y' => $y];
            }
        }
    }
    // Гарантируем наличие центра
    $potentialNodes["0,0"] = ['x' => 0, 'y' => 0];
    echo "   Найдено " . count($potentialNodes) . " точек.\n";

    // 2. BFS для удаления островков (связность от центра)
    echo "🌊 Проверка связности (BFS от центра)...\n";
    $visited = ["0,0" => true];
    $queue = ["0,0"];

    while (!empty($queue)) {
        $curr = array_shift($queue);
        [$cx, $cy] = explode(',', $curr); $cx = (int)$cx; $cy = (int)$cy;
        foreach ($directions as [$dx, $dy]) {
            $nKey = ($cx + $dx) . "," . ($cy + $dy);
            if (isset($potentialNodes[$nKey]) && !isset($visited[$nKey])) {
                $visited[$nKey] = true;
                $queue[] = $nKey;
            }
        }
    }
    $nodes = array_intersect_key($potentialNodes, $visited);
    echo "   ✅ Оставлено " . count($nodes) . " связанных нод.\n";

    // 3. Пакетная вставка в БД
    echo "📥 Вставка узлов в БД...\n";
    $nodeChunks = array_chunk(array_values($nodes), 2000);
    foreach ($nodeChunks as $chunk) {
        $vals = implode(',', array_map(fn($n) => "({$n['x']}, {$n['y']})", $chunk));
        $pdo->exec("INSERT INTO map_nodes (pos_x, pos_y) VALUES $vals");
    }

    // Получаем ID для маппинга
    $stmt = $pdo->query("SELECT id, pos_x, pos_y FROM map_nodes");
    $nodes = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nodes["{$r['pos_x']},{$r['pos_y']}"] = [
            'id' => (int)$r['id'], 
            'x' => (int)$r['pos_x'], 
            'y' => (int)$r['pos_y']
        ];
    }

    // 4. Генерация 8-направленных связей
    echo "🔗 Генерация переходов...\n";
    $buf = []; $adjCount = 0;
    $sql = "INSERT INTO map_adjacency (from_node_id, to_node_id, direction) VALUES ";

    foreach ($nodes as $k => $d) {
        foreach ($directions as $dr => [$dx, $dy]) {
            $nb = $nodes[($d['x'] + $dx) . "," . ($d['y'] + $dy)] ?? null;
            if ($nb) {
                $buf[] = "({$d['id']}, {$nb['id']}, '$dr')";
                $adjCount++;
                if (count($buf) >= 2000) {
                    $pdo->exec($sql . implode(',', $buf));
                    $buf = [];
                }
            }
        }
    }
    if ($buf) $pdo->exec($sql . implode(',', $buf));
    echo "   ✅ Создано $adjCount связей.\n";

    // 5. Размещение убежищ (ИСПРАВЛЕННАЯ ФОРМУЛА)
    echo "🏠 Размещение убежищ...\n";
    
    // Количество убежищ: ширина / 16 (для 160 → 10 убежищ)
    $vaultCount = max(3, round($gridW / ($gridW / 8)));
    $vaults = [];
    $validKeys = array_keys($nodes);
    
    // Минимальное расстояние между убежищами в "эллиптической метрике"
    // 0.25 = примерно 25% от размера эллипса
    $minVaultDistEllipse = 0.25;
    // Минимальное расстояние от края эллипса (чтобы не на самом краю)
    $minDistFromEdge = 0.15;

    for ($i = 0; $i < $vaultCount; $i++) {
        $placed = false; 
        $attempts = 0;
        $currentMinDist = $minVaultDistEllipse; // Будем смягчать требования, если не находит
        
        while (!$placed && $attempts < 2000) {
            $k = $validKeys[array_rand($validKeys)];
            [$vx, $vy] = explode(',', $k); 
            $vx = (int)$vx; 
            $vy = (int)$vy;
            
            // Проверка 1: Не ставить на центр (там клад)
            $isCenter = ($vx == 0 && $vy == 0);
            
            // Проверка 2: Не слишком близко к краю эллипса
            $edgeRatio = sqrt(($vx*$vx)/($a*$a) + ($vy*$vy)/($b*$b));
            $tooCloseToEdge = ($edgeRatio > (1.0 - $minDistFromEdge));
            
            // Проверка 3: Не слишком близко к другим убежищам (эллиптическая метрика)
            $tooCloseToVault = false;
            foreach ($vaults as $v) {
                $dx = ($vx - $v['x']) / $a;
                $dy = ($vy - $v['y']) / $b;
                $dist = sqrt($dx*$dx + $dy*$dy);
                if ($dist < $currentMinDist) {
                    $tooCloseToVault = true;
                    break;
                }
            }
            
            if (!$isCenter && !$tooCloseToEdge && !$tooCloseToVault) {
                $vaults[] = ['x' => $vx, 'y' => $vy];
                $placed = true;
            }
            
            $attempts++;
            // Если долго не находит место, немного уменьшаем требования
            if ($attempts % 500 == 0 && $currentMinDist > 0.15) {
                $currentMinDist *= 0.9;
            }
        }
        
        if (!$placed) {
            echo "   ⚠️ Не удалось разместить убежище #$i (попробуй уменьшить vaultCount или minVaultDist)\n";
        }
    }
    
    echo "   ✅ Убежищ размещено: " . count($vaults) . " из $vaultCount\n";

    // 6. Распределение контента по зонам опасности
    echo "🎒 Распределение локаций...\n";
    $locStmt = $pdo->query("SELECT id, location_key, danger_level, tile_type FROM locations");
    $cat = []; $all = [];
    while ($l = $locStmt->fetch(PDO::FETCH_ASSOC)) {
        $cat[$l['danger_level']][] = $l;
        $all[$l['location_key']] = $l;
    }
    
    $upd = $pdo->prepare("UPDATE map_nodes SET location_id = ? WHERE id = ?");
    $cnt = 0;
    // Нормализованный максимальный радиус для расчёта сложности
    $maxEllipseDist = 1.0;

    foreach ($nodes as $k => $d) {
        // Клад в центре
        if ($d['x'] == 0 && $d['y'] == 0 && isset($all['secret_cache'])) {
            $upd->execute([$all['secret_cache']['id'], $d['id']]); 
            $cnt++; 
            continue;
        }
        
        // Убежища
        $isVault = false;
        foreach ($vaults as $v) {
            if ($d['x'] == $v['x'] && $d['y'] == $v['y']) {
                if (isset($all['vault_101_exit'])) {
                    $upd->execute([$all['vault_101_exit']['id'], $d['id']]);
                }
                $isVault = true; 
                $cnt++; 
                break;
            }
        }
        if ($isVault) continue;

        // Случайные локации (шанс 15%)
        if (rand(1, 100) <= 15) {
            // Находим минимальное эллиптическое расстояние до ближайшего убежища
            $minEllipseDist = PHP_FLOAT_MAX;
            foreach ($vaults as $v) {
                $dx = ($d['x'] - $v['x']) / $a;
                $dy = ($d['y'] - $v['y']) / $b;
                $dist = sqrt($dx*$dx + $dy*$dy);
                if ($dist < $minEllipseDist) $minEllipseDist = $dist;
            }
            
            // Сложность: 1 (рядом с убежищем) → 10 (далеко)
            $danger = max(1, min(10, 1 + round(($minEllipseDist / $maxEllipseDist) * 9)));
            
            $cand = $cat[$danger] ?? [];
            // Если нет точного совпадения, ищем соседние уровни
            if (empty($cand)) {
                for ($diff = 1; $diff <= 3; $diff++) {
                    $cand = array_merge(
                        $cat[$danger - $diff] ?? [], 
                        $cat[$danger + $diff] ?? []
                    );
                    if ($cand) break;
                }
            }
            
            if ($cand) {
                $upd->execute([$cand[array_rand($cand)]['id'], $d['id']]);
                $cnt++;
            }
        }
    }
    
    echo "   🎲 Размещено: $cnt локаций.\n";
    echo "\n🌟 ГЕНЕРАЦИЯ ЗАВЕРШЕНА!\n";
    echo "   Карта: {$gridW}×{$gridH} (~" . count($nodes) . " нод)\n";
    echo "   Убежища: " . count($vaults) . "\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}
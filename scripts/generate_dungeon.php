<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();

echo "⚔️ Генерация данжей...\n";
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE dungeon_nodes");
    $pdo->exec("TRUNCATE dungeons");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 1. Сколько данжей нужно? (1.5 от числа убежищ, минимум 4)
    $vaults = $pdo->query("SELECT COUNT(*) FROM map_nodes n JOIN locations l ON n.location_id=l.id WHERE l.location_key LIKE 'vault%'")->fetchColumn();
    $count = max(4, (int)($vaults * 1.5));
    echo "🎯 Цель: {$count} данжей.\n";

    $dungeonKeys = ['sewers', 'bunker', 'cave_system', 'ruined_mall', 'military_outpost', 'lab', 'metro', 'mine'];
    $bosses = ['mirelurk_king', 'deathclaw', 'super_mutant_brute', 'sentry_bot', 'raider_boss'];
    $types = ['corridor', 'room', 'trap', 'treasure', 'exit', 'boss'];
    
    $sqlD = "INSERT INTO dungeons (dungeon_key, name, min_level, boss_key, reward_json, respawn_hours) VALUES ";
    $bufD = [];

    for ($i = 1; $i <= $count; $i++) {
        $lvl = rand(1, 10);
        $size = rand(3, 6); // 3x3 до 6x6
        $depth = rand(1, 3); // "этажи" пока логические, храним в имени
        $key = $dungeonKeys[array_rand($dungeonKeys)] . "_" . $i;
        $boss = $bosses[array_rand($bosses)];
        $reward = json_encode(['caps' => rand(50, 200) * $lvl, 'loot' => ['stimpak', 'adhesive']]);
        
        $bufD[] = "('$key', 'Данж Уровня $lvl ($size x $size)', $lvl, '$boss', '$reward', 24)";
    }
    $pdo->exec($sqlD . implode(',', $bufD));
    echo "✅ Создано $count шаблонов данжей.\n";

    // 2. Генерация мини-карт и привязка к миру
    echo "🕸️ Генерация планировок и привязка...\n";
    $dungeons = $pdo->query("SELECT id, dungeon_key, min_level, boss_key FROM dungeons")->fetchAll();
    
    // Ищем свободные клетки на глобальной карте для входов (не убежища, не центр)
    $mapNodes = $pdo->query("SELECT n.id, n.pos_x, n.pos_y, l.tile_type FROM map_nodes n LEFT JOIN locations l ON n.location_id=l.id WHERE l.location_key IS NULL OR l.location_key='wasteland' ORDER BY RAND()")->fetchAll();
    
    foreach ($dungeons as $d) {
        $dId = $d['id'];
        $dLevel = $d['min_level'];
        
        // Генерация нодов данжа
        $nodes = [];
        $sx = 0; $sy = 0; // Старт
        $ex = rand(1, 5); $ey = rand(1, 5); // Выход
        
        // Простой путь от S к E + случайные ответвления
        $nodes["0,0"] = ['type'=>'entrance'];
        for($x=0; $x<=$ex; $x++) $nodes["$x,$sy"] = ['type'=>'corridor'];
        for($y=0; $y<=$ey; $y++) $nodes["$ex,$y"] = ['type'=>'corridor'];
        
        // Заполняем случайными комнатами
        for($x=0; $x<=5; $x++) for($y=0; $y<=5; $y++) {
            if(!isset($nodes["$x,$y"]) && rand(1,100) < 40) {
                $nodes["$x,$y"] = ['type' => $types[array_rand($types)]];
            }
        }
        $nodes["$ex,$ey"] = ['type'=>'exit'];
        
        // Вставка нодов
        $bufN = [];
        $sqlN = "INSERT INTO dungeon_nodes (dungeon_id, pos_x, pos_y, tile_type, is_entrance) VALUES ";
        foreach($nodes as $k => $val) {
            [$px, $py] = explode(',', $k);
            $isEnt = ($px==0 && $py==0) ? 1 : 0;
            $bufN[] = "($dId, $px, $py, '{$val['type']}', $isEnt)";
            if(count($bufN) >= 500) { $pdo->exec($sqlN.implode(',',$bufN)); $bufN=[]; }
        }
        if($bufN) $pdo->exec($sqlN.implode(',',$bufN));

        // Привязка входа к глобальной карте
        if(!empty($mapNodes)) {
            $targetNode = array_pop($mapNodes);
            // Обновляем локацию клетки на глобальной карте на вход в данж
            $locId = $pdo->prepare("SELECT id FROM locations WHERE location_key = ?")->execute(['vault_exit']); 
            // Для простоты используем заглушку локации, в реальности создадим отдельную 'dungeon_entrance'
            $pdo->prepare("UPDATE map_nodes SET location_id = (SELECT id FROM locations WHERE tile_type='dungeon' LIMIT 1) WHERE id = ?")->execute([$targetNode['id']]);
        }
    }
    echo "🌟 ДАНЖИ ГОТОВЫ!\n";
} catch(Exception $e) { echo "❌ " . $e->getMessage(); }
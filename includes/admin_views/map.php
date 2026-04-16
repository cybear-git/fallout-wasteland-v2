<div class="page-header"><div><h1>🌍 Карта мира</h1><div class="subtitle">Визуализация сетки. Кликни на клетку, чтобы назначить локацию.</div></div></div>
<div class="card">
    <form method="POST" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px;">
        <?= csrfField() ?>
        <input type="hidden" name="node_id" id="node-id-input" value="">
        <div class="form-group" style="margin:0">
            <label class="form-label">Выбранная клетка</label>
            <input class="form-input" id="selected-node" readonly value="Кликни на карту">
        </div>
        <div class="form-row" style="margin:0">
            <div class="form-group" style="margin:0"><label class="form-label">Локация</label>
                <select class="form-select" name="location_id" id="loc-select">
                    <option value="">— Пустошь (Очистить) —</option>
                    <?php foreach($locations as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?> (<?= $l['tile_type'] ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row" style="margin:0">
            <div class="form-group" style="margin:0"><label class="form-label">Уровень опасности (1-10)</label><input class="form-input" type="number" id="edit-danger" name="danger_level" min="1" max="10" disabled></div>
            <div class="form-group" style="margin:0"><label class="form-label">Радиация (0-100)</label><input class="form-input" type="number" id="edit-radiation" name="radiation_level" min="0" max="100" disabled></div>
        </div>
        <button type="submit" class="btn btn-orange" id="btn-update-node" disabled style="height:40px; margin-top:5px;">💾 Сохранить параметры клетки</button>
    </form>
    
    <div class="map-container" id="mapContainer">
        <?php $cols = (isset($maxX) && isset($minX)) ? max(1, $maxX - $minX + 1) : 10; ?>
        <div style="font-size:10px; color:#666; margin-bottom:5px; padding-left:5px;">
            Сетка: <?= $cols ?>×<?= max(1, $maxY - $minY + 1) ?> | X: <?= $minX ?>..<?= $maxX ?>
        </div>
        <div class="map-grid" id="mapGrid" style="grid-template-columns: repeat(<?= $cols ?>, 14px);">
            <?php if (isset($grid)): ?>
                <?php for ($y = $maxY; $y >= $minY; $y--): for ($x = $minX; $x <= $maxX; $x++):
                    $node = $grid[$y][$x] ?? null;
                    $bgClass = 'cell-empty'; $nid = '';
                    $danger = $node['danger_level'] ?? 1;
                    $rad = $node['radiation_level'] ?? 0;
                    $locName = $node['loc_name'] ?: 'Пустошь';
                    
                    if ($node) {
                        $nid = $node['id'];
                        $bgClass = "cell-{$node['tile_type']}";
                    }
                    $title = "ID:{$nid} | {$locName} ($x,$y) | ⚠️ Опасность: $danger | ☢️ Рад: $rad";
                ?>
                <button class="map-cell <?= $bgClass ?>" title="<?= htmlspecialchars($title) ?>" onclick="selectMapNode(<?= $nid ?>, '<?= htmlspecialchars($title) ?>', <?= $danger ?>, <?= $rad ?>)"><?= ($x==0&&$y==0)?'🎯':'' ?></button>
                <?php endfor; endfor; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

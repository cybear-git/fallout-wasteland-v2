<div class="page-header"><div><h1>⚔️ Данжи</h1><div class="subtitle"><?= ($id>0 && $editData) ? 'Ред: '.$editData['name'] : 'Генератор и список' ?></div></div></div>
<?php if ($id === 0): ?>
    <div class="card" style="border:2px solid var(--blue);">
        <h3 style="margin-bottom:10px;">🛠️ Генератор</h3>
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="generate_dungeons" value="1">
            <div><label class="form-label">Кол-во</label><input type="number" name="count" value="3" class="form-input" style="width:70px"></div>
            <div><label class="form-label">Ур. Мин</label><input type="number" name="min_lvl" value="1" class="form-input" style="width:70px"></div>
            <div><label class="form-label">Ур. Макс</label><input type="number" name="max_lvl" value="5" class="form-input" style="width:70px"></div>
            <div><label class="form-label">Размер Мин</label><input type="number" name="min_size" value="2" class="form-input" style="width:70px"></div>
            <div><label class="form-label">Размер Макс</label><input type="number" name="max_size" value="4" class="form-input" style="width:70px"></div>
            <button class="btn btn-green" style="margin-top:16px;">⚔️ Сгенерировать</button>
        </form>
    </div>
    <div class="card"><table><thead><tr><th>ID</th><th>Ключ</th><th>Название</th><th>Ур.</th><th>Босс</th><th>Награда</th><th>Действия</th></tr></thead><tbody>
    <?php foreach($items as $d): $rew=json_decode($d['reward_json'],true); ?>
    <tr><td><?= $d['id'] ?></td><td><?= $d['dungeon_key'] ?></td><td><?= $d['name'] ?></td><td><?= $d['min_level'] ?></td><td><?= $d['boss_key']?:'—' ?></td><td><?= $rew['caps']??0 ?>💰</td><td class="actions"><a href="?action=dungeons&id=<?= $d['id'] ?>" class="btn btn-blue btn-sm">✏️</a><form method="POST" onsubmit="return confirm('Удалить?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" name="delete_dungeon" class="btn btn-red btn-sm">🗑️</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
<?php else: ?>
    <div style="display:flex; gap:10px; margin-bottom:10px;"><a href="?action=dungeons" class="btn btn-ghost">← Список</a></div>
    <div class="d-editor">
        <div class="d-grid-wrap">
            <div id="d-grid" class="d-grid" style="grid-template-columns: repeat(<?= $dMaxX-$dMinX+1 ?>, 28px);">
                <?php for($y=$dMaxY; $y>=$dMinY; $y--): for($x=$dMinX; $x<=$dMaxX; $x++): $node=null; foreach($dNodes as $n) if($n['pos_x']==$x && $n['pos_y']==$y) {$node=$n; break;} $cls='d-empty'; $cnt='+'; $nid=0; if($node){ $cls="d-{$node['tile_type']}"; $nid=$node['id']; switch($node['tile_type']){case'entrance':$cnt='🚪';break;case'boss':$cnt='💀';break;case'treasure':$cnt='💎';break;case'exit':$cnt='🏁';break;case'trap':$cnt='⚠️';break;} } ?>
                <div class="d-cell <?= $cls ?>" data-id="<?= $nid ?>" data-x="<?= $x ?>" data-y="<?= $y ?>" data-type="<?= $node['tile_type']??'' ?>" data-loc="<?= $node['location_id']??'' ?>" onclick="hCell(<?= $nid ?>, <?= $x ?>, <?= $y ?>)"><?= $cnt ?></div>
                <?php endfor; endfor; ?>
            </div>
        </div>
        <div class="d-panel">
            <h3 style="margin-bottom:10px;">Свойства ноды</h3>
            <div id="d-no-sel" style="color:var(--gray);font-size:12px;">Кликни на клетку. Пустая (+) создаст новую.</div>
            <div id="d-form" style="display:none;">
                <input type="hidden" id="d-nid">
                <div class="form-group"><label class="form-label">Тип</label><select class="form-select" id="d-type"><?php $types=['entrance'=>'Вход','corridor'=>'Коридор','room'=>'Комната','boss'=>'Босс','treasure'=>'Сокровище','exit'=>'Выход','trap'=>'Ловушка']; foreach($types as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Локация (Лут)</label><select class="form-select" id="d-loc"><option value="">— Нет —</option><?php foreach($locations as $l): ?><option value="<?= $l['id'] ?>"><?= $l['name'] ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-blue" style="width:100%" onclick="sProp()">💾 Сохранить</button>
                <button class="btn btn-red" style="width:100%; margin-top:5px;" onclick="dNode()">🗑️ Удалить</button>
            </div>
        </div>
    </div>
    <div class="card" style="margin-top:15px;"><h3 style="margin-bottom:10px;">⚙️ Настройки данжа</h3><form method="POST"><?= csrfField() ?><input type="hidden" name="id" value="<?= $editData['id'] ?>">
        <div class="form-row"><div><label class="form-label">Ключ</label><input class="form-input" name="dungeon_key" value="<?= $editData['dungeon_key'] ?>" required></div><div><label class="form-label">Название</label><input class="form-input" name="name" value="<?= htmlspecialchars($editData['name']) ?>" required></div></div>
        <div class="form-row"><div><label class="form-label">Мин. уровень</label><input class="form-input" type="number" name="min_level" value="<?= $editData['min_level'] ?>"></div><div><label class="form-label">Босс (ключ монстра)</label><input class="form-input" name="boss_key" value="<?= htmlspecialchars($editData['boss_key']) ?>"></div></div>
        <div class="form-row"><div><label class="form-label">Награда (Крышки)</label><input class="form-input" type="number" name="base_caps" value="<?= $editData['base_caps'] ?>"></div><div><label class="form-label">Лут (через запятую)</label><input class="form-input" name="loot_keys" value="<?= htmlspecialchars($editData['loot_keys']) ?>"></div></div>
        <button type="submit" name="save_dungeon" class="btn btn-green" style="margin-top:10px;">💾 Сохранить настройки</button>
    </form></div>
<?php endif; ?>

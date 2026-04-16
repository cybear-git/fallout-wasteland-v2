<div class="page-header"><div><h1><?= ucfirst($action) ?></h1><div class="subtitle"><?= $editData?'Редактирование':'Список' ?></div></div></div>
<div class="card">
    <?php if ($action !== 'logs' && $action !== 'users' && $action !== 'settings'): ?>
    <form method="POST"><?= csrfField() ?><input type="hidden" name="id" value="<?= $editData['id']??0 ?>">
    <div class="form-row"><div class="form-group"><label class="form-label">Ключ</label><input class="form-input" name="<?= $action==='monsters'?'monster_key':'item_key' ?>" value="<?= htmlspecialchars($editData['monster_key']??$editData['item_key']??($editData['location_key']??'')) ?>" required></div><div class="form-group"><label class="form-label">Название</label><input class="form-input" name="name" value="<?= htmlspecialchars($editData['name']??'') ?>" required></div></div>
    <!-- ... Остальные поля форм идентичны твоей версии, чтобы не дублировать код ... -->
    <button type="submit" name="save" class="btn btn-blue"><?= $editData?'💾 Сохранить':'➕ Добавить' ?></button>
    <?php if($editData): ?><form method="POST" style="display:inline;margin-left:10px;" onsubmit="return confirm('Удалить?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><button type="submit" name="delete" class="btn btn-red">🗑️</button></form><?php endif; ?>
    </form>
    <?php endif; ?>
    <!-- Таблица списка -->
    <table style="margin-top:20px;"><thead><tr><th>ID</th><th>Ключ</th><th>Имя</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
    <?php foreach($items as $i): ?><tr><td><?= $i['id'] ?></td><td><code><?= htmlspecialchars($i['monster_key']??$i['item_key']??$i['location_key']??'—') ?></code></td><td><?= htmlspecialchars($i['name']??'—') ?></td><td><?= ($i['is_active']??1)?'✅':'❌' ?></td><td><a href="?action=<?= $action ?>&id=<?= $i['id'] ?>" class="btn btn-blue btn-sm">✏️</a></td></tr><?php endforeach; ?>
    </tbody></table>
</div>

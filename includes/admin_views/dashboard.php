<div class="page-header"><div><h1>Панель управления</h1><div class="subtitle">Сводка по миру</div></div></div>
<div class="stats-grid">
    <div class="stat-card blue"><div>👥 Игроки</div><div class="value"><?= $stats['players'] ?></div></div>
    <div class="stat-card green"><div>👹 Монстры</div><div class="value"><?= $stats['monsters'] ?></div></div>
    <div class="stat-card"><div>🗺️ Клетки</div><div class="value"><?= number_format($stats['map_nodes']) ?></div></div>
    <div class="stat-card"><div>📐 Размер</div><div class="value"><?= $stats['map_width'] ?>×<?= $stats['map_height'] ?></div></div>
</div>

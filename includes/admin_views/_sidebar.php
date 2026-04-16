<nav class="sidebar">
    <div class="sidebar-header"><h2>☢️ Admin</h2></div>
    <div class="nav-section"><div class="nav-label">Главная</div><a href="?action=dashboard" class="nav-item <?= $action=='dashboard'?'active':'' ?>"><span class="icon">📊</span> <span>Дашборд</span></a></div>
    <div class="nav-section"><div class="nav-label">Контент</div>
        <a href="?action=monsters" class="nav-item <?= $action=='monsters'?'active':'' ?>"><span class="icon">👹</span> <span>Монстры</span><span class="badge"><?= $stats['monsters']??0 ?></span></a>
        <a href="?action=weapons" class="nav-item <?= $action=='weapons'?'active':'' ?>"><span class="icon">🔫</span> <span>Оружие</span></a>
        <a href="?action=armors" class="nav-item <?= $action=='armors'?'active':'' ?>"><span class="icon">🛡️</span> <span>Броня</span></a>
        <a href="?action=consumables" class="nav-item <?= $action=='consumables'?'active':'' ?>"><span class="icon">💊</span> <span>Расходники</span></a>
        <a href="?action=loot" class="nav-item <?= $action=='loot'?'active':'' ?>"><span class="icon">📦</span> <span>Лут</span></a>
        <a href="?action=locations" class="nav-item <?= $action=='locations'?'active':'' ?>"><span class="icon">🗺️</span> <span>Локации</span></a>
        <a href="?action=dungeons" class="nav-item <?= $action=='dungeons'?'active':'' ?>"><span class="icon">⚔️</span> <span>Данжи</span></a>
    </div>
    <div class="nav-section"><div class="nav-label">Система</div>
        <a href="?action=map" class="nav-item <?= $action=='map'?'active':'' ?>"><span class="icon">🌍</span> <span>Карта</span></a>
        <a href="?action=users" class="nav-item <?= $action=='users'?'active':'' ?>"><span class="icon">👥</span> <span>Игроки</span></a>
        <a href="?action=settings" class="nav-item <?= $action=='settings'?'active':'' ?>"><span class="icon">⚙️</span> <span>Настройки</span></a>
        <a href="?action=logs" class="nav-item <?= $action=='logs'?'active':'' ?>"><span class="icon">📜</span> <span>Логи</span></a>
        <a href="?action=logout" class="nav-item" style="color:var(--red);"><span class="icon">🚪</span> <span>Выход</span></a>
    </div>
</nav>

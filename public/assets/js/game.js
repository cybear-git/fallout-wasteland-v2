/**
 * PIP-BOY 3000 - Game Client JavaScript
 * Fallout Wasteland RPG
 */

// ============================================
// GLOBAL STATE
// ============================================
const GameState = {
    posX: 0,
    posY: 0,
    playerHP: 100,
    playerMaxHP: 100,
    radiation: 0,
    caps: 0,
    inCombat: false,
    currentCombatId: null,
    currentPanel: 'status',
    audioEnabled: false
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    initializeGame();
    setupKeyboardControls();
    setupAudioToggle();
    
    // CRT effect on load
    playCRTSound();
    showCRTTurnOnEffect();
});

function initializeGame() {
    // Load initial data from server if needed
    console.log('[PIP-BOY] System initialized');
}

// ============================================
// PANEL NAVIGATION
// ============================================
function showPanel(name) {
    document.querySelectorAll('.panel-content').forEach(p => {
        p.classList.add('hidden');
        p.style.opacity = '0';
    });
    
    const panel = document.getElementById('panel-' + name);
    if (panel) {
        panel.classList.remove('hidden');
        setTimeout(() => {
            panel.style.opacity = '1';
        }, 50);
    }
    
    GameState.currentPanel = name;
    
    // Update nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.getElementById('btn-' + name);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Load panel data
    loadPanelData(name);
    
    // Play click sound
    playClickSound();
}

function loadPanelData(name) {
    const loaders = {
        'inventory': loadInventory,
        'map': loadMap,
        'quests': loadQuests,
        'vendors': loadVendors,
        'crafting': loadCrafting,
        'factions': loadFactions,
        'dungeons': loadDungeons,
        'fasttravel': loadFastTravel
    };
    
    if (loaders[name]) {
        loaders[name]();
    }
}

// ============================================
// MOVEMENT SYSTEM
// ============================================
function move(dx, dy) {
    if (GameState.inCombat) {
        showAlert('⚠️ Нельзя уйти во время боя!');
        playErrorSound();
        return;
    }
    
    fetch('/api/move.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ dx, dy })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            GameState.posX = data.player.pos_x;
            GameState.posY = data.player.pos_y;
            updatePlayerStats(data.player);
            updateLocationDisplay(data.player);
            addLog(data.message);
            
            if (data.quote) {
                addLog('<i class="quote">"' + data.quote + '"</i>');
            }
            
            if (data.monster_encounter) {
                startCombat(data.monster_encounter);
            }
            
            playMoveSound();
        } else {
            showAlert(data.error || 'Ошибка перемещения');
            playErrorSound();
        }
    })
    .catch(e => {
        showAlert('Ошибка сети');
        console.error('Move error:', e);
    });
}

function updateLocationDisplay(player) {
    const locName = document.getElementById('loc-name');
    const coords = document.querySelector('.location-coords');
    
    if (locName) locName.textContent = player.location_name || 'Пустошь';
    if (coords) coords.textContent = `(${player.pos_x}, ${player.pos_y})`;
}

function updatePlayerStats(player) {
    GameState.playerHP = player.hp || GameState.playerHP;
    GameState.playerMaxHP = player.max_hp || GameState.playerMaxHP;
    GameState.radiation = player.radiation || 0;
    GameState.caps = player.caps || 0;
    
    // Update HP bar
    const hpFill = document.getElementById('hp-fill');
    const hpValue = document.querySelector('.hp-container .bar-value');
    if (hpFill && GameState.playerMaxHP > 0) {
        const pct = Math.max(0, Math.min(100, (GameState.playerHP / GameState.playerMaxHP) * 100));
        hpFill.style.width = pct + '%';
        hpFill.setAttribute('style', `width: ${pct}%`);
    }
    if (hpValue) {
        hpValue.textContent = `${GameState.playerHP}/${GameState.playerMaxHP}`;
    }
    
    // Update radiation bar
    const radFill = document.getElementById('rad-fill');
    const radValue = document.querySelector('.radiation-container .bar-value');
    if (radFill) {
        const radPct = Math.max(0, Math.min(100, GameState.radiation));
        radFill.style.width = radPct + '%';
    }
    if (radValue) {
        radValue.textContent = GameState.radiation;
    }
    
    // Update caps display
    const capsDisplay = document.querySelector('.caps-display');
    if (capsDisplay) {
        capsDisplay.textContent = `💰 ${GameState.caps}`;
    }
    
    // Check for low HP warning
    if (GameState.playerHP < GameState.playerMaxHP * 0.3) {
        showLowHPWarning();
    }
}

// ============================================
// SEARCH SYSTEM
// ============================================
function performSearch() {
    if (GameState.inCombat) {
        showAlert('⚠️ Нельзя искать во время боя!');
        playErrorSound();
        return;
    }
    
    fetch('/api/search.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message || 'Ничего не найдено');
            
            if (data.quote) {
                addLog('<i class="quote">"' + data.quote + '"</i>');
            }
            
            if (data.found_item) {
                showFoundItemModal(data.found_item);
            }
            
            if (data.monster_encounter) {
                startCombat(data.monster_encounter);
            }
            
            if (data.xp_gained) {
                addLog(`+${data.xp_gained} XP`);
                showXPGainAnimation(data.xp_gained);
            }
            
            playSearchSound();
        } else {
            showAlert(data.error || 'Ошибка поиска');
            playErrorSound();
        }
    })
    .catch(e => {
        showAlert('Ошибка сети');
        console.error('Search error:', e);
    });
}

// ============================================
// COMBAT SYSTEM
// ============================================
function startCombat(encounter) {
    GameState.inCombat = true;
    GameState.currentCombatId = encounter.combat_id;
    
    const modal = document.getElementById('combat-modal');
    if (modal) {
        modal.classList.remove('hidden');
        
        // Set monster info
        document.getElementById('combat-monster-name').textContent = encounter.monster_name || 'Враг';
        document.getElementById('monster-hp-text').textContent = `${encounter.monster_hp}/${encounter.monster_max_hp}`;
        
        // Update monster HP bar
        const hpPct = (encounter.monster_hp / encounter.monster_max_hp) * 100;
        document.getElementById('monster-hp-fill').style.width = hpPct + '%';
        
        // Clear combat log
        const log = document.getElementById('combat-log');
        if (log) log.innerHTML = '<div class="combat-log-entry">⚔️ Бой начался!</div>';
        
        playCombatStartSound();
    }
}

function combatAttack(type) {
    if (!GameState.currentCombatId) return;
    
    fetch('/api/combat_attack.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ 
            combat_id: GameState.currentCombatId,
            attack_type: type 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update combat log
            const log = document.getElementById('combat-log');
            if (log && data.log_entry) {
                log.innerHTML += `<div class="combat-log-entry">${data.log_entry}</div>`;
                log.scrollTop = log.scrollHeight;
            }
            
            // Update monster HP
            if (data.monster_hp !== undefined && data.monster_max_hp !== undefined) {
                const hpText = document.getElementById('monster-hp-text');
                const hpFill = document.getElementById('monster-hp-fill');
                if (hpText) hpText.textContent = `${data.monster_hp}/${data.monster_max_hp}`;
                if (hpFill) {
                    const pct = (data.monster_hp / data.monster_max_hp) * 100;
                    hpFill.style.width = pct + '%';
                }
            }
            
            // Update player HP
            if (data.player_hp !== undefined) {
                GameState.playerHP = data.player_hp;
                updatePlayerStats({ hp: data.player_hp });
            }
            
            // Check if combat ended
            if (data.combat_ended) {
                endCombat(data);
            }
            
            playAttackSound(type);
        } else {
            showAlert(data.error || 'Ошибка атаки');
        }
    })
    .catch(e => {
        showAlert('Ошибка сети');
        console.error('Combat attack error:', e);
    });
}

function combatFlee() {
    if (!GameState.currentCombatId) return;
    
    fetch('/api/combat_flee.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ combat_id: GameState.currentCombatId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            endCombat(data);
        } else {
            showAlert(data.error || 'Не удалось сбежать');
            // Enemy turn still happens
        }
    })
    .catch(e => {
        showAlert('Ошибка сети');
    });
}

function endCombat(result) {
    GameState.inCombat = false;
    GameState.currentCombatId = null;
    
    const modal = document.getElementById('combat-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    
    if (result.victory) {
        addLog('🏆 Победа!');
        if (result.xp_gained) {
            addLog(`+${result.xp_gained} XP`);
            showXPGainAnimation(result.xp_gained);
        }
        if (result.loot) {
            addLog(`📦 Получено: ${result.loot}`);
        }
        playVictorySound();
    } else if (result.fled) {
        addLog('🏃 Вы сбежали!');
        playFleeSound();
    } else if (result.defeat) {
        addLog('💀 Вы погибли...');
        playDefeatSound();
    }
}

// ============================================
// INVENTORY SYSTEM
// ============================================
function loadInventory() {
    const div = document.getElementById('inventory-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка...</div>';
    
    fetch('/api/inventory.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.inventory && data.inventory.length > 0) {
                let html = '<ul class="inv-items">';
                data.inventory.forEach(item => {
                    const scrapBtn = (item.item_type === 'loot' || item.item_type === 'weapon' || item.item_type === 'armor') 
                        ? `<button class="scrap-btn" onclick="scrapItem(${item.id}, '${item.item_key.replace(/'/g, "\\'")}')">🗑️</button>` 
                        : '';
                    
                    const useBtn = (item.item_type === 'consumable')
                        ? `<button class="use-btn" onclick="useItem(${item.id})">💊</button>`
                        : '';
                    
                    const equipBtn = (item.item_type === 'weapon' || item.item_type === 'armor')
                        ? `<button class="equip-btn" onclick="equipItem(${item.id})">⚔️</button>`
                        : '';
                    
                    html += `
                        <li class="inv-item">
                            <span class="inv-icon">${getItemIcon(item.item_type)}</span>
                            <div class="inv-info">
                                <div class="inv-name">${escapeHtml(item.name)}</div>
                                <div class="inv-desc">${escapeHtml(item.description || '')}</div>
                            </div>
                            <div class="inv-actions">
                                ${useBtn}${equipBtn}${scrapBtn}
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">📦 Инвентарь пуст</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
            console.error('Inventory error:', e);
        });
}

function getItemIcon(type) {
    const icons = {
        'weapon': '🗡️',
        'armor': '🛡️',
        'consumable': '💊',
        'loot': '📦',
        'misc': '🔧'
    };
    return icons[type] || '📄';
}

function useItem(itemId) {
    fetch('/api/inventory.php?action=use', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ item_id: itemId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            loadInventory(); // Refresh
            if (data.player) updatePlayerStats(data.player);
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

function equipItem(itemId) {
    fetch('/api/inventory.php?action=equip', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ item_id: itemId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            loadInventory();
            loadEquipment();
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

function scrapItem(itemId, itemKey) {
    if (!confirm('Разобрать предмет на компоненты?')) return;
    
    fetch('/api/inventory.php?action=scrap', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ item_id: itemId, item_key: itemKey })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            loadInventory();
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

function loadEquipment() {
    fetch('/api/inventory.php?action=equipment')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('equip-weapon').textContent = data.weapon || '—';
                document.getElementById('equip-armor').textContent = data.armor || '—';
                document.getElementById('equip-consumable').textContent = data.consumable || '—';
            }
        })
        .catch(e => console.error('Equipment error:', e));
}

// ============================================
// MAP SYSTEM
// ============================================
function loadMap() {
    const grid = document.getElementById('map-grid');
    if (!grid) return;
    
    grid.innerHTML = '<div class="loading">Загрузка карты...</div>';
    
    fetch('/api/map.php?action=view&radius=4')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderMapGrid(data.grid, data.player_x, data.player_y);
            } else {
                grid.innerHTML = '<div class="error-state">Ошибка загрузки карты</div>';
            }
        })
        .catch(e => {
            grid.innerHTML = '<div class="error-state">Ошибка сети</div>';
            console.error('Map error:', e);
        });
}

function renderMapGrid(grid, playerX, playerY) {
    const container = document.getElementById('map-grid');
    if (!container || !grid) return;
    
    container.innerHTML = '';
    container.className = 'map-grid';
    
    const size = grid.length;
    
    for (let y = 0; y < size; y++) {
        const row = document.createElement('div');
        row.className = 'map-row';
        
        for (let x = 0; x < size; x++) {
            const cell = document.createElement('div');
            cell.className = 'map-cell';
            
            const cellData = grid[y][x];
            
            if (cellData) {
                if (x === playerX % size && y === playerY % size) {
                    cell.classList.add('player');
                    cell.textContent = '🧭';
                } else if (cellData.has_monster) {
                    cell.classList.add('danger');
                    cell.textContent = '💀';
                } else if (cellData.has_loot) {
                    cell.classList.add('loot');
                    cell.textContent = '📦';
                } else if (cellData.radiation > 0) {
                    cell.classList.add('radiation');
                    cell.textContent = '☢️';
                } else {
                    cell.classList.add(getTileClass(cellData.tile_type));
                    cell.textContent = getTileSymbol(cellData.tile_type);
                }
            }
            
            row.appendChild(cell);
        }
        
        container.appendChild(row);
    }
}

function getTileClass(type) {
    const classes = {
        'wasteland': 'tile-wasteland',
        'city': 'tile-city',
        'forest': 'tile-forest',
        'desert': 'tile-desert',
        'mountain': 'tile-mountain',
        'ruins': 'tile-ruins',
        'vault': 'tile-vault',
        'dungeon': 'tile-dungeon'
    };
    return classes[type] || 'tile-empty';
}

function getTileSymbol(type) {
    const symbols = {
        'wasteland': '·',
        'city': '▓',
        'forest': '♣',
        'desert': '○',
        'mountain': '▲',
        'ruins': '░',
        'vault': '■',
        'dungeon': '◈'
    };
    return symbols[type] || '·';
}

// ============================================
// QUESTS SYSTEM
// ============================================
function loadQuests() {
    const div = document.getElementById('quests-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка квестов...</div>';
    
    fetch('/api/quests.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.quests && data.quests.length > 0) {
                let html = '<ul class="quest-list">';
                data.quests.forEach(q => {
                    html += `
                        <li class="quest-item ${q.status}">
                            <div class="quest-title">${escapeHtml(q.title)}</div>
                            <div class="quest-desc">${escapeHtml(q.description || '')}</div>
                            <div class="quest-progress">
                                <span class="quest-status">${getStatusBadge(q.status)}</span>
                                ${q.progress ? `<span class="quest-progress-text">${q.progress}</span>` : ''}
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">📋 Нет активных квестов</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function getStatusBadge(status) {
    const badges = {
        'active': '🟡 Активен',
        'completed': '🟢 Завершен',
        'failed': '🔴 Провален'
    };
    return badges[status] || status;
}

// ============================================
// VENDORS SYSTEM
// ============================================
function loadVendors() {
    const div = document.getElementById('vendors-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка торговцев...</div>';
    
    fetch('/api/vendors.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.vendors && data.vendors.length > 0) {
                let html = '<ul class="vendor-list">';
                data.vendors.forEach(v => {
                    html += `
                        <li class="vendor-item">
                            <div class="vendor-name">${escapeHtml(v.name)}</div>
                            <div class="vendor-location">📍 ${escapeHtml(v.location)}</div>
                            <button class="btn-trade" onclick="openTrade(${v.id})">💰 Торговать</button>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">💰 Нет доступных торговцев</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function openTrade(vendorId) {
    showAlert('Торговля с vendor #' + vendorId);
    // TODO: Implement trade modal
}

// ============================================
// CRAFTING SYSTEM
// ============================================
function loadCrafting() {
    const div = document.getElementById('crafting-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка рецептов...</div>';
    
    fetch('/api/crafting.php?action=recipes')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.recipes && data.recipes.length > 0) {
                let html = '<ul class="crafting-list">';
                data.recipes.forEach(r => {
                    html += `
                        <li class="crafting-item">
                            <div class="crafting-name">${escapeHtml(r.name)}</div>
                            <div class="crafting-requirements">${escapeHtml(r.requirements)}</div>
                            <button class="btn-craft" onclick="craftItem(${r.id})">🔨 Создать</button>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">🔨 Нет доступных рецептов</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function craftItem(recipeId) {
    fetch('/api/crafting.php?action=craft', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ recipe_id: recipeId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            loadCrafting();
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

// ============================================
// FACTIONS SYSTEM
// ============================================
function loadFactions() {
    const div = document.getElementById('factions-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка фракций...</div>';
    
    fetch('/api/factions.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.factions && data.factions.length > 0) {
                let html = '<ul class="faction-list">';
                data.factions.forEach(f => {
                    html += `
                        <li class="faction-item">
                            <div class="faction-name">${escapeHtml(f.name)}</div>
                            <div class="faction-reputation">
                                ${getReputationStars(f.reputation)} 
                                <span class="rep-value">${f.reputation || 0}</span>
                            </div>
                            <div class="faction-desc">${escapeHtml(f.description || '')}</div>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">🎖️ Нет информации о фракциях</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function getReputationStars(rep) {
    if (rep >= 100) return '⭐⭐⭐⭐⭐';
    if (rep >= 75) return '⭐⭐⭐⭐';
    if (rep >= 50) return '⭐⭐⭐';
    if (rep >= 25) return '⭐⭐';
    if (rep > 0) return '⭐';
    return '☆';
}

// ============================================
// DUNGEONS SYSTEM
// ============================================
function loadDungeons() {
    const div = document.getElementById('dungeons-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка подземелий...</div>';
    
    fetch('/api/dungeons.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.dungeons && data.dungeons.length > 0) {
                let html = '<ul class="dungeon-list">';
                data.dungeons.forEach(d => {
                    html += `
                        <li class="dungeon-item">
                            <div class="dungeon-header">
                                <span class="dungeon-name">${escapeHtml(d.name)}</span>
                                <span class="dungeon-level">Ур. ${d.level || '?'}</span>
                            </div>
                            <div class="dungeon-desc">${escapeHtml(d.description || 'Описание отсутствует')}</div>
                            <button class="btn-enter" onclick="enterDungeon(${d.id})">🚪 Войти</button>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">🏰 Нет доступных подземелий</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function enterDungeon(dungeonId) {
    fetch('/api/dungeons.php?action=enter', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ dungeon_id: dungeonId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            // Reload location
            location.reload();
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

// ============================================
// FAST TRAVEL SYSTEM
// ============================================
function loadFastTravel() {
    const div = document.getElementById('fasttravel-list');
    if (!div) return;
    
    div.innerHTML = '<div class="loading">Загрузка точек...</div>';
    
    fetch('/api/fast_travel.php?action=points')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.points && data.points.length > 0) {
                let html = '<ul class="fasttravel-list">';
                data.points.forEach(p => {
                    html += `
                        <li class="fasttravel-item">
                            <div class="fasttravel-name">${escapeHtml(p.name)}</div>
                            <div class="fasttravel-coords">📍 (${p.pos_x}, ${p.pos_y})</div>
                            <div class="fasttravel-desc">${escapeHtml(p.description || '')}</div>
                            <button class="btn-travel" onclick="fastTravel(${p.id})">⚡ Переместиться</button>
                        </li>
                    `;
                });
                html += '</ul>';
                div.innerHTML = html;
            } else {
                div.innerHTML = '<div class="empty-state">⚡ Нет доступных точек</div>';
            }
        })
        .catch(e => {
            div.innerHTML = '<div class="error-state">Ошибка загрузки</div>';
        });
}

function fastTravel(pointId) {
    fetch('/api/fast_travel.php?action=travel', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ point_id: pointId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addLog(data.message);
            if (data.player) {
                GameState.posX = data.player.pos_x;
                GameState.posY = data.player.pos_y;
                updatePlayerStats(data.player);
                updateLocationDisplay(data.player);
            }
        } else {
            showAlert(data.error);
        }
    })
    .catch(e => showAlert('Ошибка'));
}

// ============================================
// UI UTILITIES
// ============================================
function showAlert(message) {
    // Create or get alert container
    let container = document.getElementById('alert-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'alert-container';
        container.className = 'alert-container';
        document.body.appendChild(container);
    }
    
    const alert = document.createElement('div');
    alert.className = 'alert-toast';
    alert.textContent = message;
    
    container.appendChild(alert);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

function addLog(message) {
    const log = document.getElementById('history-log');
    if (!log) return;
    
    const entry = document.createElement('div');
    entry.className = 'log-entry';
    const time = new Date().toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit'});
    entry.innerHTML = `<small>[${time}]</small> ${message}`;
    
    log.appendChild(entry);
    log.scrollTop = log.scrollHeight;
}

function showFoundItemModal(item) {
    const modal = document.getElementById('found-modal');
    if (!modal) return;
    
    document.getElementById('found-item-name').textContent = item.name || 'Предмет';
    document.getElementById('found-item-desc').textContent = item.description || '';
    document.getElementById('found-message').textContent = `Вы нашли: ${item.name}`;
    
    modal.classList.remove('hidden');
    playFoundSound();
}

function closeFoundModal() {
    const modal = document.getElementById('found-modal');
    if (modal) modal.classList.add('hidden');
}

function showLowHPWarning() {
    const warning = document.createElement('div');
    warning.className = 'shock-warning';
    warning.textContent = '⚠️ НИЗКОЕ ЗДОРОВЬЕ ⚠️';
    
    const container = document.querySelector('.pipboy-main');
    if (container) {
        container.insertBefore(warning, container.firstChild);
        setTimeout(() => warning.remove(), 3000);
    }
}

function showXPGainAnimation(xp) {
    const overlay = document.createElement('div');
    overlay.className = 'xp-gain-overlay';
    overlay.textContent = `+${xp} XP`;
    
    document.body.appendChild(overlay);
    setTimeout(() => overlay.remove(), 1500);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// KEYBOARD CONTROLS
// ============================================
function setupKeyboardControls() {
    document.addEventListener('keydown', (e) => {
        // Arrow keys for movement
        switch(e.key) {
            case 'ArrowUp':
            case 'w':
            case 'W':
                e.preventDefault();
                move(0, -1);
                break;
            case 'ArrowDown':
            case 's':
            case 'S':
                e.preventDefault();
                move(0, 1);
                break;
            case 'ArrowLeft':
            case 'a':
            case 'A':
                e.preventDefault();
                move(-1, 0);
                break;
            case 'ArrowRight':
            case 'd':
            case 'D':
                e.preventDefault();
                move(1, 0);
                break;
            case ' ':
                e.preventDefault();
                performSearch();
                break;
            case 'Escape':
                closeFoundModal();
                break;
        }
    });
}

// ============================================
// AUDIO SYSTEM (Optional)
// ============================================
function setupAudioToggle() {
    const btn = document.getElementById('audio-toggle');
    if (btn) {
        btn.addEventListener('click', () => {
            GameState.audioEnabled = !GameState.audioEnabled;
            btn.textContent = GameState.audioEnabled ? '🔊' : '🔇';
        });
    }
}

function playCRTSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playClickSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playMoveSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playSearchSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playAttackSound(type) {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playFoundSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playCombatStartSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playVictorySound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playDefeatSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playFleeSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

function playErrorSound() {
    if (!GameState.audioEnabled) return;
    // TODO: Implement audio
}

// ============================================
// CRT EFFECTS
// ============================================
function showCRTTurnOnEffect() {
    const container = document.querySelector('.pipboy-container');
    if (container) {
        container.classList.add('crt-turn-on');
        setTimeout(() => {
            container.classList.remove('crt-turn-on');
        }, 500);
    }
}

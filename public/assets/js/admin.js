/**
 * Admin Panel JavaScript - Fallout: Wasteland
 * Модульная структура для управления AJAX-запросами и UI
 */

// ════════════ CONFIG ════════════
const AdminConfig = {
    ajaxUrl: 'admin.php',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
    debounceDelay: 300,
    modalOverlayClass: 'modal-overlay'
};

// ════════════ CSRF UTILS ════════════
function getCsrfToken() {
    return document.querySelector('input[name="csrf_token"]')?.value || AdminConfig.csrfToken;
}

// ════════════ AJAX HELPER ════════════
async function adminAjax(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('csrf_token', getCsrfToken());
    
    for (const [key, value] of Object.entries(data)) {
        formData.append(key, value);
    }
    
    try {
        const response = await fetch(AdminConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('AJAX Error:', error);
        showAlert('Ошибка соединения с сервером', 'error');
        return null;
    }
}

// ════════════ UI HELPERS ════════════
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const mainContent = document.querySelector('.main');
    const existingAlert = mainContent.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.style.transition = 'opacity 0.3s';
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// ════════════ DUNGEON EDITOR ════════════
class DungeonEditor {
    constructor(dungeonId, gridElement) {
        this.dungeonId = dungeonId;
        this.gridElement = gridElement;
        this.selectedCell = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        this.gridElement.addEventListener('click', (e) => {
            const cell = e.target.closest('.dungeon-cell');
            if (cell) {
                this.selectCell(cell);
            }
        });
    }
    
    selectCell(cell) {
        // Remove previous selection
        document.querySelectorAll('.dungeon-cell.selected').forEach(c => {
            c.classList.remove('selected');
        });
        
        cell.classList.add('selected');
        this.selectedCell = cell;
        
        // Update form with cell data
        const nodeId = cell.dataset.nodeId;
        const tileType = cell.dataset.tileType;
        const locationId = cell.dataset.locationId;
        
        document.getElementById('edit_node_id')?.setValue(nodeId || '');
        document.getElementById('edit_tile_type')?.setValue(tileType || 'corridor');
        document.getElementById('edit_location_id')?.setValue(locationId || '');
    }
    
    async addNode(x, y) {
        const result = await adminAjax('dungeons', {
            ajax_action: 'add_node',
            dungeon_id: this.dungeonId,
            x: x,
            y: y
        });
        
        if (result?.success) {
            showAlert('Нода добавлена', 'success');
            this.renderCell(x, y, result.id);
        }
    }
    
    async updateNode(nodeId, tileType, locationId = 0) {
        const result = await adminAjax('dungeons', {
            ajax_action: 'update_node',
            node_id: nodeId,
            tile_type: tileType,
            location_id: locationId
        });
        
        if (result?.success) {
            showAlert('Нода обновлена', 'success');
            this.updateCellVisual(nodeId, tileType);
        }
    }
    
    async deleteNode(nodeId) {
        if (!confirm('Удалить эту ноду?')) return;
        
        const result = await adminAjax('dungeons', {
            ajax_action: 'delete_node',
            node_id: nodeId,
            dungeon_id: this.dungeonId
        });
        
        if (result?.success) {
            showAlert('Нода удалена', 'success');
            this.removeCell(nodeId);
        }
    }
    
    renderCell(x, y, nodeId) {
        // Implementation depends on grid structure
        console.log('Render cell:', { x, y, nodeId });
    }
    
    updateCellVisual(nodeId, tileType) {
        const cell = document.querySelector(`[data-node-id="${nodeId}"]`);
        if (cell) {
            cell.dataset.tileType = tileType;
            cell.className = `dungeon-cell ${tileType}`;
        }
    }
    
    removeCell(nodeId) {
        const cell = document.querySelector(`[data-node-id="${nodeId}"]`);
        if (cell) {
            cell.remove();
        }
    }
}

// ════════════ MAP EDITOR ════════════
class MapEditor {
    constructor(containerElement) {
        this.container = containerElement;
        this.selectedCell = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        this.container.addEventListener('click', (e) => {
            const cell = e.target.closest('.map-cell');
            if (cell) {
                this.selectCell(cell);
            }
        });
    }
    
    selectCell(cell) {
        document.querySelectorAll('.map-cell.selected').forEach(c => {
            c.classList.remove('selected');
        });
        
        cell.classList.add('selected');
        this.selectedCell = cell;
        
        const nodeId = cell.dataset.nodeId;
        const locationId = cell.dataset.locationId;
        const dangerLevel = cell.dataset.dangerLevel;
        const radiationLevel = cell.dataset.radiationLevel;
        
        // Populate edit form
        const form = document.getElementById('map-edit-form');
        if (form) {
            form.querySelector('[name="node_id"]')?.setValue(nodeId || '');
            form.querySelector('[name="location_id"]')?.setValue(locationId || '');
            form.querySelector('[name="danger_level"]')?.setValue(dangerLevel || '1');
            form.querySelector('[name="radiation_level"]')?.setValue(radiationLevel || '0');
        }
        
        showModal('map-edit-modal');
    }
    
    highlightDungeons() {
        document.querySelectorAll('.map-cell[data-dungeon-id]').forEach(cell => {
            cell.style.border = '2px solid #ff3b30';
        });
    }
}

// ════════════ CRUD OPERATIONS ════════════
async function handleCrudSubmit(formElement, actionType, recordId = 0) {
    const formData = new FormData(formElement);
    formData.append('csrf_token', getCsrfToken());
    
    if (recordId > 0) {
        formData.append('id', recordId);
    }
    
    try {
        const response = await fetch(AdminConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            showAlert('Сохранено успешно', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Submit Error:', error);
        showAlert('Ошибка соединения', 'error');
    }
}

async function deleteRecord(actionType, recordId) {
    if (!confirm('Вы уверены? Это действие нельзя отменить.')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('id', recordId);
    
    const deleteAction = `delete_${actionType.replace(/s$/, '')}`;
    formData.append(deleteAction, '1');
    
    try {
        const response = await fetch(AdminConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            showAlert('Удалено успешно', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Delete Error:', error);
        showAlert('Ошибка соединения', 'error');
    }
}

// ════════════ INITIALIZATION ════════════
document.addEventListener('DOMContentLoaded', () => {
    // Initialize dungeon editor if on dungeon edit page
    const dungeonGrid = document.querySelector('.dungeon-grid');
    const dungeonId = dungeonGrid?.dataset.dungeonId;
    if (dungeonGrid && dungeonId) {
        new DungeonEditor(dungeonId, dungeonGrid);
    }
    
    // Initialize map editor if on map page
    const mapContainer = document.querySelector('.map-container');
    if (mapContainer) {
        new MapEditor(mapContainer);
    }
    
    // Bind form submissions
    document.querySelectorAll('.crud-form').forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const actionType = form.dataset.action || 'save';
            const recordId = form.dataset.id || 0;
            handleCrudSubmit(form, actionType, parseInt(recordId));
        });
    });
    
    // Bind delete buttons
    document.querySelectorAll('[data-delete-action]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const actionType = btn.dataset.deleteAction;
            const recordId = btn.dataset.deleteId;
            deleteRecord(actionType, parseInt(recordId));
        });
    });
    
    // Modal close handlers
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = '';
            }
        });
    });
    
    console.log('Admin panel initialized');
});

// Polyfill for setValue if needed
if (!HTMLInputElement.prototype.setValue) {
    HTMLInputElement.prototype.setValue = function(value) {
        this.value = value;
        return this;
    };
}

if (!HTMLSelectElement.prototype.setValue) {
    HTMLSelectElement.prototype.setValue = function(value) {
        this.value = value;
        return this;
    };
}

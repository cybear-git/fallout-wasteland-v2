<?php
/**
 * Admin Core Library
 * Обеспечивает проверку прав, логирование действий и безопасность админ-панели
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

/**
 * Проверяет, является ли текущий пользователь администратором
 * @return array|null Возвращает данные роли или null если нет прав
 */
function checkAdminAccess() {
    $pdo = getDbConnection();
    $player = getCurrentPlayer();
    
    if (!$player) {
        return null; // Не авторизован
    }

    $stmt = $pdo->prepare("
        SELECT ar.id, ar.name, ar.permissions 
        FROM admin_roles ar
        JOIN players p ON p.admin_role_id = ar.id
        WHERE p.id = ?
    ");
    $stmt->execute([$player['id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        return null; // Нет роли админа
    }

    return $role;
}

/**
 * Проверяет наличие конкретного права у администратора
 * @param string $permission Имя права (например, 'ban', 'edit_items')
 * @return bool
 */
function hasPermission($permission) {
    $role = checkAdminAccess();
    
    if (!$role) {
        return false;
    }

    $permissions = json_decode($role['permissions'], true);
    
    // Супер-админ имеет все права
    if (isset($permissions['all']) && $permissions['all'] === true) {
        return true;
    }

    return isset($permissions[$permission]) && $permissions[$permission] === true;
}

/**
 * Логирует действие администратора
 * @param string $action Код действия (например, 'BAN_PLAYER')
 * @param int|null $targetId ID цели действия
 * @param array $details Детали действия в виде массива
 * @return void
 */
function logAdminAction($action, $targetId = null, $details = []) {
    $pdo = getDbConnection();
    $player = getCurrentPlayer();
    
    if (!$player) {
        return; // Нельзя логировать без игрока
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address, user_agent)
        VALUES (:admin_id, :action, :target_id, :details, :ip, :ua)
    ");

    $stmt->execute([
        ':admin_id' => $player['id'],
        ':action' => $action,
        ':target_id' => $targetId,
        ':details' => json_encode($details),
        ':ip' => $ip,
        ':ua' => $ua
    ]);
}

/**
 * Получает список всех игроков для админки
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getAdminPlayersList($limit = 50, $offset = 0) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT p.id, p.username, p.email, p.created_at, p.last_login, 
               c.level, c.xp, c.hp, c.max_hp, c.caps,
               ar.name as role_name
        FROM players p
        LEFT JOIN characters c ON c.player_id = p.id
        LEFT JOIN admin_roles ar ON p.admin_role_id = ar.id
        ORDER BY p.last_login DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Банит/Разбанивает игрока
 * @param int $targetPlayerId
 * @param bool $isBanned
 * @param string $reason
 * @return bool
 */
function togglePlayerBan($targetPlayerId, $isBanned, $reason = '') {
    $pdo = getDbConnection();
    
    // Блокируем доступ к аккаунту
    $stmt = $pdo->prepare("UPDATE players SET is_banned = ?, ban_reason = ? WHERE id = ?");
    $result = $stmt->execute([$isBanned ? 1 : 0, $reason, $targetPlayerId]);

    if ($result) {
        // Если бан - разлогиниваем сессию (опционально можно добавить таблицу активных сессий)
        logAdminAction($isBanned ? 'BAN_PLAYER' : 'UNBAN_PLAYER', $targetPlayerId, ['reason' => $reason]);
    }

    return $result;
}

/**
 * Выдает предмет игроку
 * @param int $targetPlayerId
 * @param int $itemId
 * @param int $quantity
 * @return bool|string True при успехе, строка ошибки при неудаче
 */
function giveItemToPlayer($targetPlayerId, $itemId, $quantity) {
    $pdo = getDbConnection();
    
    // Проверка существования предмета
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return "Предмет не найден";
    }

    // Находим персонажа игрока
    $stmt = $pdo->prepare("SELECT id FROM characters WHERE player_id = ?");
    $stmt->execute([$targetPlayerId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        return "У игрока нет персонажа";
    }

    try {
        $pdo->beginTransaction();

        // Добавляем предмет в инвентарь
        $stmt = $pdo->prepare("
            INSERT INTO character_items (character_id, item_id, quantity)
            VALUES (:char_id, :item_id, :qty)
            ON DUPLICATE KEY UPDATE quantity = quantity + :qty
        ");
        $stmt->execute([
            ':char_id' => $character['id'],
            ':item_id' => $itemId,
            ':qty' => $quantity
        ]);

        $pdo->commit();
        
        logAdminAction('GIVE_ITEM', $targetPlayerId, [
            'item_id' => $itemId,
            'item_name' => $item['name'],
            'quantity' => $quantity
        ]);

        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return "Ошибка БД: " . $e->getMessage();
    }
}

/**
 * Изменяет настройки игры
 * @param string $settingKey
 * @param mixed $newValue
 * @return bool|string
 */
function updateGameSetting($settingKey, $newValue) {
    $pdo = getDbConnection();

    // Проверка существования настройки
    $stmt = $pdo->prepare("SELECT id FROM game_settings WHERE setting_key = ?");
    $stmt->execute([$settingKey]);
    if (!$stmt->fetch()) {
        return "Настройка не найдена";
    }

    $stmt = $pdo->prepare("UPDATE game_settings SET setting_value = ? WHERE setting_key = ?");
    $result = $stmt->execute([$newValue, $settingKey]);

    if ($result) {
        logAdminAction('CHANGE_SETTING', null, [
            'key' => $settingKey,
            'new_value' => $newValue
        ]);
    }

    return $result;
}

/**
 * Получает последние логи действий админов
 * @param int $limit
 * @return array
 */
function getAdminLogs($limit = 100) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT al.*, p.username as admin_name
        FROM admin_logs al
        JOIN players p ON al.admin_id = p.id
        ORDER BY al.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

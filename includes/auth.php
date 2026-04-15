<?php
declare(strict_types=1);

/**
 * Аутентификация и авторизация
 * 
 * @package FalloutWasteland
 */

/**
 * Проверка CSRF-токена
 * UPDATED: Добавлена строгая типизация параметров
 */
function validateCsrfToken(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Генерация CSRF-токена
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Обновление CSRF-токена (после логина)
 */
function regenerateCsrfToken(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Безопасный выход из админ-панели
 * UPDATED: Улучшена обработка ошибок и проверка сессии
 */
function adminLogout(): void {
    session_name('fw_adm_ssid');
    
    // Проверяем, не уничтожена ли сессия уже
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Логируем выход
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $pdo = getDbConnection();
        $adminId = $_SESSION['admin_id'] ?? 0;
        
        if ($adminId > 0) {
            $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, table_name, ip_address) VALUES (?, 'logout', 'session', ?)");
            $stmt->execute([$adminId, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        }
    } catch (Exception $e) {
        // Игнорируем ошибки логирования при выходе, но пишем в error_log
        error_log("Admin logout logging failed: " . $e->getMessage());
    }
    
    // Уничтожаем сессию
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"] ?? false,
            $params["httponly"] ?? true
        );
    }
    
    session_destroy();
    header('Location: admin_login.php?logged_out=1');
    exit;
}

/**
 * Проверка прав администратора
 * UPDATED: Исправлен запрос - используем таблицу admins вместо players
 * 
 * @param PDO $pdo Подключение к БД
 * @param int $adminId ID администратора
 * @return bool true если админ активен
 */
function checkAdminRights(PDO $pdo, int $adminId): bool {
    $stmt = $pdo->prepare("SELECT role, is_active FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    return $admin && $admin['role'] === 'admin' && $admin['is_active'] == 1;
}

/**
 * Логирование действия администратора
 * UPDATED: Добавлена проверка существования таблицы перед логированием
 * 
 * @param PDO $pdo Подключение к БД
 * @param int $adminId ID администратора
 * @param string $action Действие
 * @param string $table Таблица
 * @param int|null $recordId ID записи
 */
function logAdminAction(PDO $pdo, int $adminId, string $action, string $table, ?int $recordId = null): void {
    try {
        // Проверяем существование таблицы admin_logs
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($stmt->rowCount() === 0) {
            error_log("Table admin_logs does not exist. Skipping admin action logging.");
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminId,
            $action,
            $table,
            $recordId,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        error_log("Admin logging failed: " . $e->getMessage());
    }
}

<?php
/**
 * Bootstrap - Единая точка входа для инициализации приложения
 * 
 * Этот файл обеспечивает согласованную загрузку всех зависимостей
 * и предотвращает дублирование подключений к БД.
 * 
 * Использование:
 *   require_once __DIR__ . '/../bootstrap.php';
 *   // Теперь доступны: PDO, сессия, авторизация, хелперы
 */

// Защита от повторного включения
if (defined('BOOTSTRAP_INCLUDED')) {
    return;
}
define('BOOTSTRAP_INCLUDED', true);

// Определяем корень проекта
define('PROJECT_ROOT', dirname(__DIR__));

// Загружаем переменные окружения
require_once PROJECT_ROOT . '/config/database.php';

// Инициализируем сессию (если еще не запущена)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем базовые библиотеки
require_once PROJECT_ROOT . '/includes/csrf.php';
require_once PROJECT_ROOT . '/includes/db.php';
require_once PROJECT_ROOT . '/includes/auth.php';
require_once PROJECT_ROOT . '/includes/world.php';

// Подключаем боевой движок
require_once PROJECT_ROOT . '/includes/combat_engine.php';

// Подключаем админ-ядро (если нужно)
// require_once PROJECT_ROOT . '/includes/admin_core.php';

/**
 * Быстрый доступ к PDO (алиас для совместимости)
 * @deprecated Используйте getDbConnection() напрямую
 */
function db(): PDO {
    return getDbConnection();
}

/**
 * Получение текущего игрока с проверкой
 * @return array|null Данные игрока или null если не авторизован
 */
function currentPlayer(): ?array {
    return getCurrentPlayer();
}

/**
 * Получение текущего персонажа с проверкой
 * @return array|null Данные персонажа или null
 */
function currentCharacter(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $charId = getCurrentCharacterId();
    if (!$charId) {
        return null;
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$charId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Редирект с флеш-сообщением
 */
function redirectWithMessage(string $url, string $message, string $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: {$url}");
    exit;
}

/**
 * JSON ответ с автоматическими заголовками
 */
function jsonResponse(array $data, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Проверка AJAX запроса
 */
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Валидация CSRF токена
 */
function validateCsrfToken(?string $token): bool {
    if (empty($token)) {
        return false;
    }
    return verifyCsrfToken($token);
}

/**
 * Логирование ошибок в файл
 */
function logError(string $message, array $context = []) {
    $logFile = PROJECT_ROOT . '/logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

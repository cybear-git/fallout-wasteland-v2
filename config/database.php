<?php

/**
 * Загрузка .env и подключение к БД
 * UPDATED: Добавлена обработка ошибок и валидация конфигурации
 */

/**
 * Загрузка переменных окружения из .env файла
 * 
 * @param string $path Путь к .env файлу
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        error_log(".env file not found at: $path");
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (str_starts_with(trim($line), '#')) continue;
        
        // Парсим ключ=значение
        $parts = array_map('trim', explode('=', $line, 2) + ['', '']);
        if (count($parts) !== 2) continue;
        
        [$key, $value] = $parts;
        
        // Устанавливаем переменную окружения только если еще не задана
        if ($key && !getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Загружаем .env из корневой директории проекта
loadEnv(dirname(__DIR__) . '/.env');

/**
 * Получить подключение к базе данных (Singleton pattern)
 * UPDATED: Добавлена валидация обязательных параметров и безопасное логирование
 * 
 * @return PDO Подключение к базе данных
 * @throws Exception Если подключение не удалось
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    // Возвращаем существующее подключение
    if ($pdo !== null) return $pdo;

    // Получаем параметры из окружения с дефолтными значениями
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'fallout_wastelands_v2';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';
    
    // Валидация обязательных параметров
    if (empty($host) || empty($db) || empty($user)) {
        error_log("Database configuration error: missing required parameters");
        throw new Exception("Database configuration error");
    }

    // Формируем DSN
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    
    // Опции PDO для безопасности и производительности
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // Выбрасывать исключения при ошибках
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            // Ассоциативные массивы по умолчанию
        PDO::ATTR_EMULATE_PREPARES   => false,                       // Настоящие prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,                        // Возвращать количество найденных строк
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Дополнительно проверяем подключение
        $pdo->query("SELECT 1")->fetchColumn();
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Безопасное логирование: не выводим детали ошибки пользователю
        $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        $msg = $debug ? "DB Connection Error: " . $e->getMessage() : "Database connection failed.";
        
        error_log($msg);
        
        // В HTTP контексте возвращаем 500 ошибку
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $msg]);
        }
        
        exit;
    }
}

/**
 * Получить версию MySQL
 * UPDATED: Новая вспомогательная функция для отладки
 * 
 * @return string Версия сервера MySQL
 */
function getMysqlVersion(): string {
    try {
        $pdo = getDbConnection();
        return $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (Exception $e) {
        return 'unknown';
    }
}
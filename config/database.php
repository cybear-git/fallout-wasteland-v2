<?php

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2) + ['', '']);
        if ($key && !getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
loadEnv(dirname(__DIR__) . '/.env');

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'fallout_wastelands_v2';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // В продакшене здесь должен быть лог в файл, а не вывод на экран
        $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        $msg = $debug ? "DB Error: " . $e->getMessage() : "Database connection failed.";
        error_log($msg);
        http_response_code(500);
        exit(json_encode(['error' => $msg]));
    }

    return $pdo;
}
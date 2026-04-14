<?php
// Сообщаем браузеру, что это простой текст.
header('Content-Type: text/plain; charset=utf-8');


// Поднимаемся на уровень выше (../), так как файл лежит в public/
require_once __DIR__ . '/../config/database.php';

echo "=== Создание учетной записи администратора ===\n";

// 2. Проверяем подключение к БД
try {
    $pdo = getDbConnection();
    echo "✅ Подключение к базе данных успешно\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Проверяем, существует ли уже таблица players
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'players'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Таблица 'players' не найдена. Сначала выполните миграции БД.\n";
        exit(1);
    }
    echo "✅ Таблица 'players' найдена\n";
} catch (Exception $e) {
    echo "❌ Ошибка проверки таблицы: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Проверяем, есть ли уже администраторы
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM players WHERE role = 'admin'");
    $result = $stmt->fetch();
    $adminCount = (int)$result['count'];
    
    if ($adminCount > 0) {
        echo "⚠️ В базе уже есть $adminCount администратор(ов).\n";
        echo "Если вы хотите создать еще одного, продолжайте.\n";
    } else {
        echo "ℹ️ Администраторов пока нет. Создаем первого.\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка проверки администраторов: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Данные для создания админа
$adminData = [
    'username' => 'admin',
    'password' => 'admin123', // Пароль по умолчанию - ОБЯЗАТЕЛЬНО смени после входа!
    'role' => 'admin'
];

// Валидация данных
if (empty($adminData['username']) || empty($adminData['password'])) {
    echo "❌ Логин и пароль обязательны\n";
    exit(1);
}

if (strlen($adminData['password']) < 4) {
    echo "❌ Пароль должен быть минимум 4 символа\n";
    exit(1);
}

echo "\n📝 Данные для создания:\n";
echo "   Логин: " . $adminData['username'] . "\n";
echo "   Роль: " . $adminData['role'] . "\n";

// 6. Проверяем, не существует ли уже такой логин
try {
    $stmt = $pdo->prepare("SELECT id FROM players WHERE username = ?");
    $stmt->execute([$adminData['username']]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "⚠️ Пользователь с логином '{$adminData['username']}' уже существует.\n";
        echo "   ID: {$existingUser['id']}\n";
        
        // ВНИМАНИЕ: Этот блок работает только в консоли (терминале).
        // В браузере скрипт здесь "зависнет", ожидая ввода.
        echo "   Хотите обновить пароль? (y/n): ";
        
        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($choice) !== 'y') {
            echo "❌ Отмена создания.\n";
            exit(0);
        }
        
        // Обновляем пароль существующего пользователя
        $passwordHash = password_hash($adminData['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE players SET password_hash = ?, role = 'admin' WHERE username = ?");
        $stmt->execute([$passwordHash, $adminData['username']]);
        
        echo "✅ Пароль обновлен для пользователя '{$adminData['username']}'\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "❌ Ошибка проверки логина: " . $e->getMessage() . "\n";
    exit(1);
}

// 7. Создаем нового администратора
try {
    // Хешируем пароль с использованием современного алгоритма
    $passwordHash = password_hash($adminData['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO players (username, password_hash, role, is_active, created_at, updated_at)
        VALUES (?, ?, 'admin', 1, NOW(), NOW())
    ");
    
    $stmt->execute([
        $adminData['username'],
        $passwordHash,
    ]);
    
    $adminId = $pdo->lastInsertId();
    
    echo "\n🎉 Администратор успешно создан!\n";
    echo "   ID: $adminId\n";
    echo "   Логин: {$adminData['username']}\n";
    echo "   Пароль: {$adminData['password']}\n";
    echo "   ⚠️ ОБЯЗАТЕЛЬНО смени пароль после первого входа!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка создания администратора: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Готово ===\n";
echo "Теперь можешь войти в систему с логином '{$adminData['username']}' и паролем '{$adminData['password']}'\n";
echo "После входа перейди в личный кабинет и смени пароль!\n";

?>
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Получить все доступные рецепты
            $sql = "SELECT r.*, i.name as result_name, i.icon as result_icon
                    FROM recipes r
                    JOIN items i ON r.result_item_id = i.id
                    ORDER BY r.name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Для каждого рецепта получить ингредиенты
            foreach ($recipes as &$recipe) {
                $ingSql = "SELECT ri.quantity, i.name, i.icon, i.id as item_id
                          FROM recipe_ingredients ri
                          JOIN items i ON ri.item_id = i.id
                          WHERE ri.recipe_id = ?";
                
                $ingStmt = $pdo->prepare($ingSql);
                $ingStmt->execute([$recipe['id']]);
                $recipe['ingredients'] = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Проверка наличия ингредиентов у игрока
                $hasIngredients = true;
                foreach ($recipe['ingredients'] as $ing) {
                    $checkSql = "SELECT quantity FROM user_items 
                                WHERE user_id = ? AND item_id = ?";
                    $checkStmt = $pdo->prepare($checkSql);
                    $checkStmt->execute([$userId, $ing['item_id']]);
                    $playerItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$playerItem || $playerItem['quantity'] < $ing['quantity']) {
                        $hasIngredients = false;
                        break;
                    }
                }
                $recipe['can_craft'] = $hasIngredients;
            }
            
            echo json_encode(['success' => true, 'recipes' => $recipes]);
            break;

        case 'craft':
            $recipeId = (int)($_POST['recipe_id'] ?? 0);
            
            if (!$recipeId) {
                throw new Exception('Invalid recipe ID');
            }
            
            // Получение рецепта
            $recipeSql = "SELECT * FROM recipes WHERE id = ?";
            $recipeStmt = $pdo->prepare($recipeSql);
            $recipeStmt->execute([$recipeId]);
            $recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipe) {
                throw new Exception('Recipe not found');
            }
            
            // Получение ингредиентов
            $ingSql = "SELECT ri.item_id, ri.quantity, i.name 
                      FROM recipe_ingredients ri
                      JOIN items i ON ri.item_id = i.id
                      WHERE ri.recipe_id = ?";
            $ingStmt = $pdo->prepare($ingSql);
            $ingStmt->execute([$recipeId]);
            $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Транзакция крафта
            $pdo->beginTransaction();
            
            // Проверка и удаление ингредиентов
            foreach ($ingredients as $ing) {
                // Проверка наличия
                $checkSql = "SELECT quantity FROM user_items 
                            WHERE user_id = ? AND item_id = ?";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([$userId, $ing['item_id']]);
                $playerItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$playerItem || $playerItem['quantity'] < $ing['quantity']) {
                    $pdo->rollBack();
                    throw new Exception("Not enough ingredients: {$ing['name']}");
                }
                
                // Удаление ингредиента
                if ($playerItem['quantity'] > $ing['quantity']) {
                    $removeSql = "UPDATE user_items SET quantity = quantity - ? 
                                 WHERE user_id = ? AND item_id = ?";
                    $removeStmt = $pdo->prepare($removeSql);
                    $removeStmt->execute([$ing['quantity'], $userId, $ing['item_id']]);
                } else {
                    $removeSql = "DELETE FROM user_items WHERE user_id = ? AND item_id = ?";
                    $removeStmt = $pdo->prepare($removeSql);
                    $removeStmt->execute([$userId, $ing['item_id']]);
                }
            }
            
            // Добавление результата крафта
            $resultSql = "INSERT INTO user_items (user_id, item_id, quantity) 
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE quantity = quantity + ?";
            $resultStmt = $pdo->prepare($resultSql);
            $resultStmt->execute([
                $userId, 
                $recipe['result_item_id'], 
                $recipe['result_count'],
                $recipe['result_count']
            ]);
            
            $pdo->commit();
            
            // Получение названия созданного предмета
            $nameSql = "SELECT name FROM items WHERE id = ?";
            $nameStmt = $pdo->prepare($nameSql);
            $nameStmt->execute([$recipe['result_item_id']]);
            $resultItem = $nameStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => "Скрафчено: {$resultItem['name']} x{$recipe['result_count']}",
                'result_item_id' => $recipe['result_item_id'],
                'result_count' => $recipe['result_count']
            ]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

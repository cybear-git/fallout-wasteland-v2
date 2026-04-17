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
            // Получить список торговцев (глобальных или в текущей локации)
            $locationId = $_GET['location_id'] ?? null;
            
            $sql = "SELECT v.*, l.name as location_name 
                    FROM vendors v 
                    LEFT JOIN locations l ON v.location_id = l.id 
                    WHERE v.location_id IS NULL OR v.location_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$locationId]);
            $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Для каждого торговца получить товары
            foreach ($vendors as &$vendor) {
                $itemSql = "SELECT vi.*, i.name, i.type_id, i.icon 
                           FROM vendor_items vi 
                           JOIN items i ON vi.item_id = i.id 
                           WHERE vi.vendor_id = ?";
                $itemStmt = $pdo->prepare($itemSql);
                $itemStmt->execute([$vendor['id']]);
                $vendor['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['success' => true, 'vendors' => $vendors]);
            break;

        case 'buy':
            $vendorId = (int)($_POST['vendor_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            if (!$vendorId || !$itemId) {
                throw new Exception('Invalid parameters');
            }
            
            // Проверка наличия товара и получение цены
            $checkSql = "SELECT vi.*, i.base_price, i.name 
                        FROM vendor_items vi 
                        JOIN items i ON vi.item_id = i.id 
                        WHERE vi.vendor_id = ? AND vi.item_id = ? 
                        AND (vi.stock_count = -1 OR vi.stock_count > 0)";
            
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$vendorId, $itemId]);
            $offer = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$offer) {
                throw new Exception('Item not available');
            }
            
            $price = floor($offer['base_price'] * $offer['price_multiplier']);
            
            // Проверка денег игрока
            $playerSql = "SELECT caps FROM users WHERE id = ?";
            $playerStmt = $pdo->prepare($playerSql);
            $playerStmt->execute([$userId]);
            $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($player['caps'] < $price) {
                throw new Exception('Not enough caps');
            }
            
            // Транзакция покупки
            $pdo->beginTransaction();
            
            // Списание денег
            $updateCaps = $pdo->prepare("UPDATE users SET caps = caps - ? WHERE id = ?");
            $updateCaps->execute([$price, $userId]);
            
            // Добавление предмета в инвентарь
            $addItem = $pdo->prepare("INSERT INTO user_items (user_id, item_id, quantity) VALUES (?, ?, 1)
                                     ON DUPLICATE KEY UPDATE quantity = quantity + 1");
            $addItem->execute([$userId, $itemId]);
            
            // Уменьшение стока (если не бесконечный)
            if ($offer['stock_count'] > 0) {
                $updateStock = $pdo->prepare("UPDATE vendor_items SET stock_count = stock_count - 1 
                                             WHERE vendor_id = ? AND item_id = ?");
                $updateStock->execute([$vendorId, $itemId]);
            }
            
            // Увеличение капитала торговца (опционально, для экономики)
            $updateVendorCaps = $pdo->prepare("UPDATE vendors SET caps = caps + ? WHERE id = ?");
            $updateVendorCaps->execute([$price, $vendorId]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Куплено: {$offer['name']}",
                'price' => $price,
                'new_caps' => $player['caps'] - $price
            ]);
            break;

        case 'sell':
            $vendorId = (int)($_POST['vendor_id'] ?? 0);
            $userItemId = (int)($_POST['user_item_id'] ?? 0);
            
            if (!$vendorId || !$userItemId) {
                throw new Exception('Invalid parameters');
            }
            
            // Проверка наличия предмета у игрока
            $itemSql = "SELECT ui.quantity, i.base_price, i.name, i.type_id 
                       FROM user_items ui 
                       JOIN items i ON ui.item_id = i.id 
                       WHERE ui.id = ? AND ui.user_id = ? AND ui.quantity > 0";
            
            $itemStmt = $pdo->prepare($itemSql);
            $itemStmt->execute([$userItemId, $userId]);
            $myItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$myItem) {
                throw new Exception('Item not found');
            }
            
            // Нельзя продавать квестовые предметы (type_id = 5, например)
            if ($myItem['type_id'] == 6) { // Квестовые предметы
                throw new Exception('Cannot sell quest items');
            }
            
            $sellPrice = floor($myItem['base_price'] * 0.5); // Продаем за 50% цены
            
            // Транзакция продажи
            $pdo->beginTransaction();
            
            // Добавление денег игроку
            $updateCaps = $pdo->prepare("UPDATE users SET caps = caps + ? WHERE id = ?");
            $updateCaps->execute([$sellPrice, $userId]);
            
            // Удаление предмета (или уменьшение количества)
            if ($myItem['quantity'] > 1) {
                $removeItem = $pdo->prepare("UPDATE user_items SET quantity = quantity - 1 WHERE id = ?");
                $removeItem->execute([$userItemId]);
            } else {
                $removeItem = $pdo->prepare("DELETE FROM user_items WHERE id = ?");
                $removeItem->execute([$userItemId]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Продано: {$myItem['name']}",
                'price' => $sellPrice,
                'new_caps' => $player['caps'] + $sellPrice
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

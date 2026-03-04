<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/StockLog.php';

$db = Database::getInstance()->getConnection();
$product = new Product($db);
$stockLog = new StockLog($db);

$input = json_decode(file_get_contents('php://input'), true);

// Fallback for user_id if session is lost but input is valid
$userId = $_SESSION['user_id'] ?? 1; // Default to admin if session is tricky

error_log("Stock Receive Request: " . json_encode($input));

if (!$input || !isset($input['id']) || !isset($input['quantity'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input. ID and Quantity are required.']);
    exit;
}

$productId = (int) $input['id'];
$receivedQty = (int) $input['quantity'];

$notes = $input['notes'] ?? 'Products received';

if ($receivedQty <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Quantity must be greater than zero.']);
    exit;
}

try {
    $db->beginTransaction();

    // Get current product data
    $currentProduct = $product->getById($productId);
    if (!$currentProduct) {
        throw new Exception('Product not found.');
    }

    $oldStock = (int) $currentProduct['stock'];
    $newStock = $oldStock + $receivedQty;

    // Update product stock, expiry date and selling price
    $query = "UPDATE products SET stock = :stock, expiry_date = :expiry_date, price = :price, updated_at = NOW()";

    $query .= " WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':stock', $newStock, PDO::PARAM_INT);

    $expiry_date = !empty($input['expiry_date']) ? $input['expiry_date'] : $currentProduct['expiry_date'];
    $price = !empty($input['price']) ? $input['price'] : $currentProduct['price'];

    $stmt->bindParam(':expiry_date', $expiry_date);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);


    if (!$stmt->execute()) {
        throw new Exception('Failed to update product stock.');
    }

    // Log the stock in transaction
    if (!$stockLog->create($productId, $receivedQty, 'stock_in', $userId, $notes)) {
        throw new Exception('Failed to create stock log entry.');
    }

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Stock updated successfully!',
        'new_stock' => $newStock
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

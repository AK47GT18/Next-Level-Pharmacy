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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

$productId = (int) $input['id'];

try {
    // Get current stock before updating
    $currentProduct = $product->getById($productId);
    if (!$currentProduct) {
        throw new Exception('Product not found.');
    }
    $oldStock = (int) $currentProduct['stock'];
    $newStock = (int) $input['stock'];

    // Assign properties to product object
    $product->id = $productId;
    $product->name = $input['name'];
    $product->category_id = $input['category_id'];
    $product->price = $input['price'];
    $product->cost_price = $input['cost_price'] ?? null;
    $product->stock = $newStock;
    $product->low_stock_threshold = $input['low_stock_threshold'] ?? 5;
    $product->has_expiry = $input['has_expiry'] ?? 0;
    $product->expiry_date = !empty($input['expiry_date']) ? $input['expiry_date'] : null;
    $product->description = $input['description'] ?? null;

    if ($product->update()) {
        // Log the stock change if it's different
        $quantityChange = $newStock - $oldStock;
        if ($quantityChange != 0) {
            $logType = $quantityChange > 0 ? 'stock_in' : 'adjustment';
            $note = "Stock manually adjusted from {$oldStock} to {$newStock}.";
            $stockLog->create($productId, $quantityChange, $logType, $userId, $note);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Product updated successfully.'
        ]);
    } else {
        throw new Exception('Failed to update product in the database.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
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

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

$product->name = $input['name'];
$product->category_id = $input['category_id'];
$product->price = $input['price'];
$product->cost_price = $input['cost_price'] ?? null;
$product->stock = $input['stock'];
$product->low_stock_threshold = $input['low_stock_threshold'] ?? 5;
$product->has_expiry = $input['has_expiry'] ?? 0;
$product->expiry_date = !empty($input['expiry_date']) ? $input['expiry_date'] : null;
$product->description = $input['description'] ?? null;

try {
    if ($product->create()) {
        $newProductId = $product->id;
        $initialStock = (int) $input['stock'];

        // Create the initial stock log
        if ($initialStock > 0) {
            $stockLog->create($newProductId, $initialStock, 'initial', $userId, 'Initial stock on product creation.');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Product created successfully.',
            'product_id' => $newProductId
        ]);
    } else {
        throw new Exception('Failed to create product in the database.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
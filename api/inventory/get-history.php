<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\inventory\get-history.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/StockLog.php';
require_once __DIR__ . '/../../classes/Product.php';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Product ID is required.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stockLog = new StockLog($db);
    $product = new Product($db);

    $history = $stockLog->getByProductId($productId);
    $productDetails = $product->getById($productId);

    if (!$productDetails) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'product_name' => $productDetails['name'],
        'history' => array_slice($history, 0, 10)
    ]);

} catch (Exception $e) {
    error_log("Get Stock History Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'A server error occurred.']);
}
<?php

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Product.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    https_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    https_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$product_id) {
        https_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
        exit;
    }

    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();
    $product = new Product($db);

    $result = $product->getById($product_id);

    if (!$result) {
        https_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    https_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);

} catch (PDOException $e) {
    https_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    https_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
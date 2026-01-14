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

try {
    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();
    $product = new Product($db);

    // Get filter from query string
    $filter = isset($_GET['filter']) ? $_GET['filter'] : null;

    // Fetch products
    $products = $product->getAll($filter);

    if ($products === false) {
        throw new Exception('Failed to fetch products');
    }

    https_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $products,
        'count' => count($products)
    ]);

} catch (PDOException $e) {
    https_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    https_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\products.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ✅ Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Product.php';

try {
    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $product = new Product($db);
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

    // Get products
    if ($filter === 'all') {
        $result = $product->getAll();
    } else {
        $filterType = ucfirst(strtolower($filter));
        $result = $product->getAll($filterType);
    }

    // ✅ Return valid JSON
    http_response_code(200);
    echo json_encode($result ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Products API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
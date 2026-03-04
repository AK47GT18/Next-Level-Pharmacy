<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\inventory\delete.php

header('Content-Type: application/json');

// ✅ Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Product.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);  // ✅ Fixed: was https_response_code
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Check authorization - only admin can delete
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);  // ✅ Fixed: was https_response_code
    echo json_encode(['status' => 'error', 'message' => 'Only administrators can delete products']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);  // ✅ Fixed: was https_response_code
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get product ID from POST body
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = $data['id'] ?? null;

    if (!$product_id) {
        http_response_code(400);  // ✅ Fixed: was https_response_code
        echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
        exit;
    }

    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();
    $product = new Product($db);

    // Check if product exists
    $existing = $product->getById($product_id);
    if (!$existing) {
        http_response_code(404);  // ✅ Fixed: was https_response_code
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    // Set ID and delete
    $product->id = $product_id;
    if ($product->delete()) {
        http_response_code(200);  // ✅ Fixed: was https_response_code
        echo json_encode([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    } else {
        http_response_code(500);  // ✅ Fixed: was https_response_code
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
    }

} catch (PDOException $e) {
    error_log('Delete error: ' . $e->getMessage());
    http_response_code(500);  // ✅ Fixed: was https_response_code
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('Delete error: ' . $e->getMessage());
    http_response_code(500);  // ✅ Fixed: was https_response_code
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
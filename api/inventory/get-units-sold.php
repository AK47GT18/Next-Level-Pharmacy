<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\inventory\get-units-sold.php

session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

header('Content-Type: application/json');

$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

if (!$productId || $productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // ✅ Debug: Check if product exists
    $checkStmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $checkStmt->execute([$productId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => true, 'units_sold' => 0]);
        exit;
    }
    
    // ✅ Sum all quantities sold for this product from sale_items table
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(si.quantity), 0) as units_sold
        FROM sale_items si
        WHERE si.product_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->execute([$productId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unitsSold = intval($result['units_sold'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'units_sold' => $unitsSold,
        'product_id' => $productId
    ]);
    
} catch (Exception $e) {
    error_log('Get units sold error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
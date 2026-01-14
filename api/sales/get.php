<?php

require_once '../../includes/auth.php';
require_once '../../classes/Database.php';
require_once '../../classes/Sale.php';

// Check authentication and permissions
checkAuth(['admin', 'cashier']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    https_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get sale ID from query parameters
$sale_id = $_GET['id'] ?? null;

if (!$sale_id) {
    https_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Sale ID is required']);
    exit;
}

$database = new Database();
$db = $database->connect();
$sale = new Sale($db);

try {
    $result = $sale->readOne($sale_id);
    
    if (!$result) {
        https_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Sale not found']);
        exit;
    }

    // Log the access
    logActivity($_SESSION['user_id'], 'sale_viewed', "Viewed sale #{$sale_id}");

    echo json_encode([
        'status' => 'success',
        'message' => 'Sale retrieved successfully',
        'data' => [
            'sale' => $result,
            'items' => $sale->getSaleItems($sale_id),
            'customer' => $sale->getCustomerDetails($result['customer_id']),
            'served_by' => $sale->getCashierDetails($result['served_by'])
        ]
    ]);

} catch (Exception $e) {
    logError('sale_retrieval_failed', $e->getMessage(), [
        'user_id' => $_SESSION['user_id'],
        'sale_id' => $sale_id
    ]);

    https_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve sale details',
        'error_code' => $e->getCode()
    ]);
}
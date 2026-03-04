<?php
// filepath: api/sales/get-items.php
// Returns all sale_items for a given sale, with product info
// Admin-only: only admins need to fetch sale items (for the edit modal)

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Admin-only auth
checkAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$saleId = intval($_GET['sale_id'] ?? 0);
if ($saleId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid sale_id is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT 
            si.id,
            si.sale_id,
            si.product_id,
            si.quantity,
            si.price_at_sale,
            si.total,
            p.name as product_name,
            p.stock as current_stock
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id ASC
    ");
    $stmt->execute([$saleId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $items
    ]);

}
catch (Exception $e) {
    error_log('Get sale items error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch sale items']);
}

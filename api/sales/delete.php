<?php
// filepath: api/sales/delete.php
// Admin-only: Delete a sale, restore product stock, log changes

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Admin-only auth
checkAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF Protection
if (!validateCsrfToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$saleId = intval($input['sale_id'] ?? 0);

if ($saleId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid sale_id is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // Fetch all sale items to restore stock
    $itemsStmt = $conn->prepare("
        SELECT si.product_id, si.quantity, p.name as product_name 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ");
    $itemsStmt->execute([$saleId]);
    $saleItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($saleItems)) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Sale not found or has no items']);
        exit;
    }

    // Restore stock for each product and log it
    foreach ($saleItems as $item) {
        // Restore product stock
        $stockStmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stockStmt->execute([$item['quantity'], $item['product_id']]);

        // Log stock restoration (sanitize product name)
        $productName = mb_substr(strip_tags($item['product_name']), 0, 100);
        $logStmt = $conn->prepare("
            INSERT INTO stock_logs (product_id, quantity_change, type, notes, created_by) 
            VALUES (?, ?, 'adjustment', ?, ?)
        ");
        $logStmt->execute([
            $item['product_id'],
            $item['quantity'],
            "Sale #{$saleId} deleted: +{$item['quantity']} restored for {$productName}",
            $_SESSION['user_id']
        ]);
    }

    // Delete the sale (cascade will delete sale_items and payments)
    $deleteStmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $deleteStmt->execute([$saleId]);

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sale deleted and stock restored successfully',
        'restored_items' => count($saleItems)
    ]);

}
catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Sale delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete sale']);
}

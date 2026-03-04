<?php
// Paginated products API endpoint
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Product.php';

try {
    $db = Database::getInstance()->getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $product = new Product($db);

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 50;
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Use the paginated method
    $result = $product->getAllWithSalesPaginated(
        $type !== 'all' ? ucfirst(strtolower($type)) : null,
        $page,
        $perPage,
        $search
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $result['products'],
        'pagination' => [
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages']
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Products Paginated API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
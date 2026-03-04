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

$database = new Database();
$db = $database->connect();
$sale = new Sale($db);

// Get and validate query parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, min(50, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// Search and filter parameters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'payment_method' => $_GET['payment_method'] ?? null,
    'status' => $_GET['status'] ?? 'completed',
    'cashier_id' => intval($_GET['cashier_id'] ?? 0)
];

// Validate dates
if (!validateDate($filters['start_date']) || !validateDate($filters['end_date'])) {
    https_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    exit;
}

try {
    // Get total count and summary stats
    $total = $sale->getCount($filters);
    $summary = $sale->getSalesSummary($filters);

    // Get sales list
    $result = $sale->getList($filters, $limit, $offset);

    echo json_encode([
        'status' => 'success',
        'message' => 'Sales retrieved successfully',
        'data' => [
            'sales' => $result,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ],
            'summary' => [
                'total_sales' => $summary['total_sales'],
                'total_revenue' => $summary['total_revenue'],
                'average_sale' => $summary['average_sale'],
                'total_items_sold' => $summary['total_items']
            ],
            'filters' => $filters
        ]
    ]);

} catch (Exception $e) {
    logError('sales_list_failed', $e->getMessage(), [
        'user_id' => $_SESSION['user_id'],
        'filters' => $filters
    ]);

    https_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve sales',
        'error_code' => $e->getCode()
    ]);
}
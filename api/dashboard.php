<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\dashboard.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ✅ Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // ✅ GET - Fetch dashboard statistics
    if ($method === 'GET' && $action === 'stats') {
        // Total Sales (Today)
        $todaySales = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;

        // Total Sales (Last Month)
        $lastMonthSales = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn() ?? 0;

        // Calculate growth percentage
        $salesGrowth = $lastMonthSales > 0 ? (($todaySales - $lastMonthSales) / $lastMonthSales) * 100 : 0;

        // Total Products
        $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn() ?? 0;

        // Low Stock Items
        $lowStockItems = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= low_stock_threshold")->fetchColumn() ?? 0;

        // Out of Stock
        $outOfStock = $conn->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn() ?? 0;

        // Total Customers
        $totalCustomers = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?? 0;

        // New Customers (This Month)
        $newCustomers = $conn->query("SELECT COUNT(*) FROM customers WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn() ?? 0;

        // Total Prescriptions
        $totalPrescriptions = $conn->query("SELECT COUNT(*) FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;

        // Total Inventory Value
        $totalInventoryValue = $conn->query("SELECT SUM(stock * cost_price) FROM products WHERE is_deleted = 0")->fetchColumn() ?? 0;

        $stats = [
            'total_sales_today' => (float) $todaySales,
            'sales_growth' => round($salesGrowth, 1),
            'total_products' => (int) $totalProducts,
            'low_stock_items' => (int) $lowStockItems,
            'out_of_stock' => (int) $outOfStock,
            'total_customers' => (int) $totalCustomers,
            'new_customers' => (int) $newCustomers,
            'total_prescriptions' => (int) $totalPrescriptions,
            'total_inventory_value' => (float) $totalInventoryValue,
            'critical_alerts' => (int) $outOfStock + ((int) $lowStockItems > 10 ? (int) $lowStockItems : 0)
        ];

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $stats]);
        exit;
    }

    // ✅ GET - Fetch recent sales transactions
    if ($method === 'GET' && $action === 'recent-sales') {
        $limit = $_GET['limit'] ?? 10;

        $sales = $conn->prepare("
            SELECT 
                s.id,
                s.total_amount,
                s.created_at,
                u.name as cashier,
                (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count,
                COALESCE(p.payment_method, 'cash') as payment_method
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            LEFT JOIN payments p ON s.id = p.sale_id
            ORDER BY s.created_at DESC
            LIMIT ?
        ");
        $sales->execute([(int) $limit]);
        $recentSales = $sales->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function ($sale) {
            return [
                'id' => $sale['id'],
                'invoice' => '#' . str_pad($sale['id'], 5, '0', STR_PAD_LEFT),
                'customer' => 'Sale by ' . htmlspecialchars($sale['cashier'] ?? 'Unknown'),
                'amount' => (float) $sale['total_amount'],
                'items_count' => (int) $sale['items_count'],
                'payment_method' => ucwords(str_replace('_', ' ', $sale['payment_method'])),
                'status' => 'Completed',
                'date' => $sale['created_at'],
                'time_ago' => getTimeAgo($sale['created_at'])
            ];
        }, $recentSales);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }

    // ✅ GET - Fetch low stock items
    if ($method === 'GET' && $action === 'low-stock') {
        $limit = $_GET['limit'] ?? 5;

        $items = $conn->prepare("
            SELECT 
                id,
                name,
                stock,
                low_stock_threshold,
                CASE 
                    WHEN stock = 0 THEN 'out_of_stock'
                    WHEN stock <= low_stock_threshold * 0.5 THEN 'critical'
                    ELSE 'low'
                END as level
            FROM products
            WHERE stock <= low_stock_threshold
            ORDER BY stock ASC
            LIMIT ?
        ");
        $items->execute([(int) $limit]);
        $lowStockItems = $items->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => htmlspecialchars($item['name']),
                'stock' => (int) $item['stock'],
                'threshold' => (int) $item['low_stock_threshold'],
                'level' => $item['level'],
                'status_text' => match ($item['level']) {
                    'out_of_stock' => 'Out of Stock',
                    'critical' => 'Critical',
                    'low' => 'Low Stock',
                    default => 'Normal'
                }
            ];
        }, $lowStockItems);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }

    // ✅ GET - Fetch top selling products
    if ($method === 'GET' && $action === 'top-products') {
        $limit = $_GET['limit'] ?? 5;
        $days = $_GET['days'] ?? 30;

        $products = $conn->prepare("
            SELECT 
                p.id,
                p.name,
                SUM(si.quantity) as units_sold,
                SUM(si.total) as total_revenue,
                p.price
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY p.id, p.name, p.price
            ORDER BY units_sold DESC
            LIMIT ?
        ");
        $products->execute([(int) $days, (int) $limit]);
        $topProducts = $products->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => htmlspecialchars($product['name']),
                'units_sold' => (int) $product['units_sold'],
                'revenue' => (float) $product['total_revenue'],
                'unit_price' => (float) $product['price']
            ];
        }, $topProducts);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }

    // ✅ GET - Fetch sales chart data
    if ($method === 'GET' && $action === 'sales-chart') {
        $days = $_GET['days'] ?? 30;

        $chartData = $conn->prepare("
            SELECT 
                DATE(created_at) as sale_date,
                SUM(total_amount) as daily_total,
                COUNT(*) as transaction_count
            FROM sales
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY sale_date ASC
        ");
        $chartData->execute([(int) $days]);
        $data = $chartData->fetchAll(PDO::FETCH_ASSOC);

        // Format for chart
        $labels = [];
        $values = [];
        $counts = [];

        for ($i = (int) $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));

            $found = false;
            foreach ($data as $row) {
                if ($row['sale_date'] === $date) {
                    $values[] = (float) $row['daily_total'];
                    $counts[] = (int) $row['transaction_count'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $values[] = 0;
                $counts[] = 0;
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'values' => $values,
                'counts' => $counts
            ]
        ]);
        exit;
    }

    // ✅ GET - Fetch payment method breakdown
    if ($method === 'GET' && $action === 'payment-methods') {
        $days = $_GET['days'] ?? 30;

        $payments = $conn->prepare("
            SELECT 
                COALESCE(p.payment_method, 'cash') as method,
                COUNT(s.id) as count,
                SUM(s.total_amount) as total
            FROM sales s
            LEFT JOIN payments p ON s.id = p.sale_id
            WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY COALESCE(p.payment_method, 'cash')
            ORDER BY total DESC
        ");
        $payments->execute([(int) $days]);
        $paymentData = $payments->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function ($payment) {
            return [
                'method' => ucwords(str_replace('_', ' ', $payment['method'])),
                'count' => (int) $payment['count'],
                'total' => (float) $payment['total'],
                'percentage' => 0 // Will be calculated on frontend
            ];
        }, $paymentData);

        // Calculate percentages
        $total = array_sum(array_column($formatted, 'total'));
        foreach ($formatted as &$item) {
            $item['percentage'] = $total > 0 ? round(($item['total'] / $total) * 100, 1) : 0;
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }

    // ✅ GET - Fetch customer statistics
    if ($method === 'GET' && $action === 'customer-stats') {
        $totalCustomers = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?? 0;
        $newCustomers = $conn->prepare("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)")->execute([7]) || 0;
        $repeatCustomers = $conn->query("SELECT COUNT(DISTINCT customer_id) FROM sales WHERE customer_id IS NOT NULL AND (SELECT COUNT(*) FROM sales s2 WHERE s2.customer_id = sales.customer_id) > 1")->fetchColumn() ?? 0;

        $stats = [
            'total' => (int) $totalCustomers,
            'new_this_week' => 0,
            'repeat_customers' => (int) $repeatCustomers
        ];

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $stats]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log('Dashboard API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

// ✅ Helper function to format time ago
function getTimeAgo($datetime)
{
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}
?>
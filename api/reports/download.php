<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\reports\download.php

// ✅ MUST be at the very top - NOTHING before this
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    // ✅ Verify authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die('Unauthorized');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $reportType = $_GET['report'] ?? '';
    
    if (!in_array($reportType, ['sales', 'inventory', 'financial'])) {
        http_response_code(400);
        die('Invalid report type');
    }
    
    // ✅ Clear any buffered output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // ✅ Set headers BEFORE any output
    $filename = "report_{$reportType}_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    
    // ✅ Add BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($reportType === 'sales') {
        fputcsv($output, ['Sale ID', 'Date', 'Time', 'Cashier', 'Items Count', 'Payment Method', 'Total Amount']);
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $paymentMethod = $_GET['payment_method'] ?? 'all';
        
        $query = "SELECT s.id, DATE(s.created_at) as sale_date, TIME(s.created_at) as sale_time, u.name as sold_by, 
                         (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as items_count, 
                         COALESCE(p.payment_method, 'cash') as payment_method, s.total_amount 
                  FROM sales s 
                  LEFT JOIN users u ON s.sold_by = u.id 
                  LEFT JOIN payments p ON s.id = p.sale_id 
                  WHERE DATE(s.created_at) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        if ($paymentMethod !== 'all') {
            $query .= " AND p.payment_method = ?";
            $params[] = $paymentMethod;
        }
        
        $query .= " ORDER BY s.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                '#' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                $row['sale_date'],
                $row['sale_time'],
                $row['sold_by'] ?? 'Unknown',
                $row['items_count'],
                ucwords(str_replace('_', ' ', $row['payment_method'])),
                number_format($row['total_amount'], 2)
            ]);
        }
        
    } elseif ($reportType === 'inventory') {
        fputcsv($output, ['Product ID', 'Product Name', 'Category', 'Stock Level', 'Low Stock Threshold', 'Cost Price', 'Stock Value']);
        $query = "SELECT p.id, p.name, c.name as category, p.stock, p.low_stock_threshold, p.cost_price, 
                         (p.cost_price * p.stock) as stock_value
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.stock ASC";
        $stmt = $conn->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['category'] ?? 'Uncategorized',
                $row['stock'],
                $row['low_stock_threshold'],
                number_format($row['cost_price'] ?? 0, 2),
                number_format($row['stock_value'] ?? 0, 2)
            ]);
        }
        
    } elseif ($reportType === 'financial') {
        fputcsv($output, ['Product ID', 'Product Name', 'Units Sold', 'Avg Sale Price', 'Total Revenue', 'Unit Cost', 'Total Cost', 'Total Profit', 'Profit Margin %']);
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        $query = "SELECT p.id, p.name, p.cost_price, SUM(si.quantity) as units_sold, AVG(si.price_at_sale) as avg_sale_price, SUM(si.total) as total_revenue
                  FROM sale_items si 
                  JOIN products p ON si.product_id = p.id 
                  JOIN sales s ON si.sale_id = s.id 
                  WHERE DATE(s.created_at) BETWEEN ? AND ? 
                  GROUP BY p.id, p.name, p.cost_price 
                  ORDER BY total_revenue DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalCost = (float)($row['cost_price'] ?? 0) * (int)$row['units_sold'];
            $totalRevenue = (float)$row['total_revenue'];
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
            
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['units_sold'],
                number_format($row['avg_sale_price'], 2),
                number_format($totalRevenue, 2),
                number_format($row['cost_price'] ?? 0, 2),
                number_format($totalCost, 2),
                number_format($totalProfit, 2),
                number_format($profitMargin, 2)
            ]);
        }
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log('Download Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating report');
}
?>
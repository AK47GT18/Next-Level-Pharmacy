<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\download-financial-csv.php

/**
 * Handles the generation and download of the financial report as a CSV file.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Get filter parameters from the URL
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');

    // Build the same query as the report page
    $query = "
        SELECT 
            p.id, p.name, SUM(si.quantity) as units_sold, AVG(si.price_at_sale) as avg_sale_price,
            SUM(si.total) as total_revenue, p.cost_price
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.cost_price
        ORDER BY total_revenue DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $financials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add CSV header row
    fputcsv($output, ['Product ID', 'Product Name', 'Units Sold', 'Avg Sale Price', 'Total Revenue', 'Total Cost', 'Total Profit', 'Margin (%)']);

    // Add data rows
    foreach ($financials as $item) {
        $itemCost = (float)($item['cost_price'] ?? 0) * (int)$item['units_sold'];
        $itemProfit = (float)$item['total_revenue'] - $itemCost;
        $itemMargin = (float)$item['total_revenue'] > 0 ? ($itemProfit / (float)$item['total_revenue']) * 100 : 0;

        fputcsv($output, [
            $item['id'],
            $item['name'],
            $item['units_sold'],
            number_format($item['avg_sale_price'], 2),
            number_format($item['total_revenue'], 2),
            number_format($itemCost, 2),
            number_format($itemProfit, 2),
            number_format($itemMargin, 2)
        ]);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log('Financial CSV Export Error: ' . $e->getMessage());
    die("Error generating CSV file. Please check the logs.");
}
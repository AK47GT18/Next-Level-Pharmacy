<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\download-sales-csv.php

/**
 * Handles the generation and download of the sales report as a CSV file.
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
    $paymentMethod = $_GET['payment_method'] ?? 'all';

    // Build the same query as the report page
    $query = "
        SELECT 
            s.id, 
            s.created_at, 
            u.name as sold_by,
            COALESCE(p.payment_method, 'cash') as payment_method,
            (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as items_count,
            s.total_amount
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";

    $params = [$startDate, $endDate];

    if ($paymentMethod !== 'all') {
        $query .= " AND p.payment_method = ?";
        $params[] = $paymentMethod;
    }
    $query .= " ORDER BY s.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add CSV header row
    fputcsv($output, ['Sale ID', 'Date', 'Cashier', 'Payment Method', 'Items Count', 'Total Amount (MWK)']);

    // Add data rows
    foreach ($sales as $sale) {
        fputcsv($output, [$sale['id'], $sale['created_at'], $sale['sold_by'], $sale['payment_method'], $sale['items_count'], $sale['total_amount']]);
    }

    fclose($output);
    exit; // IMPORTANT: Stop script execution to prevent rendering the rest of the page
} catch (Exception $e) {
    error_log('CSV Export Error: ' . $e->getMessage());
    die("Error generating CSV file. Please check the logs.");
}
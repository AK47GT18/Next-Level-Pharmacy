<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\download-inventory-csv.php

/**
 * Handles the generation and download of the inventory report as a CSV file.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Get filter parameters from the URL
    $categoryFilter = $_GET['category'] ?? '';
    $stockFilter = $_GET['stock_level'] ?? '';

    // Build the same query as the report page
    $query = "
        SELECT 
            p.id, p.name, c.name as category, p.stock, p.low_stock_threshold, p.price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1
    ";

    $params = [];

    if ($categoryFilter) {
        $query .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($stockFilter === 'low') {
        $query .= " AND p.stock > 0 AND p.stock <= p.low_stock_threshold";
    } elseif ($stockFilter === 'out') {
        $query .= " AND p.stock = 0";
    } elseif ($stockFilter === 'normal') {
        $query .= " AND p.stock > p.low_stock_threshold";
    }

    $query .= " ORDER BY p.stock ASC, p.name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add CSV header row
    fputcsv($output, ['Product ID', 'Product Name', 'Category', 'Stock', 'Low Stock Threshold', 'Selling Price (MWK)']);

    // Add data rows
    foreach ($products as $product) {
        fputcsv($output, $product);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log('Inventory CSV Export Error: ' . $e->getMessage());
    die("Error generating CSV file. Please check the logs.");
}
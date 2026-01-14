<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\pos\products.php

header('Content-Type: application/json; charset=utf-8');

// ✅ Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $sql = "SELECT 
                p.id, 
                p.name, 
                p.price, 
                p.stock,
                c.id as category_id,
                c.name as category, 
                pt.id as type_id,
                pt.name as type,
                pt.icon_class
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_types pt ON c.product_type_id = pt.id
            WHERE p.stock > 0
            ORDER BY pt.name ASC, c.name ASC, p.name ASC";

    $stmt = $conn->query($sql);
    $products = [];

    // ✅ Icon mapping for product types
    $typeIcons = [
        'medicine' => 'fas fa-capsules',
        'cosmetic' => 'fas fa-spa',
        'skincare' => 'fas fa-leaf',
        'perfume' => 'fas fa-flask-vial',
        'vitamin' => 'fas fa-tablets',
        'supplement' => 'fas fa-apple',
        'other' => 'fas fa-box'
    ];

    if ($stmt && $stmt->rowCount() > 0) {
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];
            $row['type'] = $row['type'] ?? 'Other';
            $row['category'] = $row['category'] ?? 'Uncategorized';
            
            // ✅ Use icon_class from DB or map by type
            $typeKey = strtolower($row['type'] ?? 'other');
            $row['icon'] = $row['icon_class'] ?? $typeIcons[$typeKey] ?? $typeIcons['other'];
            
            $products[] = $row;
        }
    }

    http_response_code(200);
    echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('POS Products Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load products: ' . $e->getMessage()]);
}
?>
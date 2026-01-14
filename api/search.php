<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\search.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $query = $_GET['q'] ?? '';

    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $searchTerm = '%' . $query . '%';

    // ✅ Search across products, categories, and types
    $sql = "SELECT 
                p.id,
                p.name,
                p.description,
                p.price,
                p.stock,
                p.category_id,
                c.name as category_name,
                pt.name as type_name,
                pt.icon_class,
                'product' as result_type
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_types pt ON c.product_type_id = pt.id
            WHERE p.is_deleted = 0
            AND (
                p.name LIKE :search
                OR p.description LIKE :search
                OR c.name LIKE :search
                OR pt.name LIKE :search
            )
            ORDER BY 
                CASE 
                    WHEN p.name LIKE :exact THEN 1
                    WHEN p.name LIKE :search THEN 2
                    ELSE 3
                END,
                p.name ASC
            LIMIT 20";

    $stmt = $db->prepare($sql);
    $exactMatch = $query;
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':exact', $exactMatch, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}
?>
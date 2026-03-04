<?php
/**
 * Product Class
 *
 * Handles all database operations related to products.
 */
class Product {
    private $conn;
    private $table = 'products';

    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all products with their category and type information.
     * Can be filtered by product type name (e.g., 'Medicine', 'Cosmetic').
     */
    public function getAll($filter_type = null) {
        $query = "SELECT 
                    p.id, 
                    p.name, 
                    p.description,
                    p.stock, 
                    p.price, 
                    p.expiry_date,
                    p.low_stock_threshold,
                    c.name as category_name, 
                    pt.name as type_name,
                    pt.icon_class
                  FROM 
                    " . $this->table . " p
                  LEFT JOIN 
                    categories c ON p.category_id = c.id
                  LEFT JOIN
                    product_types pt ON c.product_type_id = pt.id";

        if ($filter_type && $filter_type !== 'all') {
            $query .= " WHERE pt.name = :filter_type";
        }

        $query .= " ORDER BY p.created_at DESC";

        $stmt = $this->conn->prepare($query);

        if ($filter_type && $filter_type !== 'all') {
            $stmt->bindParam(':filter_type', $filter_type);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get inventory statistics.
     */
    public function getStats() {
        $stats = [];
        // Total Products
        $stmt = $this->conn->prepare("SELECT COUNT(id) as total FROM " . $this->table);
        $stmt->execute();
        $stats['total_products'] = $stmt->fetchColumn();

        // Low Stock Count
        $stmt = $this->conn->prepare("SELECT COUNT(id) as total FROM " . $this->table . " WHERE stock <= low_stock_threshold");
        $stmt->execute();
        $stats['low_stock_count'] = $stmt->fetchColumn();

        return $stats;
    }
}
?>
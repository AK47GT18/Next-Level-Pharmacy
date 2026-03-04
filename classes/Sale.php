<?php
/**
 * Sale Class
 * Handles all sales/POS operations
 */

require_once __DIR__ . '/../includes/database.php';

class Sale {
    private $conn;
    private $table = 'sales';

    public $id;
    public $cashier_id;
    public $subtotal;
    public $discount_amount;
    public $tax_amount;
    public $total_amount;
    public $customer_name;
    public $customer_phone;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (sold_by, total_amount, customer_name, customer_phone)
                  VALUES
                  (:cashier_id, :total_amount, :customer_name, :customer_phone)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cashier_id', $this->cashier_id);
        $stmt->bindParam(':total_amount', $this->total_amount);
        $stmt->bindParam(':customer_name', $this->customer_name);
        $stmt->bindParam(':customer_phone', $this->customer_phone);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function addItem($saleId, $productId, $quantity, $unitPrice) {
        $query = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, total)
                  VALUES (:sale_id, :product_id, :quantity, :price_at_sale, :total)";

        $total = $quantity * $unitPrice;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sale_id', $saleId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price_at_sale', $unitPrice);
        $stmt->bindParam(':total', $total);

        return $stmt->execute();
    }

    public function getCount($filters = []) {
        $query = "SELECT COUNT(DISTINCT s.id) FROM " . $this->table . " s 
                  LEFT JOIN payments p ON s.id = p.sale_id
                  WHERE 1=1";
        
        $params = [];
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND DATE(s.created_at) BETWEEN :start AND :end";
            $params[':start'] = $filters['start_date'];
            $params[':end'] = $filters['end_date'];
        }
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query .= " AND p.payment_method = :payment";
            $params[':payment'] = $filters['payment_method'];
        }
        if (!empty($filters['cashier_id'])) {
            $query .= " AND s.sold_by = :cashier";
            $params[':cashier'] = $filters['cashier_id'];
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getSalesSummary($filters = []) {
        $query = "SELECT 
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(s.total_amount) as total_revenue,
                    AVG(s.total_amount) as average_sale,
                    SUM((SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.id)) as total_items
                  FROM " . $this->table . " s
                  LEFT JOIN payments p ON s.id = p.sale_id
                  WHERE 1=1";

        $params = [];
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND DATE(s.created_at) BETWEEN :start AND :end";
            $params[':start'] = $filters['start_date'];
            $params[':end'] = $filters['end_date'];
        }
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query .= " AND p.payment_method = :payment";
            $params[':payment'] = $filters['payment_method'];
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getList($filters = [], $limit = 10, $offset = 0) {
        $query = "SELECT s.*, u.name as cashier_name, p.payment_method,
                  (SELECT GROUP_CONCAT(pr.name SEPARATOR ', ') FROM sale_items si JOIN products pr ON si.product_id = pr.id WHERE si.sale_id = s.id) as items
                  FROM " . $this->table . " s
                  LEFT JOIN users u ON s.sold_by = u.id
                  LEFT JOIN payments p ON s.id = p.sale_id
                  WHERE 1=1";

        $params = [];
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND DATE(s.created_at) BETWEEN :start AND :end";
            $params[':start'] = $filters['start_date'];
            $params[':end'] = $filters['end_date'];
        }
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query .= " AND p.payment_method = :payment";
            $params[':payment'] = $filters['payment_method'];
        }

        $query .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
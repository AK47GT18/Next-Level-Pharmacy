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
}
?>
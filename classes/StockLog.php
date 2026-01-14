<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\classes\StockLog.php

class StockLog
{
    private $conn;
    private $table = 'stock_logs';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Creates a new stock log entry.
     *
     * @param int $productId The ID of the product.
     * @param int $quantityChange The change in quantity (can be negative).
     * @param string $type The type of stock change ('initial', 'sale', 'stock_in', 'adjustment').
     * @param int|null $userId The ID of the user performing the action.
     * @param string|null $notes Additional notes for the log entry.
     * @return bool True on success, false on failure.
     */
    public function create(int $productId, int $quantityChange, string $type, ?int $userId, ?string $notes = null): bool
    {
        $query = "INSERT INTO " . $this->table . " (product_id, quantity_change, type, created_by, notes, created_at) 
                  VALUES (:product_id, :quantity_change, :type, :created_by, :notes, NOW())";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_change', $quantityChange, PDO::PARAM_INT);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("StockLog create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the history for a specific product.
     */
    public function getByProductId(int $productId): array
    {
        $query = "SELECT sl.*, u.name as user_name FROM " . $this->table . " sl 
                  LEFT JOIN users u ON sl.created_by = u.id
                  WHERE sl.product_id = ? ORDER BY sl.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
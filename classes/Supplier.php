<?php
/**
 * Supplier Class
 * Handles supplier management operations
 */

require_once __DIR__ . '/../includes/database.php';

class Supplier {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Get all suppliers
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM suppliers WHERE status != -1";
        
        if (!empty($filters['search'])) {
            $sql .= " AND (supplier_name LIKE :search 
                      OR contact_person LIKE :search 
                      OR email LIKE :search 
                      OR phone LIKE :search)";
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND status = :status";
        }
        
        $sql .= " ORDER BY supplier_name ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $stmt->bindValue(':search', $searchTerm);
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $stmt->bindValue(':status', $filters['status'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get supplier by ID
     */
    public function getById($supplier_id) {
        $sql = "SELECT * FROM suppliers WHERE supplier_id = :supplier_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Create new supplier
     */
    public function create($data) {
        $sql = "INSERT INTO suppliers (
                    supplier_name, contact_person, email, phone,
                    address, city, country, status
                ) VALUES (
                    :supplier_name, :contact_person, :email, :phone,
                    :address, :city, :country, :status
                )";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':supplier_name', $data['supplier_name']);
        $stmt->bindParam(':contact_person', $data['contact_person']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'supplier_id' => $this->conn->lastInsertId()
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to create supplier'];
    }
    
    /**
     * Update supplier
     */
    public function update($supplier_id, $data) {
        $sql = "UPDATE suppliers SET
                    supplier_name = :supplier_name,
                    contact_person = :contact_person,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    city = :city,
                    country = :country,
                    status = :status
                WHERE supplier_id = :supplier_id";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        $stmt->bindParam(':supplier_name', $data['supplier_name']);
        $stmt->bindParam(':contact_person', $data['contact_person']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Delete supplier (soft delete)
     */
    public function delete($supplier_id) {
        // Check if supplier has associated medicines
        $checkSql = "SELECT COUNT(*) as count FROM medicines WHERE supplier_id = :supplier_id AND status = 1";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            return [
                'success' => false,
                'error' => 'Cannot delete supplier with active medicines'
            ];
        }
        
        $sql = "UPDATE suppliers SET status = -1 WHERE supplier_id = :supplier_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to delete supplier'];
    }
    
    /**
     * Get supplier with medicine count
     */
    public function getWithStats($supplier_id) {
        $sql = "SELECT 
                    s.*,
                    COUNT(m.medicine_id) as medicine_count,
                    SUM(m.quantity_in_stock * m.unit_price) as inventory_value
                FROM suppliers s
                LEFT JOIN medicines m ON s.supplier_id = m.supplier_id AND m.status = 1
                WHERE s.supplier_id = :supplier_id
                GROUP BY s.supplier_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Get all active suppliers (for dropdowns)
     */
    public function getActive() {
        $sql = "SELECT supplier_id, supplier_name, phone FROM suppliers 
                WHERE status = 1 ORDER BY supplier_name ASC";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get supplier statistics
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total_suppliers,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_suppliers,
                    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_suppliers
                FROM suppliers
                WHERE status != -1";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Get supplier medicines
     */
    public function getMedicines($supplier_id) {
        $sql = "SELECT 
                    m.*,
                    mc.category_name
                FROM medicines m
                LEFT JOIN medicine_categories mc ON m.category_id = mc.category_id
                WHERE m.supplier_id = :supplier_id AND m.status = 1
                ORDER BY m.medicine_name ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>
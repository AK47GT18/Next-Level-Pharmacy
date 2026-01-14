<?php
/**
 * Medicine Class
 * Handles all medicine/inventory operations
 */

require_once __DIR__ . '/../includes/database.php';

class Medicine {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Get all medicines with pagination and filters
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    m.*,
                    mc.category_name,
                    s.supplier_name,
                    u.full_name as created_by_name,
                    CASE 
                        WHEN m.quantity_in_stock <= 0 THEN 'OUT_OF_STOCK'
                        WHEN m.quantity_in_stock <= m.reorder_level THEN 'LOW_STOCK'
                        ELSE 'IN_STOCK'
                    END as stock_status
                FROM medicines m
                LEFT JOIN medicine_categories mc ON m.category_id = mc.category_id
                LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id
                LEFT JOIN users u ON m.created_by = u.user_id
                WHERE m.status != -1";
        
        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (m.medicine_name LIKE :search 
                      OR m.generic_name LIKE :search 
                      OR m.sku LIKE :search 
                      OR m.barcode LIKE :search)";
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND m.category_id = :category_id";
        }
        
        if (!empty($filters['supplier_id'])) {
            $sql .= " AND m.supplier_id = :supplier_id";
        }
        
        if (!empty($filters['stock_status'])) {
            if ($filters['stock_status'] === 'LOW_STOCK') {
                $sql .= " AND m.quantity_in_stock <= m.reorder_level AND m.quantity_in_stock > 0";
            } elseif ($filters['stock_status'] === 'OUT_OF_STOCK') {
                $sql .= " AND m.quantity_in_stock <= 0";
            }
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $stmt->bindValue(':search', $searchTerm);
        }
        
        if (!empty($filters['category_id'])) {
            $stmt->bindValue(':category_id', $filters['category_id'], PDO::PARAM_INT);
        }
        
        if (!empty($filters['supplier_id'])) {
            $stmt->bindValue(':supplier_id', $filters['supplier_id'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get total count for pagination
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM medicines m WHERE m.status != -1";
        
        if (!empty($filters['search'])) {
            $sql .= " AND (m.medicine_name LIKE :search 
                      OR m.generic_name LIKE :search 
                      OR m.sku LIKE :search 
                      OR m.barcode LIKE :search)";
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND m.category_id = :category_id";
        }
        
        if (!empty($filters['supplier_id'])) {
            $sql .= " AND m.supplier_id = :supplier_id";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $stmt->bindValue(':search', $searchTerm);
        }
        
        if (!empty($filters['category_id'])) {
            $stmt->bindValue(':category_id', $filters['category_id'], PDO::PARAM_INT);
        }
        
        if (!empty($filters['supplier_id'])) {
            $stmt->bindValue(':supplier_id', $filters['supplier_id'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Get single medicine by ID
     */
    public function getById($medicine_id) {
        $sql = "SELECT 
                    m.*,
                    mc.category_name,
                    s.supplier_name,
                    s.phone as supplier_phone,
                    u.full_name as created_by_name
                FROM medicines m
                LEFT JOIN medicine_categories mc ON m.category_id = mc.category_id
                LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id
                LEFT JOIN users u ON m.created_by = u.user_id
                WHERE m.medicine_id = :medicine_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':medicine_id', $medicine_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Create new medicine
     */
    public function create($data) {
        $sql = "INSERT INTO medicines (
                    medicine_name, generic_name, category_id, supplier_id,
                    sku, barcode, description, dosage_form, strength,
                    manufacturer, unit_price, selling_price, quantity_in_stock,
                    reorder_level, expiry_date, prescription_required, created_by
                ) VALUES (
                    :medicine_name, :generic_name, :category_id, :supplier_id,
                    :sku, :barcode, :description, :dosage_form, :strength,
                    :manufacturer, :unit_price, :selling_price, :quantity_in_stock,
                    :reorder_level, :expiry_date, :prescription_required, :created_by
                )";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':medicine_name', $data['medicine_name']);
        $stmt->bindParam(':generic_name', $data['generic_name']);
        $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':supplier_id', $data['supplier_id'], PDO::PARAM_INT);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindParam(':barcode', $data['barcode']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':dosage_form', $data['dosage_form']);
        $stmt->bindParam(':strength', $data['strength']);
        $stmt->bindParam(':manufacturer', $data['manufacturer']);
        $stmt->bindParam(':unit_price', $data['unit_price']);
        $stmt->bindParam(':selling_price', $data['selling_price']);
        $stmt->bindParam(':quantity_in_stock', $data['quantity_in_stock'], PDO::PARAM_INT);
        $stmt->bindParam(':reorder_level', $data['reorder_level'], PDO::PARAM_INT);
        $stmt->bindParam(':expiry_date', $data['expiry_date']);
        $stmt->bindParam(':prescription_required', $data['prescription_required'], PDO::PARAM_BOOL);
        $stmt->bindParam(':created_by', $data['created_by'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update medicine
     */
    public function update($medicine_id, $data) {
        $sql = "UPDATE medicines SET
                    medicine_name = :medicine_name,
                    generic_name = :generic_name,
                    category_id = :category_id,
                    supplier_id = :supplier_id,
                    sku = :sku,
                    barcode = :barcode,
                    description = :description,
                    dosage_form = :dosage_form,
                    strength = :strength,
                    manufacturer = :manufacturer,
                    unit_price = :unit_price,
                    selling_price = :selling_price,
                    quantity_in_stock = :quantity_in_stock,
                    reorder_level = :reorder_level,
                    expiry_date = :expiry_date,
                    prescription_required = :prescription_required,
                    status = :status
                WHERE medicine_id = :medicine_id";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':medicine_id', $medicine_id, PDO::PARAM_INT);
        $stmt->bindParam(':medicine_name', $data['medicine_name']);
        $stmt->bindParam(':generic_name', $data['generic_name']);
        $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':supplier_id', $data['supplier_id'], PDO::PARAM_INT);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindParam(':barcode', $data['barcode']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':dosage_form', $data['dosage_form']);
        $stmt->bindParam(':strength', $data['strength']);
        $stmt->bindParam(':manufacturer', $data['manufacturer']);
        $stmt->bindParam(':unit_price', $data['unit_price']);
        $stmt->bindParam(':selling_price', $data['selling_price']);
        $stmt->bindParam(':quantity_in_stock', $data['quantity_in_stock'], PDO::PARAM_INT);
        $stmt->bindParam(':reorder_level', $data['reorder_level'], PDO::PARAM_INT);
        $stmt->bindParam(':expiry_date', $data['expiry_date']);
        $stmt->bindParam(':prescription_required', $data['prescription_required'], PDO::PARAM_BOOL);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Delete medicine (soft delete)
     */
    public function delete($medicine_id) {
        $sql = "UPDATE medicines SET status = -1 WHERE medicine_id = :medicine_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':medicine_id', $medicine_id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Update stock quantity
     */
    public function updateStock($medicine_id, $quantity, $operation = 'add') {
        if ($operation === 'add') {
            $sql = "UPDATE medicines SET quantity_in_stock = quantity_in_stock + :quantity WHERE medicine_id = :medicine_id";
        } else {
            $sql = "UPDATE medicines SET quantity_in_stock = quantity_in_stock - :quantity WHERE medicine_id = :medicine_id";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':medicine_id', $medicine_id, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Get low stock medicines
     */
    public function getLowStock() {
        $sql = "SELECT * FROM vw_low_stock_medicines LIMIT 50";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get expiring medicines
     */
    public function getExpiring($days = 90) {
        $sql = "SELECT * FROM vw_expiring_medicines WHERE days_to_expiry <= :days ORDER BY days_to_expiry ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get expired medicines
     */
    public function getExpired() {
        $sql = "SELECT * FROM vw_expired_medicines";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Search medicines (for POS autocomplete)
     */
    public function search($query, $limit = 10) {
        $sql = "SELECT 
                    medicine_id, medicine_name, generic_name, sku, barcode,
                    selling_price, quantity_in_stock, prescription_required,
                    dosage_form, strength
                FROM medicines
                WHERE status = 1 
                AND (medicine_name LIKE :query 
                     OR generic_name LIKE :query 
                     OR sku LIKE :query 
                     OR barcode = :exact_query)
                ORDER BY medicine_name ASC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $searchTerm = '%' . $query . '%';
        $stmt->bindValue(':query', $searchTerm);
        $stmt->bindValue(':exact_query', $query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get medicine categories
     */
    public function getCategories() {
        $sql = "SELECT * FROM medicine_categories WHERE status = 1 ORDER BY category_name ASC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get inventory statistics
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total_medicines,
                    SUM(quantity_in_stock) as total_items,
                    SUM(quantity_in_stock * unit_price) as total_value,
                    SUM(CASE WHEN quantity_in_stock <= reorder_level THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN quantity_in_stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
                FROM medicines 
                WHERE status = 1";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Record stock movement
     */
    public function recordMovement($data) {
        $sql = "INSERT INTO stock_movements (
                    medicine_id, movement_type, quantity, unit_price,
                    reference_type, reference_id, notes, performed_by
                ) VALUES (
                    :medicine_id, :movement_type, :quantity, :unit_price,
                    :reference_type, :reference_id, :notes, :performed_by
                )";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':medicine_id', $data['medicine_id'], PDO::PARAM_INT);
        $stmt->bindParam(':movement_type', $data['movement_type']);
        $stmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $data['unit_price']);
        $stmt->bindParam(':reference_type', $data['reference_type']);
        $stmt->bindParam(':reference_id', $data['reference_id'], PDO::PARAM_INT);
        $stmt->bindParam(':notes', $data['notes']);
        $stmt->bindParam(':performed_by', $data['performed_by'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
}
?>
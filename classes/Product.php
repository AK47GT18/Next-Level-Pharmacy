<?php

class Product
{
    private $conn;
    private $table = 'products';

    // Properties matching database columns
    public $id;
    public $name;
    public $category_id;
    public $description;
    public $price;

    public $stock;
    public $low_stock_threshold;
    public $has_expiry;
    public $expiry_date;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Create new product
     */
    public function create()
    {
        try {
            $query = "INSERT INTO " . $this->table . "
                      (name, category_id, description, price, stock, 
                       low_stock_threshold, has_expiry, expiry_date, created_at, updated_at)
                      VALUES
                      (:name, :category_id, :description, :price, :stock,
                       :low_stock_threshold, :has_expiry, :expiry_date, NOW(), NOW())";

            $stmt = $this->conn->prepare($query);

            // Sanitize inputs
            $name = htmlspecialchars(strip_tags($this->name));
            $description = htmlspecialchars(strip_tags($this->description ?? ''));

            // Allow NULL category_id
            $category_id = !empty($this->category_id) ? intval($this->category_id) : null;

            $price = floatval($this->price);

            $stock = intval($this->stock);
            $low_stock_threshold = !empty($this->low_stock_threshold) ? intval($this->low_stock_threshold) : 5;
            $has_expiry = intval($this->has_expiry ?? 0);
            $expiry_date = ($has_expiry === 1 && !empty($this->expiry_date)) ? $this->expiry_date : null;

            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $category_id, is_null($category_id) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price);

            $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
            $stmt->bindParam(':low_stock_threshold', $low_stock_threshold, PDO::PARAM_INT);
            $stmt->bindParam(':has_expiry', $has_expiry, PDO::PARAM_INT);
            $stmt->bindParam(':expiry_date', $expiry_date, is_null($expiry_date) ? PDO::PARAM_NULL : PDO::PARAM_STR);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            error_log("PDO Error in create(): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Error in create(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all products (with is_deleted filter)
     */
    public function getAll($type = null)
    {
        try {
            $query = "SELECT 
                        p.id,
                        p.name,
                        p.description,
                        p.category_id,
                        p.price,
                        p.stock,
                        p.low_stock_threshold,
                        p.has_expiry,
                        p.expiry_date,
                        p.created_at,
                        p.updated_at,
                        c.name as category_name,
                        pt.name as type_name,
                        pt.icon_class
                      FROM " . $this->table . " p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN product_types pt ON c.product_type_id = pt.id
                      WHERE p.is_deleted = 0";

            if ($type && $type !== 'all') {
                $query .= " AND pt.name = :type";
            }

            $query .= " ORDER BY p.name ASC";

            $stmt = $this->conn->prepare($query);

            if ($type && $type !== 'all') {
                $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated products with sales data.
     * Returns ['products' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public function getAllWithSalesPaginated($type = null, $page = 1, $perPage = 50, $search = '')
    {
        try {
            $page = max(1, intval($page));
            $perPage = max(10, min(100, intval($perPage)));
            $offset = ($page - 1) * $perPage;

            // --- Count query (fast, no joins to sale_items) ---
            $countQuery = "SELECT COUNT(*) as total
                           FROM " . $this->table . " p
                           LEFT JOIN categories c ON p.category_id = c.id
                           LEFT JOIN product_types pt ON c.product_type_id = pt.id
                           WHERE p.is_deleted = 0";

            $params = [];

            if ($type && $type !== 'all') {
                $countQuery .= " AND pt.name = ?";
                $params[] = $type;
            }

            if (!empty($search)) {
                $countQuery .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countStmt = $this->conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // --- Data query with LIMIT/OFFSET ---
            $query = "SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.category_id,
                    p.price,
                    p.stock,
                    p.low_stock_threshold,
                    p.has_expiry,
                    p.expiry_date,
                    p.created_at,
                    p.updated_at,
                    c.name as category_name,
                    pt.name as type_name,
                    pt.icon_class,
                    COALESCE(sales.units_sold, 0) as units_sold
                  FROM " . $this->table . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN product_types pt ON c.product_type_id = pt.id
                  LEFT JOIN (
                      SELECT product_id, SUM(quantity) as units_sold
                      FROM sale_items
                      GROUP BY product_id
                  ) sales ON p.id = sales.product_id
                  WHERE p.is_deleted = 0";

            $dataParams = [];

            if ($type && $type !== 'all') {
                $query .= " AND pt.name = ?";
                $dataParams[] = $type;
            }

            if (!empty($search)) {
                $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $dataParams[] = $searchTerm;
                $dataParams[] = $searchTerm;
                $dataParams[] = $searchTerm;
            }

            $query .= " ORDER BY p.name ASC LIMIT ? OFFSET ?";
            $dataParams[] = $perPage;
            $dataParams[] = $offset;

            $stmt = $this->conn->prepare($query);

            // Bind all params — strings first, then the two ints at the end
            foreach ($dataParams as $i => $val) {
                $paramIndex = $i + 1;
                if ($i >= count($dataParams) - 2) {
                    // Last two are LIMIT and OFFSET (integers)
                    $stmt->bindValue($paramIndex, (int) $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($paramIndex, $val, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];

        } catch (PDOException $e) {
            error_log("Product getAllWithSalesPaginated error: " . $e->getMessage());
            return [
                'products' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }

    /**
     * Get all products with their total units sold (non-paginated, kept for backward compatibility).
     */
    public function getAllWithSales($type = null)
    {
        try {
            $query = "SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.category_id,
                    p.price,
                    p.stock,
                    p.low_stock_threshold,
                    p.has_expiry,
                    p.expiry_date,
                    p.created_at,
                    p.updated_at,
                    c.name as category_name,
                    pt.name as type_name,
                    pt.icon_class,
                    COALESCE(sales.units_sold, 0) as units_sold
                  FROM " . $this->table . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN product_types pt ON c.product_type_id = pt.id
                  LEFT JOIN (
                      SELECT product_id, SUM(quantity) as units_sold
                      FROM sale_items
                      GROUP BY product_id
                  ) sales ON p.id = sales.product_id
                  WHERE p.is_deleted = 0";

            if ($type && $type !== 'all') {
                $query .= " AND pt.name = :type";
            }

            $query .= " ORDER BY p.name ASC";

            $stmt = $this->conn->prepare($query);

            if ($type && $type !== 'all') {
                $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Product getAllWithSales error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product by ID (optimized — no sale_items join)
     */
    public function getById($id)
    {
        try {
            $query = "SELECT 
                        p.*, 
                        c.name as category_name, 
                        pt.name as type_name, 
                        pt.icon_class
                      FROM " . $this->table . " p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN product_types pt ON c.product_type_id = pt.id
                      WHERE p.id = :id
                      LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get units sold for a specific product (separate call when needed)
     */
    public function getUnitsSold($productId)
    {
        try {
            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity), 0) as units_sold FROM sale_items WHERE product_id = ?");
            $stmt->execute([$productId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Product getUnitsSold error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get statistics (with is_deleted filter)
     */
    public function getStats()
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN stock <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count,
                        SUM(stock) as total_stock
                      FROM " . $this->table . "
                      WHERE is_deleted = 0";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: ['total_products' => 0, 'low_stock_count' => 0, 'total_stock' => 0];
        } catch (PDOException $e) {
            error_log("Product getStats error: " . $e->getMessage());
            return ['total_products' => 0, 'low_stock_count' => 0, 'total_stock' => 0];
        }
    }

    /**
     * Get counts by type
     */
    public function getCountsByType()
    {
        try {
            $query = "SELECT 
                        pt.name as type_name,
                        pt.icon_class,
                        COUNT(p.id) as count
                      FROM product_types pt
                      LEFT JOIN categories c ON c.product_type_id = pt.id
                      LEFT JOIN products p ON p.category_id = c.id AND p.is_deleted = 0
                      GROUP BY pt.id, pt.name, pt.icon_class
                      ORDER BY pt.name ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product getCountsByType error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update product
     */
    public function update()
    {
        try {
            $query = "UPDATE " . $this->table . "
                      SET name = :name,
                          category_id = :category_id,
                          description = :description,
                          price = :price,
                          stock = :stock,
                          low_stock_threshold = :low_stock_threshold,
                          has_expiry = :has_expiry,
                          expiry_date = :expiry_date,
                          updated_at = NOW()
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $name = htmlspecialchars(strip_tags($this->name));
            $description = htmlspecialchars(strip_tags($this->description ?? ''));
            $category_id = !empty($this->category_id) ? intval($this->category_id) : null;

            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $category_id, is_null($category_id) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $this->price);
            $stmt->bindParam(':stock', $this->stock, PDO::PARAM_INT);
            $stmt->bindParam(':low_stock_threshold', $this->low_stock_threshold, PDO::PARAM_INT);
            $stmt->bindParam(':has_expiry', $this->has_expiry, PDO::PARAM_INT);
            $stmt->bindParam(':expiry_date', $this->expiry_date, is_null($this->expiry_date) ? PDO::PARAM_NULL : PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Product update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete product (Hard Delete)
     * Removes product and all related data (sales, logs)
     */
    public function delete()
    {
        try {
            $this->conn->beginTransaction();

            // 1. Delete from sale_items
            $querySales = "DELETE FROM sale_items WHERE product_id = :id";
            $stmtSales = $this->conn->prepare($querySales);
            $stmtSales->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmtSales->execute();

            // 2. Delete from stock_logs
            $queryLogs = "DELETE FROM stock_logs WHERE product_id = :id";
            $stmtLogs = $this->conn->prepare($queryLogs);
            $stmtLogs->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmtLogs->execute();

            // 3. Delete the product itself
            $queryProduct = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmtProduct = $this->conn->prepare($queryProduct);
            $stmtProduct->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmtProduct->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Product cascade delete error: " . $e->getMessage());
            return false;
        }
    }
}
?>
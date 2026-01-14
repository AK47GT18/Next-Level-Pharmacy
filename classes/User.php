<?php
// filepath: classes/User.php

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $phone;
    public $password;
    public $password_hash;
    public $role;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Login user with email and password
     */
    public function login($email, $password) {
        $query = "SELECT id, name, email, password_hash, role FROM " . $this->table . " WHERE email = :email LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $this->verifyPassword($password, $user['password_hash'])) {
                return [
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
            }
        }

        return false;
    }

    /**
     * Get all users
     */
    public function getAll() {
        $query = "SELECT id, name, email, phone, role, created_at 
                  FROM " . $this->table . " 
                  ORDER BY name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $query = "SELECT id, name, email, phone, role, created_at 
                  FROM " . $this->table . " 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new user
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (name, email, phone, password_hash, role)
                  VALUES
                  (:name, :email, :phone, :password_hash, :role)";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? ''));

        // ✅ Hash password if not already hashed
        if (!empty($this->password) && empty($this->password_hash)) {
            $this->password_hash = $this->hashPassword($this->password);
        }

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':role', $this->role);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Update user
     */
    public function update() {
        $query = "UPDATE " . $this->table . " SET
                  name = :name,
                  email = :email,
                  phone = :phone,
                  role = :role";

        // ✅ Only update password if provided
        $updatePassword = false;
        if (!empty($this->password)) {
            $this->password_hash = $this->hashPassword($this->password);
            $query .= ", password_hash = :password_hash";
            $updatePassword = true;
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? ''));

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':role', $this->role);

        if ($updatePassword) {
            $stmt->bindParam(':password_hash', $this->password_hash);
        }

        return $stmt->execute();
    }

    /**
     * ✅ FIXED: Delete user - proper method signature
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";

        if ($excludeId) {
            $query .= " AND id != :id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);

        if ($excludeId) {
            $stmt->bindParam(':id', $excludeId);
        }

        $stmt->execute();
        return $stmt->fetch() ? true : false;
    }

    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
?>
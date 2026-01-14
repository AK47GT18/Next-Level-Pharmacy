<?php
/**
 * Database Connection Class
 *
 * Establishes a PDO connection to the database using the Singleton pattern.
 */
class Database
{
    private static $instance = null;
    private $conn;

    private $host = 'sql212.infinityfree.com';
    private $db_name = 'if0_40370459_next_level';
    private $username = 'if0_40370459';
    private $password = '3ymHlQ1I5m'; // As requested, no passw

    // Private constructor so it can't be instantiated directly
    private function __construct()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }
    }

    // The single entry point to get the instance
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Get the database connection
    public function getConnection()
    {
        return $this->conn;
    }
}
?>
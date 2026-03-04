<?php
require_once __DIR__ . '/../../config/database.php';
try {
    $db = Database::getInstance()->getConnection();

    // Add last_activity column if it doesn't exist
    $check = $db->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN last_activity timestamp NULL DEFAULT NULL");
        echo "Column 'last_activity' added successfully.<br>";
    } else {
        echo "Column 'last_activity' already exists.<br>";
    }

    // Ensure we are active right now
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        echo "Updated current user activity.<br>";
    }
    echo "Database migration completed.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
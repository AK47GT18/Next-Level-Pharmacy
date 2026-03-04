<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\logout.php
require_once './includes/PathHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all of the session variables.
// Unset all of the session variables.
if (isset($_SESSION['user_id'])) {
    require_once './config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
        // Set last_activity to NULL to indicate explicit logout/inactive
        $stmt = $db->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Continue with logout even if db update fails
    }
}

$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page (NOT dashboard!)
header('Location: ' . PathHelper::getBaseUrl() . '/login.php');
exit;
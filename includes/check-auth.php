<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\includes\check-auth.php

// ✅ Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Update last_activity for the logged-in user on EVERY request (API or Page)
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Silently fail log
    }
}

// ✅ Detect if this is an API call
$isApiCall = (
    strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/checkout.php') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/products.php') !== false ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// ✅ If it's an API call, don't redirect - let the API handle auth
if ($isApiCall) {
    // Just return, don't output anything
    return;
}

// ✅ For regular pages, check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the page they tried to access
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

    // Redirect to login
    header('Location: login.php');
    exit;
}

// User is logged in, continue


?>
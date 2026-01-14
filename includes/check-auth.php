<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\includes\check-auth.php

// ✅ Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
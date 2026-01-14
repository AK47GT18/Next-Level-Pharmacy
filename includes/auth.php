<?php
require_once 'session.php';

/**
 * Check if user is authenticated and has required role
 * @param array|string $allowed_roles Array of allowed roles or single role
 * @return bool
 */
function checkAuth($allowed_roles = null) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        https_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit;
    }

    // If no roles specified, just check authentication
    if ($allowed_roles === null) {
        return true;
    }

    // Convert single role to array
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    // Check role
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        https_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }

    return true;
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if user has specific role
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function hasRole($roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array($_SESSION['role'], $roles);
}
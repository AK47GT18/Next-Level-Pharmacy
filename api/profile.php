<?php
// filepath: api/profile.php
// Allows any authenticated user to update their OWN profile
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE)
    session_start();

// Authentication required
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';

try {
    $db = Database::getInstance()->getConnection();
    $user = new User($db);

    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id']; // User can only update their own profile

    // GET - Get current user profile
    if ($method === 'GET') {
        $userData = $user->getById($userId);
        if ($userData) {
            echo json_encode(['success' => true, 'user' => $userData]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }

    // PUT - Update current user profile
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }

        // Check email uniqueness (excluding current user)
        $existing = $user->getByEmail($email);
        if ($existing && $existing['id'] != $userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit;
        }

        // Get current user data to preserve role
        $currentUser = $user->getById($userId);

        $user->id = $userId;
        $user->name = $name;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $currentUser['role']; // Preserve current role

        if (!empty($password)) {
            $user->password_hash = $user->hashPassword($password);
        }

        if ($user->update()) {
            // Update session name if changed
            $_SESSION['name'] = $name;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
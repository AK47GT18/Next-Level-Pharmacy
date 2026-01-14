<?php
// filepath: api/users.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Authentication
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
    $id = $_GET['id'] ?? null;

    // DELETE user
    if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit;
        }
        if (intval($id) === intval($_SESSION['user_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
            exit;
        }
        if ($user->delete(intval($id))) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        exit;
    }

    // GET user(s)
    if ($method === 'GET') {
        if ($id) {
            $userData = $user->getById(intval($id));
            if ($userData) {
                echo json_encode(['success' => true, 'user' => $userData]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } else {
            $users = $user->getAll();
            echo json_encode(['success' => true, 'users' => $users]);
        }
        exit;
    }

    // POST - create or update
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }

        $userId = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $role = trim($input['role'] ?? 'staff');
        $password = trim($input['password'] ?? '');

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }

        // Check email uniqueness
        $existing = $user->getByEmail($email);
        if ($existing && (!$userId || $existing['id'] != intval($userId))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // CREATE
        if (!$userId) {
            if (empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password is required']);
                exit;
            }
            $user->name = $name;
            $user->email = $email;
            $user->phone = $phone;
            $user->role = $role;
            $user->password_hash = $user->hashPassword($password);

            if ($user->create()) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $user->id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create user']);
            }
            exit;
        }

        // UPDATE
        $user->id = intval($userId);
        $user->name = $name;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $role;
        if (!empty($password)) $user->password_hash = $user->hashPassword($password);

        if ($user->update()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
                
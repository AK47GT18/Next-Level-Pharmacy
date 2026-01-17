<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\auth\login.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}


try {
    $db = Database::getInstance()->getConnection();
    $userClass = new User($db);

    $user = $userClass->getByEmail($email);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
        exit;
    }

    // Verify the password
    if (password_verify($password, $user['password_hash'])) {
        // Password is correct, start the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Determine redirect URL
        $redirectUrl = 'index.php?page=dashboard';

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful!',
            'redirect' => $redirectUrl
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    }
} catch (Exception $e) {
    error_log('Login API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Please try again later.']);
}
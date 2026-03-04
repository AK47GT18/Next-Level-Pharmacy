<?php
// filepath: api/notifications/test.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Notification.php';

try {
    $db = Database::getInstance()->getConnection();
    $notificationHandler = new Notification($db);

    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['name'] ?? 'User';

    $title = "Test Notification";
    $message = "This is a test notification sent at " . date('H:i:s') . ". If you see this in your notification bell, notifications are working correctly!";
    $type = 'info';

    if ($notificationHandler->create($userId, $title, $message, $type)) {
        echo json_encode([
            'success' => true,
            'message' => 'Test notification sent! Click the bell icon to see it.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create test notification.']);
    }

} catch (Exception $e) {
    error_log("Test Notification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Notification.php';

$db = Database::getInstance()->getConnection();
$notificationHandler = new Notification($db);

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $notificationHandler->markAllAsRead((int)$userId);
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
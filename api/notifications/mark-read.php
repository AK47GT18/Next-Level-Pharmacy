<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Notification.php';

$db = Database::getInstance()->getConnection();
$notificationHandler = new Notification($db);

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID is required.']);
    exit;
}

try {
    // Delete the notification instead of just marking as read
    if ($notificationHandler->delete((int) $notificationId, (int) $userId)) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found or you do not have permission.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
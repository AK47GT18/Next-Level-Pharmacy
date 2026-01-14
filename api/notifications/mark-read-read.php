<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\notifications\mark-all-read.php

require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET `read` = 1 
        WHERE user_id = ? AND `read` = 0
    ");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} catch (Exception $e) {
    error_log('Mark all read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
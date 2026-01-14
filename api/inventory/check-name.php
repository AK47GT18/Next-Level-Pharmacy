<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\inventory\check-name.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['exists' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$name = $_GET['name'] ?? '';
$excludeId = isset($_GET['excludeId']) ? (int)$_GET['excludeId'] : null;

if (empty($name)) {
    echo json_encode(['exists' => false, 'message' => 'Name not provided.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT id FROM products WHERE LOWER(name) = LOWER(?)";
    $params = [$name];

    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $exists = $stmt->fetch() !== false;

    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    error_log("Check name error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exists' => false, 'message' => 'Server error during name check.']);
}
<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\api\notification-settings.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ✅ Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // ✅ GET - Retrieve notification settings
    if ($method === 'GET') {
        $stmt = $conn->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            // ✅ Create default settings if they don't exist
            $defaults = [
                'email_notifications' => true,
                'low_stock_alerts' => true,
                'expiring_soon_alerts' => true,
                'daily_sales_summary' => false,
                'system_updates' => true
            ];

            $insertStmt = $conn->prepare("
                INSERT INTO user_notification_settings 
                (user_id, email_notifications, low_stock_alerts, expiring_soon_alerts, daily_sales_summary, system_updates) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $userId,
                $defaults['email_notifications'] ? 1 : 0,
                $defaults['low_stock_alerts'] ? 1 : 0,
                $defaults['expiring_soon_alerts'] ? 1 : 0,
                $defaults['daily_sales_summary'] ? 1 : 0,
                $defaults['system_updates'] ? 1 : 0
            ]);

            $settings = $defaults;
        } else {
            // ✅ Convert to boolean
            $settings = [
                'user_id' => $settings['user_id'],
                'email_notifications' => (bool)$settings['email_notifications'],
                'low_stock_alerts' => (bool)$settings['low_stock_alerts'],
                'expiring_soon_alerts' => (bool)$settings['expiring_soon_alerts'],
                'daily_sales_summary' => (bool)$settings['daily_sales_summary'],
                'system_updates' => (bool)$settings['system_updates']
            ];
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $settings]);
        exit;
    }

    // ✅ PUT - Update notification settings
    if ($method === 'PUT') {
        $updateFields = [];
        $params = [];

        // ✅ Validate and map all settings
        $allowedSettings = [
            'email_notifications',
            'low_stock_alerts',
            'expiring_soon_alerts',
            'daily_sales_summary',
            'system_updates'
        ];

        foreach ($allowedSettings as $setting) {
            if (isset($input[$setting])) {
                $updateFields[] = "$setting = ?";
                $params[] = (int)(bool)$input[$setting];
            }
        }

        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid settings provided']);
            exit;
        }

        $params[] = $userId;
        $query = "UPDATE user_notification_settings SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt->execute($params)) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Notification settings updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('Notification Settings API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
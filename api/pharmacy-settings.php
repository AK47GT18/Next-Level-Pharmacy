<?php
// filepath: api/pharmacy-settings.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE)
    session_start();

// Admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Ensure table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `pharmacy_settings` (
        `setting_key` varchar(50) NOT NULL,
        `setting_value` text DEFAULT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->query("SELECT setting_key, setting_value FROM pharmacy_settings");
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Defaults
        $settings = array_merge([
            'pharmacy_name' => 'Next-Level Pharmacy Malawi',
            'pharmacy_address' => 'Rumphi, Livingstonia',
            'pharmacy_phone' => '+265 999 123 456'
        ], $results);

        echo json_encode(['success' => true, 'data' => $settings]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }

        $db->beginTransaction();
        foreach ($input as $key => $value) {
            $stmt = $db->prepare("INSERT INTO pharmacy_settings (setting_key, setting_value) 
                                VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction())
        $db->rollBack();
    error_log("Pharmacy Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
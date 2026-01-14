<?php
/**
 * RxPMS Database Setup Script
 * Run this script to sync categories and initial data.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

echo "<h1>RxPMS Database Setup</h1>";
echo "<p>Starting synchronization...</p>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // 1. Ensure product_types exist
    $productTypes = [
        ['name' => 'Medicine', 'icon' => 'fa-capsules'],
        ['name' => 'Cosmetic', 'icon' => 'fa-magic'],
        ['name' => 'Skincare', 'icon' => 'fa-pump-medical'],
        ['name' => 'Perfume', 'icon' => 'fa-spray-can']
    ];

    foreach ($productTypes as $type) {
        $stmt = $conn->prepare("INSERT IGNORE INTO product_types (name, icon_class) VALUES (?, ?)");
        $stmt->execute([$type['name'], $type['icon']]);
    }
    echo "<p>✓ Product types verified.</p>";

    // 2. Get Medicine Product Type ID
    $medTypeStmt = $conn->query("SELECT id FROM product_types WHERE name = 'Medicine'");
    $medTypeId = $medTypeStmt->fetchColumn();

    if (!$medTypeId) {
        throw new Exception("Medicine product type not found. Please check database tables.");
    }

    // 3. Sync Medicine Categories from Constants
    $count = 0;
    foreach (MEDICINE_CATEGORIES as $categoryName) {
        if ($categoryName === 'Other')
            continue;

        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND product_type_id = ?");
        $stmt->execute([$categoryName, $medTypeId]);

        if (!$stmt->fetch()) {
            $insert = $conn->prepare("INSERT INTO categories (name, product_type_id) VALUES (?, ?)");
            $insert->execute([$categoryName, $medTypeId]);
            $count++;
            echo "<li>Added category: $categoryName</li>";
        }
    }

    echo "<p>✓ Sync complete. Added $count new categories.</p>";
    echo "<p><strong>SUCCESS: Your database is now up to date.</strong></p>";
    echo "<p><a href='../index.php?page=inventory'>Return to Inventory</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'><strong>ERROR: " . $e->getMessage() . "</strong></p>";
}
?>
<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Product Types: Cosmetic (ID could vary, but name is unique), Other
    $types = [
        'Cosmetic' => 'Cosmetics',
        'Other' => 'Others'
    ];

    foreach ($types as $typeName => $catName) {
        // Get type ID
        $stmt = $db->prepare("SELECT id FROM product_types WHERE name = ?");
        $stmt->execute([$typeName]);
        $type = $stmt->fetch();

        if ($type) {
            $typeId = $type['id'];

            // Check if category exists
            $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$catName]);
            if (!$stmt->fetch()) {
                // Add category
                $stmt = $db->prepare("INSERT INTO categories (name, product_type_id) VALUES (?, ?)");
                if ($stmt->execute([$catName, $typeId])) {
                    echo "Added category: $catName under type: $typeName\n";
                }
            } else {
                echo "Category: $catName already exists.\n";
            }
        } else {
            // If type doesn't exist, add it first? 
            // The schema says they should be there, but let's be safe.
            echo "Warning: Product type $typeName not found. Skipping $catName.\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

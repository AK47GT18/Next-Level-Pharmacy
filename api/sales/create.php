<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Sale.php';
require_once __DIR__ . '/../../classes/Product.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['items']) || empty($data['total_amount'])) {
        https_response_code(400);
        echo json_encode(['error' => 'Invalid sale data']);
        exit;
    }

    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();

    $sale = new Sale($db);
    $product = new Product($db);

    // Set sale properties
    $sale->cashier_id = $_SESSION['user_id'] ?? 1;
    $sale->subtotal = $data['subtotal'];
    $sale->discount_amount = $data['discount_amount'] ?? 0;
    $sale->tax_amount = $data['tax_amount'] ?? 0;
    $sale->total_amount = $data['total_amount'];
    $sale->customer_name = $data['customer_name'] ?? null;
    $sale->customer_phone = $data['customer_phone'] ?? null;

    // Create the sale
    $saleId = $sale->create();

    if (!$saleId) {
        https_response_code(500);
        echo json_encode(['error' => 'Failed to create sale']);
        exit;
    }

    // Add items to sale and update stock
    foreach ($data['items'] as $item) {
        $sale->addItem($saleId, $item['product_id'], $item['quantity'], $item['unit_price']);
        $product->updateStock($item['product_id'], -$item['quantity']);
    }

    https_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Sale created successfully',
        'sale_id' => $saleId
    ]);

} catch (Exception $e) {
    https_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
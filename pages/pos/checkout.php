<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\pos\checkout.php

// 1. Clear any previous output buffers so we only send JSON
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide HTML errors
ini_set('log_errors', 1);     // Log errors to file

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Strict Output Clearing function
function sendJson($data, $code = 200) {
    ob_clean(); // Discard any previous output (warnings, whitespace)
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/StockLog.php';

try {
    $db = Database::getInstance()->getConnection();
    $stockLog = new StockLog($db);

    // Get raw POST data
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON received');
    }

    $cart = $input['cart'] ?? [];
    $paymentMethod = $input['paymentMethod'] ?? 'cash';
    $userId = $_SESSION['user_id'];

    if (empty($cart)) {
        sendJson(['success' => false, 'message' => 'Cart is empty'], 400);
    }

    $db->beginTransaction();

    // 1. Calculate total
    $totalAmount = 0;
    foreach ($cart as $item) {
        $totalAmount += floatval($item['price']) * intval($item['qty']);
    }

    // 2. Create sale record
    $saleStmt = $db->prepare("INSERT INTO sales (sold_by, total_amount, created_at) VALUES (?, ?, NOW())");
    if (!$saleStmt->execute([$userId, $totalAmount])) {
        throw new Exception('Failed to create sale record');
    }
    $saleId = $db->lastInsertId();

    // 3. Create payment record
    $paymentStmt = $db->prepare("INSERT INTO payments (sale_id, payment_method, amount) VALUES (?, ?, ?)");
    if (!$paymentStmt->execute([$saleId, $paymentMethod, $totalAmount])) {
        throw new Exception('Failed to create payment record');
    }

    // 4. Process each cart item
    $saleItemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, total) VALUES (?, ?, ?, ?, ?)");
    $updateStockStmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $checkStockStmt = $db->prepare("SELECT name, stock FROM products WHERE id = ?");

    foreach ($cart as $item) {
        $productId = intval($item['id']);
        $quantity = intval($item['qty']);
        $priceAtSale = floatval($item['price']);
        $itemTotal = $quantity * $priceAtSale;

        // Check stock
        $checkStockStmt->execute([$productId]);
        $product = $checkStockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product ID {$productId} not found");
        }

        if ($product['stock'] < $quantity) {
            throw new Exception("Insufficient stock for '{$product['name']}'. Available: {$product['stock']}, Requested: {$quantity}");
        }

        // Insert sale item
        if (!$saleItemStmt->execute([$saleId, $productId, $quantity, $priceAtSale, $itemTotal])) {
            throw new Exception('Failed to create sale item');
        }

        // Update stock
        if (!$updateStockStmt->execute([$quantity, $productId])) {
            throw new Exception('Failed to update product stock');
        }

        // Log stock change (Silent fail if log errors)
        try {
            $stockLog->create($productId, -$quantity, 'sale', $userId, "Sale #{$saleId}");
        } catch (Exception $logError) {
            error_log("StockLog warning: " . $logError->getMessage());
        }
    }

    $db->commit();

    sendJson([
        'success' => true,
        'message' => 'Sale completed successfully',
        'sale_id' => intval($saleId),
        'total_amount' => floatval($totalAmount)
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Checkout Error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 500);
}
<?php
// filepath: api/sales/update.php
// Admin-only: Update sale item quantities with stock recalculation

require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../config/database.php";

header("Content-Type: application/json");

checkAuth(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!validateCsrfToken($input["csrf_token"] ?? "")) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid security token. Please refresh the page."]);
    exit;
}

$saleId = intval($input["sale_id"] ?? 0);
$items = $input["items"] ?? [];

if ($saleId <= 0 || empty($items)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "sale_id and items are required"]);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $newTotal = 0;

    foreach ($items as $item) {
        $saleItemId = intval($item["sale_item_id"] ?? 0);
        $newQuantity = max(1, intval($item["new_quantity"] ?? 1));

        if ($saleItemId <= 0) continue;

        $stmt = $conn->prepare("SELECT si.*, p.stock FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.id = ? AND si.sale_id = ?");
        $stmt->execute([$saleItemId, $saleId]);
        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentItem) continue;

        $oldQuantity = (int) $currentItem["quantity"];
        $priceAtSale = (float) $currentItem["price_at_sale"];
        $quantityDiff = $oldQuantity - $newQuantity;
        $newItemTotal = $newQuantity * $priceAtSale;

        $updateStmt = $conn->prepare("UPDATE sale_items SET quantity = ?, total = ? WHERE id = ?");
        $updateStmt->execute([$newQuantity, $newItemTotal, $saleItemId]);

        if ($quantityDiff !== 0) {
            $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$quantityDiff, $currentItem["product_id"]]);
            $conn->prepare("INSERT INTO stock_logs (product_id, quantity_change, type, notes, created_by) VALUES (?, ?, \"adjustment\", ?, ?)")
                 ->execute([$currentItem["product_id"], $quantityDiff, "Sale #$saleId edited: diff $quantityDiff (was $oldQuantity, now $newQuantity)", $_SESSION["user_id"]]);
        }

        $newTotal += $newItemTotal;
    }

    $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?")->execute([$newTotal, $saleId]);
    $conn->prepare("UPDATE payments SET amount = ? WHERE sale_id = ?")->execute([$newTotal, $saleId]);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Sale updated successfully", "new_total" => $newTotal]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("Sale update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update sale"]);
}

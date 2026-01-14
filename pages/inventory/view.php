<?php
require_once '../../components/shared/card.php';
require_once '../../components/shared/button.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$medicine = json_decode(file_get_contents("https://localhost/Next-Level/rxpms/api/inventory/get.php?id=$id"), true);

$backButton = new Button('Back to List', 'button', 'gray', 'fa-arrow-left', [
    'onclick' => "window.location.href='index.php'"
]);

$editButton = new Button('Edit Medicine', 'button', 'blue', 'fa-edit', [
    'onclick' => "window.location.href='edit.php?id=$id'"
]);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <?= $backButton->render() ?>
            <h1 class="text-2xl font-bold text-gray-900">Medicine Details</h1>
        </div>
        <?= $editButton->render() ?>
    </div>

    <!-- Medicine Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <div class="glassmorphism rounded-2xl p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-pills text-blue-600 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?= $medicine['name'] ?></h2>
                        <p class="text-gray-500"><?= $medicine['category'] ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Medicine Code</p>
                        <p class="font-semibold"><?= $medicine['code'] ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Generic Name</p>
                        <p class="font-semibold"><?= $medicine['generic_name'] ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Manufacturer</p>
                        <p class="font-semibold"><?= $medicine['manufacturer'] ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Category</p>
                        <p class="font-semibold"><?= $medicine['category'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Description</h3>
                <p class="text-gray-600"><?= $medicine['description'] ?></p>
            </div>

            <!-- Usage & Side Effects -->
            <div class="grid grid-cols-2 gap-6">
                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Usage Instructions</h3>
                    <p class="text-gray-600"><?= $medicine['usage'] ?></p>
                </div>
                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Side Effects</h3>
                    <p class="text-gray-600"><?= $medicine['side_effects'] ?></p>
                </div>
            </div>
        </div>

        <!-- Stock Info -->
        <div class="space-y-6">
            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Stock Information</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Current Stock</p>
                        <p class="text-2xl font-bold"><?= $medicine['quantity_in_stock'] ?> units</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Reorder Level</p>
                            <p class="font-semibold"><?= $medicine['reorder_level'] ?> units</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Max Stock</p>
                            <p class="font-semibold"><?= $medicine['max_stock'] ?> units</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Pricing</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Selling Price</p>
                        <p class="text-2xl font-bold text-emerald-600">
                            <?= formatCurrency($medicine['selling_price']) ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Cost Price</p>
                            <p class="font-semibold"><?= formatCurrency($medicine['cost_price']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Profit Margin</p>
                            <p class="font-semibold text-emerald-600">
                                <?= calculateMargin($medicine['selling_price'], $medicine['cost_price']) ?>%
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function formatCurrency($amount) {
    return 'MWK ' . number_format($amount, 2);
}

function calculateMargin($selling, $cost) {
    return round((($selling - $cost) / $cost) * 100, 1);
}
?>
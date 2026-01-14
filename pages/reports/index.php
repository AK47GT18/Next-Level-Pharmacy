<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\index.php

// ✅ Check session status before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

// ✅ Handle Report Views (NO download logic here)
if (isset($_GET['view'])) {
    $view = $_GET['view'];
    $view_path = __DIR__ . '/' . $view . '.php';
    if (file_exists($view_path)) {
        include $view_path;
    } else {
        echo "<p>Report view '{$view}' not found.</p>";
    }
} else {
    // --- Main Dashboard View ---
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Fetch Quick Stats
    $todaySales = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;
    $lowStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= low_stock_threshold")->fetchColumn() ?? 0;
    
    // ✅ Fixed: Profit calculation
    $totalProfitToday = $conn->query("SELECT SUM((si.price_at_sale - COALESCE(p.cost_price, 0)) * si.quantity) FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) = CURDATE()")->fetchColumn() ?? 0;

    // Define report types
    $reports = [
        ['id' => 'sales', 'title' => 'Sales Reports', 'description' => 'View detailed sales analysis and trends', 'icon' => 'fa-chart-line', 'color' => 'blue', 'link' => '?page=reports&view=sales'],
        ['id' => 'inventory', 'title' => 'Inventory Reports', 'description' => 'Monitor stock levels and movements', 'icon' => 'fa-boxes', 'color' => 'emerald', 'link' => '?page=reports&view=inventory'],
        ['id' => 'financial', 'title' => 'Financial Reports', 'description' => 'Revenue, profits, and financial analytics', 'icon' => 'fa-dollar-sign', 'color' => 'rose', 'link' => '?page=reports&view=financial']
    ];
?>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Reports Dashboard</h1>
            <p class="text-gray-500">Access and generate various system reports</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-4">Today's Sales</h3>
                <p class="text-2xl font-bold text-gray-900">MWK <?= number_format($todaySales, 2) ?></p>
                <a href="?page=reports&view=sales" class="text-sm text-blue-600 hover:text-blue-700 mt-2 inline-block">View Details →</a>
            </div>
            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-4">Today's Profit (Est.)</h3>
                <p class="text-2xl font-bold text-gray-900">MWK <?= number_format($totalProfitToday, 2) ?></p>
                <a href="?page=reports&view=financial" class="text-sm text-blue-600 hover:text-blue-700 mt-2 inline-block">View Details →</a>
            </div>
            <div class="glassmorphism rounded-2xl p-6">
                <h3 class="text-sm font-semibold text-gray-600 mb-4">Low Stock Items</h3>
                <p class="text-2xl font-bold text-gray-900"><?= $lowStockCount ?> Items</p>
                <a href="?page=reports&view=inventory" class="text-sm text-blue-600 hover:text-blue-700 mt-2 inline-block">View Details →</a>
            </div>
        </div>

        <!-- Report Types Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($reports as $report): ?>
            <a href="<?= $report['link'] ?>" class="group block">
                <div class="glassmorphism rounded-2xl p-6 h-full hover:shadow-lg transition-all duration-300 relative overflow-hidden">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-<?= $report['color'] ?>-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <i class="fas <?= $report['icon'] ?> text-<?= $report['color'] ?>-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900"><?= $report['title'] ?></h3>
                            <p class="text-sm text-gray-500"><?= $report['description'] ?></p>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php 
} // End of main dashboard else block
?>
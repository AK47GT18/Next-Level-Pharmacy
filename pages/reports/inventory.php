<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\inventory.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get filter parameters
    $categoryFilter = $_GET['category'] ?? '';
    $stockFilter = $_GET['stock_level'] ?? '';

    // Build query with filters
    $query = "
        SELECT 
            p.id, 
            p.name, 
            p.stock,
            p.low_stock_threshold,
            p.price,
            c.id as category_id,
            c.name as category
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1
    ";

    $params = [];

    if ($categoryFilter) {
        $query .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($stockFilter === 'low') {
        $query .= " AND p.stock > 0 AND p.stock <= p.low_stock_threshold";
    } elseif ($stockFilter === 'out') {
        $query .= " AND p.stock = 0";
    } elseif ($stockFilter === 'normal') {
        $query .= " AND p.stock > p.low_stock_threshold";
    }

    $query .= " ORDER BY p.stock ASC, p.name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $totalItems = count($products);
    $lowStockItems = 0;
    $outOfStockItems = 0;
    $stockValue = 0;

    foreach ($products as $product) {
        if ((int) $product['stock'] <= 0) {
            $outOfStockItems++;
        } elseif ((int) $product['stock'] <= (int) $product['low_stock_threshold']) {
            $lowStockItems++;
        }
        $stockValue += (float) ($product['price'] ?? 0) * (int) $product['stock'];
    }

    // Get all categories for filter dropdown
    $categoriesStmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get low stock items (top 6 for alerts)
    $lowStockQuery = "
        SELECT id, name, stock, low_stock_threshold 
        FROM products 
        WHERE stock > 0 AND stock <= low_stock_threshold 
        ORDER BY stock ASC 
        LIMIT 6
    ";
    $lowStockAlerts = $conn->query($lowStockQuery)->fetchAll(PDO::FETCH_ASSOC);

    // Get stock by category for chart
    $categoryStatsQuery = "
        SELECT c.name as category, COUNT(p.id) as count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        GROUP BY c.id, c.name
        ORDER BY count DESC
    ";
    $categoryStats = $conn->query($categoryStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Inventory Report Error: ' . $e->getMessage());
    echo "<div class='p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-800'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    exit;
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inventory Report</h1>
            <p class="text-gray-500">Monitor your stock levels and movement</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=inventory"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Items</h3>
            <p class="text-3xl font-bold text-gray-900"><?= number_format($totalItems) ?></p>
            <div class="flex items-center gap-1 text-sm mt-1 text-gray-500">
                <span class="text-gray-500">items in inventory</span>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">Low Stock Items</h3>
            <p class="text-3xl font-bold text-amber-600"><?= number_format($lowStockItems) ?></p>
            <div class="flex items-center gap-1 text-sm mt-1 text-gray-500">
                <span class="text-gray-500">items need reorder</span>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">Out of Stock</h3>
            <p class="text-3xl font-bold text-rose-600"><?= number_format($outOfStockItems) ?></p>
            <div class="flex items-center gap-1 text-sm mt-1 text-gray-500">
                <span class="text-gray-500">items out of stock</span>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">Stock Value</h3>
            <p class="text-3xl font-bold text-gray-900">MWK <?= number_format($stockValue, 2) ?></p>
            <div class="flex items-center gap-1 text-sm mt-1 text-gray-500">
                <span class="text-gray-500">total inventory value</span>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Stock by Category</h3>
            <canvas id="categoryChart" height="300"></canvas>
        </div>

        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Stock Status Distribution</h3>
            <canvas id="statusChart" height="300"></canvas>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <?php if (!empty($lowStockAlerts)): ?>
        <div class="glassmorphism rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Low Stock Alerts</h3>
                <a href="?page=reports&view=inventory&stock_level=low"
                    class="px-4 py-2 text-amber-600 bg-amber-50 rounded-xl text-sm font-semibold hover:bg-amber-100 transition-all">
                    View All
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($lowStockAlerts as $item): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h4>
                            <span class="text-xs font-bold text-amber-700 px-2 py-1 bg-amber-200 rounded-full">LOW</span>
                        </div>
                        <div class="flex items-baseline gap-2 mt-2">
                            <p class="text-2xl font-bold text-gray-900"><?= $item['stock'] ?></p>
                            <p class="text-sm text-gray-500">/ <?= $item['low_stock_threshold'] ?> units</p>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <?php
                            $percentage = ($item['stock'] / $item['low_stock_threshold']) * 100;
                            $barColor = $percentage < 50 ? 'bg-rose-500' : 'bg-amber-500';
                            ?>
                            <div class="<?= $barColor ?> h-2 rounded-full" style="width: <?= min($percentage, 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Inventory Table -->
    <div class="glassmorphism rounded-2xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-900">Inventory List</h3>
            <form method="GET" class="flex items-center gap-3">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="view" value="inventory">

                <select name="category"
                    class="rounded-xl border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="stock_level"
                    class="rounded-xl border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Stock Levels</option>
                    <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    <option value="normal" <?= $stockFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                </select>

                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all">
                    <i class="fas fa-filter mr-1"></i>Apply
                </button>

                <?php if ($categoryFilter || $stockFilter): ?>
                    <a href="?page=reports&view=inventory"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-all">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stock Level</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Threshold</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Value</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-box-open text-4xl text-gray-300 mb-3"></i>
                                <p class="text-lg font-medium">No products found</p>
                                <p class="text-sm">Try adjusting your filters</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $statusBadge = '';
                            if ($product['stock'] <= 0) {
                                $statusBadge = '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-rose-100 text-rose-800"><i class="fas fa-times-circle mr-1"></i>Out of Stock</span>';
                            } elseif ($product['stock'] <= $product['low_stock_threshold']) {
                                $statusBadge = '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800"><i class="fas fa-exclamation-triangle mr-1"></i>Low Stock</span>';
                            } else {
                                $statusBadge = '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800"><i class="fas fa-check-circle mr-1"></i>In Stock</span>';
                            }
                            $itemValue = ($product['price'] ?? 0) * $product['stock'];
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($product['name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium">
                                        <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <span class="text-lg font-bold text-gray-900"><?= $product['stock'] ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <?= $product['low_stock_threshold'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold text-right">
                                    MWK <?= number_format($itemValue, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <?= $statusBadge ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            return;
        }

        // Category Chart
        const categoryCategoryStats = <?= json_encode($categoryStats) ?>;
        if (categoryCategoryStats.length > 0) {
            const categoryCtx = document.getElementById('categoryChart');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categoryCategoryStats.map(s => s.category),
                        datasets: [{
                            data: categoryCategoryStats.map(s => s.count),
                            backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#64748b', '#ec4899', '#14b8a6'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 15, font: { size: 12 } }
                            }
                        }
                    }
                });
            }
        }

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const inStock = <?= $totalItems - $lowStockItems - $outOfStockItems ?>;
            const lowStock = <?= $lowStockItems ?>;
            const outOfStock = <?= $outOfStockItems ?>;

            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                    datasets: [{
                        data: [inStock, lowStock, outOfStock],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, font: { size: 12 } }
                        }
                    }
                }
            });
        }
    });
</script>
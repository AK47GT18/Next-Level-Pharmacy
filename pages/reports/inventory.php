<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\inventory.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db   = Database::getInstance();
    $conn = $db->getConnection();

    // Filters
    $categoryFilter = $_GET['category']    ?? '';
    $stockFilter    = $_GET['stock_level'] ?? '';

    // Pagination
    $perPage     = 20;
    $currentPage = isset($_GET['pg']) && is_numeric($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;

    // ── WHERE clause (shared) ────────────────────────────────────────────
    $where  = " WHERE 1=1";
    $params = [];

    if ($categoryFilter) {
        $where   .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }
    if ($stockFilter === 'low') {
        $where .= " AND p.stock > 0 AND p.stock <= p.low_stock_threshold";
    } elseif ($stockFilter === 'out') {
        $where .= " AND p.stock = 0";
    } elseif ($stockFilter === 'normal') {
        $where .= " AND p.stock > p.low_stock_threshold";
    }

    // ── COUNT ────────────────────────────────────────────────────────────
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id" . $where);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalRecords / $perPage));
    $currentPage  = min($currentPage, $totalPages);
    $offset       = ($currentPage - 1) * $perPage;

    // ── PAGINATED products ───────────────────────────────────────────────
    $pageParams   = $params;
    $pageParams[] = $perPage;
    $pageParams[] = $offset;

    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.stock, p.low_stock_threshold, p.price,
               c.id as category_id, c.name as category
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        {$where}
        ORDER BY p.name ASC
        LIMIT ? OFFSET ?
    ");
    foreach ($pageParams as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── FULL set stats (not paged) ───────────────────────────────────────
    $allStmt = $conn->prepare("
        SELECT p.stock, p.low_stock_threshold, p.price
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        {$where}
    ");
    $allStmt->execute($params);
    $allProducts = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalItems      = count($allProducts);
    $lowStockItems   = 0;
    $outOfStockItems = 0;
    $stockValue      = 0;

    foreach ($allProducts as $p) {
        if ((int)$p['stock'] <= 0)                                         $outOfStockItems++;
        elseif ((int)$p['stock'] <= (int)$p['low_stock_threshold'])        $lowStockItems++;
        $stockValue += (float)($p['price'] ?? 0) * (int)$p['stock'];
    }

    // ── Categories dropdown ──────────────────────────────────────────────
    $categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // ── Low stock alerts (top 6) ─────────────────────────────────────────
    $lowStockAlerts = $conn->query("
        SELECT id, name, stock, low_stock_threshold FROM products
        WHERE stock > 0 AND stock <= low_stock_threshold
        ORDER BY stock ASC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Category chart data ──────────────────────────────────────────────
    $categoryStats = $conn->query("
        SELECT c.name as category, COUNT(p.id) as count
        FROM products p LEFT JOIN categories c ON p.category_id = c.id
        GROUP BY c.id, c.name ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Inventory Report Error: ' . $e->getMessage());
    echo "<div class='p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-800'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Helper: build pagination URL preserving all filters
function invPgUrl(int $pg): string {
    $p = $_GET; $p['pg'] = $pg;
    return '?' . http_build_query($p);
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inventory Report</h1>
            <p class="text-gray-500 mt-0.5">Monitor your stock levels and movement</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports"
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=inventory"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all text-sm font-medium">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <?php
        $cards = [
            ['label'=>'Total Items',     'val'=>number_format($totalItems),          'sub'=>'items in inventory',    'color'=>'text-gray-900',    'icon'=>'fa-boxes',           'bg'=>'bg-gray-100',    'ic'=>'text-gray-500'],
            ['label'=>'Low Stock',       'val'=>number_format($lowStockItems),        'sub'=>'items need reorder',    'color'=>'text-amber-600',   'icon'=>'fa-exclamation-triangle','bg'=>'bg-amber-100','ic'=>'text-amber-500'],
            ['label'=>'Out of Stock',    'val'=>number_format($outOfStockItems),      'sub'=>'items out of stock',    'color'=>'text-rose-600',    'icon'=>'fa-times-circle',    'bg'=>'bg-rose-100',    'ic'=>'text-rose-500'],
            ['label'=>'Stock Value',     'val'=>'MWK '.number_format($stockValue,2), 'sub'=>'total inventory value', 'color'=>'text-gray-900',    'icon'=>'fa-dollar-sign',     'bg'=>'bg-blue-100',    'ic'=>'text-blue-500'],
        ];
        foreach ($cards as $c): ?>
            <div class="glassmorphism rounded-2xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 <?= $c['bg'] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $c['icon'] ?> <?= $c['ic'] ?>"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-0.5"><?= $c['label'] ?></p>
                    <p class="text-2xl font-bold <?= $c['color'] ?>"><?= $c['val'] ?></p>
                    <p class="text-xs text-gray-400 mt-0.5"><?= $c['sub'] ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Stock by Category</h3>
            <canvas id="categoryChart" height="300"></canvas>
        </div>
        <div class="glassmorphism rounded-2xl p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Stock Status Distribution</h3>
            <canvas id="statusChart" height="300"></canvas>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <?php if (!empty($lowStockAlerts)): ?>
        <div class="glassmorphism rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
                <h3 class="text-base font-bold text-gray-900">Low Stock Alerts</h3>
                <a href="?page=reports&view=inventory&stock_level=low"
                   class="px-3 py-1.5 text-amber-600 bg-amber-50 border border-amber-100 rounded-xl text-xs font-semibold hover:bg-amber-100 transition-all">
                    View All
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($lowStockAlerts as $item): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($item['name']) ?></h4>
                            <span class="text-[10px] font-bold text-amber-700 px-2 py-0.5 bg-amber-200 rounded-full uppercase tracking-wide">Low</span>
                        </div>
                        <div class="flex items-baseline gap-1.5">
                            <p class="text-2xl font-bold text-gray-900"><?= $item['stock'] ?></p>
                            <p class="text-xs text-gray-500">/ <?= $item['low_stock_threshold'] ?> threshold</p>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2.5">
                            <?php
                            $pct      = ($item['stock'] / max(1, $item['low_stock_threshold'])) * 100;
                            $barColor = $pct < 50 ? 'bg-rose-500' : 'bg-amber-500';
                            ?>
                            <div class="<?= $barColor ?> h-1.5 rounded-full transition-all" style="width:<?= min($pct,100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Inventory Table -->
    <div class="glassmorphism rounded-2xl p-6">
        <!-- Table header + filters -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-4">
            <div>
                <h3 class="text-base font-bold text-gray-900">Inventory List</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Showing
                    <span class="font-semibold text-gray-700"><?= number_format($offset + 1) ?></span>–<span class="font-semibold text-gray-700"><?= number_format(min($offset + $perPage, $totalRecords)) ?></span>
                    of <span class="font-semibold text-gray-700"><?= number_format($totalRecords) ?></span> products
                </p>
            </div>
            <form method="GET" class="flex items-center gap-2 flex-wrap">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="view" value="inventory">
                <input type="hidden" name="pg"   value="1">

                <select name="category"
                        class="px-3 py-2 bg-white rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="stock_level"
                        class="px-3 py-2 bg-white rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="">All Stock Levels</option>
                    <option value="low"    <?= $stockFilter==='low'    ? 'selected':'' ?>>Low Stock</option>
                    <option value="out"    <?= $stockFilter==='out'    ? 'selected':'' ?>>Out of Stock</option>
                    <option value="normal" <?= $stockFilter==='normal' ? 'selected':'' ?>>Normal</option>
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
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3.5 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider">Product Name</th>
                        <th class="px-5 py-3.5 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Threshold</th>
                        <th class="px-5 py-3.5 text-right  text-xs font-semibold text-gray-500 uppercase tracking-wider">Value</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-box-open text-4xl text-gray-300"></i>
                                    <p class="text-base font-semibold">No products found</p>
                                    <p class="text-sm text-gray-400">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product):
                            $stock     = (int)$product['stock'];
                            $threshold = (int)$product['low_stock_threshold'];
                            $itemValue = (float)($product['price'] ?? 0) * $stock;

                            if ($stock <= 0) {
                                $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700"><i class="fas fa-times-circle text-[10px]"></i>Out of Stock</span>';
                            } elseif ($stock <= $threshold) {
                                $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><i class="fas fa-exclamation-triangle text-[10px]"></i>Low Stock</span>';
                            } else {
                                $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><i class="fas fa-check-circle text-[10px]"></i>In Stock</span>';
                            }
                        ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    <?= htmlspecialchars($product['name']) ?>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 rounded-lg text-xs font-medium">
                                        <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    <span class="text-base font-bold <?= $stock <= 0 ? 'text-rose-600' : ($stock <= $threshold ? 'text-amber-600' : 'text-gray-900') ?>">
                                        <?= $stock ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <?= $threshold ?>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                    MWK <?= number_format($itemValue, 2) ?>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    <?= $badge ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-5 pt-5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-4">
                <p class="text-sm text-gray-500">
                    Page <span class="font-semibold text-gray-800"><?= $currentPage ?></span>
                    of <span class="font-semibold text-gray-800"><?= $totalPages ?></span>
                    &mdash; <?= number_format($totalRecords) ?> total products
                </p>
                <div class="flex items-center gap-1">
                    <!-- Prev -->
                    <a href="<?= $currentPage > 1 ? invPgUrl($currentPage - 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage > 1 ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>

                    <?php
                    $pgStart = max(1, $currentPage - 2);
                    $pgEnd   = min($totalPages, $currentPage + 2);
                    if ($pgStart > 1): ?>
                        <a href="<?= invPgUrl(1) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all">1</a>
                        <?php if ($pgStart > 2): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($pg = $pgStart; $pg <= $pgEnd; $pg++): ?>
                        <a href="<?= invPgUrl($pg) ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-semibold transition-all
                                  <?= $pg === $currentPage ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $pg ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pgEnd < $totalPages): ?>
                        <?php if ($pgEnd < $totalPages - 1): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                        <a href="<?= invPgUrl($totalPages) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <!-- Next -->
                    <a href="<?= $currentPage < $totalPages ? invPgUrl($currentPage + 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage < $totalPages ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const categoryStats = <?= json_encode($categoryStats) ?>;
    if (categoryStats.length > 0) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryStats.map(s => s.category),
                datasets: [{
                    data: categoryStats.map(s => s.count),
                    backgroundColor: ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#64748b','#ec4899','#14b8a6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: true, cutout: '70%',
                plugins: { legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } } }
            }
        });
    }

    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: ['In Stock', 'Low Stock', 'Out of Stock'],
            datasets: [{
                data: [<?= $totalItems - $lowStockItems - $outOfStockItems ?>, <?= $lowStockItems ?>, <?= $outOfStockItems ?>],
                backgroundColor: ['#10b981','#f59e0b','#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } } }
        }
    });
});
</script>
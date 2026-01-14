<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\dashboard\index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../components/dashboard/stat-card.php';
require_once __DIR__ . '/../../components/dashboard/quick-actions.php';
require_once __DIR__ . '/../../components/dashboard/sales-table.php';
require_once __DIR__ . '/../../components/dashboard/low-stock-alert.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// ✅ Get user's first name from database
$userId = $_SESSION['user_id'] ?? null;
$userFirstName = 'User';

if ($userId) {
    $userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $userFirstName = explode(' ', $userData['name'])[0];
    }
}

$_SESSION['role'] = $_SESSION['role'] ?? 'guest';

try {
    $todaySales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;

    $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn() ?? 0;

    $lowStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= low_stock_threshold")->fetchColumn() ?? 0;
    $outOfStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn() ?? 0;

    $totalCustomers = $conn->query("SELECT COUNT(DISTINCT sold_by) FROM sales")->fetchColumn() ?? 0;

    // Total Inventory Value
    $totalInventoryValue = $conn->query("SELECT SUM(stock * cost_price) FROM products")->fetchColumn() ?? 0;

    $recentSalesQuery = $conn->query("
        SELECT 
            s.id, 
            s.total_amount, 
            s.created_at, 
            u.name as sold_by,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count,
            (SELECT payment_method FROM payments WHERE sale_id = s.id ORDER BY created_at DESC LIMIT 1) as payment_method
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        ORDER BY s.created_at DESC
        LIMIT 4
    ");
    $recentSalesData = $recentSalesQuery->fetchAll(PDO::FETCH_ASSOC);

    $recentSales = array_map(function ($sale) {
        $now = new DateTime();
        $created = new DateTime($sale['created_at']);
        $diff = $now->diff($created);

        if ($diff->d > 0) {
            $timeAgo = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } else {
            $timeAgo = max(1, $diff->i) . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
        }

        return [
            'invoice' => '#' . str_pad($sale['id'], 5, '0', STR_PAD_LEFT),
            'customer' => 'Sale by ' . htmlspecialchars($sale['sold_by'] ?? 'Unknown'),
            'amount' => (float) $sale['total_amount'],
            'status' => 'Completed',
            'date' => $timeAgo,
            'items_count' => $sale['items_count'],
            'payment_method' => !empty($sale['payment_method']) ? ucwords(str_replace('_', ' ', $sale['payment_method'])) : 'Cash'
        ];
    }, $recentSalesData);

    $lowStockQuery = $conn->query("
        SELECT id, name, stock, low_stock_threshold
        FROM products
        WHERE stock > 0 AND stock <= low_stock_threshold
        ORDER BY stock ASC
        LIMIT 4
    ");
    $lowStockData = $lowStockQuery->fetchAll(PDO::FETCH_ASSOC);

    $lowStockItems = array_map(function ($item) {
        $level = $item['stock'] <= ($item['low_stock_threshold'] * 0.5) ? 'critical' : 'low';
        return [
            'name' => htmlspecialchars($item['name']),
            'stock' => (int) $item['stock'],
            'threshold' => (int) $item['low_stock_threshold'],
            'level' => $level
        ];
    }, $lowStockData);

    $topProductsQuery = $conn->query("
        SELECT p.name, SUM(si.quantity) as units_sold, SUM(si.total) as revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name
        ORDER BY units_sold DESC
        LIMIT 5
    ");
    $topProducts = $topProductsQuery->fetchAll(PDO::FETCH_ASSOC) ?? [];

    $stats = [
        'total_sales' => [
            'value' => 'MWK ' . number_format($todaySales, 2),
            'growth' => null,
            'subtitle' => 'Today'
        ],
        'total_products' => [
            'value' => (int) $totalProducts,
            'growth' => null,
            'subtitle' => 'in inventory'
        ],
        'low_stock' => [
            'value' => (int) $lowStockCount,
            'growth' => null,
            'subtitle' => 'need reorder'
        ],
        'total_cashiers' => [
            'value' => (int) $totalCustomers,
            'growth' => null,
            'subtitle' => 'active users'
        ],
        'inventory_value' => [
            'value' => 'MWK ' . number_format($totalInventoryValue, 2),
            'growth' => null,
            'subtitle' => 'total assets'
        ]
    ];

    $quickActions = new QuickActions();
    $salesTable = new SalesTable($recentSales);
    $lowStockAlert = new LowStockAlert($lowStockItems);

} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    echo "<div class='p-6 bg-rose-100 border border-rose-300 rounded-lg text-rose-800'>";
    echo "<i class='fas fa-exclamation-circle mr-2'></i>Error loading dashboard data: " . htmlspecialchars($e->getMessage());
    echo "</div>";
    exit;
}
?>

<div class="space-y-6 md:space-y-8">
    <!-- Welcome Section -->
    <div class="animate-slide-in">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">
            Welcome back, <?= htmlspecialchars($userFirstName) ?>! 👋
        </h1>
        <p class="text-gray-600">Here's what's happening with your store today.</p>
    </div>

    <!-- Quick Actions -->
    <div class="animate-slide-in animation-delay-100">
        <?= $quickActions->render() ?>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 animate-slide-in animation-delay-200">
        <?php
        $statCards = [
            ['title' => 'Total Sales (Today)', 'icon' => 'fa-dollar-sign', 'color' => 'blue', 'data' => $stats['total_sales']],
            ['title' => 'Total Products', 'icon' => 'fa-box', 'color' => 'indigo', 'data' => $stats['total_products']],
            ['title' => 'Low Stock Items', 'icon' => 'fa-exclamation-triangle', 'color' => 'amber', 'data' => $stats['low_stock']],
            ['title' => 'Inventory Value', 'icon' => 'fa-vault', 'color' => 'emerald', 'data' => $stats['inventory_value']]
        ];

        foreach ($statCards as $card) {
            echo (new StatCard(
                $card['title'],
                $card['data']['value'],
                $card['icon'],
                $card['data']['growth'],
                $card['color'],
                $card['data']['subtitle']
            ))->render();
        }
        ?>
    </div>

    <!-- Recent Sales and Low Stock -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-slide-in animation-delay-400">
        <?= $salesTable->render() ?>
        <?= $lowStockAlert->render() ?>
    </div>

    <!-- Top Products Chart -->
    <?php if (!empty($topProducts)): ?>
        <div class="glassmorphism rounded-2xl shadow-lg p-6 animate-slide-in animation-delay-500">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Top Selling Products (Last 30 Days)</h2>
            <div style="position: relative; height: 300px;">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const topProductsCtx = document.getElementById('topProductsChart');
        if (topProductsCtx && typeof Chart !== 'undefined') {
            const products = <?= json_encode($topProducts) ?>;

            if (products.length > 0) {
                new Chart(topProductsCtx, {
                    type: 'bar',
                    data: {
                        labels: products.map(p => p.name.substring(0, 20)),
                        datasets: [{
                            label: 'Units Sold',
                            data: products.map(p => parseInt(p.units_sold)),
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1,
                            borderRadius: 6
                        }, {
                            label: 'Revenue (MWK)',
                            data: products.map(p => parseFloat(p.revenue)),
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, labels: { padding: 15 } },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        if (context.dataset.label === 'Revenue (MWK)') {
                                            return context.dataset.label + ': MWK ' + context.parsed.y.toLocaleString();
                                        }
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function (value) {
                                        return value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
</script>
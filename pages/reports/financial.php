<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\financial.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Date filtering
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');

    // Validate dates
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));

    $query = "
        SELECT 
            p.id, 
            p.name, 
            p.cost_price,
            SUM(si.quantity) as units_sold,
            AVG(si.price_at_sale) as avg_sale_price,
            SUM(si.total) as total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.cost_price
        ORDER BY total_revenue DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $financials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRevenue = 0;
    $totalCost = 0;
    $chartLabels = [];
    $chartRevenue = [];
    $chartCost = [];
    $chartProfit = [];

    foreach ($financials as $item) {
        $itemCost = (float)($item['cost_price'] ?? 0) * (int)$item['units_sold'];
        $itemProfit = (float)$item['total_revenue'] - $itemCost;
        
        $totalRevenue += (float)$item['total_revenue'];
        $totalCost += $itemCost;
        
        // Prepare chart data (top 10 products)
        if (count($chartLabels) < 10) {
            $chartLabels[] = $item['name'];
            $chartRevenue[] = (float)$item['total_revenue'];
            $chartCost[] = $itemCost;
            $chartProfit[] = $itemProfit;
        }
    }

    $totalProfit = $totalRevenue - $totalCost;
    $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

} catch (Exception $e) {
    error_log('Financial Report Error: ' . $e->getMessage());
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
            <h1 class="text-2xl font-bold text-gray-900">Financial Report</h1>
            <p class="text-gray-500">Analysis of revenue, costs, and profits.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=financial&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="glassmorphism rounded-2xl p-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="financial">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" 
                       name="start_date" 
                       id="start_date" 
                       value="<?= htmlspecialchars($startDate) ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" 
                       name="end_date" 
                       id="end_date" 
                       value="<?= htmlspecialchars($endDate) ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all">
                <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
            <?php if ($startDate !== date('Y-m-01') || $endDate !== date('Y-m-t')): ?>
            <a href="?page=reports&view=financial" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-up text-emerald-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Total Revenue</h3>
                <p class="text-2xl font-bold text-emerald-600">MWK <?= number_format($totalRevenue, 2) ?></p>
            </div>
        </div>
        
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-down text-rose-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Total Costs</h3>
                <p class="text-2xl font-bold text-rose-600">MWK <?= number_format($totalCost, 2) ?></p>
            </div>
        </div>
        
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-piggy-bank text-blue-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Net Profit</h3>
                <p class="text-2xl font-bold text-blue-600">MWK <?= number_format($totalProfit, 2) ?></p>
            </div>
        </div>
        
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-percentage text-purple-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Profit Margin</h3>
                <p class="text-2xl font-bold text-purple-600"><?= number_format($profitMargin, 1) ?>%</p>
            </div>
        </div>
    </div>

    <!-- Financial Chart -->
    <?php if (!empty($chartLabels)): ?>
    <div class="glassmorphism rounded-2xl p-6 shadow-lg">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Financial Overview - Top Products</h3>
        <div style="position: relative; height: 350px;">
            <canvas id="financialChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Financial Table -->
    <div class="glassmorphism rounded-2xl p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Product Performance Details</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Profit</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($financials)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-chart-line text-4xl text-gray-300 mb-3"></i>
                                <p class="text-lg font-medium">No financial data found</p>
                                <p class="text-sm">Try selecting a different date range</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($financials as $item): 
                            $itemCost = (float)($item['cost_price'] ?? 0) * (int)$item['units_sold'];
                            $itemProfit = (float)$item['total_revenue'] - $itemCost;
                            // ✅ Fixed: Correct profit margin calculation
                            $itemMargin = (float)$item['total_revenue'] > 0 ? (max(0, $itemProfit) / (float)$item['total_revenue']) * 100 : 0;
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($item['name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?= number_format($item['units_sold']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    MWK <?= number_format($item['avg_sale_price'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-emerald-600 text-right">
                                    MWK <?= number_format($item['total_revenue'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-rose-600 text-right">
                                    MWK <?= number_format($itemCost, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right <?= $itemProfit >= 0 ? 'text-blue-600' : 'text-rose-600' ?>">
                                    MWK <?= number_format($itemProfit, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $itemMargin >= 30 ? 'bg-emerald-100 text-emerald-800' : ($itemMargin >= 15 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                                        <?= number_format($itemMargin, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($financials)): ?>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right text-sm text-gray-700">TOTALS:</td>
                            <td class="px-6 py-4 text-right text-sm text-emerald-600">
                                MWK <?= number_format($totalRevenue, 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-rose-600">
                                MWK <?= number_format($totalCost, 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-blue-600">
                                MWK <?= number_format($totalProfit, 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900">
                                <?= number_format($profitMargin, 1) ?>%
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($chartLabels) && !empty($chartRevenue)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('financialChart');
    
    if (!ctx || typeof Chart === 'undefined') {
        console.error('Chart not available');
        return;
    }
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Revenue (MWK)',
                    data: <?= json_encode($chartRevenue) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1
                },
                {
                    label: 'Cost (MWK)',
                    data: <?= json_encode($chartCost) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                },
                {
                    label: 'Profit (MWK)',
                    data: <?= json_encode($chartProfit) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { padding: 15 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': MWK ' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'MWK ' + (value / 1000).toFixed(0) + 'k';
                        }
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>
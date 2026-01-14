<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\sales.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Date filtering
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    $paymentMethod = $_GET['payment_method'] ?? 'all';

    // Validate dates
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));

    // Build query with payment filter
    $query = "
        SELECT 
            s.id, 
            s.total_amount,
            s.created_at, 
            u.name as sold_by,
            u.id as sold_by_id,
            COALESCE(p.payment_method, 'cash') as payment_method,
            (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as items_count
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";

    $params = [$startDate, $endDate];

    if ($paymentMethod !== 'all') {
        $query .= " AND p.payment_method = ?";
        $params[] = $paymentMethod;
    }

    $query .= " ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $totalSales = array_reduce($sales, fn($sum, $sale) => $sum + (float) ($sale['total_amount'] ?? 0), 0);
    $totalTransactions = count($sales);
    $avgSaleValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

    // Get sales trend data for chart (last 30 days)
    $trendQuery = "
        SELECT 
            DATE(created_at) as sale_date,
            SUM(total_amount) as daily_total,
            COUNT(*) as transaction_count
        FROM sales
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ";
    $trendStmt = $conn->query($trendQuery);
    $trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare chart data
    $chartLabels = [];
    $chartValues = [];
    $chartCounts = [];

    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('M j', strtotime($date));

        // Find matching data or use 0
        $found = false;
        foreach ($trendData as $trend) {
            if ($trend['sale_date'] === $date) {
                $chartValues[] = (float) $trend['daily_total'];
                $chartCounts[] = (int) $trend['transaction_count'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chartValues[] = 0;
            $chartCounts[] = 0;
        }
    }

    // Get payment method breakdown
    $paymentBreakdownQuery = "
        SELECT 
            COALESCE(p.payment_method, 'cash') as method,
            COUNT(s.id) as count,
            SUM(s.total_amount) as total
        FROM sales s
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN :startDate AND :endDate
        GROUP BY COALESCE(p.payment_method, 'cash')
    ";
    $paymentStmt = $conn->prepare($paymentBreakdownQuery);
    $paymentStmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
    $paymentBreakdown = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

    $dailyItemQuery = "
        SELECT 
            DATE(s.created_at) as sale_date,
            p.name as product_name,
            SUM(si.quantity) as total_qty,
            SUM(si.total) as total_revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY DATE(s.created_at), p.id
        ORDER BY sale_date DESC, total_revenue DESC
    ";
    $dailyItemStmt = $conn->prepare($dailyItemQuery);
    $dailyItemStmt->execute([$startDate, $endDate]);
    $dailyItems = $dailyItemStmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedDailyItems = [];
    foreach ($dailyItems as $item) {
        $date = $item['sale_date'];
        if (!isset($groupedDailyItems[$date])) {
            $groupedDailyItems[$date] = [
                'items' => [],
                'total_revenue' => 0
            ];
        }
        $groupedDailyItems[$date]['items'][] = $item;
        $groupedDailyItems[$date]['total_revenue'] += (float) $item['total_revenue'];
    }

} catch (Exception $e) {
    error_log('Sales Report Error: ' . $e->getMessage());
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
            <h1 class="text-2xl font-bold text-gray-900">Sales Report</h1>
            <p class="text-gray-500">Detailed analysis of sales transactions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=sales&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?><?= $paymentMethod !== 'all' ? '&payment_method=' . urlencode($paymentMethod) : '' ?>"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="glassmorphism rounded-2xl p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="sales">

            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>"
                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>"
                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select name="payment_method" id="payment_method"
                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>All Methods</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="mobile_money" <?= $paymentMethod === 'mobile_money' ? 'selected' : '' ?>>Mobile Money
                    </option>
                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer
                    </option>
                </select>
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button type="submit"
                    class="flex-1 px-5 py-2 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i>
                    <span>Apply Filters</span>
                </button>
                <?php if ($paymentMethod !== 'all' || $startDate !== date('Y-m-01') || $endDate !== date('Y-m-t')): ?>
                    <a href="?page=reports&view=sales"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all flex items-center gap-2">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Sales Trend Chart -->
    <div class="glassmorphism rounded-2xl p-6 shadow-lg">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Sales Trend (Last 30 Days)</h3>
        <div style="position: relative; height: 300px;">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-dollar-sign text-blue-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Total Revenue</h3>
                <p class="text-2xl font-bold text-gray-900">MWK <?= number_format($totalSales, 2) ?></p>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-receipt text-emerald-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Total Transactions</h3>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalTransactions) ?></p>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-balance-scale text-amber-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-600 mb-1">Average Sale Value</h3>
                <p class="text-2xl font-bold text-gray-900">MWK <?= number_format($avgSaleValue, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- Daily Itemized Summary -->
    <div class="glassmorphism rounded-2xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-900">Daily Itemized Summary</h3>
            <span
                class="text-xs font-medium text-gray-400 bg-gray-50 px-3 py-1 rounded-full border border-gray-100 italic">Sorted
                by revenue per day</span>
        </div>

        <?php if (empty($groupedDailyItems)): ?>
            <div class="text-center py-10 text-gray-500">
                <i class="fas fa-list-ul text-3xl mb-3 opacity-20"></i>
                <p>No itemized data available for the selected period.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($groupedDailyItems as $date => $data): ?>
                    <div class="border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                        <div class="bg-gray-50/80 px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <span class="font-bold text-gray-700 flex items-center gap-2">
                                <i class="far fa-calendar-alt text-blue-500"></i>
                                <?= date('l, M j, Y', strtotime($date)) ?>
                            </span>
                            <span class="text-sm font-bold text-blue-600">
                                Day Total: MWK <?= number_format($data['total_revenue'], 2) ?>
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-white text-gray-500 font-semibold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Product / Item Name</th>
                                        <th class="px-6 py-3 text-center">Qty Sold</th>
                                        <th class="px-6 py-3 text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach ($data['items'] as $item): ?>
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-6 py-3 font-medium text-gray-900">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <span
                                                    class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-md font-semibold text-xs">
                                                    <?= $item['total_qty'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-right font-bold text-gray-900">MWK
                                                <?= number_format($item['total_revenue'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sales Table -->
    <div class="glassmorphism rounded-2xl p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Transaction Details</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale
                            ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cashier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg font-medium">No sales found</p>
                                    <p class="text-sm">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= str_pad($sale['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($sale['created_at'])) ?>
                                    <span class="text-gray-400">at <?= date('H:i', strtotime($sale['created_at'])) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-user-circle mr-1 text-gray-400"></i>
                                    <?= htmlspecialchars($sale['sold_by'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= $sale['items_count'] ?> item<?= $sale['items_count'] != 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $paymentIcons = [
                                        'cash' => ['icon' => 'fa-money-bill-wave', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'],
                                        'card' => ['icon' => 'fa-credit-card', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
                                        'mobile_money' => ['icon' => 'fa-mobile-alt', 'bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
                                        'bank_transfer' => ['icon' => 'fa-university', 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-800']
                                    ];
                                    $payment = $paymentIcons[$sale['payment_method']] ?? ['icon' => 'fa-question', 'bg' => 'bg-gray-100', 'text' => 'text-gray-800'];
                                    ?>
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $payment['bg'] ?> <?= $payment['text'] ?>">
                                        <i class="fas <?= $payment['icon'] ?>"></i>
                                        <?= ucwords(str_replace('_', ' ', $sale['payment_method'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold text-right">
                                    MWK <?= number_format($sale['total_amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($sales)): ?>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-right text-sm text-gray-700">Total:</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900 font-bold">
                                MWK <?= number_format($totalSales, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('salesTrendChart');

            if (!ctx) {
                console.error('Canvas element not found');
                return;
            }

            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [{
                        label: 'Daily Sales (MWK)',
                        data: <?= json_encode($chartValues) ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return 'MWK ' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return 'MWK ' + (value / 1000).toFixed(0) + 'k';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: { display: false },
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
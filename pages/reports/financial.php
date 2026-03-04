<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\reports\financial.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db   = Database::getInstance();
    $conn = $db->getConnection();

    // Date filtering
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate   = $_GET['end_date']   ?? date('Y-m-t');
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate   = date('Y-m-d', strtotime($endDate));

    // Pagination
    $perPage     = 20;
    $currentPage = isset($_GET['pg']) && is_numeric($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;

    // ── COUNT (full set) ─────────────────────────────────────────────────
    $countStmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.id)
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s     ON si.sale_id   = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ");
    $countStmt->execute([$startDate, $endDate]);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalRecords / $perPage));
    $currentPage  = min($currentPage, $totalPages);
    $offset       = ($currentPage - 1) * $perPage;

    // ── PAGINATED rows ───────────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.name,
            SUM(si.quantity)      AS units_sold,
            AVG(si.price_at_sale) AS avg_sale_price,
            SUM(si.total)         AS total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s     ON si.sale_id   = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $startDate);
    $stmt->bindValue(2, $endDate);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $financials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── FULL-SET totals (for summary cards + chart — not paged) ─────────
    $allStmt = $conn->prepare("
        SELECT
            p.name,
            SUM(si.quantity)      AS units_sold,
            SUM(si.total)         AS total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s     ON si.sale_id   = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
    ");
    $allStmt->execute([$startDate, $endDate]);
    $allFinancials = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRevenue = 0;
    $chartLabels  = [];
    $chartRevenue = [];

    foreach ($allFinancials as $item) {
        $totalRevenue += (float)$item['total_revenue'];
        if (count($chartLabels) < 10) {
            $chartLabels[]  = $item['name'];
            $chartRevenue[] = (float)$item['total_revenue'];
        }
    }

    $totalProfit  = $totalRevenue;
    $profitMargin = $totalRevenue > 0 ? 100 : 0;

    // Page subtotal
    $pageRevenue = array_reduce($financials, fn($s, $r) => $s + (float)$r['total_revenue'], 0);

} catch (Exception $e) {
    error_log('Financial Report Error: ' . $e->getMessage());
    echo "<div class='p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-800'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

if (!function_exists('finPgUrl')) {
    function finPgUrl(int $pg): string {
        $p = $_GET; $p['pg'] = $pg;
        return '?' . http_build_query($p);
    }
}
?>

<div class="space-y-8">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Financial Report</h1>
            <p class="text-gray-500 mt-0.5">Analysis of revenue and product performance.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports"
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=financial&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all text-sm font-medium">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="glassmorphism rounded-2xl p-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="financial">
            <input type="hidden" name="pg"   value="1">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                       class="px-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                       class="px-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            <button type="submit"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all flex items-center gap-2">
                <i class="fas fa-filter"></i>Apply Filters
            </button>
            <?php if ($startDate !== date('Y-m-01') || $endDate !== date('Y-m-t')): ?>
                <a href="?page=reports&view=financial"
                   class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-300 transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i>Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Summary Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-5">
            <div class="w-14 h-14 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-arrow-trend-up text-emerald-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Total Revenue</h3>
                <p class="text-2xl font-bold text-emerald-600">MWK <?= number_format($totalRevenue, 2) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= date('M j', strtotime($startDate)) ?> – <?= date('M j, Y', strtotime($endDate)) ?></p>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-5">
            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-boxes-stacked text-blue-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Products Sold</h3>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalRecords) ?></p>
                <p class="text-xs text-gray-400 mt-0.5">unique products</p>
            </div>
        </div>

        <div class="glassmorphism rounded-2xl p-6 flex items-center gap-5">
            <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trophy text-amber-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Top Product</h3>
                <p class="text-base font-bold text-gray-900 truncate max-w-[160px]">
                    <?= !empty($allFinancials) ? htmlspecialchars($allFinancials[0]['name']) : '—' ?>
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    <?= !empty($allFinancials) ? 'MWK '.number_format((float)$allFinancials[0]['total_revenue'], 2) : 'No data' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <?php if (!empty($chartLabels)): ?>
        <div class="glassmorphism rounded-2xl p-6 shadow-sm">
            <h3 class="text-base font-bold text-gray-900 mb-6">Top 10 Products by Revenue</h3>
            <div style="position:relative;height:320px;">
                <canvas id="financialChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="glassmorphism rounded-2xl p-6">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-900">Product Performance</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Showing
                    <span class="font-semibold text-gray-700"><?= number_format($offset + 1) ?></span>–<span class="font-semibold text-gray-700"><?= number_format(min($offset + $perPage, $totalRecords)) ?></span>
                    of <span class="font-semibold text-gray-700"><?= number_format($totalRecords) ?></span> products
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3.5 text-left  text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Units Sold</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Avg Price</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Total Revenue</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">% of Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($financials)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-chart-line text-4xl text-gray-300"></i>
                                    <p class="text-base font-semibold">No financial data found</p>
                                    <p class="text-sm text-gray-400">Try selecting a different date range</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($financials as $item):
                            $share = $totalRevenue > 0 ? ((float)$item['total_revenue'] / $totalRevenue) * 100 : 0;
                        ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-4 text-sm font-semibold text-gray-900">
                                    <?= htmlspecialchars($item['name']) ?>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 text-right whitespace-nowrap">
                                    <?= number_format($item['units_sold']) ?>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 text-right whitespace-nowrap">
                                    MWK <?= number_format($item['avg_sale_price'], 2) ?>
                                </td>
                                <td class="px-5 py-4 text-sm font-bold text-emerald-600 text-right whitespace-nowrap">
                                    MWK <?= number_format($item['total_revenue'], 2) ?>
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-emerald-500 h-1.5 rounded-full" style="width:<?= min($share, 100) ?>%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-500 w-10 text-right"><?= number_format($share, 1) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($financials)): ?>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="3" class="px-5 py-3.5 text-right text-sm text-gray-600 font-semibold">Page Total:</td>
                            <td class="px-5 py-3.5 text-right text-sm font-bold text-gray-700">MWK <?= number_format($pageRevenue, 2) ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-5 py-3 text-right text-sm text-gray-900 font-bold">Overall Total:</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-emerald-600">MWK <?= number_format($totalRevenue, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-4">
                <p class="text-sm text-gray-500">
                    Page <span class="font-semibold text-gray-800"><?= $currentPage ?></span>
                    of <span class="font-semibold text-gray-800"><?= $totalPages ?></span>
                    &mdash; <?= number_format($totalRecords) ?> products
                </p>
                <div class="flex items-center gap-1">
                    <!-- Prev -->
                    <a href="<?= $currentPage > 1 ? finPgUrl($currentPage - 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage > 1 ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>

                    <?php
                    $pgStart = max(1, $currentPage - 2);
                    $pgEnd   = min($totalPages, $currentPage + 2);
                    if ($pgStart > 1): ?>
                        <a href="<?= finPgUrl(1) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all">1</a>
                        <?php if ($pgStart > 2): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($pg = $pgStart; $pg <= $pgEnd; $pg++): ?>
                        <a href="<?= finPgUrl($pg) ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-semibold transition-all
                                  <?= $pg === $currentPage ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $pg ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pgEnd < $totalPages): ?>
                        <?php if ($pgEnd < $totalPages - 1): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                        <a href="<?= finPgUrl($totalPages) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <!-- Next -->
                    <a href="<?= $currentPage < $totalPages ? finPgUrl($currentPage + 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage < $totalPages ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chartLabels) && !empty($chartRevenue)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('financialChart');
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Revenue (MWK)',
                data: <?= json_encode($chartRevenue) ?>,
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderColor: 'rgb(16,185,129)',
                borderWidth: 0,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: c => 'MWK ' + c.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { callback: v => 'MWK ' + (v / 1000).toFixed(0) + 'k' }
                },
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 40, minRotation: 40, font: { size: 11 } }
                }
            }
        }
    });
});
</script>
<?php endif; ?>
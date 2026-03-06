<?php
// filepath: pages/reports/sales.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Date filtering
    $startDate     = $_GET['start_date']     ?? date('Y-m-01');
    $endDate       = $_GET['end_date']       ?? date('Y-m-t');
    $paymentMethod = $_GET['payment_method'] ?? 'all';
    $saleIdSearch  = isset($_GET['sale_id']) && is_numeric($_GET['sale_id']) ? (int)$_GET['sale_id'] : null;
    $dayFilter     = $_GET['day_filter']     ?? '';

    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate   = date('Y-m-d', strtotime($endDate));

    // Pagination
    $perPage     = 15;
    $currentPage = isset($_GET['pg']) && is_numeric($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;

    // ── COUNT query ──────────────────────────────────────────────────────────
    $countQuery  = "SELECT COUNT(*) FROM sales s LEFT JOIN payments p ON s.id = p.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ?";
    $countParams = [$startDate, $endDate];
    if ($paymentMethod !== 'all') { $countQuery .= " AND COALESCE(p.payment_method,'cash') = ?"; $countParams[] = $paymentMethod; }
    if ($saleIdSearch !== null)   { $countQuery .= " AND s.id = ?";                              $countParams[] = $saleIdSearch; }
    if ($dayFilter !== '')        { $countQuery .= " AND DAYNAME(s.created_at) = ?";             $countParams[] = $dayFilter; }

    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalRecords / $perPage));
    $currentPage  = min($currentPage, $totalPages);
    $offset       = ($currentPage - 1) * $perPage;

    // ── MAIN query ───────────────────────────────────────────────────────────
    $query = "
        SELECT
            s.id, s.total_amount, s.created_at,
            u.name AS sold_by, u.id AS sold_by_id,
            COALESCE(p.payment_method, 'cash')  AS payment_method,
            COALESCE(si_counts.cnt, 0)           AS items_count
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN payments p ON s.id = p.sale_id
        LEFT JOIN (SELECT sale_id, COUNT(*) AS cnt FROM sale_items GROUP BY sale_id) si_counts ON s.id = si_counts.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    if ($paymentMethod !== 'all') { $query .= " AND COALESCE(p.payment_method,'cash') = ?"; $params[] = $paymentMethod; }
    if ($saleIdSearch !== null)   { $query .= " AND s.id = ?";                              $params[] = $saleIdSearch; }
    if ($dayFilter !== '')        { $query .= " AND DAYNAME(s.created_at) = ?";             $params[] = $dayFilter; }
    $query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Fetch product names for each sale on this page ────────────────────
    $saleProducts = [];
    if (!empty($sales)) {
        $saleIds = array_column($sales, 'id');
        $inPh    = implode(',', array_fill(0, count($saleIds), '?'));
        $prodStmt = $conn->prepare("
            SELECT si.sale_id, p.name AS product_name, si.quantity, si.price_at_sale, si.total, si.id AS sale_item_id
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id IN ($inPh)
            ORDER BY si.id ASC
        ");
        $prodStmt->execute($saleIds);
        foreach ($prodStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saleProducts[$row['sale_id']][] = $row;
        }
    }

    // ── TOTALS ────────────────────────────────────────────────────────────
    $totalsQuery  = "SELECT COUNT(*) AS tc, SUM(s.total_amount) AS ts FROM sales s LEFT JOIN payments p ON s.id = p.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ?";
    $totalsParams = [$startDate, $endDate];
    if ($paymentMethod !== 'all') { $totalsQuery .= " AND COALESCE(p.payment_method,'cash') = ?"; $totalsParams[] = $paymentMethod; }
    if ($saleIdSearch !== null)   { $totalsQuery .= " AND s.id = ?";                              $totalsParams[] = $saleIdSearch; }
    if ($dayFilter !== '')        { $totalsQuery .= " AND DAYNAME(s.created_at) = ?";             $totalsParams[] = $dayFilter; }
    $totalsStmt = $conn->prepare($totalsQuery);
    $totalsStmt->execute($totalsParams);
    $totalsRow = $totalsStmt->fetch(PDO::FETCH_ASSOC);

    $totalSales        = (float)($totalsRow['ts'] ?? 0);
    $totalTransactions = (int)($totalsRow['tc']   ?? 0);
    $avgSaleValue      = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
    $pageSalesTotal    = array_reduce($sales, fn($s, $r) => $s + (float)($r['total_amount'] ?? 0), 0);

    // ── TREND data ────────────────────────────────────────────────────────
    $trendData = $conn->query("
        SELECT DATE(created_at) AS sale_date, SUM(total_amount) AS daily_total, COUNT(*) AS transaction_count
        FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) ORDER BY sale_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = $chartValues = $chartCounts = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('M j', strtotime($date));
        $found = false;
        foreach ($trendData as $t) {
            if ($t['sale_date'] === $date) { $chartValues[] = (float)$t['daily_total']; $chartCounts[] = (int)$t['transaction_count']; $found = true; break; }
        }
        if (!$found) { $chartValues[] = 0; $chartCounts[] = 0; }
    }

    // ── DAILY ITEMIZED ────────────────────────────────────────────────────
    $diPerPage     = 5;
    $diCurrentPage = isset($_GET['di_pg']) && is_numeric($_GET['di_pg']) ? max(1, (int)$_GET['di_pg']) : 1;
    $groupedDailyItems = [];
    $diTotalPages = 1;

    if ($dayFilter !== '') {
        $diCountQuery  = "SELECT COUNT(DISTINCT DATE(s.created_at)) FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND DAYNAME(s.created_at) = ?";
        $diCountStmt   = $conn->prepare($diCountQuery);
        $diCountStmt->execute([$startDate, $endDate, $dayFilter]);
        $diTotalDates  = (int)$diCountStmt->fetchColumn();
        $diTotalPages  = max(1, (int)ceil($diTotalDates / $diPerPage));
        $diCurrentPage = min($diCurrentPage, $diTotalPages);
        $diOffset      = ($diCurrentPage - 1) * $diPerPage;

        $diDatesStmt = $conn->prepare("SELECT DISTINCT DATE(s.created_at) AS d FROM sales s WHERE DATE(s.created_at) BETWEEN ? AND ? AND DAYNAME(s.created_at) = ? ORDER BY d DESC LIMIT ? OFFSET ?");
        $diDatesStmt->bindValue(1, $startDate);
        $diDatesStmt->bindValue(2, $endDate);
        $diDatesStmt->bindValue(3, $dayFilter);
        $diDatesStmt->bindValue(4, $diPerPage, PDO::PARAM_INT);
        $diDatesStmt->bindValue(5, $diOffset,  PDO::PARAM_INT);
        $diDatesStmt->execute();
        $diDates = array_column($diDatesStmt->fetchAll(PDO::FETCH_ASSOC), 'd');

        if (!empty($diDates)) {
            $inPlaceholders = implode(',', array_fill(0, count($diDates), '?'));
            $diQuery  = "SELECT DATE(s.created_at) AS sale_date, p.name AS product_name, SUM(si.quantity) AS total_qty, SUM(si.total) AS total_revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE DATE(s.created_at) IN ($inPlaceholders) GROUP BY DATE(s.created_at), p.id ORDER BY sale_date DESC, total_revenue DESC";
            $diStmt   = $conn->prepare($diQuery);
            $diStmt->execute($diDates);
            $dailyItems = $diStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dailyItems as $item) {
                $d = $item['sale_date'];
                if (!isset($groupedDailyItems[$d])) $groupedDailyItems[$d] = ['items' => [], 'total_revenue' => 0];
                $groupedDailyItems[$d]['items'][]       = $item;
                $groupedDailyItems[$d]['total_revenue'] += (float)$item['total_revenue'];
            }
        }
    }

} catch (Exception $e) {
    error_log('Sales Report Error: ' . $e->getMessage());
    echo "<div class='p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-800'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

if (!function_exists("pgUrl")) {
    function pgUrl(int $pg): string {
        $p = $_GET; $p['pg'] = $pg;
        return '?' . http_build_query($p);
    }
}
if (!function_exists("diPgUrl")) {
    function diPgUrl(int $pg): string {
        $p = $_GET; $p['di_pg'] = $pg;
        return '?' . http_build_query($p);
    }
}
?>

<div class="space-y-8">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sales Report</h1>
            <p class="text-gray-500 mt-1">Detailed analysis of sales transactions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports"
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=sales&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?><?= $paymentMethod !== 'all' ? '&payment_method='.urlencode($paymentMethod) : '' ?><?= $dayFilter !== '' ? '&day_filter='.urlencode($dayFilter) : '' ?>"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all text-sm font-medium">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="glassmorphism rounded-2xl p-6">
        <form method="GET" class="space-y-5">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="sales">
            <input type="hidden" name="pg"   value="1">

            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                           class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                           class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                    <select name="payment_method"
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="all"           <?= $paymentMethod==='all'           ? 'selected':'' ?>>All Methods</option>
                        <option value="cash"          <?= $paymentMethod==='cash'          ? 'selected':'' ?>>Cash</option>
                        <option value="card"          <?= $paymentMethod==='card'          ? 'selected':'' ?>>Card</option>
                        <option value="mobile_money"  <?= $paymentMethod==='mobile_money'  ? 'selected':'' ?>>Mobile Money</option>
                        <option value="bank_transfer" <?= $paymentMethod==='bank_transfer' ? 'selected':'' ?>>Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search by Sale ID</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-xs">
                            <i class="fas fa-hashtag"></i>
                        </span>
                        <input type="number" name="sale_id"
                               value="<?= htmlspecialchars($_GET['sale_id'] ?? '') ?>"
                               placeholder="e.g. 197" min="1"
                               class="w-full pl-8 pr-3 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                </div>
                <div class="md:col-span-2 flex gap-2 items-end">
                    <button type="submit"
                            class="flex-1 px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-filter"></i>Apply Filters
                    </button>
                    <?php if ($paymentMethod !== 'all' || $startDate !== date('Y-m-01') || $endDate !== date('Y-m-t') || $saleIdSearch !== null || $dayFilter !== ''): ?>
                        <a href="?page=reports&view=sales"
                           class="px-4 py-2.5 bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-300 transition-all flex items-center gap-1.5"
                           title="Clear all filters">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Day Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Quick Filter
                    <span class="text-gray-400 font-normal ml-1">— filter by weekday</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day):
                        $active = $dayFilter === $day; ?>
                        <button type="submit" name="day_filter" value="<?= $day ?>"
                                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all
                                       <?= $active
                                           ? 'bg-blue-600 text-white border-blue-600 shadow'
                                           : 'bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-600' ?>">
                            <?= $day ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if ($dayFilter !== ''): ?>
                        <button type="submit" name="day_filter" value=""
                                class="px-4 py-2 rounded-xl text-sm font-semibold border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-all">
                            <i class="fas fa-times mr-1"></i>Clear Day
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Sales Trend Chart -->
    <div class="glassmorphism rounded-2xl p-6 shadow-lg">
        <h3 class="text-lg font-bold text-gray-900 mb-6">Sales Trend (Last 30 Days)</h3>
        <div style="position:relative;height:300px;">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php
        $cards = [
            ['icon'=>'fa-dollar-sign',   'bg'=>'bg-blue-100',    'ic'=>'text-blue-600',    'label'=>'Total Revenue',      'val'=>'MWK '.number_format($totalSales,2)],
            ['icon'=>'fa-receipt',       'bg'=>'bg-emerald-100', 'ic'=>'text-emerald-600', 'label'=>'Total Transactions', 'val'=>number_format($totalTransactions)],
            ['icon'=>'fa-balance-scale', 'bg'=>'bg-amber-100',   'ic'=>'text-amber-600',   'label'=>'Average Sale Value', 'val'=>'MWK '.number_format($avgSaleValue,2)],
        ];
        foreach ($cards as $c): ?>
            <div class="glassmorphism rounded-2xl p-6 flex items-center gap-5">
                <div class="w-14 h-14 <?= $c['bg'] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $c['icon'] ?> <?= $c['ic'] ?> text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 mb-1"><?= $c['label'] ?></h3>
                    <p class="text-2xl font-bold text-gray-900"><?= $c['val'] ?></p>
                    <?php if ($dayFilter !== ''): ?>
                        <p class="text-xs text-blue-500 mt-0.5"><i class="fas fa-calendar-day mr-1"></i><?= $dayFilter ?>s only</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Daily Itemized Summary -->
    <div class="glassmorphism rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Daily Itemized Summary</h3>
                <p class="text-sm text-gray-400 mt-0.5">Select a weekday filter above to view itemized breakdowns</p>
            </div>
            <?php if ($dayFilter !== ''): ?>
                <span class="text-xs font-medium text-gray-400 bg-gray-50 px-3 py-1 rounded-full border border-gray-100 italic">
                    Sorted by revenue per day
                </span>
            <?php endif; ?>
        </div>

        <?php if ($dayFilter === ''): ?>
            <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl">
                <i class="fas fa-calendar-week text-4xl text-gray-300 mb-3 block"></i>
                <p class="font-semibold text-gray-500">No day selected</p>
                <p class="text-sm text-gray-400 mt-1">Use the <span class="font-medium text-blue-500">Quick Filter</span> buttons above to pick a weekday and see its itemized sales</p>
            </div>
        <?php elseif (empty($groupedDailyItems)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-list-ul text-4xl mb-3 opacity-20 block"></i>
                <p class="font-medium">No itemized data found for <?= htmlspecialchars($dayFilter) ?>s in this period.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($groupedDailyItems as $date => $data): ?>
                    <div class="border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                        <div class="bg-gray-50/80 px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
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
                                            <td class="px-6 py-3.5 font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="px-6 py-3.5 text-center">
                                                <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md font-semibold text-xs"><?= $item['total_qty'] ?></span>
                                            </td>
                                            <td class="px-6 py-3.5 text-right font-bold text-gray-900">MWK <?= number_format($item['total_revenue'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($diTotalPages > 1): ?>
                <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-4">
                    <p class="text-sm text-gray-500">
                        Showing page <span class="font-semibold text-gray-800"><?= $diCurrentPage ?></span>
                        of <span class="font-semibold text-gray-800"><?= $diTotalPages ?></span>
                        &mdash; <?= $diTotalDates ?> <?= htmlspecialchars($dayFilter) ?> dates total
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($diCurrentPage > 1): ?>
                            <a href="<?= diPgUrl(1) ?>" title="First" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition-all text-sm"><i class="fas fa-angle-double-left"></i></a>
                            <a href="<?= diPgUrl($diCurrentPage - 1) ?>" title="Previous" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition-all text-sm"><i class="fas fa-angle-left"></i></a>
                        <?php endif; ?>
                        <?php
                        $diStart = max(1, $diCurrentPage - 2);
                        $diEnd   = min($diTotalPages, $diCurrentPage + 2);
                        if ($diStart > 1) echo '<span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span>';
                        for ($dp = $diStart; $dp <= $diEnd; $dp++): ?>
                            <a href="<?= diPgUrl($dp) ?>"
                               class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-semibold transition-all
                                      <?= $dp === $diCurrentPage ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                                <?= $dp ?>
                            </a>
                        <?php endfor;
                        if ($diEnd < $diTotalPages) echo '<span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span>';
                        ?>
                        <?php if ($diCurrentPage < $diTotalPages): ?>
                            <a href="<?= diPgUrl($diCurrentPage + 1) ?>" title="Next" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition-all text-sm"><i class="fas fa-angle-right"></i></a>
                            <a href="<?= diPgUrl($diTotalPages) ?>" title="Last" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition-all text-sm"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Transaction Details -->
    <div class="glassmorphism rounded-2xl p-6">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Transaction Details</h3>
                <p class="text-sm text-gray-400 mt-0.5 flex flex-wrap items-center gap-2">
                    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRecords)) ?> of <?= number_format($totalRecords) ?> results
                    <?php if ($dayFilter !== ''): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-xs font-semibold border border-blue-100">
                            <i class="fas fa-calendar-day"></i> <?= $dayFilter ?>s
                        </span>
                    <?php endif; ?>
                    <?php if ($saleIdSearch !== null): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-xs font-semibold border border-purple-100">
                            <i class="fas fa-hashtag"></i> Sale #<?= str_pad($saleIdSearch, 5, '0', STR_PAD_LEFT) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-4 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Sale ID</th>
                        <th class="px-5 py-4 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Date &amp; Time</th>
                        <th class="px-5 py-4 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Cashier</th>
                        <th class="px-5 py-4 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider">Products</th>
                        <th class="px-5 py-4 text-left   text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment</th>
                        <th class="px-5 py-4 text-right  text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Total Amount</th>
                        <th class="px-5 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-inbox text-4xl text-gray-300"></i>
                                    <p class="text-lg font-medium">No sales found</p>
                                    <p class="text-sm text-gray-400">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $paymentIcons = [
                            'cash'          => ['icon'=>'fa-money-bill-wave','bg'=>'bg-emerald-100','text'=>'text-emerald-800'],
                            'card'          => ['icon'=>'fa-credit-card',    'bg'=>'bg-blue-100',   'text'=>'text-blue-800'],
                            'mobile_money'  => ['icon'=>'fa-mobile-alt',     'bg'=>'bg-purple-100', 'text'=>'text-purple-800'],
                            'bank_transfer' => ['icon'=>'fa-university',      'bg'=>'bg-indigo-100', 'text'=>'text-indigo-800'],
                        ];
                        foreach ($sales as $sale):
                            $pm       = $paymentIcons[$sale['payment_method']] ?? ['icon'=>'fa-question','bg'=>'bg-gray-100','text'=>'text-gray-800'];
                            $products = $saleProducts[$sale['id']] ?? [];
                        ?>
                            <tr class="hover:bg-gray-50/70 transition-colors align-top">
                                <!-- Sale ID -->
                                <td class="px-5 py-4 whitespace-nowrap text-sm font-bold text-gray-800">
                                    #<?= str_pad($sale['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <!-- Date & Time -->
                                <td class="px-5 py-4 whitespace-nowrap text-sm">
                                    <div class="font-medium text-gray-800"><?= date('M j, Y', strtotime($sale['created_at'])) ?></div>
                                    <div class="text-xs text-gray-400 mt-0.5"><?= date('H:i', strtotime($sale['created_at'])) ?> &middot; <?= date('l', strtotime($sale['created_at'])) ?></div>
                                </td>
                                <!-- Cashier -->
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-user-circle text-gray-400"></i>
                                        <?= htmlspecialchars($sale['sold_by'] ?? 'Unknown') ?>
                                    </div>
                                </td>
                                <!-- Products -->
                                <td class="px-5 py-4 text-sm min-w-[200px]">
                                    <?php if (empty($products)): ?>
                                        <span class="text-gray-300 text-xs italic">No items</span>
                                    <?php else: ?>
                                        <div class="space-y-1">
                                            <?php foreach ($products as $prod): ?>
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-50 text-blue-600 rounded text-[10px] font-bold border border-blue-100 flex-shrink-0">
                                                        <?= $prod['quantity'] ?>
                                                    </span>
                                                    <span class="font-medium text-gray-800 text-xs leading-tight"><?= htmlspecialchars($prod['product_name']) ?></span>
                                                    <span class="text-[10px] text-gray-400 ml-auto whitespace-nowrap">MWK <?= number_format($prod['total'], 2) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <!-- Payment -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $pm['bg'] ?> <?= $pm['text'] ?>">
                                        <i class="fas <?= $pm['icon'] ?>"></i>
                                        <?= ucwords(str_replace('_', ' ', $sale['payment_method'])) ?>
                                    </span>
                                </td>
                                <!-- Total -->
                                <td class="px-5 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                    MWK <?= number_format($sale['total_amount'], 2) ?>
                                </td>
                                <!-- Actions -->
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Print -->
                                        <button onclick="printSale(<?= htmlspecialchars(json_encode($sale)) ?>)"
                                                title="Print receipt"
                                                class="w-8 h-8 inline-flex items-center justify-center bg-gray-100 text-gray-600 rounded-lg text-xs hover:bg-gray-800 hover:text-white transition-all">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <!-- Edit -->
                                        <button onclick="editSale(<?= $sale['id'] ?>)"
                                                title="Edit sale"
                                                class="w-8 h-8 inline-flex items-center justify-center bg-blue-50 text-blue-600 border border-blue-100 rounded-lg text-xs hover:bg-blue-600 hover:text-white transition-all">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <!-- Delete -->
                                        <button onclick="deleteSale(<?= $sale['id'] ?>)"
                                                title="Void &amp; delete sale"
                                                class="w-8 h-8 inline-flex items-center justify-center bg-red-50 text-red-500 border border-red-100 rounded-lg text-xs hover:bg-red-500 hover:text-white transition-all">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($sales)): ?>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="5" class="px-5 py-3 text-right text-sm text-gray-600 font-semibold">Page Total:</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-gray-700">MWK <?= number_format($pageSalesTotal, 2) ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="px-5 py-3 text-right text-sm text-gray-900 font-bold">
                                Overall Total (<?= number_format($totalTransactions) ?> transactions):
                            </td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-blue-700">MWK <?= number_format($totalSales, 2) ?></td>
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
                    <span class="font-semibold text-gray-800"><?= number_format($offset + 1) ?></span>–<span class="font-semibold text-gray-800"><?= number_format(min($offset + $perPage, $totalRecords)) ?></span>
                    of <span class="font-semibold text-gray-800"><?= number_format($totalRecords) ?></span> transactions
                </p>
                <div class="flex items-center gap-1">
                    <a href="<?= $currentPage > 1 ? pgUrl($currentPage - 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage > 1 ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 cursor-not-allowed' ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php
                    $pgStart = max(1, $currentPage - 2);
                    $pgEnd   = min($totalPages, $currentPage + 2);
                    if ($pgStart > 1): ?>
                        <a href="<?= pgUrl(1) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all">1</a>
                        <?php if ($pgStart > 2): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($pg = $pgStart; $pg <= $pgEnd; $pg++): ?>
                        <a href="<?= pgUrl($pg) ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-semibold transition-all
                                  <?= $pg === $currentPage ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $pg ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($pgEnd < $totalPages): ?>
                        <?php if ($pgEnd < $totalPages - 1): ?><span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">…</span><?php endif; ?>
                        <a href="<?= pgUrl($totalPages) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-100 transition-all"><?= $totalPages ?></a>
                    <?php endif; ?>
                    <a href="<?= $currentPage < $totalPages ? pgUrl($currentPage + 1) : '#' ?>"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm transition-all
                              <?= $currentPage < $totalPages ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-gray-100 text-gray-300 cursor-not-allowed' ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Edit Sale Modal ─────────────────────────────────────────────────── -->
<div id="editSaleModal" class="hidden fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Modify Sale <span id="editSaleIdDisplay" class="text-blue-600"></span></h2>
                <p class="text-xs text-gray-400 mt-0.5">Adjust quantities or remove items</p>
            </div>
            <button onclick="closeEditModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all text-xs">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="editSaleContent" class="p-5 max-h-[55vh] overflow-y-auto space-y-3">
            <!-- Loaded via AJAX -->
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">New Total</p>
                <p id="editSaleNewTotal" class="text-lg font-bold text-gray-900">MWK 0.00</p>
            </div>
            <button onclick="saveSaleChanges()" id="saveEditBtn"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all shadow-sm flex items-center gap-2">
                <i class="fas fa-check"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ── Delete Sale Modal ─────────────────────────────────────────────────── -->
<div id="deleteSaleModal" class="hidden fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Void Sale <span id="deleteSaleIdDisplay" class="text-red-600"></span></h2>
                <p class="text-xs text-gray-400 mt-0.5">Select items to remove from this sale</p>
            </div>
            <button onclick="closeDeleteModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all text-xs">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="deleteSaleContent" class="p-5 max-h-[55vh] overflow-y-auto space-y-3">
            <!-- Loaded via AJAX -->
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="selectAllDeleteItems" onchange="toggleSelectAllDelete()" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                    <span class="text-sm text-gray-600">Select All</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button onclick="closeDeleteModal()"
                        class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-all">
                    Cancel
                </button>
                <button onclick="confirmDeleteItems()" id="confirmDeleteBtn"
                        class="px-5 py-2.5 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition-all shadow-sm flex items-center gap-2">
                    <i class="fas fa-trash"></i> Remove Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEditingSaleId = null;

// ── Edit ──────────────────────────────────────────────────────────────────
async function editSale(saleId) {
    currentEditingSaleId = saleId;
    document.getElementById('editSaleIdDisplay').innerText = '#' + String(saleId).padStart(5, '0');
    const modal   = document.getElementById('editSaleModal');
    const content = document.getElementById('editSaleContent');
    modal.classList.remove('hidden');

    content.innerHTML = `
        <div class="flex flex-col items-center py-10 gap-3 text-gray-300">
            <i class="fas fa-circle-notch fa-spin text-2xl"></i>
            <p class="text-xs font-semibold uppercase tracking-wide">Loading…</p>
        </div>`;

    try {
        const res  = await fetch(`api/sales/get-items.php?sale_id=${saleId}`);
        const data = await res.json();

        if (data.status === 'success') {
            let html = '';
            // Use data.data since API returns {status, data} not {status, items}
            const items = data.data || [];
            items.forEach(item => {
                html += `
                <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100 rounded-xl sale-item-row"
                     data-id="${item.id}" data-price="${item.price_at_sale}" data-product-id="${item.product_id}">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">${item.product_name}</p>
                        <p class="text-xs text-gray-400 mt-0.5">MWK ${parseFloat(item.price_at_sale).toFixed(2)} / unit</p>
                    </div>
                    <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl p-1.5">
                        <button onclick="updateQty(${item.id}, -1)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-red-100 hover:text-red-500 transition-all text-xs">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" value="${item.quantity}" min="0"
                               class="w-10 text-center text-sm font-bold text-gray-800 bg-transparent border-none outline-none qty-input"
                               onchange="calculateEditTotal()" id="qty-${item.id}">
                        <button onclick="updateQty(${item.id}, 1)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-emerald-100 hover:text-emerald-500 transition-all text-xs">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <button onclick="removeItem(${item.id}, ${item.product_id}, ${item.quantity})"
                            class="ml-2 w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all text-xs"
                            title="Remove item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>`;
            });
            content.innerHTML = html;
            calculateEditTotal();
        } else {
            content.innerHTML = `<div class="py-8 text-center text-red-500 text-sm font-semibold">${data.message}</div>`;
        }
    } catch {
        content.innerHTML = `<div class="py-8 text-center text-red-500 text-sm font-semibold">Network error. Please try again.</div>`;
    }
}

function updateQty(itemId, delta) {
    const input = document.getElementById(`qty-${itemId}`);
    const newVal = Math.max(0, parseInt(input.value) + delta);
    input.value = newVal;
    
    // If quantity becomes 0, remove the item row
    if (newVal === 0) {
        const row = input.closest('.sale-item-row');
        const productId = row.dataset.productId;
        const quantity = parseInt(row.querySelector('.qty-input').dataset.originalQty || row.querySelector('.qty-input').value);
        
        // Mark for removal (we'll handle it when saving)
        row.dataset.removeItem = 'true';
        row.style.opacity = '0.5';
        row.style.textDecoration = 'line-through';
    }
    
    calculateEditTotal();
}

// Remove item completely from the sale
function removeItem(itemId, productId, quantity) {
    if (!confirm('Are you sure you want to remove this item from the sale? This will restore ' + quantity + ' unit(s) to inventory.')) {
        return;
    }
    
    const row = document.querySelector(`.sale-item-row[data-id="${itemId}"]`);
    if (row) {
        row.dataset.removeItem = 'true';
        row.style.opacity = '0.5';
        row.style.textDecoration = 'line-through';
    }
    
    calculateEditTotal();
}

function calculateEditTotal() {
    let total = 0;
    document.querySelectorAll('.sale-item-row').forEach(row => {
        // Skip removed items
        if (row.dataset.removeItem === 'true') return;
        
        const qty = parseInt(row.querySelector('.qty-input').value);
        if (qty > 0) {
            total += parseFloat(row.dataset.price) * qty;
        }
    });
    document.getElementById('editSaleNewTotal').innerText = 'MWK ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function closeEditModal() {
    document.getElementById('editSaleModal').classList.add('hidden');
}

async function saveSaleChanges() {
    const btn  = document.getElementById('saveEditBtn');
    const orig = btn.innerHTML;
    const items = [];
    const removedItems = [];
    
    document.querySelectorAll('.sale-item-row').forEach(row => {
        const itemId = parseInt(row.dataset.id);
        const productId = parseInt(row.dataset.productId);
        const newQuantity = parseInt(row.querySelector('.qty-input').value);
        
        // Check if item should be removed
        if (row.dataset.removeItem === 'true') {
            removedItems.push({ 
                sale_item_id: itemId, 
                product_id: productId,
                quantity: newQuantity 
            });
        } else {
            items.push({ sale_item_id: itemId, new_quantity: newQuantity });
        }
    });

    // If there are removed items, confirm with user
    if (removedItems.length > 0) {
        if (!confirm(`You are about to remove ${removedItems.length} item(s) from this sale. This will restore the items to inventory. Continue?`)) {
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    try {
        const res  = await fetch('api/sales/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                sale_id: currentEditingSaleId, 
                items, 
                removed_items: removedItems,
                csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' 
            })
        });
        const data = await res.json();
        if (data.status === 'success') { location.reload(); }
        else { alert(data.message); btn.disabled = false; btn.innerHTML = orig; }
    } catch {
        alert('A network error occurred.'); btn.disabled = false; btn.innerHTML = orig;
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
let currentDeletingSaleId = null;

async function deleteSale(saleId) {
    currentDeletingSaleId = saleId;
    document.getElementById('deleteSaleIdDisplay').innerText = '#' + String(saleId).padStart(5, '0');
    const modal   = document.getElementById('deleteSaleModal');
    const content = document.getElementById('deleteSaleContent');
    modal.classList.remove('hidden');

    content.innerHTML = `
        <div class="flex flex-col items-center py-10 gap-3 text-gray-300">
            <i class="fas fa-circle-notch fa-spin text-2xl"></i>
            <p class="text-xs font-semibold uppercase tracking-wide">Loading…</p>
        </div>`;

    try {
        const res  = await fetch(`api/sales/get-items.php?sale_id=${saleId}`);
        const data = await res.json();

        if (data.status === 'success') {
            const items = data.data || [];
            if (items.length === 0) {
                content.innerHTML = `<div class="py-8 text-center text-gray-500 text-sm font-semibold">No items in this sale</div>`;
                return;
            }
            
            let html = '';
            items.forEach(item => {
                html += `
                <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100 rounded-xl">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="${item.id}" data-product-id="${item.product_id}" data-quantity="${item.quantity}" 
                               class="delete-item-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">${item.product_name}</p>
                            <p class="text-xs text-gray-400 mt-0.5">MWK ${parseFloat(item.price_at_sale).toFixed(2)} × ${item.quantity}</p>
                        </div>
                    </div>
                    <p class="text-sm font-bold text-gray-900">MWK ${(parseFloat(item.price_at_sale) * item.quantity).toFixed(2)}</p>
                </div>`;
            });
            content.innerHTML = html;
        } else {
            content.innerHTML = `<div class="py-8 text-center text-red-500 text-sm font-semibold">${data.message}</div>`;
        }
    } catch {
        content.innerHTML = `<div class="py-8 text-center text-red-500 text-sm font-semibold">Network error. Please try again.</div>`;
    }
}

function closeDeleteModal() {
    document.getElementById('deleteSaleModal').classList.add('hidden');
}

function toggleSelectAllDelete() {
    const selectAll = document.getElementById('selectAllDeleteItems');
    const checkboxes = document.querySelectorAll('.delete-item-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

async function confirmDeleteItems() {
    const selectedItems = [];
    document.querySelectorAll('.delete-item-checkbox:checked').forEach(cb => {
        selectedItems.push({
            sale_item_id: parseInt(cb.value),
            product_id: parseInt(cb.dataset.productId),
            quantity: parseInt(cb.dataset.quantity)
        });
    });

    if (selectedItems.length === 0) {
        alert('Please select at least one item to remove.');
        return;
    }

    if (!confirm(`Are you sure you want to remove ${selectedItems.length} item(s) from this sale? This will restore the items to inventory.`)) {
        return;
    }

    const btn  = document.getElementById('confirmDeleteBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing…';

    try {
        const res  = await fetch('api/sales/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                sale_id: currentDeletingSaleId, 
                items: [],
                removed_items: selectedItems,
                csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' 
            })
        });
        const data = await res.json();
        if (data.status === 'success') { 
            // Check if there are remaining items
            const remainingItems = document.querySelectorAll('.delete-item-checkbox:not(:checked)');
            if (remainingItems.length === 0) {
                // All items removed, reload page
                location.reload(); 
            } else {
                // Some items remain, close modal and refresh
                closeDeleteModal();
                // Refresh the sales list
                location.reload();
            }
        } else { 
            alert(data.message); 
            btn.disabled = false; 
            btn.innerHTML = orig; 
        }
    } catch {
        alert('A network error occurred.'); 
        btn.disabled = false; 
        btn.innerHTML = orig;
    }
}

// Close modal on backdrop click
document.getElementById('deleteSaleModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ── Print ─────────────────────────────────────────────────────────────────
function printSale(sale) {
    const w = window.open('', '_blank');
    const date = new Date(sale.created_at).toLocaleString();
    w.document.write(`
        <html><head><title>Sale Receipt #${sale.id}</title>
        <style>
            body{font-family:'Courier New',monospace;padding:20px;max-width:300px;margin:0 auto}
            .header{text-align:center;margin-bottom:20px;border-bottom:1px dashed #000;padding-bottom:10px}
            .row{display:flex;justify-content:space-between;margin-bottom:5px}
            .total{border-top:1px dashed #000;margin-top:10px;padding-top:10px;font-weight:bold}
            .footer{text-align:center;margin-top:20px;font-size:12px}
            @media print{@page{margin:0}body{margin:1cm}}
        </style></head>
        <body>
            <div class="header">
                <h3>Next-Level Pharmacy</h3>
                <p>Sale #${String(sale.id).padStart(5,'0')}</p>
                <p>${date}</p>
            </div>
            <div>
                <div class="row"><span>Cashier:</span><span>${sale.sold_by||'Unknown'}</span></div>
                <div class="total row"><span>Total:</span><span>MWK ${parseFloat(sale.total_amount).toFixed(2)}</span></div>
                <div class="row"><span>Payment:</span><span>${sale.payment_method||'Cash'}</span></div>
            </div>
            <div class="footer"><p>Thank you for your business!</p></div>
            <script>window.onload=function(){window.print();window.close()}<\/script>
        </body></html>`);
    w.document.close();
}

// Close modal on backdrop click
document.getElementById('editSaleModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('salesTrendChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Daily Sales (MWK)',
                data: <?= json_encode($chartValues) ?>,
                borderColor: 'rgb(59,130,246)',
                backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true, tension: 0.4,
                pointRadius: 4, pointHoverRadius: 6,
                pointBackgroundColor: 'rgb(59,130,246)',
                pointBorderColor: '#fff', pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true },
                tooltip: { callbacks: { label: c => 'MWK ' + c.parsed.y.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => 'MWK '+(v/1000).toFixed(0)+'k' }, grid: { color:'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45 } }
            }
        }
    });
});
</script>
<?php endif; ?>
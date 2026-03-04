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

    // Date filtering - Default to today
    $startDateInput = $_GET['start_date'] ?? date('Y-m-d');
    $endDateInput = $_GET['end_date'] ?? date('Y-m-d');
    $paymentMethod = $_GET['payment_method'] ?? 'all';
    $searchId = $_GET['search_id'] ?? '';

    // Pagination settings
    $page = max(1, intval($_GET['p'] ?? 1));
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    // Validate dates
    $startDate = date('Y-m-d', strtotime($startDateInput));
    $endDate = date('Y-m-d', strtotime($endDateInput));

    // Count total for pagination
    $countQuery = "
        SELECT COUNT(DISTINCT s.id) 
        FROM sales s
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";
    $countParams = [$startDate, $endDate];
    if ($paymentMethod !== 'all') {
        $countQuery .= " AND p.payment_method = ?";
        $countParams[] = $paymentMethod;
    }
    if (!empty($searchId)) {
        $countQuery .= " AND s.id = ?";
        $countParams[] = intval($searchId);
    }
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // Build query with payment filter and product names
    $query = "
        SELECT 
            s.id, 
            s.total_amount,
            s.created_at, 
            u.name as sold_by,
            u.id as sold_by_id,
            COALESCE(p.payment_method, 'cash') as payment_method,
            COALESCE(si_counts.cnt, 0) as items_count,
            GROUP_CONCAT(DISTINCT pr.name SEPARATOR ', ') as products_list
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN payments p ON s.id = p.sale_id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products pr ON si.product_id = pr.id
        LEFT JOIN (
            SELECT sale_id, COUNT(*) as cnt FROM sale_items GROUP BY sale_id
        ) si_counts ON s.id = si_counts.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";

    $params = [$startDate, $endDate];

    if ($paymentMethod !== 'all') {
        $query .= " AND p.payment_method = ?";
        $params[] = $paymentMethod;
    }

    if (!empty($searchId)) {
        $query .= " AND s.id = ?";
        $params[] = intval($searchId);
    }

    $query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $statsQuery = "
        SELECT 
            SUM(total_amount) as total_sales,
            COUNT(*) as total_transactions
        FROM sales s
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";
    $statsParams = [$startDate, $endDate];
    if ($paymentMethod !== 'all') {
        $statsQuery .= " AND p.payment_method = ?";
        $statsParams[] = $paymentMethod;
    }
    if (!empty($searchId)) {
        $statsQuery .= " AND s.id = ?";
        $statsParams[] = intval($searchId);
    }
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $totalSales = (float)($stats['total_sales'] ?? 0);
    $totalTransactions = (int)($stats['total_transactions'] ?? 0);
    $avgSaleValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

    // Get daily items query
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

<div class="space-y-8 pb-12">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Sales Report</h1>
            <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-1">Detailed analysis of revenue and transactions</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=reports" class="px-6 py-3 bg-white border border-gray-200 text-gray-700 rounded-2xl font-black text-sm shadow-sm hover:bg-gray-50 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            <a href="<?= defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms'; ?>/api/reports/download.php?report=sales&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?><?= $paymentMethod !== 'all' ? '&payment_method=' . urlencode($paymentMethod) : '' ?>" 
               class="px-6 py-3 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-500/20 hover:bg-blue-700 transition-all flex items-center gap-2">
                <i class="fas fa-download"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Filters & Search Bar (Premium Glassmorphism) -->
    <div class="glassmorphism rounded-[32px] p-12 border border-white/40 shadow-sm bg-white/50 backdrop-blur-md">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-10 items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="sales">

            <div class="md:col-span-1">
                <label for="search_id" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Sale ID</label>
                <div class="relative">
                    <input type="number" name="search_id" id="search_id" value="<?= htmlspecialchars($searchId) ?>" placeholder="e.g. 197"
                        class="w-full pl-11 pr-4 py-4 bg-white border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold placeholder:text-gray-300">
                    <i class="fas fa-hashtag absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                </div>
            </div>

            <div class="md:col-span-1">
                <label for="start_date" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Date From</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>"
                    class="w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold">
            </div>

            <div class="md:col-span-1">
                <label for="end_date" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Date To</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>"
                    class="w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold">
            </div>

            <div class="md:col-span-1">
                <label for="payment_method" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Payment</label>
                <select name="payment_method" id="payment_method"
                    class="w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold text-gray-700">
                    <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>All Methods</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="mobile_money" <?= $paymentMethod === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                </select>
            </div>

            <div class="md:col-span-2 flex gap-4">
                <button type="submit"
                    class="flex-1 px-8 py-4 bg-gray-900 text-white rounded-2xl font-black text-sm shadow-xl shadow-gray-900/10 hover:bg-black transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-filter"></i>
                    <span>Apply Filter</span>
                </button>
                <?php if (!empty($searchId) || $startDate !== date('Y-m-d') || $endDate !== date('Y-m-d') || $paymentMethod !== 'all'): ?>
                    <a href="?page=reports&view=sales"
                        class="px-6 py-4 bg-rose-50 text-rose-600 rounded-2xl font-black text-sm hover:bg-rose-100 transition-all flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Quick Filters (Mon-Sun) -->
        <div class="mt-8 pt-6 border-t border-gray-100/50 flex flex-wrap items-center gap-3">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mr-4">Quick Date Selection:</span>
            <?php
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days as $day):
                $dayDate = date('Y-m-d', strtotime("this week $day"));
                $isActive = $startDate === $dayDate && $endDate === $dayDate;
                ?>
                <a href="?page=reports&view=sales&start_date=<?= $dayDate ?>&end_date=<?= $dayDate ?>"
                    class="px-5 py-2.5 rounded-xl text-xs font-black transition-all <?= $isActive ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-blue-50 border border-gray-100' ?>">
                    <?= $day ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Summary Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <div class="glassmorphism rounded-[32px] p-10 bg-white border border-white/40 shadow-sm flex items-center gap-8 group hover:translate-y--1 transition-all">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-[24px] flex items-center justify-center text-3xl shadow-inner shadow-blue-100/50 group-hover:bg-blue-600 group-hover:text-white transition-all">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div>
                <h3 class="text-[10px] uppercase font-black text-gray-400 tracking-widest leading-none mb-3">Total Revenue</h3>
                <p class="text-4xl font-black text-gray-900 tracking-tight">MWK <?= number_format($totalSales, 2) ?></p>
            </div>
        </div>

        <div class="glassmorphism rounded-[32px] p-8 bg-white border border-white/40 shadow-sm flex items-center gap-6 group hover:translate-y--1 transition-all">
            <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-[24px] flex items-center justify-center text-3xl shadow-inner shadow-emerald-100/50 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <h3 class="text-[10px] uppercase font-black text-gray-400 tracking-widest leading-none mb-3">Total Transactions</h3>
                <p class="text-4xl font-black text-gray-900 tracking-tight"><?= number_format($totalTransactions) ?></p>
            </div>
        </div>

        <div class="glassmorphism rounded-[32px] p-8 bg-white border border-white/40 shadow-sm flex items-center gap-6 group hover:translate-y--1 transition-all">
            <div class="w-20 h-20 bg-amber-50 text-amber-600 rounded-[24px] flex items-center justify-center text-3xl shadow-inner shadow-amber-100/50 group-hover:bg-amber-600 group-hover:text-white transition-all">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div>
                <h3 class="text-[10px] uppercase font-black text-gray-400 tracking-widest leading-none mb-3">Avg Sale Value</h3>
                <p class="text-4xl font-black text-gray-900 tracking-tight">MWK <?= number_format($avgSaleValue, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- Daily Itemized Summary Section -->
    <div class="glassmorphism rounded-[32px] p-12 bg-white border border-white/40 shadow-sm">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="text-2xl font-black text-gray-900">Daily Itemized Summary</h2>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Grouped by product performance</p>
            </div>
        </div>

        <?php if (empty($groupedDailyItems)): ?>
            <div class="text-center py-20 bg-gray-50/50 rounded-3xl border border-dashed border-gray-200">
                <i class="fas fa-list-ul text-4xl text-gray-200 mb-4 opacity-50"></i>
                <p class="font-bold text-gray-400 uppercase tracking-widest text-xs">No records for this period</p>
            </div>
        <?php else: ?>
            <div class="space-y-10">
                <?php foreach ($groupedDailyItems as $date => $data): ?>
                    <div class="rounded-3xl border border-gray-100 overflow-hidden shadow-sm bg-white">
                        <div class="bg-gray-50/50 px-8 py-5 border-b border-gray-100 flex items-center justify-between">
                            <span class="font-black text-gray-900 flex items-center gap-3">
                                <span class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-blue-600">
                                    <i class="far fa-calendar-alt"></i>
                                </span>
                                <?= date('l, M j, Y', strtotime($date)) ?>
                            </span>
                            <span class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-2xl text-sm font-black shadow-lg shadow-blue-500/20">
                                Day Total: MWK <?= number_format($data['total_revenue'], 2) ?>
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50/30 text-gray-400 font-bold uppercase text-[10px] tracking-widest">
                                    <tr>
                                        <th class="px-12 py-6 text-left">Product / Item Name</th>
                                        <th class="px-12 py-6 text-center">Qty Sold</th>
                                        <th class="px-12 py-6 text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach ($data['items'] as $item): ?>
                                        <tr class="hover:bg-blue-50/30 transition-all group">
                                            <td class="px-12 py-6 font-black text-gray-800">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                            </td>
                                            <td class="px-12 py-6 text-center">
                                                <span class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-xl font-black text-xs">
                                                    <?= $item['total_qty'] ?>
                                                </span>
                                            </td>
                                            <td class="px-12 py-6 text-right font-black text-gray-900">
                                                MWK <?= number_format($item['total_revenue'], 2) ?>
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

    <!-- Detailed Transaction Table -->
    <div class="glassmorphism rounded-[32px] p-12 bg-white border border-white/40 shadow-sm">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="text-2xl font-black text-gray-900">Transaction History</h2>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Detailed log of individual sales</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-10 py-8 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Sale ID</th>
                        <th class="px-10 py-8 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Time & Date</th>
                        <th class="px-10 py-8 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Cashier</th>
                        <th class="px-10 py-8 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Method</th>
                        <th class="px-10 py-8 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Included Products</th>
                        <th class="px-10 py-8 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Total</th>
                        <th class="px-10 py-8 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="7" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-inbox text-5xl text-gray-100 mb-6"></i>
                                    <p class="text-gray-400 font-black uppercase tracking-widest text-xs">No transactions found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr class="hover:bg-gray-50/50 transition-all group">
                                <td class="px-10 py-10 whitespace-nowrap">
                                    <span class="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-black border border-blue-100/50 shadow-sm shadow-blue-500/5">
                                        #<?= str_pad($sale['id'], 5, '0', STR_PAD_LEFT) ?>
                                    </span>
                                </td>
                                <td class="px-10 py-10 whitespace-nowrap">
                                    <p class="text-sm font-black text-gray-800 tracking-tight"><?= date('M j, Y', strtotime($sale['created_at'])) ?></p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-2"><?= date('H:i', strtotime($sale['created_at'])) ?></p>
                                </td>
                                <td class="px-10 py-10 whitespace-nowrap">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400 group-hover:bg-blue-600 group-hover:text-white group-hover:border-blue-700 transition-all">
                                            <i class="fas fa-user-tie text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-gray-700 leading-tight"><?= htmlspecialchars($sale['sold_by'] ?? 'Unknown') ?></p>
                                            <p class="text-[9px] font-black text-gray-300 uppercase tracking-widest">Officer</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-8 whitespace-nowrap">
                                    <?php
                                    $paymentIcons = [
                                        'cash' => ['icon' => 'fa-money-bill-wave', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
                                        'card' => ['icon' => 'fa-credit-card', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-600'],
                                        'mobile_money' => ['icon' => 'fa-mobile-alt', 'bg' => 'bg-purple-50', 'text' => 'text-purple-600'],
                                        'bank_transfer' => ['icon' => 'fa-university', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600']
                                    ];
                                    $payment = $paymentIcons[$sale['payment_method']] ?? ['icon' => 'fa-question', 'bg' => 'bg-gray-100', 'text' => 'text-gray-400'];
                                    ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-[10px] font-black <?= $payment['bg'] ?> <?= $payment['text'] ?> uppercase tracking-widest">
                                        <i class="fas <?= $payment['icon'] ?>"></i>
                                        <?= str_replace('_', ' ', $sale['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="px-10 py-10">
                                    <div class="max-w-[200px] truncate text-sm font-bold text-gray-400 group-hover:text-gray-700 transition-colors" title="<?= htmlspecialchars($sale['products_list'] ?? '') ?>">
                                        <?= htmlspecialchars($sale['products_list'] ?? 'Items unavailable') ?>
                                    </div>
                                    <p class="text-[9px] font-black text-blue-500 uppercase tracking-widest mt-2"><?= $sale['items_count'] ?> Total Types</p>
                                </td>
                                <td class="px-10 py-10 whitespace-nowrap text-right">
                                    <p class="text-sm font-black text-gray-900 leading-none">MWK <?= number_format($sale['total_amount'], 2) ?></p>
                                    <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mt-1.5 flex items-center justify-end gap-1">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                        Completed
                                    </p>
                                </td>
                                <td class="px-10 py-10 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button onclick="editSale(<?= $sale['id'] ?>)" class="w-11 h-11 flex items-center justify-center rounded-2xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm shadow-blue-500/5 group/btn" title="Edit Entry">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button onclick="deleteSale(<?= $sale['id'] ?>)" class="w-11 h-11 flex items-center justify-center rounded-2xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm shadow-rose-500/5 group/btn" title="Remove Entry">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                        <button onclick='printSale(<?= json_encode($sale) ?>)' class="w-11 h-11 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-600 hover:bg-gray-900 hover:text-white transition-all shadow-sm shadow-gray-500/5 group/btn" title="Download Voucher">
                                            <i class="fas fa-receipt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($sales)): ?>
                    <tfoot class="bg-gray-50/80 border-t border-gray-100">
                        <tr>
                            <td colspan="5" class="px-10 py-12 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Running Page Total:</td>
                            <td class="px-10 py-12 text-right text-base font-black text-gray-900">
                                MWK <?= number_format(array_reduce($sales, fn($sum, $sale) => $sum + (float)$sale['total_amount'], 0), 2) ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="px-10 py-6 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest pt-0">Selection Overall Sum:</td>
                            <td class="px-10 py-6 text-right text-xl font-black text-blue-600 pt-0">
                                MWK <?= number_format($totalSales, 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Pagination Section -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-16 flex flex-col md:flex-row items-center justify-between border-t border-gray-100 pt-12 gap-8">
                <div class="flex items-center gap-4 text-sm font-black text-gray-400 uppercase tracking-widest">
                    <span class="px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-gray-900">Records <?= $offset + 1 ?> — <?= min($offset + $perPage, $totalRecords) ?></span>
                    <span>of <?= $totalRecords ?> Matches</span>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=reports&view=sales&start_date=<?= $startDateInput ?>&end_date=<?= $endDateInput ?>&payment_method=<?= $paymentMethod ?>&search_id=<?= $searchId ?>&p=<?= $page - 1 ?>" 
                           class="px-8 py-4 bg-white border border-gray-200 text-gray-700 rounded-2xl hover:bg-gray-50 transition-all font-black text-xs uppercase tracking-widest shadow-sm">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <div class="flex gap-2">
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=reports&view=sales&start_date=<?= $startDateInput ?>&end_date=<?= $endDateInput ?>&payment_method=<?= $paymentMethod ?>&search_id=<?= $searchId ?>&p=<?= $i ?>" 
                               class="w-12 h-12 flex items-center justify-center rounded-2xl text-xs font-black transition-all <?= $i === $page ? 'bg-blue-600 text-white shadow-xl shadow-blue-500/20' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=reports&view=sales&start_date=<?= $startDateInput ?>&end_date=<?= $endDateInput ?>&payment_method=<?= $paymentMethod ?>&search_id=<?= $searchId ?>&p=<?= $page + 1 ?>" 
                           class="px-8 py-4 bg-white border border-gray-200 text-gray-700 rounded-2xl hover:bg-gray-50 transition-all font-black text-xs uppercase tracking-widest shadow-sm">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Sale Modal (Keep Original IDs for Existing JS Support) -->
<div id="editSaleModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[40px] w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-300">
        <div class="p-10 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-black text-gray-900">Modify Sale <span id="editSaleIdDisplay" class="text-blue-600"></span></h2>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Adjust item counts for correction</p>
            </div>
            <button onclick="closeEditModal()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400 hover:text-rose-500 hover:bg-rose-50 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="editSaleContent" class="p-10 max-h-[50vh] overflow-y-auto space-y-6">
            <!-- Items loaded via AJAX -->
        </div>
        <div class="p-10 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-2">Calculated Adjustment</p>
                <p id="editSaleNewTotal" class="text-2xl font-black text-gray-900">MWK 0.00</p>
            </div>
            <button onclick="saveSaleChanges()" id="saveBtn" class="px-10 py-5 bg-blue-600 text-white rounded-[24px] font-black text-sm shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3">
                <i class="fas fa-check-circle"></i> Complete Update
            </button>
        </div>
    </div>
</div>

<script>
    let currentEditingSaleId = null;

    async function editSale(saleId) {
        currentEditingSaleId = saleId;
        document.getElementById('editSaleIdDisplay').innerText = '#' + String(saleId).padStart(5, '0');
        const modal = document.getElementById('editSaleModal');
        const content = document.getElementById('editSaleContent');
        modal.classList.remove('hidden');
        
        content.innerHTML = '<div class="flex flex-col items-center py-10 text-gray-300"><i class="fas fa-circle-notch fa-spin text-4xl mb-4"></i><p class="font-black text-[10px] uppercase tracking-widest">Connecting...</p></div>';

        try {
            const response = await fetch(`api/sales/get-items.php?sale_id=${saleId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                let html = '<div class="space-y-4">';
                data.items.forEach(item => {
                    html += `
                        <div class="flex items-center justify-between p-6 bg-white border border-gray-100 rounded-3xl shadow-sm sale-item-row group hover:border-blue-200 transition-all" 
                             data-id="${item.id}" data-price="${item.price_at_sale}">
                            <div>
                                <p class="font-black text-gray-800">${item.product_name}</p>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5">Rate: MWK ${parseFloat(item.price_at_sale).toFixed(2)}</p>
                            </div>
                            <div class="flex items-center gap-4 bg-gray-50 p-2 rounded-2xl">
                                <button onclick="updateQty(${item.id}, -1)" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-colors shadow-sm">
                                    <i class="fas fa-minus text-[10px]"></i>
                                </button>
                                <input type="number" value="${item.quantity}" min="1" 
                                    class="w-12 bg-transparent border-none text-center font-black qty-input focus:ring-0" 
                                    onchange="calculateEditTotal()" id="qty-${item.id}">
                                <button onclick="updateQty(${item.id}, 1)" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-emerald-50 hover:text-emerald-500 transition-colors shadow-sm">
                                    <i class="fas fa-plus text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
                calculateEditTotal();
            } else {
                content.innerHTML = `<div class="p-8 text-rose-500 text-center font-black uppercase text-xs">${data.message}</div>`;
            }
        } catch (error) {
            content.innerHTML = '<div class="p-8 text-rose-500 text-center font-black uppercase text-xs">Network Connection Error</div>';
        }
    }

    function updateQty(itemId, delta) {
        const input = document.getElementById(`qty-${itemId}`);
        input.value = Math.max(1, parseInt(input.value) + delta);
        calculateEditTotal();
    }

    function calculateEditTotal() {
        let total = 0;
        document.querySelectorAll('.sale-item-row').forEach(row => {
            const price = parseFloat(row.dataset.price);
            const qty = parseInt(row.querySelector('.qty-input').value);
            total += price * qty;
        });
        document.getElementById('editSaleNewTotal').innerText = 'MWK ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    function closeEditModal() {
        document.getElementById('editSaleModal').classList.add('hidden');
    }

    async function saveSaleChanges() {
        const btn = document.getElementById('saveBtn');
        const items = [];
        document.querySelectorAll('.sale-item-row').forEach(row => {
            items.push({
                sale_item_id: parseInt(row.dataset.id),
                new_quantity: parseInt(row.querySelector('.qty-input').value)
            });
        });

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch('api/sales/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sale_id: currentEditingSaleId,
                    items: items,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
                })
            });

            const data = await response.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            alert('A network error occurred while updating the sale.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function deleteSale(saleId) {
        if (!confirm('EXTREME CAUTION: Deleting this sale will permanently remove the record and restore inventory levels. Proceed?')) return;

        try {
            const response = await fetch('api/sales/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sale_id: saleId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
                })
            });

            const data = await response.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert('Error connecting to the server for deletion.');
        }
    }

    function printSale(sale) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Next-Level Receipt #${String(sale.id).padStart(5, '0')}</title>
                    <style>
                        body { font-family: 'Inter', sans-serif; padding: 40px; color: #1a1a1a; line-height: 1.6; }
                        .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #f3f4f6; pb: 40px; }
                        .title { font-size: 24px; font-weight: 900; letter-spacing: -0.025em; margin: 0; text-transform: uppercase; }
                        .details { margin-bottom: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        .label { font-size: 10px; font-weight: 900; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 4px; }
                        .value { font-weight: 700; font-size: 14px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
                        th { text-align: left; padding: 12px; font-size: 10px; font-weight: 900; color: #9ca3af; text-transform: uppercase; border-bottom: 2px solid #f3f4f6; }
                        td { padding: 16px 12px; border-bottom: 1px solid #f9fafb; font-size: 14px; font-weight: 600; }
                        .footer { margin-top: 60px; text-align: center; color: #9ca3af; font-size: 11px; font-weight: 600; }
                        .total-row { background: #f9fafb; font-weight: 900 !important; font-size: 18px !important; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1 class="title">Next-Level Pharmacy</h1>
                        <p style="font-size: 12px; color: #6b7280; font-weight: 600; margin-top: 8px;">Official Transaction Record</p>
                    </div>
                    <div class="details">
                        <div>
                            <span class="label">Transaction Reference</span>
                            <span class="value">#${String(sale.id).padStart(5, '0')}</span>
                        </div>
                        <div style="text-align: right;">
                            <span class="label">Date & Time</span>
                            <span class="value">${new Date(sale.created_at).toLocaleString()}</span>
                        </div>
                        <div>
                            <span class="label">Issuing Officer</span>
                            <span class="value">${sale.sold_by}</span>
                        </div>
                        <div style="text-align: right;">
                            <span class="label">Settlement Mode</span>
                            <span class="value" style="text-transform: uppercase;">${sale.payment_method.replace('_', ' ')}</span>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th style="text-align: right;">Settled Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${sale.products_list}</td>
                                <td style="text-align: right;">MWK ${parseFloat(sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                            <tr class="total-row">
                                <td style="text-align: right; color: #9ca3af; font-size: 11px; text-transform: uppercase;">Final Total Sum</td>
                                <td style="text-align: right;">MWK ${parseFloat(sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="footer">
                        <p>This is a computer-generated voucher. No signature required.</p>
                        <p style="margin-top: 10px;">&copy; ${new Date().getFullYear()} Next-Level Pharmacy Management Ecosystem</p>
                    </div>
                    <script>window.print();<\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
</script>

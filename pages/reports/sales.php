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
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $paymentMethod = $_GET['payment_method'] ?? 'all';

    // Pagination settings
    $page = max(1, intval($_GET['p'] ?? 1));
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    // Validate dates
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));

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

    $query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics (for the entire date range, not just the page)
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
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $totalSales = (float)($stats['total_sales'] ?? 0);
    $totalTransactions = (int)($stats['total_transactions'] ?? 0);
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

    </div>

    <!-- Day of Week Filter -->
    <div class="glassmorphism rounded-2xl p-4 overflow-x-auto">
        <div class="flex items-center gap-2 min-w-max">
            <span class="text-sm font-bold text-gray-500 mr-2">Quick Filter:</span>
            <?php
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days as $day):
                $dayDate = date('Y-m-d', strtotime("this week $day"));
                $isActive = $startDate === $dayDate && $endDate === $dayDate;
                ?>
                <a href="?page=reports&view=sales&start_date=<?= $dayDate ?>&end_date=<?= $dayDate ?>"
                    class="px-4 py-2 rounded-xl text-sm font-bold transition-all <?= $isActive ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-100' ?>">
                    <?= $day ?>
                </a>
            <?php endforeach; ?>
            <a href="?page=reports&view=sales"
                class="px-4 py-2 rounded-xl text-sm font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 ml-auto">
                Clear
            </a>
        </div>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product(s)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                    <div class="max-w-xs overflow-hidden text-ellipsis text-gray-500" title="<?= htmlspecialchars($sale['products_list'] ?? '') ?>">
                                        <?= htmlspecialchars($sale['products_list'] ?? 'No items') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold text-right">
                                    MWK <?= number_format($sale['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="editSale(<?= $sale['id'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Sale">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteSale(<?= $sale['id'] ?>)" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Sale">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button onclick='printSale(<?= json_encode($sale) ?>)' class="p-2 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($sales)): ?>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-right text-sm text-gray-700">Page Total:</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900 font-bold">
                                MWK <?= number_format(array_reduce($sales, fn($sum, $sale) => $sum + (float)$sale['total_amount'], 0), 2) ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-right text-sm text-gray-700">Overall Total:</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900 font-bold">
                                MWK <?= number_format($totalSales, 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-6">
                <p class="text-sm text-gray-500">
                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                    <span class="font-medium"><?= min($offset + $perPage, $totalRecords) ?></span> of 
                    <span class="font-medium"><?= $totalRecords ?></span> results
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=reports&view=sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&payment_method=<?= $paymentMethod ?>&p=<?= $page - 1 ?>" 
                           class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-all font-medium text-sm">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=reports&view=sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&payment_method=<?= $paymentMethod ?>&p=<?= $i ?>" 
                           class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-bold transition-all <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=reports&view=sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&payment_method=<?= $paymentMethod ?>&p=<?= $page + 1 ?>" 
                           class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-all font-medium text-sm">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Sale Modal -->
<div id="editSaleModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[32px] w-full max-w-2xl overflow-hidden shadow-2xl animate-in zoom-in duration-300">
        <div class="p-8 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black text-gray-900">Edit Sale <span id="editSaleIdDisplay" class="text-blue-600"></span></h2>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-1">Adjust item quantities</p>
            </div>
            <button onclick="closeEditModal()" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400 hover:text-rose-500 hover:bg-rose-50 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="editSaleContent" class="p-8 max-h-[60vh] overflow-y-auto">
            <!-- Loading state -->
            <div class="flex flex-col items-center py-10 text-gray-400">
                <i class="fas fa-circle-notch fa-spin text-3xl mb-4"></i>
                <p class="font-bold uppercase tracking-widest text-xs">Loading items...</p>
            </div>
        </div>
        <div class="p-8 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="text-left">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Estimated Total</p>
                <p id="editSaleNewTotal" class="text-xl font-black text-gray-900">MWK 0.00</p>
            </div>
            <button onclick="saveSaleChanges()" id="saveBtn" class="px-10 py-4 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-500/20 hover:bg-blue-700 hover:scale-105 active:scale-95 transition-all flex items-center gap-3">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<script>
    }

    let currentEditingSaleId = null;

    async function editSale(saleId) {
        currentEditingSaleId = saleId;
        document.getElementById('editSaleIdDisplay').innerText = '#' + String(saleId).padStart(5, '0');
        const modal = document.getElementById('editSaleModal');
        const content = document.getElementById('editSaleContent');
        modal.classList.remove('hidden');
        
        try {
            const response = await fetch(`api/sales/get-items.php?sale_id=${saleId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                let html = '<div class="space-y-4">';
                data.items.forEach(item => {
                    html += `
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 sale-item-row" 
                             data-id="${item.id}" data-price="${item.price_at_sale}">
                            <div class="flex-1">
                                <p class="font-bold text-gray-900">${item.product_name}</p>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-0.5">MWK ${parseFloat(item.price_at_sale).toFixed(2)} / unit</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button onclick="updateQty(${item.id}, -1)" class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 rounded-lg hover:bg-rose-50 hover:text-rose-500 transition-colors">
                                    <i class="fas fa-minus text-xs"></i>
                                </button>
                                <input type="number" value="${item.quantity}" min="1" 
                                    class="w-16 bg-white border border-gray-200 rounded-lg px-2 py-1 text-center font-bold qty-input" 
                                    onchange="calculateEditTotal()" id="qty-${item.id}">
                                <button onclick="updateQty(${item.id}, 1)" class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 rounded-lg hover:bg-emerald-50 hover:text-emerald-500 transition-colors">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
                calculateEditTotal();
            } else {
                content.innerHTML = `<p class="text-rose-500 text-center font-bold">${data.message}</p>`;
            }
        } catch (error) {
            content.innerHTML = '<p class="text-rose-500 text-center font-bold">Failed to load items</p>';
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
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

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
            }
        } catch (error) {
            alert('An error occurred while saving.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }

    async function deleteSale(saleId) {
        if (!confirm('Are you sure you want to delete this sale? This will restore stock levels.')) return;

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
            alert('An error occurred during deletion.');
        }
    }
</script>

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

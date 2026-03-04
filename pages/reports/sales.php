<?php
// filepath: pages/reports/sales.php
require_once __DIR__ . "/../../includes/check-auth.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../config/database.php";

$isAdmin = ($_SESSION["role"] ?? "") === "admin";
$csrfToken = generateCsrfToken();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Filters & Pagination
    $startDate = $_GET["start_date"] ?? date("Y-m-01"); 
    $endDate = $_GET["end_date"] ?? date("Y-m-t"); 
    $paymentMethod = $_GET["payment_method"] ?? "all";
    $page = max(1, intval($_GET["p"] ?? 1));
    $perPage = 15; 
    $offset = ($page - 1) * $perPage;

    // Daily Summary Pagination
    $dPage = max(1, intval($_GET["dp"] ?? 1)); 
    $dPerPage = 5; 
    $dOffset = ($dPage - 1) * $dPerPage;

    // Main Transaction Count
    $countQuery = "SELECT COUNT(DISTINCT s.id) FROM sales s LEFT JOIN payments p ON s.id = p.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ?";
    $countParams = [$startDate, $endDate];
    if ($paymentMethod !== "all") { 
        $countQuery .= " AND p.payment_method = ?"; 
        $countParams[] = $paymentMethod; 
    }
    $countStmt = $conn->prepare($countQuery); 
    $countStmt->execute($countParams);
    $totalRecords = (int) $countStmt->fetchColumn(); 
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $page = min($page, $totalPages); 
    $offset = ($page - 1) * $perPage;

    // Main Transaction Query (Fixed Parameterized Pagination)
    $query = "SELECT s.id, s.total_amount, s.created_at, u.name as sold_by, COALESCE(p.payment_method, 'cash') as payment_method, 
        (SELECT GROUP_CONCAT(DISTINCT pr.name SEPARATOR ', ') FROM sale_items si JOIN products pr ON si.product_id = pr.id WHERE si.sale_id = s.id) as product_list 
        FROM sales s LEFT JOIN users u ON s.sold_by = u.id LEFT JOIN payments p ON s.id = p.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    if ($paymentMethod !== "all") { 
        $query .= " AND p.payment_method = ?"; 
        $params[] = $paymentMethod; 
    }
    $query .= " ORDER BY s.created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $conn->prepare($query); 
    $stmt->execute($params); 
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats (Fixed to respect payment method)
    $sumQ = "SELECT SUM(s.total_amount) as total_rev, COUNT(*) as trans_count FROM sales s LEFT JOIN payments p ON s.id = p.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ?";
    $sumParams = [$startDate, $endDate];
    if ($paymentMethod !== "all") {
        $sumQ .= " AND p.payment_method = ?";
        $sumParams[] = $paymentMethod;
    }
    $sumStmt = $conn->prepare($sumQ);
    $sumStmt->execute($sumParams); 
    $stats = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = (float)($stats["total_rev"] ?? 0); 
    $totalTransactions = (int)($stats["trans_count"] ?? 0); 
    $avgSaleValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

    // Daily Summary (Fixed Parameterized Pagination)
    $dCountStmt = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM sales WHERE DATE(created_at) BETWEEN ? AND ?"); 
    $dCountStmt->execute([$startDate, $endDate]);
    $totalDPages = max(1, ceil($dCountStmt->fetchColumn() / $dPerPage));
    $dailySummary = $conn->prepare("SELECT DATE(created_at) as sale_date, COUNT(*) as transactions, SUM(total_amount) as revenue FROM sales WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY sale_date DESC LIMIT " . (int)$dPerPage . " OFFSET " . (int)$dOffset);
    $dailySummary->execute([$startDate, $endDate]); 
    $dailySummary = $dailySummary->fetchAll(PDO::FETCH_ASSOC);

    // Trend Data (Last 30 days or period)
    $trendQuery = "SELECT DATE(created_at) as sale_date, SUM(total_amount) as revenue 
                   FROM sales 
                   WHERE DATE(created_at) BETWEEN ? AND ? 
                   GROUP BY DATE(created_at) 
                   ORDER BY sale_date ASC";
    $trendStmt = $conn->prepare($trendQuery);
    $trendStmt->execute([$startDate, $endDate]);
    $trendData = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $chartLabels = []; $chartValues = [];
    $current = new DateTime($startDate); $stop = new DateTime($endDate); $stop->modify('+1 day');
    while ($current < $stop) {
        $dateStr = $current->format('Y-m-d');
        $chartLabels[] = $current->format('M j');
        $chartValues[] = (float)($trendData[$dateStr] ?? 0);
        $current->modify('+1 day');
    }

} catch (Exception $e) { 
    error_log($e->getMessage()); 
    die("<div class='p-10 text-center bg-rose-50 text-rose-600 rounded-3xl font-bold'>Error loading sales report.</div>"); 
}
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="?page=reports" class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/20">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Sales Report</h1>
                <p class="text-sm font-medium text-slate-400">Detailed analysis of sales transactions.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
             <button onclick="window.print()" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold flex items-center gap-2 hover:bg-slate-50 transition-all active:scale-95 shadow-sm">
                <i class="fas fa-print"></i>
                Print
            </button>
            <button onclick="window.location.href = 'api/reports/download.php?report=sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&payment_method=<?= $paymentMethod ?>'" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold flex items-center gap-2 hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                <i class="fas fa-file-export"></i>
                Export Report
            </button>
        </div>
    </div>

    <!-- Enhanced Horizontal Filters -->
    <div class="glassmorphism p-3 rounded-[24px] border border-white/40 shadow-sm bg-gradient-to-r from-blue-500/5 to-transparent">
        <form method="GET" class="flex flex-wrap items-end gap-x-6 gap-y-4 p-2">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="sales">
            
            <div class="flex-1 min-w-[180px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-calendar-alt text-blue-500/60"></i> Starting At</label>
                <input type="date" name="start_date" value="<?= $startDate ?>" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all cursor-pointer">
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-calendar-check text-blue-500/60"></i> Ending At</label>
                <input type="date" name="end_date" value="<?= $endDate ?>" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all cursor-pointer">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-credit-card text-blue-500/60"></i> Method</label>
                <select name="payment_method" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
                    <option value="all">All Methods</option>
                    <option value="cash" <?= $paymentMethod=="cash"?"selected":""?>>Cash Only</option>
                    <option value="card" <?= $paymentMethod=="card"?"selected":""?>>Card/POS</option>
                    <option value="mobile_money" <?= $paymentMethod=="mobile_money"?"selected":""?>>Mobile Money</option>
                </select>
            </div>
            <div class="flex shrink-0">
                <button type="submit" class="bg-slate-900 hover:bg-black text-white px-8 py-3 rounded-2xl font-black text-sm transition-all shadow-xl shadow-slate-900/10 flex items-center gap-2 group">
                    <i class="fas fa-sync-alt text-xs group-hover:rotate-180 transition-transform duration-500"></i> Update Report
                </button>
            </div>
        </form>
    </div>

    <!-- Analytics Dashboard -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-3 glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-white min-h-[400px]">
             <h3 class="text-xl font-black text-slate-900 tracking-tight mb-8">Sales Analysis</h3>
             <div class="relative flex-grow h-[350px]"><canvas id="salesTrendChart"></canvas></div>
        </div>
        <div class="lg:col-span-1 flex flex-col gap-6">
            <div class="glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-gradient-to-br from-blue-600 to-blue-700 text-white">
                <p class="text-xs font-black text-white/60 uppercase tracking-widest mb-1">Total Revenue</p>
                <h4 class="text-2xl font-black">MWK <?= number_format($totalRevenue, 2) ?></h4>
            </div>
            <div class="glassmorphism p-8 rounded-[32px] bg-white border border-slate-100 shadow-sm">
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Total Sales</p>
                <h4 class="text-3xl font-black text-slate-900"><?= number_format($totalTransactions) ?></h4>
            </div>
            <div class="glassmorphism p-8 rounded-[32px] bg-white border border-slate-100 shadow-sm">
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Avg Sale</p>
                <h4 class="text-xl font-black text-slate-900">MWK <?= number_format($avgSaleValue, 0) ?></h4>
            </div>
        </div>
    </div>

    <!-- Data Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1 glassmorphism p-6 rounded-[32px] bg-white border border-white/40 shadow-sm">
            <h3 class="text-lg font-black text-slate-900 mb-6 tracking-tight">Daily Summary</h3>
            <div class="space-y-3">
                <?php foreach($dailySummary as $day): ?>
                    <div class="p-4 bg-slate-50/50 rounded-2xl flex justify-between items-center">
                        <div>
                            <p class="text-xs font-black text-slate-800"><?= date("M j, Y", strtotime($day["sale_date"])) ?></p>
                            <p class="text-[10px] font-bold text-slate-400"><?= $day["transactions"] ?> sales</p>
                        </div>
                        <p class="text-sm font-black text-emerald-600">MWK <?= number_format($day["revenue"], 0) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="lg:col-span-3 glassmorphism rounded-[32px] bg-white border border-white/40 shadow-sm overflow-hidden">
             <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/50 text-left border-b border-slate-50">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ID</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Cashier</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($sales as $s): ?>
                        <tr class="hover:bg-slate-50/40 transition-colors">
                            <td class="px-6 py-5 font-black text-slate-700 text-sm">#<?= str_pad($s["id"], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-6 py-5">
                                <p class="text-sm font-bold text-slate-800 truncate max-w-[240px]"><?= htmlspecialchars($s["product_list"] ?? "N/A") ?></p>
                                <p class="text-[10px] font-black text-slate-400 mt-0.5"><?= date("M j, Y • H:i", strtotime($s["created_at"])) ?></p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="inline-flex px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-black"><?= htmlspecialchars($s["sold_by"] ?? "System") ?></span>
                            </td>
                            <td class="px-6 py-5 text-sm font-black text-right text-slate-900">MWK <?= number_format($s["total_amount"], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => { 
    const ctx = document.getElementById("salesTrendChart").getContext("2d");
    new Chart(ctx, { 
        type: "line", 
        data: { labels: <?= json_encode($chartLabels) ?>, datasets: [{ data: <?= json_encode($chartValues) ?>, borderColor: "#3b82f6", borderWidth: 4, tension: 0.4, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.1)' }] }, 
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: "#f8fafc" } }, x: { grid: { display: false } } } } 
    }); 
});
</script>

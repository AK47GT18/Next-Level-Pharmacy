<?php
// filepath: pages/reports/financial.php
require_once __DIR__ . "/../../includes/check-auth.php";
require_once __DIR__ . "/../../config/database.php";

try {
    $db = Database::getInstance(); $conn = $db->getConnection();
    $page = max(1, intval($_GET["p"] ?? 1)); $perPage = 15; $offset = ($page - 1) * $perPage;
    $startDate = $_GET["start_date"] ?? date("Y-m-01"); $endDate = $_GET["end_date"] ?? date("Y-m-t");
    
    $countStmt = $conn->prepare("SELECT COUNT(DISTINCT si.product_id) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) BETWEEN ? AND ?");
    $countStmt->execute([$startDate, $endDate]); 
    $totalRecords = (int)$countStmt->fetchColumn(); 
    $totalPages = max(1, ceil($totalRecords/$perPage));
    $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;
    
    // Fixed Parameterized Pagination
    $perfQuery = "SELECT p.name, SUM(si.quantity) as units_sold, SUM(si.total) as revenue, AVG(si.price_at_sale) as avg_price FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) BETWEEN ? AND ? GROUP BY p.id, p.name ORDER BY revenue DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $perfStmt = $conn->prepare($perfQuery); 
    $perfStmt->execute([$startDate, $endDate]); 
    $products = $perfStmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryStmt = $conn->prepare("SELECT SUM(total) as revenue, COUNT(DISTINCT sale_id) as sales_count FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) BETWEEN ? AND ?");
    $summaryStmt->execute([$startDate, $endDate]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = (float)($summary["revenue"] ?? 0);
    $totalSales = (int)($summary["sales_count"] ?? 0);

    $topProdLabels = []; $topProdValues = [];
    $top5 = array_slice($products, 0, 5);
    foreach($top5 as $tp) { $topProdLabels[] = $tp['name']; $topProdValues[] = (float)$tp['revenue']; }

    $trendQuery = "SELECT DATE(s.created_at) as sale_date, SUM(si.total) as revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) BETWEEN ? AND ? GROUP BY DATE(s.created_at) ORDER BY sale_date ASC";
    $trendStmt = $conn->prepare($trendQuery);
    $trendStmt->execute([$startDate, $endDate]);
    $trendData = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $trendLabels = []; $trendValues = [];
    $curr = new DateTime($startDate); $stop = new DateTime($endDate); $stop->modify('+1 day');
    while($curr < $stop) {
        $d = $curr->format('Y-m-d'); $trendLabels[] = $curr->format('M j');
        $trendValues[] = (float)($trendData[$d] ?? 0); $curr->modify('+1 day');
    }

} catch (Exception $e) { die("<div class='p-12 text-center bg-rose-50 text-rose-600 rounded-3xl font-black italic'>Error loading financial performance.</div>"); }
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-10">
    <div class="flex items-center justify-between pb-2">
        <div class="flex items-center gap-4">
            <a href="?page=reports" class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-black transition-all shadow-xl shadow-slate-900/10"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Financial Performance</h1>
                <p class="text-sm font-medium text-slate-400">Revenue and product performance analysis.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href = 'api/reports/download.php?report=financial&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>'" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold flex items-center gap-2 hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 active:scale-95"><i class="fas fa-file-export"></i> Export Report</button>
            <div class="px-5 py-2 bg-blue-600 text-white rounded-2xl shadow-lg">
                <p class="text-[9px] font-black text-white/50 uppercase tracking-widest leading-none mb-1">Total Revenue</p>
                <h4 class="text-lg font-black">MWK <?= number_format($totalRevenue, 0) ?></h4>
            </div>
        </div>
    </div>

    <!-- Enhanced Filter Bar -->
    <div class="glassmorphism p-3 rounded-[24px] border border-white/40 shadow-sm bg-gradient-to-r from-emerald-500/5 to-transparent">
        <form method="GET" class="flex flex-wrap items-end gap-x-6 gap-y-4 p-2">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="financial">
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-history text-emerald-500/60"></i> Range Start</label>
                <input type="date" name="start_date" value="<?= $startDate ?>" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-check-circle text-emerald-500/60"></i> Range End</label>
                <input type="date" name="end_date" value="<?= $endDate ?>" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
            </div>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-10 py-3 rounded-2xl font-black text-sm transition-all shadow-xl shadow-emerald-500/20 active:scale-95">Generate Analysis</button>
        </form>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-white min-h-[400px]">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-8">Revenue Performance Trend</h3>
            <div class="w-full h-[300px]"><canvas id="revenueTrendChart"></canvas></div>
        </div>
        <div class="lg:col-span-1 glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-white min-h-[400px]">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-8">Top Revenue Products</h3>
            <div class="w-full h-[300px]"><canvas id="topProductsBar"></canvas></div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="glassmorphism rounded-[32px] border border-white/40 shadow-sm overflow-hidden bg-white">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50/50 text-left">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Name</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Volume Sold</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Revenue Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach($products as $p): ?>
                    <tr class="hover:bg-slate-50/40 transition-colors">
                        <td class="px-6 py-5 font-black text-slate-800 tracking-tight"><?= htmlspecialchars($p["name"]) ?></td>
                        <td class="px-6 py-5 text-center"><span class="font-black text-slate-600"><?= number_format($p["units_sold"]) ?></span><span class="text-[10px] font-black text-slate-400 uppercase ml-1 tracking-tighter">Units</span></td>
                        <td class="px-6 py-5 text-right"><p class="font-black text-emerald-600 text-lg leading-none">MWK <?= number_format($p["revenue"], 2) ?></p></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Smart Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="p-6 border-t border-slate-50 flex items-center justify-between bg-slate-50/30">
                <div class="text-xs font-black text-slate-400">
                    Showing <?= (($page - 1) * $perPage) + 1 ?> - <?= min($page * $perPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> products
                </div>
                <div class="flex items-center gap-2">
                    <?php 
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end = min($totalPages, $page + $range);
                    
                    // First button
                    if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all">
                            <i class="fas fa-angle-double-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Prev button -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all">
                            <i class="fas fa-chevron-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs transition-all <?= $i === $page ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/30' : 'bg-white border-2 border-slate-100 text-slate-400 hover:text-emerald-600 hover:border-emerald-200' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Last button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $totalPages])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all">
                            <i class="fas fa-angle-double-right text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    new Chart(document.getElementById("revenueTrendChart").getContext("2d"), { type: 'line', data: { labels: <?= json_encode($trendLabels) ?>, datasets: [{ data: <?= json_encode($trendValues) ?>, borderColor: '#10b981', borderWidth: 4, tension: 0.4, fill: true, backgroundColor: 'rgba(16, 185, 129, 0.1)' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
    new Chart(document.getElementById("topProductsBar"), { type: 'bar', data: { labels: <?= json_encode($topProdLabels) ?>, datasets: [{ data: <?= json_encode($topProdValues) ?>, backgroundColor: '#3b82f6', borderRadius: 12 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
});
</script>

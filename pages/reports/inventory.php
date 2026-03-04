<?php
// filepath: pages/reports/inventory.php
require_once __DIR__ . "/../../includes/check-auth.php";
require_once __DIR__ . "/../../config/database.php";

try {
    $db = Database::getInstance(); $conn = $db->getConnection();
    $page = max(1, intval($_GET["p"] ?? 1)); $perPage = 15; $offset = ($page - 1) * $perPage;
    $catId = $_GET["category"] ?? ""; $stockType = $_GET["stock_level"] ?? "";
    
    $where = " WHERE 1=1"; $countP = [];
    if($catId){ $where .= " AND p.category_id = ?"; $countP[] = $catId; }
    if($stockType=="low"){ $where .= " AND p.stock > 0 AND p.stock <= p.low_stock_threshold"; }
    elseif($stockType=="out"){ $where .= " AND p.stock = 0"; }
    
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM products p $where"); $countStmt->execute($countP);
    $totalRecords = (int)$countStmt->fetchColumn(); $totalPages = max(1, ceil($totalRecords / $perPage));
    $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;
    
    // Fixed Parameterized Pagination
    $query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.name ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $conn->prepare($query); $stmt->execute($countP); 
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Stats Logic
    $totalStockValue = $conn->query("SELECT SUM(stock * price) FROM products")->fetchColumn() ?? 0;
    $lowStockItems = (int)$conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= low_stock_threshold")->fetchColumn();
    $outOfStockItems = (int)$conn->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
    $safeStockItems = (int)$conn->query("SELECT COUNT(*) FROM products WHERE stock > low_stock_threshold")->fetchColumn();

    $statusLabels = ["Safe", "Low", "Empty"];
    $statusCounts = [$safeStockItems, $lowStockItems, $outOfStockItems];
    $catValueData = $conn->query("SELECT c.name, SUM(p.stock * p.price) as total_val FROM products p JOIN categories c ON p.category_id = c.id GROUP BY c.id ORDER BY total_val DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $catLabels = []; $catValues = [];
    foreach($catValueData as $cv) { $catLabels[] = $cv['name']; $catValues[] = (float)$cv['total_val']; }

} catch (Exception $e) { die("<div class='p-12 text-center bg-rose-50 text-rose-600 rounded-3xl font-black italic'>Error initializing inventory insights.</div>"); }
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-10">
    <div class="flex items-center justify-between pb-2">
        <div class="flex items-center gap-4">
            <a href="?page=reports" class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-black transition-all shadow-xl shadow-slate-900/10"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Inventory Insights</h1>
                <p class="text-sm font-medium text-slate-400">Stock distribution and asset valuation.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href = 'api/reports/download.php?report=inventory'" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold flex items-center gap-2 hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 shadow-sm"><i class="fas fa-file-export"></i> Export</button>
            <div class="px-5 py-2 bg-emerald-50 border border-emerald-100 rounded-2xl">
                <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest leading-none mb-1">Total Valuation</p>
                <h4 class="text-lg font-black text-slate-800">MWK <?= number_format($totalStockValue, 0) ?></h4>
            </div>
        </div>
    </div>

    <!-- Enhanced Filter Bar -->
    <div class="glassmorphism p-3 rounded-[24px] border border-white/40 shadow-sm bg-gradient-to-r from-blue-500/5 to-transparent">
        <form method="GET" class="flex flex-wrap items-end gap-x-6 gap-y-4 p-2">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="view" value="inventory">
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-tags text-blue-500/60"></i> Category</label>
                <select name="category" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $c): ?><option value="<?= $c["id"] ?>" <?= $catId==$c["id"]?"selected":""?>><?= htmlspecialchars($c["name"])?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] uppercase font-black text-slate-500 mb-1.5 ml-1 flex items-center gap-1.5"><i class="fas fa-layer-group text-blue-500/60"></i> Stock Status</label>
                <select name="stock_level" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
                    <option value="">All Inventory</option>
                    <option value="low" <?= $stockType=="low"?"selected":""?>>Low Stock Only</option>
                    <option value="out" <?= $stockType=="out"?"selected":""?>>Out of Stock Only</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-2xl font-black text-sm transition-all shadow-xl shadow-blue-500/20">Refresh List</button>
        </form>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-white min-h-[350px] flex flex-col items-center">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-8">Stock Level Distribution</h3>
            <div class="w-full h-[240px]"><canvas id="statusDoughnut"></canvas></div>
        </div>
        <div class="glassmorphism p-8 rounded-[32px] border border-white/40 shadow-sm bg-white min-h-[350px]">
             <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-8">Asset Value by Category</h3>
            <div class="w-full h-[240px]"><canvas id="categoryValueBar"></canvas></div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="glassmorphism rounded-[32px] border border-white/40 shadow-sm overflow-hidden bg-white">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50/50 text-left">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Description</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Category</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Unit Price</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach($products as $p): ?>
                    <tr class="hover:bg-slate-50/40 transition-colors">
                        <td class="px-6 py-4">
                            <p class="font-black text-slate-900 tracking-tight"><?= htmlspecialchars($p["name"]) ?></p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase mt-0.5">SKU: <?= str_pad($p["id"]??0, 6, '0', STR_PAD_LEFT) ?></p>
                        </td>
                        <td class="px-6 py-4"><span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full"><?= htmlspecialchars($p["category_name"]??"General") ?></span></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-xl text-[11px] font-black <?= $p['stock'] == 0 ? 'bg-rose-100 text-rose-600' : ($p['stock'] <= $p['low_stock_threshold'] ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600') ?>">
                                <?= $p["stock"] ?> available
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-black text-slate-900">MWK <?= number_format($p["price"], 2) ?></td>
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
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all">
                            <i class="fas fa-angle-double-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Prev button -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all">
                            <i class="fas fa-chevron-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs transition-all <?= $i === $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'bg-white border-2 border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Last button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $totalPages])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-xs bg-white border-2 border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all">
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
    new Chart(document.getElementById("statusDoughnut"), { type: 'doughnut', data: { labels: <?= json_encode($statusLabels) ?>, datasets: [{ data: <?= json_encode($statusCounts) ?>, backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderWidth: 0 }] }, options: { cutout: '75%', responsive: true, maintainAspectRatio: false } });
    new Chart(document.getElementById("categoryValueBar"), { type: 'bar', data: { labels: <?= json_encode($catLabels) ?>, datasets: [{ data: <?= json_encode($catValues) ?>, backgroundColor: '#3b82f6', borderRadius: 12 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
});
</script>

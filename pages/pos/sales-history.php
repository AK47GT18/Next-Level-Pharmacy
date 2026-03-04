<?php
// filepath: pages/pos/sales-history.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin'; 
$userId = $_SESSION['user_id'] ?? null;

try {
    $db = Database::getInstance(); $conn = $db->getConnection();
    $page = max(1, intval($_GET['p'] ?? 1)); $perPage = 15; $offset = ($page - 1) * $perPage;
    $dateFilter = $_GET['date'] ?? date('Y-m-d'); 
    $dateFilter = date('Y-m-d', strtotime($dateFilter));
    
    $countQ = "SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) = ?";
    $countP = [$dateFilter]; 
    if (!$isAdmin) { $countQ .= " AND s.sold_by = ?"; $countP[] = $userId; }
    
    $countStmt = $conn->prepare($countQ); $countStmt->execute($countP);
    $totalRecords = (int) $countStmt->fetchColumn(); 
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;
    
    $query = "SELECT s.id as sale_id, si.id as sale_item_id, p.name as product_name, si.quantity, si.price_at_sale, si.total, u.name as seller_name, s.created_at
        FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id LEFT JOIN users u ON s.sold_by = u.id
        WHERE DATE(s.created_at) = ?";
    $queryParams = [$dateFilter]; 
    if (!$isAdmin) { $query .= " AND s.sold_by = ?"; $queryParams[] = $userId; }
    $query .= " ORDER BY s.created_at DESC, si.id ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    
    $stmt = $conn->prepare($query); $stmt->execute($queryParams); $salesItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sumStmt = $conn->prepare("SELECT COUNT(DISTINCT s.id) as total_sales, COALESCE(SUM(si.total), 0) as total_revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) = ?" . (!$isAdmin?" AND s.sold_by = ?":""));
    $sumStmt->execute($countP); $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) { error_log($e->getMessage()); die("Error loading history."); }
?>

<div class="space-y-6 animate-in fade-in duration-500 pb-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Sales History</h1>
            <p class="text-sm font-bold text-slate-400 mt-1"><?= $isAdmin?"Admin Mode":"Cashier View"?> • <?= date("M j, Y", strtotime($dateFilter)) ?></p>
        </div>
        <a href="?page=dashboard" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-900 rounded-2xl font-black text-sm transition-all active:scale-95 flex items-center gap-2">
            <i class="fas fa-home opacity-30"></i> Dashboard
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glassmorphism p-6 rounded-[32px] flex items-center gap-5 border border-white/40 shadow-sm bg-white">
            <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-inner shadow-blue-100/50">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest leading-none mb-1">Total Items Sold</p>
                <p class="text-3xl font-black text-slate-900"><?= number_format($summary['total_sales']??0) ?></p>
            </div>
        </div>
        <div class="glassmorphism p-6 rounded-[32px] flex items-center gap-5 border border-white/40 shadow-sm bg-white">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-inner shadow-emerald-100/50">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest leading-none mb-1">Daily Revenue</p>
                <p class="text-3xl font-black text-slate-900">MWK <?= number_format($summary['total_revenue']??0, 2) ?></p>
            </div>
        </div>
    </div>

    <div class="glassmorphism p-6 rounded-[32px] border border-white/40 shadow-sm bg-white">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="page" value="pos">
            <input type="hidden" name="view" value="sales-history">
            <div class="flex-1 min-w-[200px]">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 block">Filter by Date</label>
                <input type="date" name="date" value="<?= $dateFilter ?>" class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-5 py-3 text-sm font-black text-slate-700 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none">
            </div>
            <button type="submit" class="px-10 py-3.5 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-95 transition-all">
                Update View
            </button>
        </form>
    </div>

    <div class="glassmorphism rounded-[40px] shadow-sm border border-white/40 overflow-hidden bg-white">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/50 text-left border-b border-slate-50">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Sale #</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Description</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Qty</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($salesItems)):?>
                        <tr><td colspan="4" class="p-20 text-center italic text-slate-300 font-black text-lg tracking-tight">No records found for this date.</td></tr>
                    <?php else: foreach($salesItems as $it): ?>
                        <tr class="hover:bg-slate-50/40 transition-colors group">
                            <td class="px-8 py-6">
                                <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-[11px] font-black border border-blue-100/50">#<?= str_pad($it['sale_id'],5,'0',STR_PAD_LEFT) ?></span>
                            </td>
                            <td class="px-8 py-6">
                                <p class="text-sm font-black text-slate-800 tracking-tight group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($it['product_name']) ?></p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">at <?= date("H:i", strtotime($it['created_at'])) ?></p>
                            </td>
                            <td class="px-8 py-6 text-center font-black text-slate-400 text-sm"><?= $it['quantity'] ?></td>
                            <td class="px-8 py-6 text-right">
                                <p class="text-sm font-black text-slate-900 leading-none">MWK <?= number_format($it['total'], 2) ?></p>
                                <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mt-1">Settled</p>
                            </td>
                        </tr>
                    <?php endforeach; endif;?>
                </tbody>
            </table>
        </div>
        
        <?php if($totalPages > 1): ?>
            <div class="p-8 bg-slate-50/50 border-t border-slate-50 flex justify-center gap-2">
                <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
                    <a href="?page=pos&view=sales-history&date=<?= $dateFilter ?>&p=<?= $i ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-xs font-black transition-all <?= $i===$page?"bg-blue-600 text-white shadow-lg shadow-blue-500/30 scale-110":"bg-white border-2 border-slate-50 text-slate-400 hover:text-blue-600" ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

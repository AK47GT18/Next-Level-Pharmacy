<?php
// filepath: pages/pos/sales-history.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin'; 
$userId = $_SESSION['user_id'] ?? null;

try {
    $db = Database::getInstance(); 
    $conn = $db->getConnection();
    
    // Filters
    $dateFilterInput = $_GET['date'] ?? date('Y-m-d'); 
    $dateFilter = date('Y-m-d', strtotime($dateFilterInput));
    $searchId = $_GET['search_id'] ?? '';
    
    // Pagination settings
    $page = max(1, intval($_GET['p'] ?? 1)); 
    $perPage = 15; 
    $offset = ($page - 1) * $perPage;
    
    // Count total for pagination
    $countQ = "SELECT COUNT(DISTINCT s.id) 
               FROM sales s 
               LEFT JOIN sale_items si ON s.id = si.sale_id 
               WHERE DATE(s.created_at) = ?";
    $countP = [$dateFilter]; 
    
    if (!$isAdmin) { 
        $countQ .= " AND s.sold_by = ?"; 
        $countP[] = $userId; 
    }
    
    if (!empty($searchId)) {
        $countQ .= " AND s.id = ?";
        $countP[] = intval($searchId);
    }
    
    $countStmt = $conn->prepare($countQ); 
    $countStmt->execute($countP);
    $totalRecords = (int) $countStmt->fetchColumn(); 
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $page = min($page, $totalPages); 
    $offset = ($page - 1) * $perPage;
    
    // Main query - Grouped by Parent Sale for Actions, but showing itemized details
    $query = "SELECT 
                s.id as sale_id, 
                si.id as sale_item_id, 
                p.name as product_name, 
                si.quantity, 
                si.price_at_sale, 
                si.total, 
                u.name as seller_name, 
                s.created_at,
                COALESCE(pm.payment_method, 'cash') as payment_method,
                s.total_amount as sale_total
              FROM sales s
              JOIN sale_items si ON s.id = si.sale_id 
              JOIN products p ON si.product_id = p.id 
              LEFT JOIN users u ON s.sold_by = u.id
              LEFT JOIN payments pm ON s.id = pm.sale_id
              WHERE DATE(s.created_at) = ?";
              
    $queryParams = [$dateFilter]; 
    if (!$isAdmin) { 
        $query .= " AND s.sold_by = ?"; 
        $queryParams[] = $userId; 
    }
    if (!empty($searchId)) {
        $query .= " AND s.id = ?";
        $queryParams[] = intval($searchId);
    }
    
    $query .= " ORDER BY s.created_at DESC, si.id ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    
    $stmt = $conn->prepare($query); 
    $stmt->execute($queryParams); 
    $salesItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary statistics for the filtered date
    $sumQ = "SELECT COUNT(DISTINCT s.id) as total_sales, COALESCE(SUM(si.total), 0) as total_revenue 
             FROM sales s 
             JOIN sale_items si ON s.id = si.sale_id 
             WHERE DATE(s.created_at) = ?";
    $sumP = [$dateFilter];
    if (!$isAdmin) { 
        $sumQ .= " AND s.sold_by = ?"; 
        $sumP[] = $userId; 
    }
    if (!empty($searchId)) {
        $sumQ .= " AND s.id = ?";
        $sumP[] = intval($searchId);
    }
    
    $sumStmt = $conn->prepare($sumQ);
    $sumStmt->execute($sumP); 
    $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) { 
    error_log($e->getMessage()); 
    die("Error loading history: " . $e->getMessage()); 
}
?>

<div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700 pb-12">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Sales History</h1>
            <div class="flex items-center gap-3 mt-1">
                <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-black uppercase tracking-widest border border-slate-200/50">
                    <?= $isAdmin ? "Administrator" : "Cashier Session" ?>
                </span>
                <p class="text-xs font-bold text-slate-400">Viewing records for <span class="text-slate-600"><?= date("l, M j, Y", strtotime($dateFilter)) ?></span></p>
            </div>
        </div>
        <a href="?page=dashboard" class="group px-6 py-3 bg-white border border-slate-200 text-slate-700 rounded-2xl font-black text-sm shadow-sm hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 transition-all">
                <i class="fas fa-home text-xs"></i>
            </div>
            Back to Dashboard
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="glassmorphism p-8 rounded-[32px] flex items-center gap-6 border border-white/40 shadow-sm bg-white group hover:translate-y--1 transition-all">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-[24px] flex items-center justify-center text-3xl shadow-inner shadow-blue-100/50 group-hover:bg-blue-600 group-hover:text-white transition-all">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest leading-none mb-3">Today's Transactions</p>
                <p class="text-4xl font-black text-slate-900 tracking-tight"><?= number_format($summary['total_sales'] ?? 0) ?></p>
            </div>
        </div>
        <div class="glassmorphism p-8 rounded-[32px] flex items-center gap-6 border border-white/40 shadow-sm bg-white group hover:translate-y--1 transition-all">
            <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-[24px] flex items-center justify-center text-3xl shadow-inner shadow-emerald-100/50 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest leading-none mb-3">Total Revenue Recorded</p>
                <p class="text-4xl font-black text-slate-900 tracking-tight">MWK <?= number_format($summary['total_revenue'] ?? 0, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="glassmorphism p-10 rounded-[32px] border border-white/40 shadow-sm bg-white/50 backdrop-blur-md">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-8 items-end">
            <input type="hidden" name="page" value="pos">
            <input type="hidden" name="view" value="sales-history">
            
            <div class="md:col-span-3">
                <label for="search_id" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Search ID</label>
                <div class="relative">
                    <input type="number" name="search_id" id="search_id" value="<?= htmlspecialchars($searchId) ?>" placeholder="e.g. 197"
                        class="w-full pl-12 pr-5 py-4 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold placeholder:text-slate-300 outline-none">
                    <i class="fas fa-hashtag absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                </div>
            </div>

            <div class="md:col-span-6">
                <label for="date" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Select History Date</label>
                <div class="relative">
                    <input type="date" name="date" id="date" value="<?= $dateFilter ?>" 
                        class="w-full pl-12 pr-5 py-4 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold text-slate-700 outline-none">
                    <i class="far fa-calendar absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                </div>
            </div>

            <div class="md:col-span-3 flex gap-4">
                <button type="submit" class="flex-1 px-8 py-4 bg-slate-900 text-white rounded-2xl font-black text-sm shadow-xl shadow-slate-900/10 hover:bg-black transition-all flex items-center justify-center gap-3 active:scale-95">
                    <i class="fas fa-search-dollar"></i>
                    <span>Apply Filter</span>
                </button>
                <?php if (!empty($searchId) || $dateFilter !== date('Y-m-d')): ?>
                    <a href="?page=pos&view=sales-history" class="px-6 py-4 bg-rose-50 text-rose-600 rounded-2xl font-black text-sm hover:bg-rose-100 transition-all flex items-center justify-center active:scale-95">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <div class="glassmorphism rounded-[40px] shadow-sm border border-white/40 overflow-hidden bg-white">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/50 text-left border-b border-slate-100">
                        <th class="px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-24">Sale #</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Description & Seller</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Quantity</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Settlement</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($salesItems)): ?>
                        <tr>
                            <td colspan="5" class="py-24 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                                        <i class="fas fa-inbox text-3xl text-slate-200"></i>
                                    </div>
                                    <p class="font-black text-slate-400 uppercase tracking-widest text-xs">No records matching your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($salesItems as $it): ?>
                        <tr class="hover:bg-slate-50/50 transition-all group">
                            <td class="px-8 py-8 whitespace-nowrap">
                                <span class="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-black border border-blue-100/50 shadow-sm shadow-blue-500/5">
                                    #<?= str_pad($it['sale_id'], 5, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            <td class="px-8 py-8">
                                <div class="flex flex-col">
                                    <p class="text-sm font-black text-slate-800 tracking-tight group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($it['product_name']) ?></p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= date("H:i", strtotime($it['created_at'])) ?></p>
                                        <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                        <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest">by <?= htmlspecialchars($it['seller_name'] ?? 'System') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-8 text-center">
                                <span class="inline-flex items-center px-4 py-1.5 bg-slate-100 text-slate-700 rounded-xl font-black text-xs">
                                    <?= $it['quantity'] ?> units
                                </span>
                            </td>
                            <td class="px-8 py-8 text-right whitespace-nowrap">
                                <p class="text-sm font-black text-slate-900 leading-none">MWK <?= number_format($it['total'], 2) ?></p>
                                <div class="flex items-center justify-end gap-1.5 mt-2">
                                    <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">Settled</span>
                                    <?php
                                    $paymentIcons = [
                                        'cash' => 'fa-money-bill-wave',
                                        'card' => 'fa-credit-card',
                                        'mobile_money' => 'fa-mobile-alt',
                                        'bank_transfer' => 'fa-university'
                                    ];
                                    $icon = $paymentIcons[$it['payment_method']] ?? 'fa-wallet';
                                    ?>
                                    <i class="fas <?= $icon ?> text-[10px] text-slate-300"></i>
                                </div>
                            </td>
                            <td class="px-8 py-8 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="editSale(<?= $it['sale_id'] ?>)" class="w-10 h-10 flex items-center justify-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm shadow-blue-500/5 group/btn" title="Edit Parent Sale">
                                        <i class="fas fa-edit text-[11px]"></i>
                                    </button>
                                    <?php if ($isAdmin): ?>
                                    <button onclick="deleteSale(<?= $it['sale_id'] ?>)" class="w-10 h-10 flex items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm shadow-rose-500/5 group/btn" title="Void Parent Sale">
                                        <i class="fas fa-trash text-[11px]"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button onclick='printSale(<?= json_encode([
                                        "id" => $it["sale_id"],
                                        "created_at" => $it["created_at"],
                                        "sold_by" => $it["seller_name"],
                                        "payment_method" => $it["payment_method"],
                                        "total_amount" => $it["sale_total"],
                                        "products_list" => "Itemized Reference: " . $it["product_name"]
                                    ]) ?>)' class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-600 hover:bg-slate-900 hover:text-white transition-all shadow-sm shadow-slate-500/5 group/btn" title="Print Receipt">
                                        <i class="fas fa-receipt text-[11px]"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Premium Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="p-10 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <span class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-slate-700 shadow-sm">Showing Index <?= $offset + 1 ?> — <?= min($offset + $perPage, $totalRecords) ?></span>
                    <span>of <?= $totalRecords ?> Results</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=pos&view=sales-history&date=<?= $dateFilter ?>&search_id=<?= $searchId ?>&p=<?= $page - 1 ?>" 
                           class="px-5 py-3 bg-white border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition-all font-black text-[11px] uppercase tracking-widest shadow-sm active:scale-95">
                            Previous
                        </a>
                    <?php endif; ?>

                    <div class="flex gap-1.5">
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=pos&view=sales-history&date=<?= $dateFilter ?>&search_id=<?= $searchId ?>&p=<?= $i ?>" 
                               class="w-11 h-11 flex items-center justify-center rounded-xl text-[11px] font-black transition-all <?= $i === $page ? "bg-blue-600 text-white shadow-xl shadow-blue-500/20 scale-110" : "bg-white border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-100" ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=pos&view=sales-history&date=<?= $dateFilter ?>&search_id=<?= $searchId ?>&p=<?= $page + 1 ?>" 
                           class="px-5 py-3 bg-white border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition-all font-black text-[11px] uppercase tracking-widest shadow-sm active:scale-95">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Parent Sale Modals -->
<!-- Edit Sale Modal (Consistently styled across the app) -->
<div id="editSaleModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[40px] w-full max-w-2xl overflow-hidden shadow-2xl animate-in zoom-in duration-300">
        <div class="p-10 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-black text-slate-900">Modify Sale <span id="editSaleIdDisplay" class="text-blue-600"></span></h2>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">Adjust item counts for correction</p>
            </div>
            <button onclick="closeEditModal()" class="w-12 h-12 flex items-center justify-center rounded-[20px] bg-slate-50 text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="editSaleContent" class="p-10 max-h-[50vh] overflow-y-auto space-y-6">
            <!-- Items loaded via AJAX -->
        </div>
        <div class="p-10 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">Calculated Total</p>
                <p id="editSaleNewTotal" class="text-2xl font-black text-slate-900">MWK 0.00</p>
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
        
        content.innerHTML = '<div class="flex flex-col items-center py-12 text-slate-300"><i class="fas fa-circle-notch fa-spin text-4xl mb-6"></i><p class="font-black text-[10px] uppercase tracking-widest">Accessing Transaction Records...</p></div>';

        try {
            const response = await fetch(`api/sales/get-items.php?sale_id=${saleId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                let html = '<div class="space-y-4">';
                data.items.forEach(item => {
                    html += `
                        <div class="flex items-center justify-between p-6 bg-white border border-slate-100 rounded-[24px] shadow-sm sale-item-row group hover:border-blue-200 transition-all" 
                             data-id="${item.id}" data-price="${item.price_at_sale}">
                            <div>
                                <p class="font-black text-slate-800">${item.product_name}</p>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Rate: MWK ${parseFloat(item.price_at_sale).toFixed(2)}</p>
                            </div>
                            <div class="flex items-center gap-4 bg-slate-50 p-2 rounded-2xl">
                                <button onclick="updateQty(${item.id}, -1)" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-colors shadow-sm">
                                    <i class="fas fa-minus text-[10px]"></i>
                                </button>
                                <input type="number" value="${item.quantity}" min="1" 
                                    class="w-12 bg-transparent border-none text-center font-bold qty-input focus:ring-0" 
                                    onchange="calculateEditTotal()" id="qty-${item.id}">
                                <button onclick="updateQty(${item.id}, 1)" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl hover:bg-emerald-50 hover:text-emerald-500 transition-colors shadow-sm">
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
                content.innerHTML = `<div class="p-10 text-rose-500 text-center font-black uppercase text-xs">${data.message}</div>`;
            }
        } catch (error) {
            content.innerHTML = '<div class="p-10 text-rose-500 text-center font-black uppercase text-xs">Internal Network Error</div>';
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
            alert('A network error occurred while updating.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function deleteSale(saleId) {
        if (!confirm('EXTREME CAUTION: This will permanently void the entire sale record and restore inventory levels. Confirm?')) return;

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
            alert('Error communicating with server.');
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
                        .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #f3f4f6; padding-bottom: 40px; }
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
                        <p style="font-size: 12px; color: #6b7280; font-weight: 600; margin-top: 8px;">POS Transaction Voucher</p>
                    </div>
                    <div class="details">
                        <div>
                            <span class="label">Invoice Reference</span>
                            <span class="value">#${String(sale.id).padStart(5, '0')}</span>
                        </div>
                        <div style="text-align: right;">
                            <span class="label">Date Issued</span>
                            <span class="value">${new Date(sale.created_at).toLocaleString()}</span>
                        </div>
                        <div>
                            <span class="label">Sold By</span>
                            <span class="value">${sale.sold_by}</span>
                        </div>
                        <div style="text-align: right;">
                            <span class="label">Payment Mode</span>
                            <span class="value" style="text-transform: uppercase;">${sale.payment_method.replace('_', ' ')}</span>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${sale.products_list}</td>
                                <td style="text-align: right;">MWK ${parseFloat(sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                            <tr class="total-row">
                                <td style="text-align: right; color: #9ca3af; font-size: 11px; text-transform: uppercase;">Total Settlement</td>
                                <td style="text-align: right;">MWK ${parseFloat(sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="footer">
                        <p>Thank you for choosing Next-Level Pharmacy Management Ecosystem.</p>
                        <p style="margin-top: 10px;">&copy; ${new Date().getFullYear()} Next-Level Systems</p>
                    </div>
                    <script>window.print();<\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
</script>

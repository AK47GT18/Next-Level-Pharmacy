<?php
// filepath: pages/pos/sales-history.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$userId  = $_SESSION['user_id'] ?? null;

try {
    $db   = Database::getInstance();
    $conn = $db->getConnection();

    // Filters
    $dateFilterInput = $_GET['date']      ?? date('Y-m-d');
    $dateFilter      = date('Y-m-d', strtotime($dateFilterInput));
    $searchId        = $_GET['search_id'] ?? '';

    // Pagination
    $page    = max(1, intval($_GET['p'] ?? 1));
    $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    // Count
    $countQ = "SELECT COUNT(DISTINCT s.id) FROM sales s LEFT JOIN sale_items si ON s.id = si.sale_id WHERE DATE(s.created_at) = ?";
    $countP = [$dateFilter];
    if (!empty($searchId)){ $countQ .= " AND s.id = ?"; $countP[] = intval($searchId); }

    $countStmt    = $conn->prepare($countQ);
    $countStmt->execute($countP);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, ceil($totalRecords / $perPage));
    $page         = min($page, $totalPages);
    $offset       = ($page - 1) * $perPage;

    // Main query
    $query = "SELECT s.id as sale_id, si.id as sale_item_id, p.name as product_name,
                     si.quantity, si.price_at_sale, si.total,
                     u.name as seller_name, s.created_at,
                     COALESCE(pm.payment_method, 'cash') as payment_method,
                     s.total_amount as sale_total
              FROM sales s
              JOIN sale_items si ON s.id = si.sale_id
              JOIN products p ON si.product_id = p.id
              LEFT JOIN users u ON s.sold_by = u.id
              LEFT JOIN payments pm ON s.id = pm.sale_id
              WHERE DATE(s.created_at) = ?";
    $queryParams = [$dateFilter];
    if (!empty($searchId)){ $query .= " AND s.id = ?"; $queryParams[] = intval($searchId); }
    $query .= " ORDER BY s.created_at DESC, si.id ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

    $stmt      = $conn->prepare($query);
    $stmt->execute($queryParams);
    $salesItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $sumQ = "SELECT COUNT(DISTINCT s.id) as total_sales, COALESCE(SUM(si.total), 0) as total_revenue
             FROM sales s JOIN sale_items si ON s.id = si.sale_id WHERE DATE(s.created_at) = ?";
    $sumP = [$dateFilter];
    if (!empty($searchId)){ $sumQ .= " AND s.id = ?"; $sumP[] = intval($searchId); }
    $sumStmt = $conn->prepare($sumQ);
    $sumStmt->execute($sumP);
    $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    die("Error loading history: " . $e->getMessage());
}

$paymentLabels = [
    'cash'          => ['icon' => 'fa-money-bill-wave', 'label' => 'Cash',          'pill' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
    'card'          => ['icon' => 'fa-credit-card',     'label' => 'Card',          'pill' => 'bg-blue-50 text-blue-700 border-blue-100'],
    'mobile_money'  => ['icon' => 'fa-mobile-alt',      'label' => 'Mobile Money',  'pill' => 'bg-violet-50 text-violet-700 border-violet-100'],
    'bank_transfer' => ['icon' => 'fa-university',       'label' => 'Bank Transfer', 'pill' => 'bg-amber-50 text-amber-700 border-amber-100'],
];
?>

<div class="space-y-6 pb-12">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Sales History</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                <?= date('l, F j, Y', strtotime($dateFilter)) ?>
            </p>
        </div>
        <a href="?page=dashboard"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
            <i class="fas fa-home text-xs text-gray-400"></i>
            Dashboard
        </a>
    </div>

    <!-- ── Stats ──────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-receipt text-blue-500 text-base"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Transactions</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($summary['total_sales'] ?? 0) ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-wallet text-emerald-500 text-base"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Total Revenue</p>
                <p class="text-2xl font-bold text-gray-900">MWK <?= number_format($summary['total_revenue'] ?? 0, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- ── Filters ────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="page"  value="pos">
            <input type="hidden" name="view"  value="sales-history">
            <input type="hidden" name="p"     value="1">

            <!-- Sale ID -->
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Sale ID</label>
                <div class="relative">
                    <i class="fas fa-hashtag absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="number" name="search_id" value="<?= htmlspecialchars($searchId) ?>"
                           placeholder="e.g. 197"
                           class="w-full pl-8 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 placeholder:text-gray-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white outline-none transition-all">
                </div>
            </div>

            <!-- Date -->
            <div class="flex-[2] min-w-[180px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Date</label>
                <div class="relative">
                    <i class="far fa-calendar absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="date" name="date" value="<?= $dateFilter ?>"
                           class="w-full pl-8 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white outline-none transition-all">
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2">
                <button type="submit"
                        class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-semibold hover:bg-black transition-all shadow-sm flex items-center gap-2">
                    <i class="fas fa-search text-xs"></i> Search
                </button>
                <?php if (!empty($searchId) || $dateFilter !== date('Y-m-d')): ?>
                    <a href="?page=pos&view=sales-history"
                       class="px-4 py-2.5 bg-red-50 text-red-500 border border-red-100 rounded-xl text-sm font-semibold hover:bg-red-100 transition-all flex items-center gap-1.5">
                        <i class="fas fa-times text-xs"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Table ──────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Sale #</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Item &amp; Seller</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Qty</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Payment</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Amount</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($salesItems)): ?>
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-inbox text-3xl text-gray-200"></i>
                                    <p class="font-semibold text-gray-400 text-sm">No records found</p>
                                    <p class="text-xs text-gray-300">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($salesItems as $it):
                        $pm = $paymentLabels[$it['payment_method']] ?? ['icon'=>'fa-wallet','label'=>ucfirst($it['payment_method']),'pill'=>'bg-gray-50 text-gray-600 border-gray-100'];
                    ?>
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <!-- Sale ID -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold border border-blue-100">
                                    #<?= str_pad($it['sale_id'], 5, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            <!-- Product + seller + time -->
                            <td class="px-5 py-4">
                                <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($it['product_name']) ?></p>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <i class="fas fa-user-circle text-gray-300 text-[10px]"></i>
                                    <span class="text-xs text-gray-400"><?= htmlspecialchars($it['seller_name'] ?? 'System') ?></span>
                                    <span class="text-gray-200">·</span>
                                    <span class="text-xs text-gray-400"><?= date('H:i', strtotime($it['created_at'])) ?></span>
                                </div>
                            </td>
                            <!-- Qty -->
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-gray-700 rounded-lg text-xs font-bold">
                                    <?= $it['quantity'] ?>
                                </span>
                            </td>
                            <!-- Payment -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border <?= $pm['pill'] ?>">
                                    <i class="fas <?= $pm['icon'] ?> text-[10px]"></i>
                                    <?= $pm['label'] ?>
                                </span>
                            </td>
                            <!-- Amount -->
                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <p class="font-bold text-gray-900 text-sm">MWK <?= number_format($it['total'], 2) ?></p>
                                <p class="text-xs text-gray-400 mt-0.5">of MWK <?= number_format($it['sale_total'], 2) ?></p>
                            </td>
                            <!-- Actions -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick='printSale(<?= json_encode([
                                        "id"             => $it["sale_id"],
                                        "created_at"     => $it["created_at"],
                                        "sold_by"        => $it["seller_name"],
                                        "payment_method" => $it["payment_method"],
                                        "total_amount"   => $it["sale_total"],
                                        "products_list"  => $it["product_name"],
                                    ]) ?>)'
                                            title="Print receipt"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-800 hover:text-white transition-all text-xs">
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between flex-wrap gap-3">
                <p class="text-xs text-gray-400 font-medium">
                    Showing
                    <span class="font-semibold text-gray-700"><?= number_format($offset + 1) ?></span>–<span class="font-semibold text-gray-700"><?= number_format(min($offset + $perPage, $totalRecords)) ?></span>
                    of <span class="font-semibold text-gray-700"><?= number_format($totalRecords) ?></span>
                </p>
                <div class="flex items-center gap-1">
                    <?php
                    $base = "?page=pos&view=sales-history&date={$dateFilter}&search_id={$searchId}&p=";
                    ?>
                    <!-- Prev -->
                    <a href="<?= $page > 1 ? $base.($page-1) : '#' ?>"
                       class="w-8 h-8 flex items-center justify-center rounded-lg border text-xs transition-all
                              <?= $page > 1 ? 'border-gray-200 text-gray-500 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>

                    <?php
                    $pgStart = max(1, $page - 2);
                    $pgEnd   = min($totalPages, $page + 2);
                    if ($pgStart > 1): ?>
                        <a href="<?= $base.'1' ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-xs text-gray-600 hover:bg-gray-100 transition-all">1</a>
                        <?php if ($pgStart > 2): ?><span class="w-8 h-8 flex items-center justify-center text-gray-300 text-xs">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $pgStart; $i <= $pgEnd; $i++): ?>
                        <a href="<?= $base.$i ?>"
                           class="w-8 h-8 flex items-center justify-center rounded-lg border text-xs font-semibold transition-all
                                  <?= $i === $page ? 'bg-gray-900 text-white border-gray-900 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pgEnd < $totalPages): ?>
                        <?php if ($pgEnd < $totalPages - 1): ?><span class="w-8 h-8 flex items-center justify-center text-gray-300 text-xs">…</span><?php endif; ?>
                        <a href="<?= $base.$totalPages ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-xs text-gray-600 hover:bg-gray-100 transition-all"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <!-- Next -->
                    <a href="<?= $page < $totalPages ? $base.($page+1) : '#' ?>"
                       class="w-8 h-8 flex items-center justify-center rounded-lg border text-xs transition-all
                              <?= $page < $totalPages ? 'border-gray-200 text-gray-500 hover:bg-gray-100' : 'border-gray-100 text-gray-300 pointer-events-none' ?>">
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
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Modify Sale <span id="editSaleIdDisplay" class="text-blue-600"></span></h2>
                <p class="text-xs text-gray-400 mt-0.5">Adjust quantities for correction</p>
            </div>
            <button onclick="closeEditModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all text-xs">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <!-- Modal Body -->
        <div id="editSaleContent" class="p-5 max-h-[55vh] overflow-y-auto space-y-3">
            <!-- Items loaded via AJAX -->
        </div>
        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">New Total</p>
                <p id="editSaleNewTotal" class="text-lg font-bold text-gray-900">MWK 0.00</p>
            </div>
            <button onclick="saveSaleChanges()" id="saveBtn"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all shadow-sm flex items-center gap-2">
                <i class="fas fa-check"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<script>
let currentEditingSaleId = null;

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
            data.items.forEach(item => {
                html += `
                <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100 rounded-xl sale-item-row"
                     data-id="${item.id}" data-price="${item.price_at_sale}">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">${item.product_name}</p>
                        <p class="text-xs text-gray-400 mt-0.5">MWK ${parseFloat(item.price_at_sale).toFixed(2)} / unit</p>
                    </div>
                    <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl p-1.5">
                        <button onclick="updateQty(${item.id}, -1)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-red-100 hover:text-red-500 transition-all text-xs">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" value="${item.quantity}" min="1"
                               class="w-10 text-center text-sm font-bold text-gray-800 bg-transparent border-none outline-none qty-input"
                               onchange="calculateEditTotal()" id="qty-${item.id}">
                        <button onclick="updateQty(${item.id}, 1)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-emerald-100 hover:text-emerald-500 transition-all text-xs">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
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
    input.value = Math.max(1, parseInt(input.value) + delta);
    calculateEditTotal();
}

function calculateEditTotal() {
    let total = 0;
    document.querySelectorAll('.sale-item-row').forEach(row => {
        total += parseFloat(row.dataset.price) * parseInt(row.querySelector('.qty-input').value);
    });
    document.getElementById('editSaleNewTotal').innerText = 'MWK ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function closeEditModal() {
    document.getElementById('editSaleModal').classList.add('hidden');
}

async function saveSaleChanges() {
    const btn  = document.getElementById('saveBtn');
    const orig = btn.innerHTML;
    const items = [];
    document.querySelectorAll('.sale-item-row').forEach(row => {
        items.push({ sale_item_id: parseInt(row.dataset.id), new_quantity: parseInt(row.querySelector('.qty-input').value) });
    });

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    try {
        const res  = await fetch('api/sales/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: currentEditingSaleId, items, csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
        });
        const data = await res.json();
        if (data.status === 'success') { location.reload(); }
        else { alert(data.message); btn.disabled = false; btn.innerHTML = orig; }
    } catch {
        alert('A network error occurred.'); btn.disabled = false; btn.innerHTML = orig;
    }
}

async function deleteSale(saleId) {
    if (!confirm('This will permanently void this sale and restore inventory. Are you sure?')) return;
    try {
        const res  = await fetch('api/sales/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: saleId, csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
        });
        const data = await res.json();
        if (data.status === 'success') { location.reload(); }
        else { alert(data.message); }
    } catch { alert('Error communicating with server.'); }
}

function printSale(sale) {
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head><title>Receipt #${String(sale.id).padStart(5,'0')}</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;900&display=swap');
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;padding:48px;max-width:480px;margin:auto}
            .brand{font-size:20px;font-weight:900;letter-spacing:-0.03em;text-transform:uppercase}
            .sub{font-size:11px;color:#9ca3af;font-weight:600;margin-top:4px}
            hr{border:none;border-top:1px solid #f3f4f6;margin:24px 0}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:24px 0}
            .lbl{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;display:block;margin-bottom:3px}
            .val{font-size:13px;font-weight:700;color:#111}
            table{width:100%;border-collapse:collapse;margin:24px 0}
            th{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:left}
            th:last-child,td:last-child{text-align:right}
            td{padding:12px 0;font-size:13px;font-weight:600;border-bottom:1px solid #f9fafb}
            .total td{font-weight:900;font-size:15px;border-bottom:none;padding-top:16px}
            .footer{text-align:center;color:#9ca3af;font-size:11px;font-weight:600;margin-top:40px}
        </style></head>
        <body>
            <p class="brand">Next-Level Pharmacy</p>
            <p class="sub">Transaction Receipt</p>
            <hr>
            <div class="grid">
                <div><span class="lbl">Invoice #</span><span class="val">#${String(sale.id).padStart(5,'0')}</span></div>
                <div style="text-align:right"><span class="lbl">Date</span><span class="val">${new Date(sale.created_at).toLocaleString()}</span></div>
                <div><span class="lbl">Served by</span><span class="val">${sale.sold_by || 'System'}</span></div>
                <div style="text-align:right"><span class="lbl">Payment</span><span class="val">${sale.payment_method.replace('_',' ').toUpperCase()}</span></div>
            </div>
            <hr>
            <table>
                <thead><tr><th>Item</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr><td>${sale.products_list}</td><td>MWK ${parseFloat(sale.total_amount).toLocaleString('en-US',{minimumFractionDigits:2})}</td></tr>
                    <tr class="total"><td style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.08em">Total</td><td>MWK ${parseFloat(sale.total_amount).toLocaleString('en-US',{minimumFractionDigits:2})}</td></tr>
                </tbody>
            </table>
            <div class="footer">
                <p>Thank you for choosing Next-Level Pharmacy</p>
                <p style="margin-top:6px">&copy; ${new Date().getFullYear()} Next-Level Systems</p>
            </div>
            <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
    w.document.close();
}
</script>
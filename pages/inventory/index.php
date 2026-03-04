<?php
// DON'T call session_start() - check-auth.php handles it
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../components/shared/table.php';
require_once __DIR__ . '/../../components/shared/form-input.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Product.php';

// Define base URL only if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/Next-Level');
}

// --- Data Fetching (only stats + first page, NOT all products) ---
try {
    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();

    $product_handler = new Product($db);

    // Only fetch first page of products (50 items)
    $paginatedResult = $product_handler->getAllWithSalesPaginated(null, 1, 50);
    $products = $paginatedResult['products'];
    $totalProducts_db = $paginatedResult['total'];
    $totalPages = $paginatedResult['total_pages'];

    $stats = $product_handler->getStats();
    $typeCounts = $product_handler->getCountsByType();

    $totalProducts = $stats['total_products'] ?? 0;
    $lowStockCount = $stats['low_stock_count'] ?? 0;

    // Get products about to expire (within 4 months) - this is a count query, fast
    $expiringStmt = $db->prepare("
        SELECT COUNT(*) as expiring_count
        FROM products
        WHERE has_expiry = 1 
        AND expiry_date IS NOT NULL
        AND expiry_date != '0000-00-00'
        AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 MONTH)
        AND is_deleted = 0
    ");
    $expiringStmt->execute();
    $expiringResult = $expiringStmt->fetch(PDO::FETCH_ASSOC);
    $expiringCount = $expiringResult['expiring_count'] ?? 0;

    // Get out of stock count
    $outOfStockStmt = $db->prepare("
        SELECT COUNT(*) as out_of_stock_count
        FROM products
        WHERE stock <= 0
        AND is_deleted = 0
    ");
    $outOfStockStmt->execute();
    $outOfStockResult = $outOfStockStmt->fetch(PDO::FETCH_ASSOC);
    $outOfStockCount = $outOfStockResult['out_of_stock_count'] ?? 0;

    // Categories for filters
    $category_sql = "SELECT id, name FROM categories ORDER BY name ASC";
    $categories = $db->query($category_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Inventory page error: ' . $e->getMessage());
    $products = [];
    $categories = [];
    $totalProducts = 0;
    $lowStockCount = 0;
    $expiringCount = 0;
    $outOfStockCount = 0;
    $typeCounts = [];
    $totalPages = 0;
}

// SearchBar
require_once __DIR__ . '/../../components/widgets/search-bar.php';
$searchBar = new SearchBar([
    'placeholder' => 'Search products by name, description or category...',
    'showShortcut' => false,
    'live' => false,
    'width' => 'full',
    'variant' => 'default'
]);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>

<div class="space-y-6" data-table-filter-container>
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div
                class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-box text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Product Inventory</h1>
                <p class="text-gray-500 text-sm">Manage medicines, cosmetics, skincare & perfumes</p>
            </div>
        </div>
        <div>
            <button data-open-add-product
                class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:shadow-lg flex items-center gap-2 transition-all hover:-translate-y-1">
                <i class="fas fa-plus"></i>
                Add New Product
            </button>
        </div>
    </div>

    <!-- Stats Cards in Row Format -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- All Products Card -->
        <div
            class="stat-card glassmorphism rounded-2xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-all bg-gradient-to-br from-blue-50/50 to-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-box text-blue-600"></i>
                </div>
                <span
                    class="text-[9px] font-bold text-blue-600 bg-blue-100 px-2 py-1 rounded-full uppercase tracking-wider">Total</span>
            </div>
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wide mb-1">All Products</h3>
            <p class="text-xl font-black text-gray-900"><?= $totalProducts ?></p>
            <p class="text-[10px] text-blue-600 font-medium mt-2 flex items-center gap-1">
                <i class="fas fa-boxes"></i> Complete inventory
            </p>
        </div>

        <!-- Low Stock Card -->
        <div
            class="stat-card glassmorphism rounded-2xl shadow-lg p-6 border-l-4 border-amber-500 hover:shadow-xl transition-all bg-gradient-to-br from-amber-50/50 to-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-amber-600"></i>
                </div>
                <span
                    class="text-[9px] font-bold text-amber-600 bg-amber-100 px-2 py-1 rounded-full uppercase tracking-wider">Alert</span>
            </div>
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wide mb-1">Low Stock</h3>
            <p class="text-xl font-black text-amber-700"><?= $lowStockCount ?></p>
            <p class="text-[10px] text-amber-600 font-medium mt-2 flex items-center gap-1">
                <i class="fas fa-arrow-down"></i> Below threshold
            </p>
        </div>

        <!-- Out of Stock Card -->
        <div
            class="stat-card glassmorphism rounded-2xl shadow-lg p-6 border-l-4 border-rose-500 hover:shadow-xl transition-all bg-gradient-to-br from-rose-50/50 to-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-rose-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ban text-rose-600"></i>
                </div>
                <span
                    class="text-[9px] font-bold text-rose-600 bg-rose-100 px-2 py-1 rounded-full uppercase tracking-wider">Critical</span>
            </div>
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wide mb-1">Out of Stock</h3>
            <p class="text-xl font-black text-rose-700"><?= $outOfStockCount ?></p>
            <p class="text-[10px] text-rose-600 font-medium mt-2 flex items-center gap-1">
                <i class="fas fa-times-circle"></i> No stock available
            </p>
        </div>

        <!-- Expiring Soon Card -->
        <div
            class="stat-card glassmorphism rounded-2xl shadow-lg p-6 border-l-4 border-purple-500 hover:shadow-xl transition-all bg-gradient-to-br from-purple-50/50 to-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-purple-600"></i>
                </div>
                <span
                    class="text-[9px] font-bold text-purple-600 bg-purple-100 px-2 py-1 rounded-full uppercase tracking-wider">Expiry</span>
            </div>
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wide mb-1">Expiring Soon</h3>
            <p class="text-xl font-black text-purple-700"><?= $expiringCount ?></p>
            <p class="text-[10px] text-purple-600 font-medium mt-2 flex items-center gap-1">
                <i class="fas fa-calendar-times"></i> Within 4 months
            </p>
        </div>
    </div>

    <!-- Product Type Filter -->
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-3">Filter by Type</label>
        <div class="flex items-center gap-2 flex-wrap">
            <button
                class="px-4 py-2 rounded-lg bg-blue-100 text-blue-700 text-sm font-medium hover:bg-blue-200 transition"
                data-filter="all">
                <i class="fas fa-th mr-2"></i>All Products
            </button>
            <button
                class="px-4 py-2 rounded-lg hover:bg-gray-100 text-sm font-medium transition text-gray-600 hover:text-blue-700"
                data-filter="low-stock">
                <i class="fas fa-exclamation-triangle mr-2"></i>Low Stock
            </button>
            <button
                class="px-4 py-2 rounded-lg hover:bg-gray-100 text-sm font-medium transition text-gray-600 hover:text-blue-700"
                data-filter="out-of-stock">
                <i class="fas fa-ban mr-2"></i>Out of Stock
            </button>
            <button
                class="px-4 py-2 rounded-lg hover:bg-gray-100 text-sm font-medium transition text-gray-600 hover:text-blue-700"
                data-filter="expiring-soon">
                <i class="fas fa-clock mr-2"></i>Expiring Soon
            </button>

            <div class="border-l border-gray-300 h-8 mx-2"></div>

            <?php foreach ($typeCounts as $type): ?>
                <?php
                $filterSlug = strtolower(str_replace([' ', '&'], ['_', ''], $type['type_name']));
                ?>
                <button
                    class="px-4 py-2 rounded-lg hover:bg-gray-100 text-sm font-medium transition text-gray-600 hover:text-blue-700"
                    data-filter="<?= htmlspecialchars($filterSlug) ?>">
                    <i
                        class="<?= htmlspecialchars($type['icon_class'] ?? 'fas fa-tag') ?> mr-2"></i><?= htmlspecialchars($type['type_name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100 min-h-[400px]">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-gray-100">
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Product
                        Name</th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Type</th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Category
                    </th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Stock</th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Price</th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Expiry Date
                    </th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Actions
                    </th>
                </tr>
            </thead>
            <tbody id="product-table-body" class="divide-y divide-gray-50">
                <!-- Product rows will be dynamically inserted here -->
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div id="pagination-controls" class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
        <div class="text-sm text-gray-500">
            Showing <span id="pagination-showing">0</span> of <span id="pagination-total">0</span> products
        </div>
        <div class="flex items-center gap-2">
            <button id="prev-page-btn" disabled
                class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                <i class="fas fa-chevron-left mr-1"></i> Previous
            </button>
            <div id="page-numbers" class="flex items-center gap-1">
                <!-- Page number buttons inserted by JS -->
            </div>
            <button id="next-page-btn" disabled
                class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                Next <i class="fas fa-chevron-right ml-1"></i>
            </button>
        </div>
    </div>
</div>
</div>

<!-- Modals -->
<?php
require_once __DIR__ . '/../../components/shared/add-product-modal.php';
require_once __DIR__ . '/../../components/shared/edit-product-modal.php';
require_once __DIR__ . '/../../components/shared/delete-confirmation-modal.php';
require_once __DIR__ . '/../../components/shared/stock-history-modal.php';
require_once __DIR__ . '/../../components/shared/receive-stock-modal.php';

$addModal = new AddProductModal($categories);
echo $addModal->render();

$editModal = new EditProductModal($categories);
echo $editModal->render();

$deleteModal = new DeleteConfirmationModal();
echo $deleteModal->render();

$historyModal = new StockHistoryModal();
echo $historyModal->render();

$receiveModal = new ReceiveStockModal();
echo $receiveModal->render();
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const BASE_URL = '<?= BASE_URL ?>';
        const tableBody = document.getElementById('product-table-body');
        const filterButtons = document.querySelectorAll('[data-filter]');

        const searchInput = document.querySelector('input[type="search"]') ||
            document.querySelector('input[placeholder*="Search"]') ||
            document.querySelector('[data-search-input]') ||
            document.querySelector('.search-input');

        // Initial data from PHP (first page only)
        let allProducts = <?= json_encode($products) ?>;
        const isAdmin = <?= json_encode($isAdmin) ?>;
        let currentFilterType = 'all';
        let currentSearchTerm = '';
        let currentPage = 1;
        let totalPages = <?= json_encode($totalPages) ?>;
        let totalProducts = <?= json_encode($totalProducts_db) ?>;
        const perPage = 50;

        // Debounce timer
        let searchDebounceTimer = null;

        const editModal = document.getElementById('edit-product-modal');
        const editForm = document.getElementById('edit-product-form');
        const deleteModal = document.getElementById('delete-confirmation-modal');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
        let productIdToDelete = null;

        const historyModal = document.getElementById('stock-history-modal');
        const historyContent = document.getElementById('history-content');
        const historyProductName = document.getElementById('history-product-name');

        // Pagination elements
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');
        const pageNumbers = document.getElementById('page-numbers');
        const paginationShowing = document.getElementById('pagination-showing');
        const paginationTotal = document.getElementById('pagination-total');

        const isExpiringSoon = (product) => {
            if (!product.has_expiry || !product.expiry_date || product.expiry_date === '0000-00-00') {
                return false;
            }
            const expiryDate = new Date(product.expiry_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const fourMonthsFromNow = new Date(today.getTime() + 120 * 24 * 60 * 60 * 1000);
            return expiryDate >= today && expiryDate <= fourMonthsFromNow;
        };

        // --- Fetch products from server (paginated) ---
        async function fetchProducts(page = 1, search = '') {
            try {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-10">
                            <i class="fas fa-spinner fa-spin text-2xl text-blue-400"></i>
                            <p class="text-gray-400 mt-2 text-sm">Loading products...</p>
                        </td>
                    </tr>`;

                let url = `${BASE_URL}/api/products-paginated.php?page=${page}&per_page=${perPage}`;
                if (search) url += `&search=${encodeURIComponent(search)}`;

                const response = await fetch(url, { credentials: 'include' });
                const result = await response.json();

                if (result.success) {
                    allProducts = result.data;
                    totalPages = result.pagination.total_pages;
                    totalProducts = result.pagination.total;
                    currentPage = result.pagination.page;

                    applyClientFilters();
                    updatePaginationUI();
                } else {
                    tableBody.innerHTML = `
                        <tr><td colspan="8" class="text-center py-10 text-rose-500">
                            <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                            <p>Failed to load products</p>
                        </td></tr>`;
                }
            } catch (err) {
                console.error('Fetch error:', err);
                tableBody.innerHTML = `
                    <tr><td colspan="8" class="text-center py-10 text-rose-500">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p>Network error loading products</p>
                    </td></tr>`;
            }
        }

        // --- Client-side filtering (on already-fetched page data) ---
        const applyClientFilters = () => {
            let productsToRender = [...allProducts];

            // Type filters (client-side on current page data)
            if (currentFilterType !== 'all' &&
                !['low-stock', 'out-of-stock', 'expiring-soon'].includes(currentFilterType)) {
                productsToRender = productsToRender.filter(p => {
                    if (!p.type_name) return false;
                    const typeName = p.type_name.toLowerCase().replace(/\s+/g, '_').replace(/&/g, '');
                    const filterType = currentFilterType.replace(/-/g, '_');
                    return typeName === filterType || typeName.includes(filterType);
                });
            }

            if (currentFilterType === 'low-stock') {
                productsToRender = productsToRender.filter(p => p.stock > 0 && p.stock <= p.low_stock_threshold);
            } else if (currentFilterType === 'out-of-stock') {
                productsToRender = productsToRender.filter(p => p.stock <= 0);
            } else if (currentFilterType === 'expiring-soon') {
                productsToRender = productsToRender.filter(p => isExpiringSoon(p));
            }

            renderTable(productsToRender);
        };

        // --- Update pagination UI ---
        function updatePaginationUI() {
            const start = Math.min((currentPage - 1) * perPage + 1, totalProducts);
            const end = Math.min(currentPage * perPage, totalProducts);
            paginationShowing.textContent = totalProducts > 0 ? `${start}-${end}` : '0';
            paginationTotal.textContent = totalProducts;

            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;

            // Build page number buttons (show max 5 around current)
            pageNumbers.innerHTML = '';
            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = i === currentPage
                    ? 'w-9 h-9 rounded-lg text-sm font-bold bg-blue-600 text-white'
                    : 'w-9 h-9 rounded-lg text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-200 transition';
                btn.addEventListener('click', () => goToPage(i));
                pageNumbers.appendChild(btn);
            }
        }

        function goToPage(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            currentPage = page;
            fetchProducts(page, currentSearchTerm);
        }

        prevBtn.addEventListener('click', () => goToPage(currentPage - 1));
        nextBtn.addEventListener('click', () => goToPage(currentPage + 1));

        const renderTable = (products) => {
            tableBody.innerHTML = '';

            if (!products || products.length === 0) {
                tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-10">
                        <div class="flex flex-col items-center gap-3">
                            <i class="fas fa-search text-gray-300 text-4xl"></i>
                            <p class="text-gray-500 font-medium">No products found</p>
                            ${currentSearchTerm ? `<p class="text-sm text-gray-400">Try adjusting your search or filters</p>` : ''}
                        </div>
                    </td>
                </tr>`;
                return;
            }

            // Use DocumentFragment for efficient DOM insertion
            const fragment = document.createDocumentFragment();

            products.forEach((product, index) => {
                let statusBadge = '';
                if (product.stock <= 0) {
                    statusBadge = `<span class="inline-flex items-center gap-1 px-3 py-1 bg-rose-50 text-rose-700 rounded-lg text-xs font-bold"><i class="fas fa-times-circle"></i>Out of Stock</span>`;
                } else if (product.stock <= product.low_stock_threshold) {
                    statusBadge = `<span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 rounded-lg text-xs font-bold"><i class="fas fa-exclamation-triangle"></i>Low Stock</span>`;
                } else if (isExpiringSoon(product)) {
                    statusBadge = `<span class="inline-flex items-center gap-1 px-3 py-1 bg-purple-50 text-purple-700 rounded-lg text-xs font-bold"><i class="fas fa-clock"></i>Expiring Soon</span>`;
                } else {
                    statusBadge = `<span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold"><i class="fas fa-check-circle"></i>In Stock</span>`;
                }

                const expiryDate = product.expiry_date && product.expiry_date !== '0000-00-00'
                    ? new Date(product.expiry_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                    : 'N/A';

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50/50 transition-all duration-300 group';
                tr.innerHTML = `
                    <td class="py-4 px-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-300">
                                <i class="${product.icon_class || 'fas fa-box'} text-blue-600 group-hover:text-white text-xs transition-colors duration-300"></i>
                            </div>
                            <div>
                                <span class="text-sm font-bold text-gray-900">${product.name}</span>
                                <p class="text-xs text-gray-500">${product.description || ''}</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-4"><span class="text-xs font-semibold text-blue-700 bg-blue-50 px-3 py-1 rounded-lg">${product.type_name || 'N/A'}</span></td>
                    <td class="py-4 px-4 text-sm">${product.category_name || 'N/A'}</td>
                    <td class="py-4 px-4"><span class="text-sm font-bold text-gray-900">${product.stock} units</span></td>
                    <td class="py-4 px-4 text-sm font-bold text-gray-900">MWK ${parseFloat(product.price).toFixed(2)}</td>
                    <td class="py-4 px-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar text-blue-600 text-xs"></i>
                            <span class="text-sm text-gray-900">${expiryDate}</span>
                        </div>
                    </td>
                    <td class="py-4 px-4">${statusBadge}</td>
                    <td class="py-4 px-4">
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button data-action="receive" data-id="${product.id}" class="text-emerald-600 hover:text-emerald-800 transition" title="Receive Stock"><i class="fas fa-truck-loading"></i></button>
                            <button data-action="edit" data-id="${product.id}" class="text-blue-600 hover:text-blue-800 transition" title="Edit"><i class="fas fa-edit"></i></button>
                            ${isAdmin ? `
                                <button data-action="delete" data-id="${product.id}" class="text-rose-600 hover:text-rose-800 transition" title="Delete"><i class="fas fa-trash"></i></button>
                            ` : ''}
                            <button data-action="history" data-id="${product.id}" class="text-gray-500 hover:text-gray-800 transition" title="Stock History"><i class="fas fa-history"></i></button>
                        </div>
                    </td>
                `;
                fragment.appendChild(tr);
            });

            tableBody.appendChild(fragment);
        };

        tableBody.addEventListener('click', function (e) {
            const button = e.target.closest('button[data-action]');
            if (!button) return;

            const action = button.dataset.action;
            const id = button.dataset.id;

            if (action === 'receive') {
                openReceiveModal(id);
            } else if (action === 'edit') {
                openEditModal(id);
            } else if (action === 'delete') {
                openDeleteModal(id);
            } else if (action === 'history') {
                openHistoryModal(id);
            }
        });

        function openReceiveModal(id) {
            const product = allProducts.find(p => p.id == id);
            if (!product) return;
            window.openReceiveModal(product);
        }

        function openEditModal(id) {
            const product = allProducts.find(p => p.id == id);
            if (!product) return;
            window.openEditModal(product);
        }

        function closeEditModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
            editForm.reset();
        }

        editForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitBtn = document.getElementById('edit-submit-btn');
            const errorDiv = document.getElementById('edit-modal-error');
            const successDiv = document.getElementById('edit-modal-success');

            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            const formData = new FormData(editForm);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(`${BASE_URL}/api/inventory/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    successDiv.classList.remove('hidden');
                    setTimeout(() => {
                        // Reload current page instead of full page reload
                        fetchProducts(currentPage, currentSearchTerm);
                        closeEditModal();
                    }, 800);
                } else {
                    throw new Error(result.message || 'Failed to update product');
                }
            } catch (err) {
                console.error('Update failed:', err);
                errorDiv.querySelector('span').textContent = err.message;
                errorDiv.classList.remove('hidden');
            } finally {
                if (!successDiv.classList.contains('hidden')) {
                    submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Updated';
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Save Changes</span>';
                }
            }
        });

        document.querySelectorAll('[data-modal-close]').forEach(btn =>
            btn.addEventListener('click', closeEditModal)
        );

        editModal.addEventListener('click', function (e) {
            if (e.target === editModal || e.target.closest('[data-modal-backdrop]')) closeEditModal();
        });

        function openDeleteModal(id) {
            productIdToDelete = id;
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');
            deleteModal.style.display = 'flex';
        }

        function closeDeleteModal() {
            productIdToDelete = null;
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
            deleteModal.style.display = 'none';
        }

        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) closeDeleteModal();
        });

        cancelDeleteBtn.addEventListener('click', closeDeleteModal);

        confirmDeleteBtn.addEventListener('click', () => {
            if (!productIdToDelete) return;

            fetch(`${BASE_URL}/api/inventory/delete.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id: productIdToDelete })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Product deleted successfully!');
                        closeDeleteModal();
                        // Reload current page data
                        fetchProducts(currentPage, currentSearchTerm);
                    } else {
                        alert(data.message || 'Failed to delete product');
                    }
                })
                .catch(err => {
                    console.error('Delete failed:', err);
                    alert('Failed to delete product: ' + err.message);
                });
        });

        function openHistoryModal(id) {
            historyModal.classList.remove('hidden');
            historyModal.classList.add('flex');
            historyModal.style.display = 'flex';
            historyProductName.textContent = 'Loading...';
            historyContent.innerHTML = '<div class="text-center py-10"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';

            document.body.style.overflow = 'hidden';

            fetch(`${BASE_URL}/api/inventory/get-history.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        historyProductName.textContent = data.product_name;
                        renderHistory(data.history);
                    } else {
                        historyContent.innerHTML = `<p class="text-rose-500">${data.message}</p>`;
                    }
                })
                .catch(err => {
                    console.error('History fetch error:', err);
                    historyContent.innerHTML = `<p class="text-rose-500">Failed to load history.</p>`;
                });
        }

        function closeHistoryModal() {
            historyModal.classList.add('hidden');
            historyModal.classList.remove('flex');
            historyModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.getElementById('close-history-modal')?.addEventListener('click', closeHistoryModal);
        historyModal.addEventListener('click', function (e) {
            if (e.target === historyModal) closeHistoryModal();
        });

        function renderHistory(historyItems) {
            if (historyItems.length === 0) {
                historyContent.innerHTML = '<p class="text-center text-gray-500 py-10">No stock history found for this product.</p>';
                return;
            }

            historyContent.innerHTML = historyItems.map(item => {
                const isPositive = item.quantity_change > 0;
                const typeInfo = {
                    'initial': { icon: 'fa-star', color: 'blue' },
                    'stock_in': { icon: 'fa-plus-circle', color: 'emerald' },
                    'sale': { icon: 'fa-shopping-cart', color: 'rose' },
                    'adjustment': { icon: 'fa-wrench', color: 'amber' }
                }[item.type] || { icon: 'fa-question-circle', color: 'gray' };

                const date = new Date(item.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });

                return `
                <div class="flex items-start gap-4 p-3 border-b border-gray-100 last:border-none">
                    <div class="w-10 h-10 bg-${typeInfo.color}-100 rounded-full flex-shrink-0 flex items-center justify-center">
                        <i class="fas ${typeInfo.icon} text-${typeInfo.color}-600"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-800">${item.type.charAt(0).toUpperCase() + item.type.slice(1).replace('_', ' ')}</p>
                            <span class="font-bold text-lg ${isPositive ? 'text-emerald-600' : 'text-rose-600'}">
                                ${isPositive ? '+' : ''}${item.quantity_change}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">${item.notes || 'No notes'}</p>
                    </div>
                    <div class="text-right text-xs text-gray-400 w-28 flex-shrink-0">
                        <p>${date}</p>
                        <p>by ${item.user_name || 'System'}</p>
                    </div>
                </div>`;
            }).join('');
        }

        // --- Filter Functionality ---
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                currentFilterType = this.getAttribute('data-filter');

                filterButtons.forEach(b => {
                    b.classList.remove('bg-blue-100', 'text-blue-700');
                    b.classList.add('hover:bg-gray-100', 'text-gray-600', 'hover:text-blue-700');
                });
                this.classList.add('bg-blue-100', 'text-blue-700');
                this.classList.remove('hover:bg-gray-100', 'text-gray-600', 'hover:text-blue-700');

                applyClientFilters();
            });
        });

        // --- Search with 300ms debounce ---
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    const term = e.target.value.trim();
                    currentSearchTerm = term;
                    // If search term changed, fetch from server (page 1)
                    if (term.length >= 2 || term.length === 0) {
                        currentPage = 1;
                        fetchProducts(1, term);
                    }
                }, 300);
            });

            searchInput.addEventListener('search', function (e) {
                // Handles clearing the search input (X button)
                clearTimeout(searchDebounceTimer);
                currentSearchTerm = '';
                currentPage = 1;
                fetchProducts(1, '');
            });
        }

        // Initial render on page load (use the PHP-provided first page)
        renderTable(allProducts);
        updatePaginationUI();
    });
</script>
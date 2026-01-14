<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\pos\index.php
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Today's Sales
$todaysSales = 0;
try {
    $todaysSalesQuery = "SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = CURDATE()";
    $todaysSales = $conn->query($todaysSalesQuery)->fetchColumn() ?? 0;
} catch (Exception $e) {
    error_log("POS Today's Sales Error: " . $e->getMessage());
}

// Fetch Product Types for filters
$types = [];
try {
    $types_sql = "SELECT name, icon_class FROM product_types ORDER BY name ASC";
    $stmt = $conn->query($types_sql);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Product Types Error: " . $e->getMessage());
}

// Fetch Categories for filters
$categories = [];
try {
    $category_sql = "SELECT c.id, c.name, COALESCE(pt.name, 'Other') as type_name
                     FROM categories c
                     LEFT JOIN product_types pt ON c.product_type_id = pt.id
                     ORDER BY COALESCE(pt.name, 'Other'), c.name";
    $stmt = $conn->query($category_sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Categories Error: " . $e->getMessage());
}
?>

<div class="space-y-6 h-full">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div
                class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-cash-register text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Point of Sale (POS)</h1>
                <p class="text-gray-500 text-sm">Sell medicines, cosmetics, skincare & perfumes</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Today's Sales</p>
            <p class="text-3xl font-bold text-blue-600">MWK <?= number_format($todaysSales, 2) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 flex-1 overflow-hidden">
        <div class="lg:col-span-3 space-y-4 flex flex-col h-full overflow-hidden">
            <!-- Filters Section -->
            <div class="glassmorphism rounded-2xl shadow-lg p-4 border border-gray-100 flex-shrink-0 space-y-4">

                <!-- Type Filters (Horizontal Scroll on Mobile, Wrap on Desktop) -->
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 px-1">Product Type</h3>
                    <div id="typeFiltersContainer"
                        class="flex overflow-x-auto lg:overflow-visible lg:flex-wrap lg:max-h-[110px] lg:overflow-y-auto lg:custom-scrollbar-visible pb-2 lg:pb-0 gap-2 custom-scrollbar-hide -mx-1 px-1 lg:mx-0 lg:px-0 lg:pr-2">
                        <button
                            class="pos-type-filter active flex-shrink-0 px-4 py-2 rounded-full border border-blue-100 bg-blue-100 text-blue-700 text-sm font-semibold transition hover:bg-blue-200 whitespace-nowrap"
                            data-type="all">
                            All Types
                        </button>
                        <?php foreach ($types as $type):
                            $icon = $type['icon_class'] ?: 'fa-tag';
                            ?>
                            <button
                                class="pos-type-filter flex-shrink-0 px-4 py-2 rounded-full border border-gray-200 bg-white text-gray-600 text-sm font-semibold transition hover:bg-gray-50 hover:border-gray-300 whitespace-nowrap"
                                data-type="<?= htmlspecialchars($type['name']) ?>">
                                <i class="fas <?= $icon ?> mr-1.5 opacity-70"></i><?= htmlspecialchars($type['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Filters (Horizontal Scroll on Mobile, Wrap on Desktop with Max Height) -->
                <div id="categoryFiltersSection">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 px-1">Category</h3>
                    <div id="categoryFiltersContainer"
                        class="flex overflow-x-auto lg:overflow-visible lg:flex-wrap lg:max-h-[200px] lg:overflow-y-auto lg:custom-scrollbar-visible pb-2 lg:pb-0 gap-2 custom-scrollbar-hide -mx-1 px-1 lg:mx-0 lg:px-0 lg:pr-2">
                        <button
                            class="pos-category-filter active flex-shrink-0 px-4 py-2 rounded-full border border-blue-100 bg-blue-100 text-blue-700 text-sm font-semibold transition hover:bg-blue-200 whitespace-nowrap"
                            data-category="all" data-type="all">
                            All Categories
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button
                                class="pos-category-filter flex-shrink-0 px-4 py-2 rounded-full border border-gray-200 bg-white text-gray-600 text-sm font-semibold transition hover:bg-gray-50 hover:border-gray-300 whitespace-nowrap hidden"
                                data-category="<?= htmlspecialchars($category['name']) ?>"
                                data-type="<?= htmlspecialchars($category['type_name']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div
                class="glassmorphism rounded-2xl shadow-lg p-5 border border-gray-100 flex-1 overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4" id="productsGrid">
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-3xl"></i>
                        <p class="mt-2 text-gray-500">Loading products...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="lg:col-span-2 flex flex-col h-full overflow-hidden space-y-4">
            <div
                class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100 flex-1 flex flex-col overflow-hidden">
                <div class="flex items-center justify-between mb-4 flex-shrink-0">
                    <h3 class="text-lg font-bold text-gray-900"><i
                            class="fas fa-shopping-cart mr-2 text-blue-600"></i>Current Sale</h3>
                    <span class="text-xs font-medium px-2 py-1 bg-blue-50 text-blue-600 rounded-lg"
                        id="cartCountBadge">0 Items</span>
                </div>

                <div id="cartItems" class="flex-1 overflow-y-auto custom-scrollbar space-y-3 mb-4 pr-1">
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <i class="fas fa-shopping-basket text-4xl mb-3 opacity-20"></i>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-xs">Select products to start selling</p>
                    </div>
                </div>

                <div class="space-y-3 border-t border-gray-100 pt-4 flex-shrink-0 bg-white">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal</span>
                        <span id="subtotal" class="font-bold text-gray-800">MWK 0.00</span>
                    </div>
                    <div class="flex justify-between items-end border-t border-gray-100 pt-3">
                        <span class="text-gray-900 font-bold text-lg">Total</span>
                        <span id="total" class="text-2xl font-black text-blue-600 leading-none">MWK 0.00</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 flex-shrink-0">
                <button id="clearCart"
                    class="px-4 py-3 bg-white border border-red-100 text-red-600 hover:bg-red-50 rounded-xl font-semibold transition shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                    <i class="fas fa-trash-alt"></i> Clear
                </button>
                <button id="checkoutBtn"
                    class="px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    disabled>
                    <span>Checkout</span> <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="checkoutModal"
    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md m-4 transform transition-all scale-95 opacity-0"
        id="modalContent">
        <!-- Content injected by JS -->
    </div>
</div>

<style>
    /* Hide scrollbar for Chrome, Safari and Opera */
    .custom-scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    /* Hide scrollbar for IE, Edge and Firefox */
    .custom-scrollbar-hide {
        -ms-overflow-style: none;
        /* IE and Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    /* Show scrollbar on Desktop (lg and up) */
    @media (min-width: 1024px) {
        .lg\:custom-scrollbar-visible::-webkit-scrollbar {
            display: block;
            width: 6px;
            height: 6px;
        }

        .lg\:custom-scrollbar-visible::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }

        .lg\:custom-scrollbar-visible::-webkit-scrollbar-track {
            background-color: transparent;
        }

        .lg\:custom-scrollbar-visible {
            -ms-overflow-style: auto;
            scrollbar-width: thin;
        }
    }

    .pos-type-filter.active {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    .pos-category-filter.active {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ===========================
        // 1. STATE & VARIABLES
        // ===========================
        let cart = JSON.parse(localStorage.getItem('posCart')) || [];
        let allProducts = [];
        const BASE_URL = '<?= BASE_URL ?>';
        let currentSearchTerm = '';
        let activeType = 'all';
        let activeCategory = 'all';

        // DOM Elements
        const productsGrid = document.getElementById('productsGrid');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const typeFiltersContainer = document.getElementById('typeFiltersContainer');
        const categoryFiltersContainer = document.getElementById('categoryFiltersContainer');
        const categoryFiltersSection = document.getElementById('categoryFiltersSection');
        const checkoutModal = document.getElementById('checkoutModal');
        const modalContent = document.getElementById('modalContent');
        const searchInput = document.querySelector('input[type="search"], .header-search-input, [placeholder*="Search"], .search-input') || document.querySelector('input[placeholder*="search"]');
        const cartCountBadge = document.getElementById('cartCountBadge');

        // ===========================
        // 2. CORE FUNCTIONS
        // ===========================

        // -- Modal Functions --
        window.openModal = function () {
            if (cart.length === 0) return alert('Cart is empty');

            const totalAmount = document.getElementById('total').textContent;

            modalContent.innerHTML = `
            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Complete Sale</h2>
                <p class="text-gray-500 mb-8">Total Amount: <span class="text-blue-600 font-bold text-xl">${totalAmount}</span></p>
                
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <button onclick="window.processCheckout('cash')" class="flex flex-col items-center justify-center p-6 rounded-xl bg-gray-50 border-2 border-gray-100 hover:border-blue-500 hover:bg-blue-50 transition group">
                        <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center mb-3 group-hover:scale-110 transition">
                            <i class="fas fa-money-bill-wave text-emerald-500 text-xl"></i>
                        </div>
                        <span class="font-bold text-gray-700 group-hover:text-blue-700">Cash</span>
                    </button>
                    <button onclick="window.processCheckout('mobile_money')" class="flex flex-col items-center justify-center p-6 rounded-xl bg-gray-50 border-2 border-gray-100 hover:border-blue-500 hover:bg-blue-50 transition group">
                        <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center mb-3 group-hover:scale-110 transition">
                            <i class="fas fa-mobile-alt text-blue-500 text-xl"></i>
                        </div>
                        <span class="font-bold text-gray-700 group-hover:text-blue-700">Mobile Money</span>
                    </button>
                </div>
                
                <button onclick="window.closeModal()" class="text-gray-400 hover:text-gray-600 font-medium text-sm">Cancel Transaction</button>
            </div>
        `;

            checkoutModal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        };

        window.closeModal = function () {
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => checkoutModal.classList.add('hidden'), 200);
        };

        checkoutModal.addEventListener('click', function (e) {
            if (e.target === checkoutModal) window.closeModal();
        });

        // -- Checkout Process --
        window.processCheckout = async function (paymentMethod) {
            modalContent.innerHTML = `
            <div class="text-center py-12">
                <div class="inline-block w-16 h-16 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin mb-4"></div>
                <h3 class="text-lg font-bold text-gray-900">Processing Payment...</h3>
                <p class="text-gray-500 text-sm">Please wait while we record the sale.</p>
            </div>`;

            try {
                const response = await fetch(`${BASE_URL}/pages/pos/checkout.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ cart, paymentMethod })
                });

                const textResult = await response.text();
                let result;
                try {
                    result = JSON.parse(textResult);
                } catch (e) {
                    console.error("Server Raw Response:", textResult);
                    throw new Error("Invalid server response.");
                }

                if (result.success) {
                    modalContent.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce">
                            <i class="fas fa-check text-emerald-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Sale Successful!</h3>
                        <div class="bg-gray-50 rounded-xl p-4 mb-6 text-left inline-block w-full">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-500 text-sm">Sale ID</span>
                                <span class="font-mono font-bold text-gray-900">#${result.sale_id}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">Amount Paid</span>
                                <span class="font-bold text-gray-900">MWK ${parseFloat(result.total_amount).toFixed(2)}</span>
                            </div>
                        </div>
                        <button onclick="window.location.reload()" class="w-full bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg transition">Start New Sale</button>
                    </div>`;
                    cart = [];
                    updateCart();
                } else {
                    throw new Error(result.message || 'Transaction failed');
                }
            } catch (error) {
                modalContent.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times text-red-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Transaction Failed</h3>
                    <p class="text-red-500 text-sm mb-6 max-w-xs mx-auto">${error.message}</p>
                    <div class="flex gap-3">
                        <button onclick="window.location.reload()" class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg font-semibold hover:bg-gray-200">Reload</button>
                        <button onclick="window.closeModal()" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700">Try Again</button>
                    </div>
                </div>`;
            }
        };

        // -- Cart Functions --
        function updateCart() {
            const cartDiv = document.getElementById('cartItems');
            const itemCount = cart.reduce((sum, item) => sum + item.qty, 0);

            cartCountBadge.textContent = `${itemCount} Item${itemCount !== 1 ? 's' : ''}`;

            if (cart.length === 0) {
                cartDiv.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-gray-400 py-12">
                    <i class="fas fa-shopping-basket text-5xl mb-3 opacity-20"></i>
                    <p class="text-sm font-medium">Your cart is empty</p>
                    <p class="text-xs">Tap on products to add them</p>
                </div>`;
                checkoutBtn.disabled = true;
                document.getElementById('subtotal').textContent = 'MWK 0.00';
                document.getElementById('total').textContent = 'MWK 0.00';
            } else {
                checkoutBtn.disabled = false;
                cartDiv.innerHTML = cart.map((item, idx) => `
                <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-blue-200 transition group">
                    <div class="flex-1 min-w-0 mr-3">
                        <p class="text-sm font-bold text-gray-800 truncate">${item.name}</p>
                        <p class="text-xs text-gray-500">MWK ${parseFloat(item.price).toFixed(2)}</p>
                    </div>
                    <div class="flex items-center bg-gray-50 rounded-lg p-1">
                        <button onclick="window.decreaseQty(${idx})" class="w-7 h-7 flex items-center justify-center bg-white rounded-md shadow-sm text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition font-bold disabled:opacity-50">−</button>
                        <span class="w-8 text-center font-bold text-sm text-gray-800">${item.qty}</span>
                        <button onclick="window.increaseQty(${idx})" class="w-7 h-7 flex items-center justify-center bg-white rounded-md shadow-sm text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition font-bold">+</button>
                    </div>
                    <button onclick="window.removeItem(${idx})" class="ml-3 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"><i class="fas fa-trash-alt text-sm"></i></button>
                </div>
            `).join('');

                const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                document.getElementById('subtotal').textContent = `MWK ${total.toFixed(2)}`;
                document.getElementById('total').textContent = `MWK ${total.toFixed(2)}`;
            }

            localStorage.setItem('posCart', JSON.stringify(cart));
        }

        window.increaseQty = idx => { if (cart[idx]) { if (cart[idx].qty < cart[idx].stock) cart[idx].qty++; else alert('Max stock reached'); updateCart(); } };
        window.decreaseQty = idx => { if (cart[idx]) { if (cart[idx].qty > 1) cart[idx].qty--; else window.removeItem(idx); updateCart(); } };
        window.removeItem = idx => { cart.splice(idx, 1); updateCart(); };

        // -- Product Rendering & Filtering --
        function updateCategoryVisibility() {
            const categoryButtons = document.querySelectorAll('.pos-category-filter');
            let hasVisibleCategories = false;

            categoryButtons.forEach(btn => {
                if (activeType === 'all' || btn.dataset.type === activeType || btn.dataset.category === 'all') {
                    btn.classList.remove('hidden');
                    if (btn.dataset.category !== 'all') hasVisibleCategories = true;
                } else {
                    btn.classList.add('hidden');
                }
            });

            // Hide category section if no categories for this type (except 'All')
            // OR if activeType matches, we want to show relevant categories.
            // We actually want to show 'All Categories' button always if we are filtering.
            // If type is ALL, show ALL categories.
        }

        function renderProducts(productsToRender) {
            if (productsToRender.length === 0) {
                productsGrid.innerHTML = `
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-search text-3xl opacity-50"></i>
                    </div>
                    <p class="font-medium">No products found</p>
                    <p class="text-sm">Try adjusting your filters</p>
                </div>`;
                return;
            }

            productsGrid.innerHTML = productsToRender.map(product => `
            <div class="pos-product-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition cursor-pointer group flex flex-col h-full relative overflow-hidden"
                 data-product-id="${product.id}">
                
                <div class="absolute top-3 right-3 z-10">
                    <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider ${product.stock <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                }">
                        ${product.stock} left
                    </span>
                </div>

                <div class="w-full h-32 bg-gray-50 rounded-xl flex items-center justify-center mb-4 group-hover:bg-blue-50 transition-colors">
                    <i class="${product.icon || 'fas fa-box'} text-4xl text-gray-300 group-hover:text-blue-500 transition-colors duration-300 transform group-hover:scale-110"></i>
                </div>
                
                <div class="flex-1">
                    <p class="text-xs text-gray-500 mb-1 uppercase tracking-wide truncate">${product.category || 'General'}</p>
                    <h4 class="font-bold text-gray-900 leading-tight mb-2 line-clamp-2 min-h-[2.5em]">${product.name}</h4>
                </div>
                
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-lg font-bold text-blue-600">MWK ${parseFloat(product.price).toFixed(2)}</span>
                    <button class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition shadow-sm">
                        <i class="fas fa-plus text-sm"></i>
                    </button>
                </div>
            </div>
        `).join('');
        }

        function filterAndRenderProducts() {
            const filtered = allProducts.filter(p => {
                const matchesType = activeType === 'all' || p.type === activeType;
                const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
                let matchesSearch = true;
                if (currentSearchTerm) {
                    const searchable = [p.name, p.description, p.category, p.type].join(' ').toLowerCase();
                    matchesSearch = searchable.includes(currentSearchTerm);
                }
                return matchesType && matchesCategory && matchesSearch;
            });
            renderProducts(filtered);
        }

        async function fetchProducts() {
            try {
                const response = await fetch(`${BASE_URL}/pages/pos/products.php`, { credentials: 'include' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                allProducts = await response.json();
                // Normalize
                allProducts = allProducts.map(p => ({
                    ...p,
                    category: p.category || '',
                    type: p.type || ''
                }));

                // Initial Match
                filterAndRenderProducts();
            } catch (error) {
                console.error('Error fetching products:', error);
                productsGrid.innerHTML = `<div class="col-span-full py-8 text-center text-red-500">Failed to load products</div>`;
            }
        }

        // ===========================
        // 3. LISTENERS
        // ===========================

        // Type Filters
        typeFiltersContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.pos-type-filter');
            if (!btn) return;

            // UI Update
            document.querySelectorAll('.pos-type-filter').forEach(b => {
                b.classList.remove('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');
                b.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
            });
            btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
            btn.classList.add('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');

            // Logic Update
            activeType = btn.dataset.type;

            // Reset Category to All when changing Type
            activeCategory = 'all';
            document.querySelectorAll('.pos-category-filter').forEach(b => {
                b.classList.remove('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');
                if (b.dataset.category === 'all') {
                    b.classList.add('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');
                } else {
                    b.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
                }
            });

            updateCategoryVisibility();
            filterAndRenderProducts();
        });

        // Category Filters
        categoryFiltersContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.pos-category-filter');
            if (!btn) return;

            // UI Update
            document.querySelectorAll('.pos-category-filter').forEach(b => {
                b.classList.remove('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');
                b.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
            });
            btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
            btn.classList.add('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');

            activeCategory = btn.dataset.category;
            filterAndRenderProducts();
        });

        productsGrid.addEventListener('click', (e) => {
            const card = e.target.closest('.pos-product-card');
            if (!card) return;

            const productId = parseInt(card.dataset.productId);
            const product = allProducts.find(p => p.id === productId);

            if (product.stock <= 0) return; // Alert handled in UI by showing low stock/out of stock visually maybe? Or just strict check.
            // Actually, let's keep the alert or sound effects in a polished app.
            // For now, simple check.

            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty >= product.stock) {
                    // Shake card or simple alert
                    alert('Max stock available reached');
                    return;
                }
                existing.qty++;
            } else {
                cart.push({ ...product, qty: 1 });
            }
            updateCart();
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentSearchTerm = this.value.toLowerCase().trim();
                filterAndRenderProducts();
            });
        }

        document.getElementById('clearCart').addEventListener('click', () => {
            if (cart.length && confirm('Clear cart?')) {
                cart = [];
                updateCart();
            }
        });

        document.getElementById('checkoutBtn').addEventListener('click', window.openModal);

        // Initial Fetch
        fetchProducts();
        updateCart();
        updateCategoryVisibility();
    });
</script>
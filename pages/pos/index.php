<?php
// filepath: c:\xampp5\htdocs\Next-Level\pages\pos\index.php
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch Pharmacy Settings
$pharmacyInfo = [
    'name' => 'Next-Level Pharmacy',
    'address' => 'Not Set',
    'phone' => 'Not Set'
];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM pharmacy_settings WHERE setting_key IN ('pharmacy_name', 'pharmacy_address', 'pharmacy_phone')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($settings) {
        $pharmacyInfo['name'] = $settings['pharmacy_name'] ?? $pharmacyInfo['name'];
        $pharmacyInfo['address'] = $settings['pharmacy_address'] ?? $pharmacyInfo['address'];
        $pharmacyInfo['phone'] = $settings['pharmacy_phone'] ?? $pharmacyInfo['phone'];
    }
} catch (Exception $e) {
    error_log("Pharmacy Settings Error: " . $e->getMessage());
}

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
                     ORDER BY c.name ASC";
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

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 flex-1">
        <!-- Products Side (3 cols) -->
        <div class="lg:col-span-3 space-y-4 flex flex-col">
            <!-- Filters Section -->
            <div class="glassmorphism rounded-2xl shadow-lg p-4 border border-gray-100 flex-shrink-0 space-y-4">

                <!-- Type Filters -->
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

                <!-- Category Filters -->
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
            <div class="glassmorphism rounded-2xl shadow-lg p-5 border border-gray-100 flex-1">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4" id="productsGrid">
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-3xl"></i>
                        <p class="mt-2 text-gray-500">Loading products...</p>
                    </div>
                </div>

                <!-- POS Pagination -->
                <div id="pos-pagination" class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100">
                    <div class="text-sm text-gray-500">
                        Showing <span id="pos-page-showing">0</span> of <span id="pos-page-total">0</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="pos-prev-btn" disabled
                            class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fas fa-chevron-left mr-1"></i> Prev
                        </button>
                        <div id="pos-page-numbers" class="flex items-center gap-1"></div>
                        <button id="pos-next-btn" disabled
                            class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section (2 cols) — STICKY so it stays visible while scrolling -->
        <div class="lg:col-span-2 flex flex-col space-y-4 lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-6rem)]">
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

<!-- Mobile Floating Cart Summary — visible only on small screens when cart has items -->
<div id="mobileCartSummary"
    class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t-2 border-blue-500 shadow-2xl p-4 z-40 transform translate-y-full transition-transform duration-300"
    style="display: none;">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-shopping-cart text-blue-600"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900" id="mobileCartCount">0 items</p>
                <p class="text-lg font-black text-blue-600" id="mobileCartTotal">MWK 0.00</p>
            </div>
        </div>
        <button id="mobileCheckoutBtn"
            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-bold shadow-lg flex items-center gap-2">
            Checkout <i class="fas fa-arrow-right"></i>
        </button>
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
    .custom-scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    .custom-scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

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

    .receipt-paper {
        background: white;
        position: relative;
        padding: 2rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .receipt-paper::before {
        content: "";
        position: absolute;
        bottom: -10px;
        left: 0;
        right: 0;
        height: 10px;
        background: radial-gradient(circle, transparent, transparent 50%, #fff 50%, #fff) 0 0 / 20px 20px repeat-x;
    }

    .receipt-dashed-line {
        border-top: 1px dashed #e2e8f0;
        margin: 1rem 0;
    }

    .receipt-items-container {
        max-height: 300px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .receipt-items-container::-webkit-scrollbar {
        width: 4px;
    }

    .receipt-items-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ===========================
        // 1. STATE & VARIABLES
        // ===========================
        let cart = JSON.parse(localStorage.getItem('posCart')) || [];
        let allProducts = [];
        let filteredProducts = [];
        const BASE_URL = '<?= BASE_URL ?>';
        const PHARMACY_INFO = <?= json_encode($pharmacyInfo) ?>;
        let currentSearchTerm = '';
        let activeType = 'all';
        let activeCategory = 'all';
        let searchDebounceTimer = null;

        // Pagination state
        const POS_PER_PAGE = 30;
        let posCurrentPage = 1;
        let posTotalPages = 1;

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

        // Pagination DOM
        const posPrevBtn = document.getElementById('pos-prev-btn');
        const posNextBtn = document.getElementById('pos-next-btn');
        const posPageNumbers = document.getElementById('pos-page-numbers');
        const posPageShowing = document.getElementById('pos-page-showing');
        const posPageTotal = document.getElementById('pos-page-total');

        // Mobile cart DOM
        const mobileCartSummary = document.getElementById('mobileCartSummary');
        const mobileCheckoutBtn = document.getElementById('mobileCheckoutBtn');

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
                    const now = new Date();
                    const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    const timeStr = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                    modalContent.innerHTML = `
                    <div class="receipt-paper rounded-t-2xl text-left font-sans">
                        <div class="text-center mb-6">
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tighter">${PHARMACY_INFO.name}</h2>
                            <p class="text-xs text-gray-500 mt-1">${PHARMACY_INFO.address}</p>
                            <p class="text-xs text-gray-500">${PHARMACY_INFO.phone}</p>
                        </div>

                        <div class="receipt-dashed-line"></div>

                        <div class="flex justify-between items-start mb-4 text-[10px] text-gray-500 font-mono uppercase">
                            <div>
                                <p>Receipt: <span class="text-gray-900 font-bold">#${result.sale_id}</span></p>
                                <p>Date: <span class="text-gray-900 font-bold">${dateStr}</span></p>
                            </div>
                            <div class="text-right">
                                <p>Time: <span class="text-gray-900 font-bold">${timeStr}</span></p>
                                <p>Method: <span class="text-gray-900 font-bold">${paymentMethod.replace('_', ' ').toUpperCase()}</span></p>
                            </div>
                        </div>

                        <div class="receipt-dashed-line"></div>

                        <div class="receipt-items-container custom-scrollbar">
                            <table class="w-full text-xs mb-4">
                                <thead class="text-gray-400 uppercase font-bold text-[9px] border-b border-gray-50">
                                    <tr>
                                        <th class="text-left py-2 font-medium">Item</th>
                                        <th class="text-center py-2 font-medium">Qty</th>
                                        <th class="text-right py-2 font-medium">Price</th>
                                        <th class="text-right py-2 font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    ${cart.map(item => `
                                        <tr>
                                            <td class="py-2.5 font-medium text-gray-800 pr-2">${item.name}</td>
                                            <td class="py-2.5 text-center text-gray-600">${item.qty}</td>
                                            <td class="py-2.5 text-right text-gray-600">${parseFloat(item.price).toFixed(2)}</td>
                                            <td class="py-2.5 text-right font-bold text-gray-900">${(item.price * item.qty).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>

                        <div class="receipt-dashed-line"></div>

                        <div class="space-y-2 mb-6">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-mono text-gray-900">MWK ${parseFloat(result.total_amount).toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                <span class="text-sm font-black text-gray-900 uppercase">Grand Total</span>
                                <span class="text-xl font-black text-blue-600 font-mono">MWK ${parseFloat(result.total_amount).toFixed(2)}</span>
                            </div>
                        </div>

                        <div class="text-center text-[10px] text-gray-400 italic mb-8">
                            <p>Thank you for choosing ${PHARMACY_INFO.name}</p>
                            <p>Please keep this receipt for your records</p>
                        </div>

                        <button onclick="window.location.reload()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-xl font-bold transition shadow-lg flex items-center justify-center gap-2 group">
                            <span>Start New Sale</span>
                            <i class="fas fa-plus text-sm group-hover:rotate-90 transition-transform"></i>
                        </button>
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

                // Hide mobile cart bar
                if (mobileCartSummary) {
                    mobileCartSummary.style.display = 'none';
                    mobileCartSummary.classList.add('translate-y-full');
                }
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

                // Show mobile cart bar
                if (mobileCartSummary) {
                    mobileCartSummary.style.display = 'block';
                    setTimeout(() => mobileCartSummary.classList.remove('translate-y-full'), 10);
                    document.getElementById('mobileCartCount').textContent = `${itemCount} item${itemCount !== 1 ? 's' : ''}`;
                    document.getElementById('mobileCartTotal').textContent = `MWK ${total.toFixed(2)}`;
                }
            }

            localStorage.setItem('posCart', JSON.stringify(cart));
        }

        window.increaseQty = idx => { if (cart[idx]) { if (cart[idx].qty < cart[idx].stock) cart[idx].qty++; else alert('Max stock reached'); updateCart(); } };
        window.decreaseQty = idx => { if (cart[idx]) { if (cart[idx].qty > 1) cart[idx].qty--; else window.removeItem(idx); updateCart(); } };
        window.removeItem = idx => { cart.splice(idx, 1); updateCart(); };

        // -- Product Rendering & Filtering with Pagination --
        function updateCategoryVisibility() {
            const categoryButtons = document.querySelectorAll('.pos-category-filter');
            categoryButtons.forEach(btn => {
                if (activeType === 'all' || btn.dataset.type === activeType || btn.dataset.category === 'all') {
                    btn.classList.remove('hidden');
                } else {
                    btn.classList.add('hidden');
                }
            });
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

            const fragment = document.createDocumentFragment();
            productsToRender.forEach(product => {
                const div = document.createElement('div');
                div.className = 'pos-product-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition cursor-pointer group flex flex-col h-full relative overflow-hidden';
                div.dataset.productId = product.id;
                div.innerHTML = `
                <div class="absolute top-3 right-3 z-10">
                    <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider ${product.stock <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}">
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
                </div>`;
                fragment.appendChild(div);
            });
            productsGrid.innerHTML = '';
            productsGrid.appendChild(fragment);
        }

        function filterAndRenderProducts() {
            filteredProducts = allProducts.filter(p => {
                const matchesType = activeType === 'all' || p.type === activeType;
                const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
                let matchesSearch = true;
                if (currentSearchTerm) {
                    const searchable = [p.name, p.description, p.category, p.type].join(' ').toLowerCase();
                    matchesSearch = searchable.includes(currentSearchTerm);
                }
                return matchesType && matchesCategory && matchesSearch;
            });

            // Reset to page 1 on filter change
            posCurrentPage = 1;
            posTotalPages = Math.max(1, Math.ceil(filteredProducts.length / POS_PER_PAGE));

            renderCurrentPage();
            updatePosPagination();
        }

        function renderCurrentPage() {
            const start = (posCurrentPage - 1) * POS_PER_PAGE;
            const end = start + POS_PER_PAGE;
            const pageProducts = filteredProducts.slice(start, end);
            renderProducts(pageProducts);
        }

        function updatePosPagination() {
            const start = Math.min((posCurrentPage - 1) * POS_PER_PAGE + 1, filteredProducts.length);
            const end = Math.min(posCurrentPage * POS_PER_PAGE, filteredProducts.length);
            posPageShowing.textContent = filteredProducts.length > 0 ? `${start}-${end}` : '0';
            posPageTotal.textContent = filteredProducts.length;

            posPrevBtn.disabled = posCurrentPage <= 1;
            posNextBtn.disabled = posCurrentPage >= posTotalPages;

            // Build page buttons
            posPageNumbers.innerHTML = '';
            const maxVisible = 5;
            let startPage = Math.max(1, posCurrentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(posTotalPages, startPage + maxVisible - 1);
            if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

            for (let i = startPage; i <= endPage; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = i === posCurrentPage
                    ? 'w-9 h-9 rounded-lg text-sm font-bold bg-blue-600 text-white'
                    : 'w-9 h-9 rounded-lg text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-200 transition';
                btn.addEventListener('click', () => {
                    posCurrentPage = i;
                    renderCurrentPage();
                    updatePosPagination();
                });
                posPageNumbers.appendChild(btn);
            }
        }

        posPrevBtn.addEventListener('click', () => {
            if (posCurrentPage > 1) {
                posCurrentPage--;
                renderCurrentPage();
                updatePosPagination();
            }
        });

        posNextBtn.addEventListener('click', () => {
            if (posCurrentPage < posTotalPages) {
                posCurrentPage++;
                renderCurrentPage();
                updatePosPagination();
            }
        });

        async function fetchProducts() {
            try {
                const response = await fetch(`${BASE_URL}/pages/pos/products.php`, { credentials: 'include' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                allProducts = await response.json();
                allProducts = allProducts.map(p => ({
                    ...p,
                    category: p.category || '',
                    type: p.type || ''
                }));

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

            document.querySelectorAll('.pos-type-filter').forEach(b => {
                b.classList.remove('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');
                b.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
            });
            btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
            btn.classList.add('active', 'bg-blue-100', 'text-blue-700', 'border-blue-100');

            activeType = btn.dataset.type;
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

            if (product.stock <= 0) return;

            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty >= product.stock) {
                    alert('Max stock available reached');
                    return;
                }
                existing.qty++;
            } else {
                cart.push({ ...product, qty: 1 });
            }
            updateCart();
        });

        // Search with debounce
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    currentSearchTerm = this.value.toLowerCase().trim();
                    filterAndRenderProducts();
                }, 300);
            });
        }

        document.getElementById('clearCart').addEventListener('click', () => {
            if (cart.length && confirm('Clear cart?')) {
                cart = [];
                updateCart();
            }
        });

        document.getElementById('checkoutBtn').addEventListener('click', window.openModal);

        // Mobile checkout button
        if (mobileCheckoutBtn) {
            mobileCheckoutBtn.addEventListener('click', window.openModal);
        }

        // Initial Fetch
        fetchProducts();
        updateCart();
        updateCategoryVisibility();
    });
</script>
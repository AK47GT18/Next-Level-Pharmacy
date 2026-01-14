<?php
// ✅ Don't call session_start() - check-auth.php handles it
require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../components/shared/form-input.php';
require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Product.php';

// Get categories from database for form
try {
    $db_instance = Database::getInstance();
    $db = $db_instance->getConnection();
    
    $stmt = $db->prepare("SELECT c.id, c.name, pt.name as type_name FROM categories c LEFT JOIN product_types pt ON c.product_type_id = pt.id ORDER BY pt.name, c.name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Add product error: ' . $e->getMessage());
    $categories = [];
}
?>

<div class="space-y-6 animate-slide-in">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
            <i class="fas fa-plus text-white text-2xl"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add New Product</h1>
            <p class="text-gray-500 text-sm">Add medicines, cosmetics, skincare, or perfumes to inventory</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar: Product Type Selection -->
        <div class="lg:col-span-1">
            <div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100 sticky top-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Product Type</h3>
                <div class="space-y-3">
                    <button class="product-type-btn w-full text-left p-4 rounded-xl border-2 border-gray-100 hover:border-blue-500 hover:bg-blue-50 transition group active" data-type="Medicine">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                                <i class="fas fa-pills text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Medicine</p>
                                <p class="text-xs text-gray-500">Drugs & Supplements</p>
                            </div>
                        </div>
                    </button>

                    <button class="product-type-btn w-full text-left p-4 rounded-xl border-2 border-gray-100 hover:border-pink-500 hover:bg-pink-50 transition group" data-type="Cosmetic">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                                <i class="fas fa-sparkles text-pink-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Cosmetic</p>
                                <p class="text-xs text-gray-500">Makeup & Beauty</p>
                            </div>
                        </div>
                    </button>

                    <button class="product-type-btn w-full text-left p-4 rounded-xl border-2 border-gray-100 hover:border-green-500 hover:bg-green-50 transition group" data-type="Skincare">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                                <i class="fas fa-leaf text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Skincare</p>
                                <p class="text-xs text-gray-500">Face & Body Care</p>
                            </div>
                        </div>
                    </button>

                    <button class="product-type-btn w-full text-left p-4 rounded-xl border-2 border-gray-100 hover:border-purple-500 hover:bg-purple-50 transition group" data-type="Perfume">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                                <i class="fas fa-spray-can text-purple-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Perfume</p>
                                <p class="text-xs text-gray-500">Fragrances & Scents</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="lg:col-span-2">
            <form id="addProductForm" class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100 space-y-6">
                <!-- Basic Info -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="text" name="name" placeholder="Product Name *" required class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                        <input type="text" name="sku" placeholder="SKU/Code" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                            <select id="categorySelect" name="category_id" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                                <option value="">Select Category</option>
                                <?php
                                $currentType = '';
                                foreach ($categories as $cat):
                                    if ($cat['type_name'] !== $currentType):
                                        if ($currentType !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($cat['type_name']) . '">';
                                        $currentType = $cat['type_name'];
                                    endif;
                                ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <input type="text" name="supplier" placeholder="Supplier name" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                </div>

                <!-- Pricing & Stock -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Pricing & Stock</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="number" name="cost_price" placeholder="Cost Price (MWK)" step="0.01" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                        <input type="number" name="price" placeholder="Selling Price (MWK) *" required step="0.01" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                        <input type="number" name="stock" placeholder="Initial Stock *" required min="0" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <input type="number" name="low_stock_threshold" placeholder="Reorder Level" min="0" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                        <input type="date" name="expiry_date" placeholder="Expiry Date" class="px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Additional Details</h3>
                    <textarea name="description" placeholder="Product description..." rows="4" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none"></textarea>
                </div>

                <!-- Error/Success Messages -->
                <div id="errorMessage" class="hidden bg-red-50 border-2 border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"></span>
                </div>

                <div id="successMessage" class="hidden bg-green-50 border-2 border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span id="successText">Product added successfully!</span>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <a href="/Next-Level/rxpms/pages/inventory/index.php" class="px-6 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-save mr-2"></i>Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const typeButtons = document.querySelectorAll('.product-type-btn');

    typeButtons.forEach(btn => {
        btn.addEventListener('click', function(){
            // Update active state
            typeButtons.forEach(b => {
                b.classList.remove('active', 'border-blue-500', 'bg-blue-50');
                b.classList.add('border-gray-100');
            });
            this.classList.add('active', 'border-blue-500', 'bg-blue-50');
            this.classList.remove('border-gray-100');
        });
    });

    // Form submission
    document.getElementById('addProductForm').addEventListener('submit', async function(e){
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const successMessage = document.getElementById('successMessage');

        // Validation
        if (!data.name || !data.price || data.stock === '') {
            errorMessage.classList.remove('hidden');
            errorText.textContent = 'Please fill in all required fields';
            return;
        }

        try {
            const response = await fetch('/Next-Level/rxpms/api/inventory/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                successMessage.classList.remove('hidden');
                setTimeout(() => {
                    window.location.href = '/Next-Level/rxpms/pages/inventory/index.php';
                }, 1500);
            } else {
                errorMessage.classList.remove('hidden');
                errorText.textContent = result.message || 'Failed to add product';
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage.classList.remove('hidden');
            errorText.textContent = 'Failed to add product. Please try again.';
        }
    });
});
</script>
<?php
// filepath: components/shared/add-product-modal.php
class AddProductModal
{
    private string $id;
    private array $categories;

    public function __construct(array $categories = [], string $id = 'addProductModal')
    {
        $this->id = $id;
        $this->categories = $categories ?? [];
    }

    public function render(): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms';

        // Build category options
        $categoryOptions = '';
        if (!empty($this->categories)) {
            $currentType = '';
            foreach ($this->categories as $cat) {
                if (($cat['type_name'] ?? '') !== $currentType) {
                    if ($currentType !== '')
                        $categoryOptions .= '</optgroup>';
                    $categoryOptions .= '<optgroup label="' . htmlspecialchars($cat['type_name'] ?? 'Other') . '">';
                    $currentType = $cat['type_name'] ?? '';
                }
                $categoryOptions .= '<option value="' . intval($cat['id']) . '">' . htmlspecialchars($cat['name'] ?? '') . '</option>';
            }
            if ($currentType !== '')
                $categoryOptions .= '</optgroup>';
        }

        return <<<HTML
<div id="{$this->id}" class="fixed inset-0 z-50 hidden overflow-y-auto items-start md:items-center justify-center p-4 transition-all duration-300">
    
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity" data-modal-backdrop></div>
    
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col min-h-0 max-h-[90vh] animate-modal-scale z-10 overflow-hidden ring-1 ring-gray-200" style="max-height: calc(100vh - 2rem);">
        
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 flex-shrink-0 bg-white rounded-t-xl">
            <div>
                <h3 class="text-xl font-bold text-gray-800">Add New Product</h3>
                <p class="text-sm text-gray-500 mt-0.5">Enter details to update inventory</p>
            </div>
            <button data-modal-close class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto custom-scrollbar p-5 bg-gray-50/50">
            <form id="addProductForm" class="space-y-6">
                
                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Product Name <span class="text-red-500">*</span></label>
                    <input
                        name="name"
                        required
                        placeholder="e.g. Paracetamol 500mg"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition placeholder-gray-400"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Category <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select
                                name="category_id"
                                required
                                class="w-full pl-4 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition appearance-none bg-white text-gray-700"
                            >
                                <option value="">Select Category...</option>
                                {$categoryOptions}
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Stock Quantity <span class="text-red-500">*</span></label>
                        <input
                            name="stock"
                            type="number"
                            min="0"
                            required
                            placeholder="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Selling Price (MWK) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            
                            <input
                                name="price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                                class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition font-medium"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Cost Price (MWK)</label>
                        <div class="relative">
                           
                            <input
                                name="cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Low Stock Alert</label>
                        <input
                            name="low_stock_threshold"
                            type="number"
                            min="0"
                            placeholder="5"
                            value="5"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Has Expiry Date?</label>
                        <div class="relative">
                            <select
                                name="has_expiry"
                                id="hasExpiry"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition appearance-none bg-white"
                            >
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                
                            </div>
                        </div>
                    </div>
                </div>

                <div id="expiryDateContainer" class="hidden animate-fade-in-down">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Expiry Date</label>
                        <input
                            name="expiry_date"
                            type="date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Description <span class="text-gray-400 font-normal text-xs">(Optional)</span></label>
                    <textarea
                        name="description"
                        placeholder="Add details about ingredients, dosage, or usage..."
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition resize-none"
                    ></textarea>
                </div>

                <div id="modalError" class="hidden p-4 rounded-lg bg-red-50 border border-red-100 text-red-600 text-sm font-medium flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <span></span>
                </div>
                <div id="modalSuccess" class="hidden p-4 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-600 text-sm font-medium flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <span>Product added successfully!</span>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-gray-100 bg-gray-50 rounded-b-xl flex flex-col sm:flex-row items-center justify-end gap-3 flex-shrink-0">
            <button
                type="button"
                data-modal-cancel
                class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition shadow-sm order-2 sm:order-1"
            >
                Cancel
            </button>
            <button
                type="submit"
                form="addProductForm"
                id="submitBtn"
                class="w-full sm:w-auto px-8 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow-md transition flex items-center justify-center gap-2 order-1 sm:order-2"
            >
                <i class="fas fa-save"></i>
                <span>Save Product</span>
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes modalScale {
        from { opacity: 0; transform: scale(0.98); }
        to { opacity: 1; transform: scale(1); }
    }
    .animate-modal-scale {
        animation: modalScale 0.15s ease-out forwards;
    }
    
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-down {
        animation: fadeInDown 0.2s ease-out forwards;
    }
    
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>

<script>
(function(){
    const modal = document.getElementById("{$this->id}");
    if (!modal) return;

    const BASE_URL = "{$base}";
    const backdrop = modal.querySelector('[data-modal-backdrop]');
    const hasExpirySelect = document.getElementById('hasExpiry');
    const expiryDateContainer = document.getElementById('expiryDateContainer');
    const form = document.getElementById('addProductForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorDiv = document.getElementById('modalError');
    const successDiv = document.getElementById('modalSuccess');

    function show() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        hideMessages();
        form.querySelector('input[name="name"]')?.focus();
    }

    function hide() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        form.reset();
        expiryDateContainer.classList.add('hidden');
        hideMessages();
    }

    function showError(message) {
        errorDiv.querySelector('span').textContent = message;
        errorDiv.classList.remove('hidden');
        successDiv.classList.add('hidden');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function showSuccess(message = 'Product added successfully!') {
        successDiv.querySelector('span').textContent = message;
        successDiv.classList.remove('hidden');
        errorDiv.classList.add('hidden');
    }

    function hideMessages() {
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
    }

    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitBtn.innerHTML = loading 
            ? '<i class="fas fa-spinner fa-spin"></i> Saving...' 
            : '<i class="fas fa-save"></i> Save Product';
    }

    document.querySelectorAll('[data-open-add-product]').forEach(btn => {
        btn.addEventListener('click', show);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === backdrop || e.target.closest('[data-modal-close]') || e.target.closest('[data-modal-cancel]')) {
            hide();
        }
    });

    hasExpirySelect.addEventListener('change', () => {
        if (hasExpirySelect.value === '1') {
            expiryDateContainer.classList.remove('hidden');
        } else {
            expiryDateContainer.classList.add('hidden');
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        hideMessages();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const productName = data.name?.trim();

        if (!productName) return showError('Please enter product name');
        if (!data.category_id) return showError('Please select a category');
        if (!data.price || parseFloat(data.price) <= 0) return showError('Please enter a valid selling price');

        setLoading(true);

        try {
            const checkNameUrl = BASE_URL + '/api/inventory/check-name.php?name=' + encodeURIComponent(productName);
            const checkNameResponse = await fetch(checkNameUrl);
            const nameCheck = await checkNameResponse.json();

            if (nameCheck.exists) {
                showError('A product with the name "' + productName + '" already exists.');
                setLoading(false);
                return;
            }

            const response = await fetch(BASE_URL + '/api/inventory/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showSuccess();
                setTimeout(() => {
                    hide();
                    window.location.reload();
                }, 1500);
            } else {
                showError(result.message || 'Failed to add product');
            }
        } catch (err) {
            console.error('API Error:', err);
            showError('Connection failed. Please try again.');
        } finally {
            setLoading(false);
        }
    });
})();
</script>
HTML;
    }
}
?>
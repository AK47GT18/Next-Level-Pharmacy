<?php
// filepath: components/shared/edit-product-modal.php

class EditProductModal
{
    private string $id;
    private array $categories;

    public function __construct(array $categories = [], string $id = 'edit-product-modal')
    {
        $this->id = $id;
        $this->categories = $categories ?? [];
    }

    public function render(): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms';
        $modalId = $this->id;

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
<div id="{$modalId}" class="fixed inset-0 z-50 hidden items-center justify-center p-4 transition-all duration-300">
    
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity duration-300" data-modal-backdrop></div>

    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh] animate-modal-scale z-10 overflow-hidden ring-1 ring-gray-200">

        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 bg-white flex-shrink-0">
            <h3 class="text-xl font-bold text-gray-800">Edit Product</h3>
            <button data-modal-close class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-8 bg-white">
            <form id="edit-product-form" class="space-y-6">
                <!-- ... form fields ... -->
                <input type="hidden" name="id" id="edit-product-id">

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Product Name <span class="text-red-500">*</span></label>
                    <input id="edit-name" name="name" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                    >
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Category <span class="text-red-500">*</span></label>
                        <select id="edit-category_id" name="category_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition bg-white">
                            <option value="">Select Category</option>
                            {$categoryOptions}
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Stock <span class="text-red-500">*</span></label>
                        <input id="edit-stock" name="stock" type="number" min="0" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Price (MWK) <span class="text-red-500">*</span></label>
                        <input id="edit-price" name="price" type="number" step="0.01" min="0" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Cost Price (MWK)</label>
                        <input id="edit-cost_price" name="cost_price" type="number" step="0.01" min="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Low Stock Alert</label>
                        <input id="edit-low_stock_threshold" name="low_stock_threshold" type="number" min="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Has Expiry?</label>
                        <select id="edit-has-expiry" name="has_expiry"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition bg-white">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>

                <div id="edit-expiry-date-container" class="hidden">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Expiry Date</label>
                        <input id="edit-expiry_date" name="expiry_date" type="date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Description</label>
                    <textarea id="edit-description" name="description" rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition resize-none"></textarea>
                </div>

                 <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Units Sold</label>
                    <div id="edit-units-sold" class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 font-semibold select-none">
                        0
                    </div>
                </div>

                <div id="edit-modal-error" class="hidden p-4 rounded-lg bg-red-50 border border-red-100 text-red-600 text-sm font-medium">
                    <span></span>
                </div>
                <div id="edit-modal-success" class="hidden p-4 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-600 text-sm font-medium">
                    <span>Product updated successfully!</span>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-gray-100 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3 flex-shrink-0">
            <button data-modal-cancel
                class="px-6 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition shadow-sm">
                Cancel
            </button>
            <button id="edit-submit-btn" type="submit" form="edit-product-form"
                class="px-8 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow-md transition flex items-center gap-2">
                <span>Save Changes</span>
            </button>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    @keyframes modalScale {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .animate-modal-scale {
        animation: modalScale 0.2s ease-out forwards;
    }
</style>

<script>
(function(){
    const modal = document.getElementById("{$modalId}");
    if (!modal) return;

    const BASE_URL = "{$base}";
    const backdrop = modal.querySelector('[data-modal-backdrop]');
    const form = document.getElementById('edit-product-form');
    const submitBtn = document.getElementById('edit-submit-btn');
    const errorDiv = document.getElementById('edit-modal-error');
    const successDiv = document.getElementById('edit-modal-success');
    const hasExpiry = document.getElementById('edit-has-expiry');
    const expiryContainer = document.getElementById('edit-expiry-date-container');

    function hide() {
        modal.classList.add('hidden');
        modal.classList.remove('flex'); 
        form.reset();
        expiryContainer.classList.add('hidden');
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
    }

    modal.addEventListener('click', (e) => {
        if (e.target === backdrop || e.target.closest('[data-modal-close]') || e.target.closest('[data-modal-cancel]')) {
            hide();
        }
    });

    hasExpiry.addEventListener('change', () => {
        if (hasExpiry.value === '1') {
            expiryContainer.classList.remove('hidden');
        } else {
            expiryContainer.classList.add('hidden');
        }
    });

    window.openEditModal = function(product) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');

        document.getElementById('edit-product-id').value = product.id || '';
        document.getElementById('edit-name').value = product.name || '';
        document.getElementById('edit-category_id').value = product.category_id || '';
        document.getElementById('edit-price').value = product.price || '';
        document.getElementById('edit-cost_price').value = product.cost_price || '';
        document.getElementById('edit-stock').value = product.stock || '';
        document.getElementById('edit-low_stock_threshold').value = product.low_stock_threshold || '';
        document.getElementById('edit-description').value = product.description || '';
        document.getElementById('edit-units-sold').textContent = product.units_sold || 0;

        document.getElementById('edit-has-expiry').value = product.has_expiry ?? '0';
        
        if (product.expiry_date) {
            document.getElementById('edit-expiry_date').value = product.expiry_date.split(' ')[0];
        } else {
            document.getElementById('edit-expiry_date').value = '';
        }

        const hasExp = (product.has_expiry == 1 || (product.expiry_date && product.expiry_date !== '0000-00-00'));
        document.getElementById('edit-has-expiry').value = hasExp ? '1' : '0';
        
        if (hasExp) {
            expiryContainer.classList.remove('hidden');
        } else {
            expiryContainer.classList.add('hidden');
        }
    };
})();
</script>
HTML;
    }
}
?>
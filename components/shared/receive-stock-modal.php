<?php
// filepath: components/shared/receive-stock-modal.php

class ReceiveStockModal
{
    private string $id;

    public function __construct(string $id = 'receive-stock-modal')
    {
        $this->id = $id;
    }

    public function render(): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '/Next-Level/rxpms';

        return <<<HTML
<div id="{$this->id}" class="fixed inset-0 z-50 hidden items-center justify-center p-4 transition-all duration-300">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-md" data-modal-backdrop></div>

    <!-- Modal Card -->
    <div class="relative w-full max-w-lg max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-modal-open">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-truck-loading text-blue-600"></i> Receive Products
            </h3>
            <button data-modal-close class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 p-6 overflow-y-auto custom-scrollbar">
            <form id="receive-stock-form" class="space-y-4">
                <input type="hidden" name="id" id="receive-product-id">
                
                <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100 mb-4">
                    <p class="text-sm text-emerald-800 font-semibold" id="receive-product-name-display">Product: Loading...</p>
                    <p class="text-xs text-emerald-600" id="receive-current-stock-display">Current Stock: 0</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity Received *</label>
                    <input type="number" name="quantity" id="receive-quantity" required min="1"
                        placeholder="0"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Cost Price (MWK)</label>
                    <input type="number" name="cost_price" id="receive-cost-price" step="0.01" min="0"
                        placeholder="0.00"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500 outline-none transition">
                    <p class="text-[10px] text-gray-400 mt-1">Leave empty to keep current cost price.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="receive-notes" rows="2"
                        placeholder="e.g. Received from primary supplier"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500 outline-none transition resize-none"></textarea>
                </div>

                <div id="receive-modal-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex gap-2">
                    <i class="fas fa-exclamation-circle"></i><span></span>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 p-5 border-t bg-gray-50/80">
            <button data-modal-close
                class="px-6 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition shadow-sm">
                Cancel
            </button>
            <button id="receive-submit-btn" type="submit" form="receive-stock-form"
                class="px-8 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-md hover:shadow-blue-200/50 transition flex items-center gap-2">
                <i class="fas fa-check"></i> Complete Receipt
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    const modal = document.getElementById("{$this->id}");
    if (!modal) return;

    const BASE_URL = "{$base}";
    const form = document.getElementById('receive-stock-form');
    const submitBtn = document.getElementById('receive-submit-btn');
    const errorDiv = document.getElementById('receive-modal-error');
    const backdrop = modal.querySelector('[data-modal-backdrop]');

    function hide() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        form.reset();
        errorDiv.classList.add('hidden');
    }

    modal.addEventListener('click', (e) => {
        if (e.target === backdrop || e.target.closest('[data-modal-close]')) hide();
    });

    window.openReceiveModal = function(product) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        document.getElementById('receive-product-id').value = product.id;
        document.getElementById('receive-product-name-display').textContent = 'Product: ' + product.name;
        document.getElementById('receive-current-stock-display').textContent = 'Current Stock: ' + product.stock + ' units';
        document.getElementById('receive-cost-price').value = product.cost_price || '';
        
        document.getElementById('receive-quantity').focus();
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorDiv.classList.add('hidden');
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        if (!data.quantity || data.quantity <= 0) {
            errorDiv.querySelector('span').textContent = 'Please enter a valid quantity.';
            errorDiv.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch(BASE_URL + '/api/inventory/receive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                hide();
                // Show success message and reload
                if (window.showToast) {
                    window.showToast('success', result.message);
                } else {
                    alert(result.message);
                }
                location.reload();
            } else {
                throw new Exception(result.message || 'Failed to update stock.');
            }
        } catch (err) {
            errorDiv.querySelector('span').textContent = err.message || 'An error occurred. Please try again.';
            errorDiv.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Complete Receipt';
        }
    });
})();
</script>
HTML;
    }
}

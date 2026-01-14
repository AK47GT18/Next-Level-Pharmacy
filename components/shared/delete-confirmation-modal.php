<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\components\shared\delete-confirmation-modal.php

class DeleteConfirmationModal
{
    public function render(): string
    {
        ob_start();
        ?>
        <div id="delete-confirmation-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center p-4 transition-all duration-300">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity" data-modal-backdrop></div>
            <div
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-modal-scale z-10 ring-1 ring-gray-200">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white flex-shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center">
                            <i class="fas fa-trash-alt text-red-500 text-sm"></i>
                        </div>
                        Delete Product
                    </h3>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-rose-100 rounded-full">
                        <i class="fas fa-exclamation text-rose-600 text-xl"></i>
                    </div>

                    <div class="text-center">
                        <h3 class="text-lg font-bold text-gray-900">Are you sure?</h3>
                        <p class="text-gray-600 text-sm mt-2">
                            This action cannot be undone. The product will be permanently deleted from the inventory.
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t rounded-b-2xl">
                    <button id="cancel-delete-btn"
                        class="px-6 py-2.5 rounded-lg bg-gray-200 border border-gray-300 text-gray-800 font-bold hover:bg-gray-300 transition shadow-sm">
                        Cancel
                    </button>
                    <button id="confirm-delete-btn" style="background-color: #dc2626 !important; color: #ffffff !important;"
                        class="px-8 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold shadow-md hover:shadow-xl transition flex items-center gap-2">
                        <i class="fas fa-trash-alt" style="color: #ffffff !important;"></i> Yes, Delete
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>
<script>
    (function () {
        const modal = document.getElementById('delete-confirmation-modal');
        const backdrop = modal.querySelector('[data-modal-backdrop]');
        const cancelBtn = document.getElementById('cancel-delete-btn');

        function hide() {
            modal.classList.add('hidden');
        }

        if (backdrop) backdrop.addEventListener('click', hide);
        if (cancelBtn) cancelBtn.addEventListener('click', hide);
    })();
</script>
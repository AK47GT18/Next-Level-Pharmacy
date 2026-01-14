<?php
// filepath: components/shared/stock-history-modal.php

class StockHistoryModal
{
    public function render(): string
    {
        ob_start();
        ?>
        <div id="stock-history-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center p-4 transition-all duration-300">

            <div id="history-backdrop" class="absolute inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity duration-300">
            </div>

            <div
                class="relative w-full max-w-lg max-h-[calc(100vh-2rem)] md:max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col z-10 animate-modal-scale overflow-hidden ring-1 ring-gray-200">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-white flex-shrink-0">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-history text-blue-600"></i>
                        Stock History
                    </h2>

                    <button id="close-history-modal"
                        class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-red-500 transition cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-5 bg-gray-50/50">

                    <h3 id="history-product-name" class="text-sm font-bold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                        Loading...
                    </h3>

                    <div id="history-content" class="space-y-3 overflow-y-auto custom-scrollbar pr-2">
                        <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <span class="text-xs">Loading records...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar {
                width: 5px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background-color: #cbd5e1;
                border-radius: 10px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background-color: #94a3b8;
            }

            @keyframes modalScale {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }

                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .animate-modal-scale {
                animation: modalScale 0.2s ease-out forwards;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('stock-history-modal');
                const closeBtn = document.getElementById('close-history-modal');
                const backdrop = document.getElementById('history-backdrop');

                function closeHistoryModal() {
                    modal.classList.add('hidden');
                }

                if (closeBtn) closeBtn.addEventListener('click', closeHistoryModal);
                if (backdrop) backdrop.addEventListener('click', closeHistoryModal);
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
?>
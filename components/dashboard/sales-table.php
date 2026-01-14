<?php

class SalesTable
{
    private array $recentSales;

    public function __construct(array $recentSales)
    {
        $this->recentSales = $recentSales;
    }

    public function render(): string
    {
        ob_start();
        ?>
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-premium p-6 border border-gray-100/50">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">Recent Sales</h2>
                    <p class="text-sm text-gray-500">Latest transactions from today</p>
                </div>
                <button
                    class="px-4 py-2 text-blue-600 bg-blue-50 rounded-xl text-sm font-semibold hover:bg-blue-100 transition">
                    <i class="fas fa-external-link-alt mr-2"></i>View All
                </button>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-100">
                            <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Invoice
                            </th>
                            <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Customer
                            </th>
                            <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Amount</th>
                            <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($this->recentSales as $sale): ?>
                            <tr class="hover:bg-blue-50/50 transition group">
                                <!-- Sale row content -->
                                <?php $this->renderSaleRow($sale); ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderSaleRow(array $sale): void
    {
        $statusClass = $this->getStatusClass($sale['status']);
        $statusIcon = $this->getStatusIcon($sale['status']);
        ?>
        <td class="py-4 px-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-600 text-xs"></i>
                </div>
                <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($sale['invoice']) ?></span>
            </div>
        </td>
        <td class="py-4 px-4">
            <div class="flex items-center gap-2">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($sale['customer']) ?>&background=3b82f6&color=fff&size=32"
                    class="w-8 h-8 rounded-lg">
                <span class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($sale['customer']) ?></span>
            </div>
        </td>
        <td class="py-4 px-4 text-sm font-bold text-gray-900">
            MWK <?= number_format($sale['amount'], 2) ?>
        </td>
        <td class="py-4 px-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 <?= $statusClass ?> rounded-xl text-xs font-bold">
                <i class="fas <?= $statusIcon ?>"></i>
                <?= htmlspecialchars($sale['status']) ?>
            </span>
        </td>
        <td class="py-4 px-4 text-sm text-gray-500"><?= htmlspecialchars($sale['date']) ?></td>
        <?php
    }

    private function getStatusClass(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => 'bg-emerald-50 text-emerald-700',
            'pending' => 'bg-amber-50 text-amber-700',
            'cancelled' => 'bg-rose-50 text-rose-700',
            default => 'bg-gray-50 text-gray-700'
        };
    }

    private function getStatusIcon(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => 'fa-check-circle',
            'pending' => 'fa-clock',
            'cancelled' => 'fa-times-circle',
            default => 'fa-circle'
        };
    }
}
?>
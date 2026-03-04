<?php

class LowStockAlert
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function render(): string
    {
        ob_start();
        ?>
        <div class="bg-white rounded-2xl shadow-premium p-6 border border-gray-100/50 h-full">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Low Stock Alert</h2>
                    <p class="text-xs text-gray-400 font-medium mt-0.5">Items need reordering</p>
                </div>
                <div
                    class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center hover:bg-amber-200 transition-colors cursor-pointer">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
            </div>

            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                <?php if (empty($this->items)): ?>
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-box-open text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm text-gray-500">Inventory is healthy</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($this->items as $item): ?>
                        <?php $this->renderStockItem($item); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderStockItem(array $item): void
    {
        $level = strtolower($item['level']);
        $styles = $this->getLevelStyles($level);

        // Calculate percentage for progress bar (cap at 100)
        $maxStock = isset($item['max_stock']) ? $item['max_stock'] : 100;
        $percentage = min(100, ($item['stock'] / $maxStock) * 100);

        ?>
        <div
            class="group relative p-4 rounded-2xl <?= $styles['bg_color'] ?> border border-transparent hover:border-<?= $styles['base_color'] ?>-200 transition-all duration-200">

            <div class="flex items-start justify-between mb-3">
                <h4 class="font-bold text-gray-800 text-sm tracking-tight"><?= htmlspecialchars($item['name']) ?></h4>
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white shadow-sm <?= $styles['text_color'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $styles['dot_color'] ?>"></span>
                    <?= htmlspecialchars($item['level']) ?>
                </span>
            </div>

            <div class="flex items-center justify-between gap-4 mb-3">
                <div class="space-y-1 flex-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500 font-medium">Stock Level</span>
                        <span
                            class="font-bold text-gray-700"><?= $item['stock'] ?>/<?= $item['reorder_level'] ?? 'N/A' ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500 font-medium">Reorder Level</span>
                        <span class="font-bold text-gray-700"><?= $item['reorder_level'] ?? 'N/A' ?></span>
                    </div>
                </div>

                <div class="w-10 h-10 bg-white/60 rounded-lg flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-capsules <?= $styles['text_color'] ?> text-lg opacity-80"></i>
                </div>
            </div>

            <div class="w-full bg-white/50 h-1.5 rounded-full overflow-hidden">
                <div class="h-full rounded-full <?= $styles['bar_color'] ?>" style="width: <?= $percentage ?>%"></div>
            </div>
        </div>
        <?php
    }

    private function getLevelStyles(string $level): array
    {
        return match ($level) {
            'critical' => [
                'base_color' => 'rose',
                'bg_color' => 'bg-rose-50',     // Light Red background
                'text_color' => 'text-rose-600',
                'dot_color' => 'bg-rose-500',
                'bar_color' => 'bg-rose-500'
            ],
            'low' => [
                'base_color' => 'amber',
                'bg_color' => 'bg-amber-50',    // Light Yellow/Orange background
                'text_color' => 'text-amber-600',
                'dot_color' => 'bg-amber-500',
                'bar_color' => 'bg-amber-500'
            ],
            'moderate' => [
                'base_color' => 'blue',
                'bg_color' => 'bg-blue-50',
                'text_color' => 'text-blue-600',
                'dot_color' => 'bg-blue-500',
                'bar_color' => 'bg-blue-500'
            ],
            default => [
                'base_color' => 'gray',
                'bg_color' => 'bg-gray-50',
                'text_color' => 'text-gray-600',
                'dot_color' => 'bg-gray-500',
                'bar_color' => 'bg-gray-500'
            ]
        };
    }
}
?>
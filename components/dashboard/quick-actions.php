<?php
require_once __DIR__ . '/../../includes/PathHelper.php';

if (!class_exists('QuickActions')) {

    class QuickActions
    {
        public function render()
        {
            // Configuration for the cards
            $actions = [
                [
                    'title' => 'New Sale',
                    'desc' => 'Process transaction',
                    'icon' => 'fa-cash-register',
                    'link' => 'index.php?page=pos',
                    'theme' => 'blue'
                ],
                [
                    'title' => 'Inventory',
                    'desc' => 'Manage products',
                    'icon' => 'fa-boxes-stacked',
                    'link' => 'index.php?page=inventory',
                    'theme' => 'indigo'
                ],
                [
                    'title' => 'Reports',
                    'desc' => 'View analytics',
                    'icon' => 'fa-chart-line',
                    'link' => 'index.php?page=reports',
                    'theme' => 'emerald'
                ],
                [
                    'title' => 'Admin',
                    'desc' => 'System settings',
                    'icon' => 'fa-user-gear',
                    'link' => 'index.php?page=settings',
                    'theme' => 'purple'
                ]
            ];

            ob_start();
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php foreach ($actions as $action): ?>
                    <?= $this->renderCard($action) ?>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private function renderCard($item)
        {
            $styles = $this->getThemeStyles($item['theme']);

            return <<<HTML
            <a href="{$item['link']}" class="group relative bg-white rounded-3xl p-6 border border-gray-100/50 shadow-premium shadow-premium-hover {$styles['hover_border']} transition-all duration-300">
                
                <div class="flex items-start justify-between mb-6">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl {$styles['bg']} {$styles['text']} group-hover:scale-110 transition-transform duration-300">
                        <i class="fas {$item['icon']}"></i>
                    </div>

                    <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300">
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1 group-hover:{$styles['text_hover']} transition-colors">
                        {$item['title']}
                    </h3>
                    <p class="text-xs text-gray-400 font-medium group-hover:text-gray-500 transition-colors">
                        {$item['desc']}
                    </p>
                </div>

                <div class="absolute bottom-4 right-6 w-12 h-1 rounded-full {$styles['bg_bar']} opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            </a>
            HTML;
        }

        private function getThemeStyles($theme)
        {
            return match ($theme) {
                'blue' => [
                    'bg' => 'bg-blue-50',
                    'text' => 'text-blue-600',
                    'text_hover' => 'text-blue-600',
                    'hover_border' => 'hover:border-blue-200',
                    'hover_shadow' => 'hover:shadow-lg hover:shadow-blue-500/10',
                    'bg_bar' => 'bg-blue-200'
                ],
                'indigo' => [
                    'bg' => 'bg-indigo-50',
                    'text' => 'text-indigo-600',
                    'text_hover' => 'text-indigo-600',
                    'hover_border' => 'hover:border-indigo-200',
                    'hover_shadow' => 'hover:shadow-lg hover:shadow-indigo-500/10',
                    'bg_bar' => 'bg-indigo-200'
                ],
                'emerald' => [
                    'bg' => 'bg-emerald-50',
                    'text' => 'text-emerald-600',
                    'text_hover' => 'text-emerald-600',
                    'hover_border' => 'hover:border-emerald-200',
                    'hover_shadow' => 'hover:shadow-lg hover:shadow-emerald-500/10',
                    'bg_bar' => 'bg-emerald-200'
                ],
                'purple' => [
                    'bg' => 'bg-purple-50',
                    'text' => 'text-purple-600',
                    'text_hover' => 'text-purple-600',
                    'hover_border' => 'hover:border-purple-200',
                    'hover_shadow' => 'hover:shadow-lg hover:shadow-purple-500/10',
                    'bg_bar' => 'bg-purple-200'
                ],
                default => [
                    'bg' => 'bg-gray-50',
                    'text' => 'text-gray-600',
                    'text_hover' => 'text-gray-800',
                    'hover_border' => 'hover:border-gray-300',
                    'hover_shadow' => 'hover:shadow-lg',
                    'bg_bar' => 'bg-gray-200'
                ],
            };
        }
    }
}
?>
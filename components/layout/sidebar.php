<?php
class Sidebar
{
    private array $menuItems;
    private string $currentPage;

    public function __construct(string $currentPage = 'dashboard')
    {
        $this->currentPage = $currentPage;
        $this->menuItems = $this->getMenuItems();
    }

    private function getMenuItems(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'icon' => 'fa-home',
                'url' => '#dashboard'
            ],
            'inventory' => [
                'label' => 'Inventory',
                'icon' => 'fa-boxes',
                'url' => '#inventory'
            ],
            'pos' => [
                'label' => 'Sales / POS',
                'icon' => 'fa-cash-register',
                'url' => '#pos'
            ],
            'reports' => [
                'label' => 'Reports',
                'icon' => 'fa-chart-line',
                'url' => '#reports'
            ],
            'settings' => [
                'label' => 'Settings',
                'icon' => 'fa-cog',
                'url' => PathHelper::page('settings')
            ]
        ];
    }

    public function render(): string
    {
        return <<<HTML
        <aside id="sidebar" class="fixed md:relative w-[280px] md:w-72 gradient-bg text-white flex flex-col
               transition-all duration-300 transform md:transform-none -translate-x-full md:translate-x-0 z-50 shadow-2xl">
            <div class="h-16 flex items-center justify-between px-6 border-b border-white/10 backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-md shadow-inner border border-white/10">
                        <i class="fas fa-pills text-white text-lg"></i>
                    </div>
                    <div id="logoText">
                        <h1 class="text-xl font-bold tracking-tight text-white font-numeric">Next-Level</h1>
                    </div>
                </div>
                <button id="sidebarToggle" class="p-2 text-blue-100 hover:bg-white/10 rounded-lg transition md:hidden">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <nav class="flex-1 px-4 py-6 space-y-1 custom-scrollbar overflow-y-auto">
                {$this->renderMenuItems()}
            </nav>
            
            {$this->renderUserProfile()}
        </aside>
        HTML;
    }

    private function renderMenuItems(): string
    {
        $items = '';
        foreach ($this->menuItems as $key => $item) {
            $isActive = false;
            if ($key === 'settings') {
                $isActive = ($this->currentPage === 'settings');
            } else {
                $isActive = ($this->currentPage === $key);
            }

            $activeClass = $isActive
                ? 'active-nav text-white shadow-lg shadow-blue-900/20'
                : 'text-blue-100 hover:bg-white/10 hover:translate-x-1';

            $pageUrl = PathHelper::page($key);

            $items .= <<<HTML
            <a href="{$pageUrl}"
               class="flex items-center gap-3 px-6 py-3.5 transition-all duration-200 group {$activeClass}" 
               data-page="{$key}">
                <i class="fas {$item['icon']} w-5 text-center group-hover:scale-110 transition-transform"></i>
                <span class="font-medium text-sm">{$item['label']}</span>
            </a>
            HTML;
        }
        return $items;
    }

    private function renderUserProfile(): string
    {
        // Construct the URL outside the HEREDOC string to ensure it's correctly processed by PHP.
        $userProfileUrl = PathHelper::page('settings', ['view' => 'user-profile']);

        return <<<HTML
        <div class="p-4 border-t border-white/10 bg-white/5">
            <div onclick="window.location.href='{$userProfileUrl}'" class="flex items-center gap-3 px-3 py-3 bg-white/5 rounded-xl hover:bg-white/10 transition cursor-pointer">
                <div class="relative">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=3b82f6&color=fff&bold=true" alt="Admin User" class="w-12 h-12 rounded-xl ring-2 ring-white/20">
                    <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-400 rounded-full ring-2 
                         ring-blue-700 pulse-dot"></div>
                </div>
                <div class="nav-text flex-1">
                    <p class="font-semibold text-sm">Admin User</p>
                    <p class="text-xs text-blue-200">Super Admin</p>
                </div>
                <i class="fas fa-chevron-right text-xs text-blue-200"></i>
            </div>
        </div>
        HTML;
    }
}
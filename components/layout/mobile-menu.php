<?php

class MobileMenu {
    private array $menuItems = [];
    private ?array $user = null;

    public function __construct() {
        $this->menuItems = $this->getMenuItems();
        $this->user = $this->loadUser();
    }

    private function loadUser(): ?array {
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../../classes/User.php';
            $db = Database::getInstance()->getConnection();
            return (new User($db))->getById($_SESSION['user_id']);
        }
        return null;
    }

    private function getMenuItems(): array {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'icon' => 'fa-home',
                'url' => '?page=dashboard'
            ],
            'inventory' => [
                'label' => 'Inventory',
                'icon' => 'fa-boxes',
                'url' => '?page=inventory'
            ],
            'pos' => [
                'label' => 'Sales / POS',
                'icon' => 'fa-cash-register',
                'url' => '?page=pos'
            ],
            'reports' => [
                'label' => 'Reports',
                'icon' => 'fa-chart-line',
                'url' => '?page=reports'
            ],
            'settings' => [
                'label' => 'Settings',
                'icon' => 'fa-cog',
                'url' => '?page=settings'
            ]
        ];
    }

    public function render(): string {
        return <<<HTML
        <div id="mobileMenu" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-[60] hidden md:hidden">
            <div class="absolute left-0 top-0 h-full w-[80%] max-w-[300px] gradient-bg text-white shadow-2xl transform
                 transition-transform duration-300 -translate-x-full">
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-sm"><i class="fas fa-pills text-2xl"></i></div>
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight">Next-Level</h1>
                                <p class="text-xs text-blue-200">Pharmacy System</p>
                            </div>
                        </div>
                        <button id="closeMobileMenu" class="p-2 hover:bg-white/10 rounded-lg"><i class="fas fa-times text-xl"></i></button>
                    </div>
                </div>
                
                <nav class="p-4 space-y-2">
                    {$this->renderMenuItems()}
                </nav>
                
                {$this->renderUserProfile()}
            </div>
        </div>
        HTML;
    }

    private function renderMenuItems(): string {
        $items = '';
        foreach ($this->menuItems as $key => $item) {
            $items .= <<<HTML
            <a href="{$item['url']}" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-xl hover:bg-white/10 transition group">
                <i class="fas {$item['icon']}"></i>
                <span>{$item['label']}</span>
            </a>
            HTML;
        }
        return $items;
    }

    private function renderUserProfile(): string {
        if (!$this->user) {
            return '';
        }

        $userName = htmlspecialchars($this->user['name'] ?? 'Guest');
        $userEmail = htmlspecialchars($this->user['email'] ?? '');
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=3b82f6&color=fff";

        return <<<HTML
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
            <div class="flex items-center gap-3 p-3 bg-white/10 rounded-xl">
                <img src="{$avatarUrl}" alt="{$userName}"
                     class="w-10 h-10 rounded-lg" />
                <div>
                    <p class="font-semibold">{$userName}</p>
                    <p class="text-xs text-blue-200">{$userEmail}</p>
                </div>
            </div>
        </div>
        HTML;
    }
}
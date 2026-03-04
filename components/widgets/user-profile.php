<?php
class UserProfile {
    private array $user;
    private array $options;

    public function __construct(array $user, array $options = []) {
        $this->user = $user;
        $this->options = array_merge([
            'showStatus' => true,
            'showRole' => true,
            'avatarSize' => 'md',
            'variant' => 'default' // default, compact, or expanded
        ], $options);
    }

    public function render(): string {
        return match($this->options['variant']) {
            'compact' => $this->renderCompact(),
            'expanded' => $this->renderExpanded(),
            default => $this->renderDefault()
        };
    }

    private function renderDefault(): string {
        $statusDot = $this->options['showStatus'] ? $this->renderStatusDot() : '';
        $roleText = $this->options['showRole'] ? "<p class='text-xs text-blue-200'>{$this->user['role']}</p>" : '';

        return <<<HTML
        <div class="flex items-center gap-3 px-3 py-3 bg-white/5 rounded-xl hover:bg-white/10 transition cursor-pointer">
            <div class="relative">
                <img src="{$this->getAvatarUrl()}" 
                     class="w-12 h-12 rounded-xl ring-2 ring-white/20">
                {$statusDot}
            </div>
            <div class="flex-1">
                <p class="font-semibold text-sm text-white">{$this->user['name']}</p>
                {$roleText}
            </div>
            <i class="fas fa-chevron-right text-xs text-blue-200"></i>
        </div>
        HTML;
    }

    private function renderCompact(): string {
        return <<<HTML
        <div class="flex items-center gap-2">
            <img src="{$this->getAvatarUrl()}" 
                 class="w-8 h-8 rounded-lg">
            <span class="text-sm font-medium">{$this->user['name']}</span>
        </div>
        HTML;
    }

    private function renderExpanded(): string {
        $statusDot = $this->options['showStatus'] ? $this->renderStatusDot() : '';

        return <<<HTML
        <div class="p-4 glassmorphism rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <img src="{$this->getAvatarUrl()}" 
                         class="w-16 h-16 rounded-xl ring-2 ring-white/20">
                    {$statusDot}
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-bold text-gray-900">{$this->user['name']}</h4>
                    <p class="text-sm text-gray-500">{$this->user['email']}</p>
                    <p class="text-xs text-gray-400 mt-1">{$this->user['role']}</p>
                </div>
                <button class="p-2 hover:bg-gray-100 rounded-xl transition">
                    <i class="fas fa-ellipsis-v text-gray-400"></i>
                </button>
            </div>
        </div>
        HTML;
    }

    private function renderStatusDot(): string {
        return <<<HTML
        <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-400 rounded-full ring-2 ring-blue-700 pulse-dot">
        </div>
        HTML;
    }

    private function getAvatarUrl(): string {
        if (isset($this->user['avatar'])) {
            return $this->user['avatar'];
        }

        $name = urlencode($this->user['name']);
        return "https://ui-avatars.com/api/?name={$name}&background=3b82f6&color=fff&bold=true";
    }
}
<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\components\widgets\search-bar.php

class SearchBar {
    private array $options;

    public function __construct(array $options = []) {
        $this->options = array_merge([
            'placeholder' => 'Search medicines, customers, invoices...',
            'showShortcut' => true,
            'live' => true,
            'width' => 'full',
            'variant' => 'default', // default, minimal, or expanded
            'onSearch' => null // Optional callback function name
        ], $options);
    }

    public function render(): string {
        return match($this->options['variant']) {
            'minimal' => $this->renderMinimal(),
            'expanded' => $this->renderExpanded(),
            default => $this->renderDefault()
        };
    }

    private function renderDefault(): string {
        $shortcut = $this->options['showShortcut'] ? $this->renderShortcut() : '';
        $widthClass = $this->getWidthClass();

        return <<<HTML
        <div class="relative {$widthClass}">
            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text"
                   id="headerSearchInput"
                   placeholder="{$this->options['placeholder']}"
                   class="w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none 
                          focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition shadow-sm"
                   autocomplete="off">
            {$shortcut}
            <div id="searchResults" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 
                        hidden max-h-96 overflow-y-auto z-50" style="max-width: 100%;">
                <!-- Search results will be populated here -->
            </div>
        </div>
        HTML;
    }

    private function renderMinimal(): string {
        return <<<HTML
        <div class="relative">
            <input type="text"
                   id="headerSearchInput"
                   placeholder="Search..."
                   class="w-full pl-4 pr-10 py-2 bg-gray-100 border-none rounded-xl focus:outline-none 
                          focus:ring-2 focus:ring-blue-500/30 focus:bg-white transition"
                   autocomplete="off">
            <i class="fas fa-search absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <div id="searchResults" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 
                        hidden max-h-96 overflow-y-auto z-50">
                <!-- Search results will be populated here -->
            </div>
        </div>
        HTML;
    }

    private function renderExpanded(): string {
        $shortcut = $this->options['showShortcut'] ? $this->renderShortcut() : '';
        return <<<HTML
        <div class="relative">
            <div class="flex items-center gap-3 w-full p-3 bg-white border border-gray-200 rounded-2xl 
                        focus-within:ring-2 focus-within:ring-blue-500/30 focus-within:border-blue-500 transition shadow-sm">
                <i class="fas fa-search text-gray-400 text-lg"></i>
                <input type="text"
                       id="headerSearchInput"
                       placeholder="{$this->options['placeholder']}"
                       class="flex-1 border-none focus:outline-none text-lg"
                       autocomplete="off">
                {$shortcut}
            </div>
            <div id="searchResults" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 
                        hidden max-h-96 overflow-y-auto z-50">
                <!-- Search results will be populated here -->
            </div>
        </div>
        HTML;
    }

    private function renderShortcut(): string {
        return <<<HTML
        <kbd class="hidden md:inline-flex absolute right-4 top-1/2 transform -translate-y-1/2 px-2 py-1 
                   bg-gray-100 border border-gray-200 rounded text-xs text-gray-600">⌘K</kbd>
        HTML;
    }

    private function getWidthClass(): string {
        return match($this->options['width']) {
            'full' => 'w-full',
            'auto' => 'w-auto',
            default => 'w-' . $this->options['width']
        };
    }
}
?>
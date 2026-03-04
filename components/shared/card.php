<?php

class Card {
    private string $title;
    private string $content;
    private ?string $footer;
    private array $options;

    public function __construct(
        string $title,
        string $content,
        ?string $footer = null,
        array $options = []
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->footer = $footer;
        $this->options = array_merge([
            'padding' => true,
            'hover' => true,
            'animate' => true
        ], $options);
    }

    public function render(): string {
        $containerClasses = $this->getContainerClasses();
        $footerHtml = $this->footer ? $this->renderFooter() : '';

        return <<<HTML
        <div class="{$containerClasses}">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">{$this->title}</h3>
            </div>
            <div class="space-y-4">
                {$this->content}
            </div>
            {$footerHtml}
        </div>
        HTML;
    }

    private function getContainerClasses(): string {
        $classes = ['glassmorphism', 'rounded-2xl', 'shadow-lg', 'border', 'border-gray-100'];
        
        if ($this->options['padding']) {
            $classes[] = 'p-6';
        }
        if ($this->options['hover']) {
            $classes[] = 'hover:shadow-xl transition-shadow duration-300';
        }
        if ($this->options['animate']) {
            $classes[] = 'animate-slide-in';
        }

        return implode(' ', $classes);
    }

    private function renderFooter(): string {
        return <<<HTML
        <div class="mt-6 pt-4 border-t border-gray-100">
            {$this->footer}
        </div>
        HTML;
    }
}
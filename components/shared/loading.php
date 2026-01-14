<?php

class Loading {
    private string $size;
    private string $color;
    private string $type;

    public function __construct(
        string $size = 'md',
        string $color = 'blue',
        string $type = 'spinner'
    ) {
        $this->size = $size;
        $this->color = $color;
        $this->type = $type;
    }

    public function render(): string {
        return match($this->type) {
            'spinner' => $this->renderSpinner(),
            'dots' => $this->renderDots(),
            'pulse' => $this->renderPulse(),
            default => $this->renderSpinner()
        };
    }

    private function renderSpinner(): string {
        $sizeClass = $this->getSizeClass();
        $colorClass = $this->getColorClass();

        return <<<HTML
        <div class="flex items-center justify-center">
            <div class="loading {$sizeClass} {$colorClass}" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        HTML;
    }

    private function renderDots(): string {
        $colorClass = $this->getColorClass();
        return <<<HTML
        <div class="flex space-x-2 animate-pulse">
            <div class="w-2 h-2 rounded-full {$colorClass}"></div>
            <div class="w-2 h-2 rounded-full {$colorClass} animation-delay-200"></div>
            <div class="w-2 h-2 rounded-full {$colorClass} animation-delay-400"></div>
        </div>
        HTML;
    }

    private function renderPulse(): string {
        $sizeClass = $this->getSizeClass('pulse');
        $colorClass = $this->getColorClass();
        
        return <<<HTML
        <div class="relative {$sizeClass}">
            <div class="absolute inset-0 {$colorClass} rounded-full animate-ping opacity-75"></div>
            <div class="relative {$colorClass} rounded-full"></div>
        </div>
        HTML;
    }

    private function getSizeClass(string $type = 'spinner'): string {
        if ($type === 'spinner') {
            return match($this->size) {
                'sm' => 'w-6 h-6',
                'lg' => 'w-12 h-12',
                default => 'w-8 h-8'
            };
        }
        
        return match($this->size) {
            'sm' => 'w-4 h-4',
            'lg' => 'w-8 h-8',
            default => 'w-6 h-6'
        };
    }

    private function getColorClass(): string {
        return match($this->color) {
            'blue' => 'text-blue-600',
            'red' => 'text-rose-600',
            'green' => 'text-emerald-600',
            'yellow' => 'text-amber-600',
            default => 'text-gray-600'
        };
    }
}
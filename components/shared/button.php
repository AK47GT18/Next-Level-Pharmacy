<?php

class Button {
    private string $label;
    private string $type;
    private string $color;
    private ?string $icon;
    private array $attributes;

    public function __construct(
        string $label, 
        string $type = 'button',
        string $color = 'blue',
        ?string $icon = null,
        array $attributes = []
    ) {
        $this->label = $label;
        $this->type = $type;
        $this->color = $color;
        $this->icon = $icon;
        $this->attributes = $attributes;
    }

    private function getColorClasses(): string {
        return match($this->color) {
            'blue' => 'bg-gradient-to-r from-blue-600 to-blue-500 hover:shadow-blue-500/30',
            'red' => 'bg-gradient-to-r from-rose-600 to-rose-500 hover:shadow-rose-500/30',
            'green' => 'bg-gradient-to-r from-emerald-600 to-emerald-500 hover:shadow-emerald-500/30',
            'yellow' => 'bg-gradient-to-r from-amber-600 to-amber-500 hover:shadow-amber-500/30',
            default => 'bg-gradient-to-r from-gray-600 to-gray-500 hover:shadow-gray-500/30'
        };
    }

    public function render(): string {
        $colorClasses = $this->getColorClasses();
        $iconHtml = $this->icon ? "<i class='fas {$this->icon} mr-2'></i>" : '';
        $attrs = $this->renderAttributes();

        return <<<HTML
        <button type="{$this->type}"
                class="px-5 py-3 {$colorClasses} text-white rounded-xl hover:shadow-lg
                transition-all font-semibold"
                {$attrs}>
            {$iconHtml}{$this->label}
        </button>
        HTML;
    }

    private function renderAttributes(): string {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            // Skip class attribute as it's handled separately
            if ($key === 'class') continue;
            
            $attrs .= " {$key}='" . htmlspecialchars($value, ENT_QUOTES) . "'";
        }
        return $attrs;
    }
}
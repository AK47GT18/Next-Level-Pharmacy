<?php

class StatCard
{
    private string $title;
    private string $value;
    private string $icon;
    private ?string $growth; // Changed to string to allow "+ 12.5%" format
    private string $color;   // blue, purple, amber, emerald
    private string $subtitle;
    private bool $isAlert;   // To handle the Red "Alert" badge seen in Low Stock

    public function __construct(
        string $title,
        string $value,
        string $icon = '',
        ?string $growth = null,
        string $color = 'blue',
        string $subtitle = '',
        bool $isAlert = false
    ) {
        $this->title = $title;
        $this->value = $value;
        $this->icon = $icon;
        $this->growth = $growth;
        $this->color = $color;
        $this->subtitle = $subtitle;
        $this->isAlert = $isAlert;
    }

    public function render(): string
    {
        // Color mapping for Tailwind
        $colors = $this->getColorClasses($this->color);
        $iconClass = $this->icon ?: $this->getFallbackIcon();

        // Determine Badge Content (Growth vs Alert)
        $badgeHtml = '';
        if ($this->isAlert) {
            $badgeHtml = <<<HTML
            <span class="bg-rose-100 text-rose-600 text-xs font-bold px-2 py-1 rounded-lg flex items-center gap-1">
                <i class="fas fa-exclamation-circle"></i> Alert
            </span>
            HTML;
        } elseif ($this->growth) {
            $badgeHtml = <<<HTML
            <span class="bg-emerald-50 text-emerald-600 text-xs font-bold px-2 py-1 rounded-lg flex items-center gap-1">
                <i class="fas fa-arrow-up text-[10px]"></i> {$this->growth}
            </span>
            HTML;
        }

        return <<<HTML
        <div class="bg-white rounded-2xl p-5 shadow-premium shadow-premium-hover transition-all duration-300 border border-gray-100/50 h-full flex flex-col justify-between">
            
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 {$colors['bg']} rounded-2xl flex items-center justify-center text-white shadow-md shadow-{$this->color}-500/20">
                    <i class="fas {$iconClass} text-xl"></i>
                </div>
                {$badgeHtml}
            </div>

            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                    {$this->title}
                </h3>
                <h2 class="text-3xl font-black {$colors['text']} tracking-tight mb-2">
                    {$this->value}
                </h2>
            </div>

            <div class="mt-auto pt-2">
                <p class="text-xs text-gray-400 font-medium flex items-center gap-1">
                    <i class="fas fa-chart-area text-gray-300"></i>
                    {$this->subtitle}
                </p>
            </div>
        </div>
        HTML;
    }

    private function getColorClasses(string $color): array
    {
        // Maps input colors to specific Tailwind shades seen in screenshot
        return match ($color) {
            'blue' => ['bg' => 'bg-blue-600', 'text' => 'text-blue-600'],
            'purple' => ['bg' => 'bg-purple-600', 'text' => 'text-purple-600'],
            'amber', 'orange' => ['bg' => 'bg-amber-500', 'text' => 'text-amber-600'], // Orange in screenshot
            'emerald', 'green' => ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-600'], // Purple text used in customer card in screenshot, but we'll stick to theme
            default => ['bg' => 'bg-blue-600', 'text' => 'text-gray-900'],
        };
    }

    private function getFallbackIcon(): string
    {
        // Simple fallback logic
        $t = strtolower($this->title);
        if (str_contains($t, 'sale'))
            return 'fa-dollar-sign';
        if (str_contains($t, 'med'))
            return 'fa-capsules';
        if (str_contains($t, 'stock'))
            return 'fa-exclamation-triangle';
        if (str_contains($t, 'cust'))
            return 'fa-users';
        return 'fa-layer-group';
    }
}
?>
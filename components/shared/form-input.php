<?php
class FormInput {
    private string $type;
    private string $name;
    private string $label;
    private array $attributes;
    private ?string $iconClass; // Renamed to avoid conflict with 'icon' attribute

    public function __construct(string $type, string $name, string $label, array $attributes = []) {
        $this->type = $type;
        $this->name = $name;
        $this->label = $label;
        $this->iconClass = $attributes['icon'] ?? null; // Extract icon from attributes
        unset($attributes['icon']); // Remove icon from attributes passed to input tag
        $this->attributes = $attributes; // Assign remaining attributes
    }


    public function render(): string {
        $attributesString = '';
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $attributesString .= " {$key}";
            } else {
                $attributesString .= " {$key}=\"{$value}\"";
            }
        }

        $baseClasses = 'mt-1 w-full pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all shadow-sm';
        $inputHtml = '';
        $iconHtml = '';
        $inputWrapperClasses = '';

        if ($this->iconClass) {
            $iconHtml = "<i class='{$this->iconClass} absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400'></i>";
            $baseClasses .= ' pl-10'; // Add left padding to input
            $inputWrapperClasses = 'relative'; // Add relative positioning to wrapper
        }

        if ($this->type === 'textarea') {
            $value = $this->attributes['value'] ?? '';
            $inputHtml = "<textarea id='{$this->name}' name='{$this->name}' class='{$baseClasses}' {$attributesString}>{$value}</textarea>";
        } else {
            $inputHtml = "<input type='{$this->type}' id='{$this->name}' name='{$this->name}' class='{$baseClasses}' {$attributesString}>";
        }


        return <<<HTML
        <div>
            <label for="{$this->name}" class="block text-sm font-semibold text-gray-700 mb-2">{$this->label}</label>
            <div class="{$inputWrapperClasses}">
                {$iconHtml}
                {$inputHtml}
            </div>
        </div>
        HTML;
    }
}
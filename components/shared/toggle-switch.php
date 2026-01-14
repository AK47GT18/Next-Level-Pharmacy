<?php
class ToggleSwitch {
    private string $label;
    private string $name;
    private bool $checked;
    private ?string $description;

    public function __construct(string $label, string $name, bool $checked = false, ?string $description = null) {
        $this->label = $label;
        $this->name = $name;
        $this->checked = $checked;
        $this->description = $description;
    }

    public function render(): string {
        $checkedAttr = $this->checked ? 'checked' : '';
        $descriptionHtml = $this->description ? "<p class='text-sm text-gray-500'>{$this->description}</p>" : '';

        return <<<HTML
        <label for="{$this->name}" class="flex items-center justify-between cursor-pointer p-4 rounded-xl hover:bg-gray-50 transition-colors">
            <div class="flex-grow pr-4">
                <span class="font-semibold text-gray-800">{$this->label}</span>
                {$descriptionHtml}
            </div>
            <div class="relative">
                <input type="checkbox" id="{$this->name}" name="{$this->name}" class="sr-only" {$checkedAttr}>
                <div class="block bg-gray-200 w-14 h-8 rounded-full transition"></div>
                <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
            </div>
        </label>
        HTML;
    }
}
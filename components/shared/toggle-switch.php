<?php
class ToggleSwitch
{
    private string $label;
    private string $name;
    private bool $checked;
    private ?string $description;
    private string $id;

    public function __construct(string $label, string $name, bool $checked = false, ?string $description = null)
    {
        $this->label = $label;
        $this->name = $name;
        $this->checked = $checked;
        $this->description = $description;
        $this->id = 'toggle_' . preg_replace('/[^a-z0-9]/i', '_', $name);
    }

    public function render(): string
    {
        $checkedAttr = $this->checked ? 'checked' : '';
        $bgColor = $this->checked ? '#3b82f6' : '#d1d5db';
        $handlePosition = $this->checked ? '26px' : '2px';
        $descriptionHtml = $this->description ? "<p class='text-sm text-gray-500 mt-0.5'>{$this->description}</p>" : '';

        return <<<HTML
        <div class="toggle-row flex items-center justify-between py-4 px-2">
            <div class="flex-1 pr-6">
                <label for="{$this->id}" class="font-semibold text-gray-800 cursor-pointer block">{$this->label}</label>
                {$descriptionHtml}
            </div>
            <div class="flex-shrink-0">
                <label class="toggle-switch" style="position: relative; display: inline-block; width: 52px; height: 28px;">
                    <input type="checkbox" 
                           id="{$this->id}" 
                           name="{$this->name}" 
                           class="toggle-input"
                           style="opacity: 0; width: 0; height: 0;"
                           {$checkedAttr}
                           onchange="toggleSwitch(this)">
                    <span class="toggle-slider" style="
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: {$bgColor};
                        transition: 0.3s;
                        border-radius: 28px;
                    ">
                        <span class="toggle-knob" style="
                            position: absolute;
                            content: '';
                            height: 24px;
                            width: 24px;
                            left: {$handlePosition};
                            top: 2px;
                            background-color: white;
                            transition: 0.3s;
                            border-radius: 50%;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        "></span>
                    </span>
                </label>
            </div>
        </div>
        HTML;
    }

    public static function renderScript(): string
    {
        return <<<HTML
        <script>
        function toggleSwitch(checkbox) {
            const slider = checkbox.nextElementSibling;
            const knob = slider.querySelector('.toggle-knob');
            
            if (checkbox.checked) {
                slider.style.backgroundColor = '#3b82f6';
                knob.style.left = '26px';
            } else {
                slider.style.backgroundColor = '#d1d5db';
                knob.style.left = '2px';
            }
        }
        </script>
        HTML;
    }
}
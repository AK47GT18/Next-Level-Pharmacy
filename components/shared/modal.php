<?php
class Modal {
    private string $id;
    private string $title;
    private string $content;
    private array $buttons;
    private array $options;

    public function __construct(
        string $id,
        string $title,
        string $content,
        array $buttons = [],
        array $options = []
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->buttons = $buttons;
        $this->options = array_merge([
            'size' => 'md',
            'closeOnClickOutside' => true,
            'showCloseButton' => true
        ], $options);
    }

    public function render(): string {
        $sizeClass = $this->getSizeClass();
        $closeButton = $this->options['showCloseButton'] ? $this->renderCloseButton() : '';
        $buttons = $this->renderButtons();

        return <<<HTML
        <div id="{$this->id}" 
             class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center"
             data-modal>
            <div class="bg-white rounded-2xl shadow-xl {$sizeClass} animate-slide-in">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">{$this->title}</h3>
                        {$closeButton}
                    </div>
                </div>
                
                <div class="p-6 space-y-4">
                    {$this->content}
                </div>
                
                {$buttons}
            </div>
        </div>
        HTML;
    }

    private function getSizeClass(): string {
        return match($this->options['size']) {
            'sm' => 'w-full max-w-md',
            'lg' => 'w-full max-w-3xl',
            'xl' => 'w-full max-w-5xl',
            default => 'w-full max-w-2xl'
        };
    }

    private function renderCloseButton(): string {
        return <<<HTML
        <button class="p-2 hover:bg-gray-100 rounded-lg transition" data-close-modal>
            <i class="fas fa-times text-gray-400 hover:text-gray-600"></i>
        </button>
        HTML;
    }

    private function renderButtons(): string {
        if (empty($this->buttons)) return '';

        $buttonHtml = '';
        foreach ($this->buttons as $button) {
            $buttonHtml .= (new Button(
                $button['label'],
                $button['type'] ?? 'button',
                $button['color'] ?? 'blue',
                $button['icon'] ?? null,
                $button['attributes'] ?? []
            ))->render();
        }

        return <<<HTML
        <div class="p-6 border-t border-gray-100 flex items-center justify-end gap-3">
            {$buttonHtml}
        </div>
        HTML;
    }
}
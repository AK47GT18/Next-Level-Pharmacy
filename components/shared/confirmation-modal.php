<?php
class ConfirmationModal {
    private string $id;
    private string $title;
    private string $message;
    private string $confirmButtonText;
    private string $cancelButtonText;
    private string $confirmButtonColor;
    private string $type; // 'delete', 'success', 'info'

    public function __construct(
        string $id,
        string $title,
        string $message,
        string $confirmButtonText = 'Confirm',
        string $cancelButtonText = 'Cancel',
        string $confirmButtonColor = 'blue',
        string $type = 'info' // Default to info
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->message = $message;
        $this->confirmButtonText = $confirmButtonText;
        $this->cancelButtonText = $cancelButtonText;
        $this->type = $type;
        $this->confirmButtonColor = $this->getConfirmButtonColor($confirmButtonColor, $type);
    }

    private function getConfirmButtonColor(string $defaultColor, string $type): string {
        return match($type) {
            'delete' => 'red',
            'success' => 'green',
            default => $defaultColor
        };
    }

    private function getModalIcon(): string {
        return match($this->type) {
            'delete' => '<i class="fas fa-trash-alt text-rose-500 text-4xl mb-4"></i>',
            'success' => '<i class="fas fa-check-circle text-emerald-500 text-4xl mb-4"></i>',
            'info' => '<i class="fas fa-info-circle text-blue-500 text-4xl mb-4"></i>',
            default => ''
        };
    }

    public function render(): string {
        $confirmBtn = new Button($this->confirmButtonText, 'button', $this->confirmButtonColor, null, ['data-modal-confirm' => true]);
        $cancelBtn = new Button($this->cancelButtonText, 'button', 'gray', null, ['data-modal-cancel' => true]);
        $modalIcon = $this->getModalIcon();

        return <<<HTML
        <div id="{$this->id}" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all animate-slide-in">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-bold text-gray-900">{$this->title}</h3>
                    <button data-modal-close class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 space-y-4 text-center">
                    {$modalIcon}
                    <p class="text-gray-700">{$this->message}</p>
                </div>
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100">
                    {$cancelBtn->render()}
                    {$confirmBtn->render()}
                </div>
            </div>
        </div>

        <script>
        (function(){
            const modal = document.getElementById("{$this->id}");
            if (!modal) return;

            function show() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
            function hide() { modal.classList.remove('flex'); modal.classList.add('hidden'); modal.dataset.confirmed = 'false'; }

            document.querySelectorAll('[data-open-modal="{$this->id}"]').forEach(btn => btn.addEventListener('click', show));
            modal.querySelectorAll('[data-modal-close], [data-modal-cancel]').forEach(el => el.addEventListener('click', hide));
            modal.addEventListener('click', (e) => { if(e.target === modal) hide(); });

            modal.querySelector('[data-modal-confirm]')?.addEventListener('click', () => {
                // Handle confirmation logic here
                modal.dataset.confirmed = 'true'; // Set a flag for external JS to check
                hide();
            });
        })();
        </script>
        HTML;
    }
}
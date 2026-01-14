<?php
class Header
{
    public function render(?NotificationBell $notificationBell = null): string
    {
        $notificationBellHtml = $notificationBell ? $notificationBell->render() : '';

        return <<<HTML
        <header class="glassmorphism sticky top-0 z-30 border-b border-white/40">
            <div class="h-16 flex items-center justify-between px-4 md:px-8">
                <button id="mobileSidebarToggle" class="p-2 -ml-2 hover:bg-slate-100 rounded-lg transition md:hidden text-slate-600">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="flex items-center gap-4 flex-1 max-w-xl mx-auto md:ml-0">
                    <div class="relative w-full md:w-96 group">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text"
                               placeholder="Search..."
                               class="w-full pl-10 pr-4 py-2 bg-white/50 border-none rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition-all text-sm placeholder-slate-400 font-numeric shadow-sm">
                        <div class="hidden md:flex absolute right-2 top-1/2 transform -translate-y-1/2 gap-1">
                            <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px] text-slate-400 font-sans">⌘K</kbd>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    {$notificationBellHtml}
                </div>
            </div>
        </header>
        HTML;
    }
}
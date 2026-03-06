<?php
class Header
{
    public function render(?NotificationBell $notificationBell = null): string
    {
        $notificationBellHtml = $notificationBell ? $notificationBell->render() : '';

        return <<<HTML
        <header class="glassmorphism sticky top-0 z-30 border-b border-slate-200/60 bg-white/70 backdrop-blur-md">
            <div class="h-16 flex items-center justify-between px-4 md:px-8 max-w-[1600px] mx-auto">
                
                <button id="mobileSidebarToggle" class="p-2 -ml-2 hover:bg-slate-100 rounded-xl transition-all md:hidden text-slate-500 active:scale-95" aria-label="Toggle Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <div class="flex-1 flex justify-center md:justify-start px-4">
                    <div class="relative w-full max-w-[480px] group">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-xl opacity-0 group-focus-within:opacity-10 blur transition duration-300"></div>
                        
                        <div class="relative flex items-center">
                            <div class="absolute left-4 flex items-center justify-center text-slate-400 group-focus-within:text-blue-600 transition-colors duration-200">
                                <i class="fas fa-search text-sm"></i>
                            </div>

                            <input type="text"
                                   id="globalSearch"
                                   name="search"
                                   placeholder="Search products, sales, reports..."
                                   autocomplete="off"
                                   class="w-full pl-12 pr-20 py-2.5 bg-slate-100/50 border border-transparent rounded-xl 
                                          focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white focus:border-blue-400
                                          transition-all duration-300 text-sm text-slate-700 placeholder:text-slate-400 font-medium
                                          group-hover:bg-slate-100 group-hover:border-slate-200"
                                   aria-label="Search">

                            <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-xl border border-slate-200 max-h-96 overflow-y-auto z-50">
                            </div>

                            <div class="hidden sm:flex absolute right-2.5 items-center gap-1">
                                <kbd class="flex items-center justify-center h-6 w-10 text-[10px] font-sans font-semibold text-slate-400 bg-white border border-slate-200 rounded-md shadow-sm">
                                    ⌘K
                                </kbd>
                            </div>
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
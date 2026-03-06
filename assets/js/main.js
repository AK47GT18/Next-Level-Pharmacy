// Global JavaScript for common UI interactions

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Menu Functionality
    const mobileMenuEl = document.getElementById('mobileMenu');
    const mobileMenuContent = mobileMenuEl?.querySelector('div'); // Select the direct child div
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const closeMobileMenu = document.getElementById('closeMobileMenu');

    const toggleMobileMenu = () => {
        mobileMenuEl?.classList.toggle('hidden');
        // Add a small delay to allow the backdrop to appear first
        setTimeout(() => {
            mobileMenuContent?.classList.toggle('-translate-x-full');
        }, 50);
    };

    const closeMobileMenuHandler = () => {
        mobileMenuContent?.classList.add('-translate-x-full');
        setTimeout(() => {
            mobileMenuEl?.classList.add('hidden');
        }, 300); // Match CSS transition duration
    };

    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleMobileMenu);
    if (closeMobileMenu) closeMobileMenu.addEventListener('click', closeMobileMenuHandler);
    
    // Close menu when clicking outside
    mobileMenuEl?.addEventListener('click', (e) => {
        if (e.target === mobileMenuEl) {
            closeMobileMenuHandler();
        }
    });

    // Prevent closing when clicking inside menu
    mobileMenuContent?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Close mobile menu on window resize (if screen becomes larger)
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            closeMobileMenuHandler();
        }
    });

    // --- Global Search Functionality ---
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout = null;

    if (searchInput && searchResults) {
        // Debounce function
        const debounce = (func, wait) => {
            return function executedFunction(...args) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => func.apply(this, args), wait);
            };
        };

        // Perform search
        const performSearch = async (query) => {
            if (query.length < 2) {
                searchResults.classList.add('hidden');
                searchResults.innerHTML = '';
                return;
            }

            try {
                // Get base URL more reliably
                const baseUrl = window.location.origin + (window.location.pathname.includes('/index.php') ? 
                    window.location.pathname.replace('/index.php', '') : 
                    window.location.pathname.replace(/\/[^\/]*$/, ''));
                const response = await fetch(`${baseUrl}/api/search.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.results && data.results.length > 0) {
                    displayResults(data.results, query);
                } else {
                    displayNoResults();
                }
            } catch (error) {
                console.error('Search error:', error);
                displayNoResults();
            }
        };

        // Display search results
        const displayResults = (results, query) => {
            const html = results.map(item => {
                const highlight = (text) => {
                    const regex = new RegExp(`(${query})`, 'gi');
                    return text.replace(regex, '<mark class="bg-blue-100 text-blue-700">$1</mark>');
                };

                return `
                    <a href="?page=inventory&search=${encodeURIComponent(item.name)}" 
                       class="block px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-b-0 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">
                                    ${highlight(item.name)}
                                </p>
                                <p class="text-xs text-gray-500 truncate">
                                    ${item.category_name || 'Uncategorized'} • ${item.type_name || 'Product'}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">MWK ${parseFloat(item.price).toLocaleString()}</p>
                                <p class="text-xs ${item.stock < 10 ? 'text-red-500' : 'text-green-500'}">
                                    ${item.stock} in stock
                                </p>
                            </div>
                        </div>
                    </a>
                `;
            }).join('');

            // Add navigation links
            const navHtml = `
                <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500">Quick Links</span>
                    <div class="flex gap-2">
                        <a href="?page=inventory" class="text-xs text-blue-600 hover:underline">Inventory</a>
                        <a href="?page=pos&view=sales-history" class="text-xs text-blue-600 hover:underline">Sales</a>
                        <a href="?page=reports&view=sales" class="text-xs text-blue-600 hover:underline">Reports</a>
                    </div>
                </div>
            `;

            searchResults.innerHTML = navHtml + html;
            searchResults.classList.remove('hidden');
        };

        // Display no results message
        const displayNoResults = () => {
            searchResults.innerHTML = `
                <div class="px-4 py-8 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900">No results found</p>
                    <p class="text-xs text-gray-500 mt-1">Try different keywords</p>
                </div>
            `;
            searchResults.classList.remove('hidden');
        };

        // Event listeners
        searchInput.addEventListener('input', debounce((e) => {
            performSearch(e.target.value.trim());
        }, 300));

        // Show results on focus if there's text
        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2) {
                performSearch(searchInput.value.trim());
            }
        });

        // Hide results when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });

        // Show results container on input focus
        searchInput.addEventListener('focus', () => {
            searchInput.closest('.group').classList.add('search-active');
        });

        searchInput.addEventListener('blur', () => {
            searchInput.closest('.group').classList.remove('search-active');
        });

        // Keyboard navigation - Enter to search
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query) {
                    // Navigate to inventory page with search
                    window.location.href = `?page=inventory&search=${encodeURIComponent(query)}`;
                }
            }
            if (e.key === 'Escape') {
                searchResults.classList.add('hidden');
                searchInput.blur();
            }
        });

        // Cmd+K to focus search
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    }

    // Add other global interactive elements here (e.g., dropdowns, modals)
});
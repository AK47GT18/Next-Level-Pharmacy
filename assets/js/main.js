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

    // Add other global interactive elements here (e.g., dropdowns, modals)
});
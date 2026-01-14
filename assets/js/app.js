import { router } from './router.js';
import { api } from './api.js';
import * as utils from './utils.js';

class App {
    constructor() {
        this.init();
        this.setupEventListeners();
    }

    init() {
        // Check authentication
        this.checkAuth();
        
        // Initialize components
        this.initializeComponents();
    }

    async checkAuth() {
        const isLoggedIn = sessionStorage.getItem('user');
        if (!isLoggedIn && window.location.pathname !== '/login.php') {
            window.location.href = '/login.php';
        }
    }

    initializeComponents() {
        // Initialize mobile menu
        this.initMobileMenu();
        
        // Initialize search
        this.initSearch();
        
        // Initialize notifications
        this.initNotifications();
    }

    setupEventListeners() {
        // Global event listeners
        document.addEventListener('click', this.handleGlobalClick.bind(this));
        
        // Form submissions
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
    }

    initMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const toggle = document.getElementById('mobileSidebarToggle');
        const close = document.getElementById('closeMobileMenu');

        if (toggle && menu && close) {
            toggle.addEventListener('click', () => {
                menu.classList.toggle('hidden');
            });

            close.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        }
    }

    initSearch() {
        const searchInput = document.querySelector('[type="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', utils.debounce((e) => {
                this.handleSearch(e.target.value);
            }, 300));
        }
    }

    async handleSearch(query) {
        try {
            const results = await api.search(query);
            // Handle search results
        } catch (error) {
            utils.showToast(error.message, 'error');
        }
    }

    handleGlobalClick(e) {
        // Handle dropdown toggles
        if (e.target.matches('[data-dropdown-toggle]')) {
            const targetId = e.target.dataset.dropdownToggle;
            document.getElementById(targetId).classList.toggle('hidden');
        }
    }

    async handleFormSubmit(e) {
        if (e.target.matches('[data-form]')) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const errors = utils.validateForm(e.target);

            if (errors.length) {
                errors.forEach(error => utils.showToast(error, 'error'));
                return;
            }

            try {
                const response = await api.request(e.target.action, {
                    method: 'POST',
                    body: formData
                });
                utils.showToast(response.message, 'success');
            } catch (error) {
                utils.showToast(error.message, 'error');
            }
        }
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new App();
});
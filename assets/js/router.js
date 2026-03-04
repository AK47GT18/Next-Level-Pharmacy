class Router {
    constructor(routes) {
        this.routes = routes;
        this.currentRoute = null;
        
        // Handle navigation
        window.addEventListener('popstate', () => this.handleRoute());
        document.addEventListener('click', e => this.handleClick(e));
        
        // Initial route
        this.handleRoute();
    }

    async handleRoute() {
        const path = window.location.hash.slice(1) || '/';
        const route = this.routes[path] || this.routes['/404'];
        
        try {
            // Update active navigation
            this.updateNavigation(path);
            
            // Load and render page
            const page = await route.component();
            document.getElementById('main-content').innerHTML = page;
            
            // Execute page script if exists
            if (route.script) {
                route.script();
            }
            
            this.currentRoute = path;
        } catch (error) {
            console.error('Routing error:', error);
        }
    }

    handleClick(e) {
        const link = e.target.closest('a[href^="#"]');
        if (link) {
            e.preventDefault();
            const path = link.getAttribute('href').slice(1);
            this.navigate(path);
        }
    }

    navigate(path) {
        window.location.hash = path;
    }

    updateNavigation(path) {
        // Remove active class from all nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to current nav item
        const activeNav = document.querySelector(`[data-page="${path}"]`);
        if (activeNav) {
            activeNav.classList.add('active');
        }
    }
}

// Define routes
const routes = {
    '/': {
        component: () => import('../pages/dashboard/index.js'),
        script: () => console.log('Dashboard loaded')
    },
    '/inventory': {
        component: () => import('../pages/inventory/index.js'),
        script: () => console.log('Inventory loaded')
    },
    '/pos': {
        component: () => import('../pages/pos/index.js'),
        script: () => console.log('POS loaded')
    }
    // Add more routes as needed
};

// Export router instance
export const router = new Router(routes);
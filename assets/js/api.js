class API {
    constructor() {
        this.baseUrl = '/Next-Level/rxpms/api';
        this.headers = {
            'Content-Type': 'application/json'
        };
    }

    async request(endpoint, options = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/${endpoint}`, {
                ...options,
                headers: this.headers
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Auth endpoints
    async login(username, password) {
        return this.request('auth/login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
    }

    async logout() {
        return this.request('auth/logout', { method: 'POST' });
    }

    // Inventory endpoints
    async getMedicines(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`inventory/list?${queryString}`);
    }

    async getMedicine(id) {
        return this.request(`inventory/get?id=${id}`);
    }

    // Sales endpoints
    async getSales(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`sales/list?${queryString}`);
    }

    async createSale(saleData) {
        return this.request('sales/create', {
            method: 'POST',
            body: JSON.stringify(saleData)
        });
    }

    // Dashboard endpoints
    async getDashboardStats() {
        return this.request('dashboard/stats');
    }
}

// Export API instance
export const api = new API();
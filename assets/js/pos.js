import { api } from './api.js';
import { showToast, formatCurrency } from './utils.js';

class POS {
    constructor() {
        this.cart = new Cart();
        this.init();
    }

    async init() {
        await this.loadProducts();
        await this.loadCustomers();
        this.setupEventListeners();
        this.updateDateTime();
    }

    async loadProducts() {
        try {
            const products = await api.request('inventory/list.php');
            this.renderProducts(products.items);
        } catch (error) {
            showToast('Failed to load products', 'error');
        }
    }

    renderProducts(products) {
        const grid = document.getElementById('productsGrid');
        grid.innerHTML = products.map(product => `
            <div class="product-card glassmorphism rounded-2xl p-4 cursor-pointer hover:shadow-lg transition"
                 onclick="addToCart(${product.id})">
                <div class="w-full aspect-square bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-pills text-blue-600 text-2xl"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">${product.name}</h3>
                <p class="text-sm text-gray-500 mb-2">${product.generic_name}</p>
                <div class="flex items-center justify-between">
                    <span class="font-bold text-blue-600">${formatCurrency(product.selling_price)}</span>
                    <span class="text-xs text-gray-500">${product.quantity_in_stock} in stock</span>
                </div>
            </div>
        `).join('');
    }

    async loadCustomers() {
        try {
            const customers = await api.request('customers/list.php');
            this.renderCustomers(customers);
        } catch (error) {
            showToast('Failed to load customers', 'error');
        }
    }

    renderCustomers(customers) {
        const select = document.getElementById('customerSelect');
        select.innerHTML += customers.map(customer => `
            <option value="${customer.id}">${customer.name}</option>
        `).join('');
    }

    setupEventListeners() {
        // Payment method change
        document.querySelector('[name="payment_method"]').addEventListener('change', (e) => {
            const changeDiv = document.getElementById('changeAmount');
            changeDiv.classList.toggle('hidden', e.target.value !== 'cash');
        });

        // Amount received input
        document.querySelector('[name="amount_received"]').addEventListener('input', (e) => {
            const change = e.target.value - this.cart.getTotal();
            if (change >= 0) {
                document.querySelector('#changeAmount input').value = formatCurrency(change);
            }
        });
    }

    updateDateTime() {
        const dateElement = document.getElementById('cartDate');
        setInterval(() => {
            dateElement.textContent = new Date().toLocaleString();
        }, 1000);
    }
}

class Cart {
    constructor() {
        this.items = [];
        this.customer = null;
    }

    addItem(product, quantity = 1) {
        const existingItem = this.items.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                price: product.selling_price,
                quantity
            });
        }

        this.render();
    }

    removeItem(id) {
        this.items = this.items.filter(item => item.id !== id);
        this.render();
    }

    updateQuantity(id, quantity) {
        const item = this.items.find(item => item.id === id);
        if (item) {
            item.quantity = quantity;
            this.render();
        }
    }

    getSubtotal() {
        return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    }

    getTax() {
        return this.getSubtotal() * 0.165;
    }

    getTotal() {
        return this.getSubtotal() + this.getTax();
    }

    render() {
        const cartElement = document.getElementById('cartItems');
        cartElement.innerHTML = this.items.map(item => `
            <div class="flex items-start gap-4 mb-4 p-4 bg-white rounded-xl">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-pills text-blue-600"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-900">${item.name}</h4>
                    <p class="text-sm text-gray-500 mb-2">${formatCurrency(item.price)} each</p>
                    <div class="flex items-center gap-2">
                        <button class="p-1 hover:bg-gray-100 rounded" 
                                onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <input type="number" value="${item.quantity}" min="1" 
                               class="w-16 text-center border rounded-lg"
                               onchange="updateQuantity(${item.id}, this.value)">
                        <button class="p-1 hover:bg-gray-100 rounded"
                                onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-bold text-gray-900">${formatCurrency(item.price * item.quantity)}</p>
                    <button class="text-rose-600 hover:text-rose-700 text-sm mt-2" 
                            onclick="removeFromCart(${item.id})">
                        Remove
                    </button>
                </div>
            </div>
        `).join('');

        // Update totals
        document.getElementById('subtotal').textContent = formatCurrency(this.getSubtotal());
        document.getElementById('tax').textContent = formatCurrency(this.getTax());
        document.getElementById('total').textContent = formatCurrency(this.getTotal());

        // Enable/disable checkout button
        document.getElementById('checkoutButton').disabled = this.items.length === 0;
    }

    async checkout(paymentDetails) {
        try {
            const response = await api.request('pos/checkout.php', {
                method: 'POST',
                body: JSON.stringify({
                    customer_id: this.customer,
                    items: this.items,
                    payment: paymentDetails
                })
            });

            showToast('Sale completed successfully', 'success');
            this.clear();
            return response;
        } catch (error) {
            showToast(error.message, 'error');
            throw error;
        }
    }

    clear() {
        this.items = [];
        this.customer = null;
        this.render();
    }
}

// Initialize POS
const pos = new POS();

// Global functions for HTML onclick events
window.addToCart = (productId) => {
    // Implementation
};

window.removeFromCart = (productId) => {
    // Implementation
};

window.updateQuantity = (productId, quantity) => {
    // Implementation
};

window.showCheckoutModal = () => {
    // Implementation
};

window.showAddCustomerModal = () => {
    // Implementation
};
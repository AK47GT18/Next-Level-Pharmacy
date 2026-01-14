// Format currency
export const formatCurrency = (amount, currency = 'MWK') => {
    return new Intl.NumberFormat('en-MW', {
        style: 'currency',
        currency: currency
    }).format(amount);
};

// Format date
export const formatDate = (date, format = 'full') => {
    const d = new Date(date);
    const options = format === 'full' 
        ? { dateStyle: 'full', timeStyle: 'short' }
        : { dateStyle: 'medium' };
    
    return new Intl.DateTimeFormat('en-MW', options).format(d);
};

// Show toast notification
export const showToast = (message, type = 'success') => {
    const toast = document.createElement('div');
    toast.className = `toast ${type} animate-slide-in`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 3000);
};

// Debounce function
export const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Validate form inputs
export const validateForm = (form) => {
    const errors = [];
    const required = form.querySelectorAll('[required]');
    
    required.forEach(field => {
        if (!field.value.trim()) {
            errors.push(`${field.name} is required`);
        }
    });
    
    return errors;
};
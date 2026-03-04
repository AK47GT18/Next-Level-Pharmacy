<?php
/**
 * RxPMS Constants
 * Application-wide constant values
 */

// Prevent direct access
//defined('APP_ENTRY') or die('Direct access not permitted');

// User Roles
define('ROLE_ADMIN', 1);
define('ROLE_PHARMACIST', 2);
define('ROLE_CASHIER', 3);

define('ROLE_NAMES', [
    ROLE_ADMIN => 'Administrator',
    ROLE_PHARMACIST => 'Pharmacist',
    ROLE_CASHIER => 'Cashier'
]);

// Status Values
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);
define('STATUS_DELETED', -1);

// Sale Status
define('SALE_COMPLETED', 1);
define('SALE_PENDING', 0);
define('SALE_CANCELLED', -1);

define('SALE_STATUS_LABELS', [
    SALE_COMPLETED => ['label' => 'Completed', 'class' => 'bg-emerald-50 text-emerald-700'],
    SALE_PENDING => ['label' => 'Pending', 'class' => 'bg-amber-50 text-amber-700'],
    SALE_CANCELLED => ['label' => 'Cancelled', 'class' => 'bg-rose-50 text-rose-700']
]);

// Payment Methods
define('PAYMENT_METHODS', [
    'cash' => ['label' => 'Cash', 'icon' => 'fa-money-bill'],
    'card' => ['label' => 'Card', 'icon' => 'fa-credit-card'],
    'mobile_money' => ['label' => 'Mobile Money', 'icon' => 'fa-mobile-alt']
]);

// Stock Alert Levels
define('STOCK_CRITICAL', 10);  // Red alert
define('STOCK_LOW', 20);       // Yellow alert
define('STOCK_MODERATE', 50);  // Green alert

define('STOCK_LEVELS', [
    'critical' => [
        'threshold' => STOCK_CRITICAL,
        'label' => 'Critical',
        'class' => 'bg-rose-50 text-rose-700 border-rose-500'
    ],
    'low' => [
        'threshold' => STOCK_LOW,
        'label' => 'Low',
        'class' => 'bg-amber-50 text-amber-700 border-amber-500'
    ],
    'moderate' => [
        'threshold' => STOCK_MODERATE,
        'label' => 'Moderate',
        'class' => 'bg-emerald-50 text-emerald-700 border-emerald-500'
    ]
]);

// Medicine Categories
define('MEDICINE_CATEGORIES', [
    'Analgesics',
    'Antibiotics',
    'Antifungal',
    'Anti protozoa',
    'Anthelmintics',
    'Antiviral',
    'Antidepressants',
    'Antipsychotics',
    'Corticosteroids',
    'Anti asthmatics',
    'Hypertensives',
    'Antihistamines',
    'Anti acids',
    'Proton pump inhibitors',
    'Antiemetics',
    'Antidiabetics',
    'Appetite stimulants',
    'Anti hemorrhoids',
    'Laxatives',
    'Hormonal contraceptives',
    'Triple action creams',
    'Sanitary products',
    'Supplements',
    'Cough & Cold Syrups',
    'Vitamins & Supplements',
    'Cardiovascular',
    'Diabetes',
    'Respiratory',
    'Gastrointestinal',
    'Other'
]);

// Product Categories - expanded to include all product types
define('PRODUCT_CATEGORIES', [
    // Medicines
    'analgesics' => 'Analgesics',
    'antibiotics' => 'Antibiotics',
    'antifungal' => 'Antifungal',
    'anti_protozoa' => 'Anti protozoa',
    'anthelmintics' => 'Anthelmintics',
    'antiviral' => 'Antiviral',
    'antidepressants' => 'Antidepressants',
    'antipsychotics' => 'Antipsychotics',
    'corticosteroids' => 'Corticosteroids',
    'anti_asthmatics' => 'Anti asthmatics',
    'hypertensives' => 'Hypertensives',
    'antihistamines' => 'Antihistamines',
    'anti_acids' => 'Anti acids',
    'proton_pump_inhibitors' => 'Proton pump inhibitors',
    'antiemetics' => 'Antiemetics',
    'antidiabetics' => 'Antidiabetics',
    'appetite_stimulants' => 'Appetite stimulants',
    'anti_hemorrhoids' => 'Anti hemorrhoids',
    'laxatives' => 'Laxatives',
    'hormonal_contraceptives' => 'Hormonal contraceptives',
    'triple_action_creams' => 'Triple action creams',
    'sanitary_products' => 'Sanitary products',
    'supplements' => 'Supplements',
    'cough_cold_syrups' => 'Cough & Cold Syrups',
    
    // Legacy/Generic Categories
    'painkiller' => 'Painkiller',
    'antibiotic' => 'Antibiotic',
    'cold_flu' => 'Cold & Flu',
    'vitamin' => 'Vitamin & Supplement',
    'cardiac' => 'Cardiac',
    'diabetes' => 'Diabetes',
    'medicine_other' => 'Other Medicine',
    
    // Cosmetics
    'makeup' => 'Makeup',
    'skincare' => 'Skincare',
    'haircare' => 'Haircare',
    'fragrance' => 'Fragrance / Perfume',
    'cosmetic_other' => 'Other Cosmetics',
    
    // Skincare Products
    'facial_care' => 'Facial Care',
    'body_care' => 'Body Care',
    'sun_protection' => 'Sun Protection',
    'anti_aging' => 'Anti-Aging',
    'acne_treatment' => 'Acne Treatment',
    
    // General
    'other' => 'Other'
]);

define('PRODUCT_TYPES', [
    'medicine' => 'Medicine',
    'cosmetic' => 'Cosmetic',
    'skincare' => 'Skincare',
    'perfume' => 'Perfume'
]);

// UI Constants
define('UI_COLORS', [
    'primary' => [
        'light' => 'bg-blue-50',
        'text' => 'text-blue-600',
        'border' => 'border-blue-500',
        'bg' => 'bg-blue-500',
        'hover' => 'hover:bg-blue-600'
    ],
    'success' => [
        'light' => 'bg-emerald-50',
        'text' => 'text-emerald-600',
        'border' => 'border-emerald-500',
        'bg' => 'bg-emerald-500',
        'hover' => 'hover:bg-emerald-600'
    ],
    'warning' => [
        'light' => 'bg-amber-50',
        'text' => 'text-amber-600',
        'border' => 'border-amber-500',
        'bg' => 'bg-amber-500',
        'hover' => 'hover:bg-amber-600'
    ],
    'danger' => [
        'light' => 'bg-rose-50',
        'text' => 'text-rose-600',
        'border' => 'border-rose-500',
        'bg' => 'bg-rose-500',
        'hover' => 'hover:bg-rose-600'
    ]
]);

// Navigation Menu
define('NAV_MENU', [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-home',
        'roles' => [ROLE_ADMIN, ROLE_PHARMACIST, ROLE_CASHIER]
    ],
    'inventory' => [
        'label' => 'Inventory',
        'icon' => 'fa-boxes',
        'roles' => [ROLE_ADMIN, ROLE_PHARMACIST]
    ],
    'pos' => [
        'label' => 'Sales / POS',
        'icon' => 'fa-cash-register',
        'roles' => [ROLE_ADMIN, ROLE_CASHIER]
    ],
    'suppliers' => [
        'label' => 'Suppliers',
        'icon' => 'fa-truck',
        'roles' => [ROLE_ADMIN, ROLE_PHARMACIST]
    ],
    'reports' => [
        'label' => 'Reports',
        'icon' => 'fa-chart-line',
        'roles' => [ROLE_ADMIN]
    ],
    'settings' => [
        'label' => 'Settings',
        'icon' => 'fa-cog',
        'roles' => [ROLE_ADMIN]
    ]
]);
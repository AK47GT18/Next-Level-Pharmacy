# RxPMS - Next Level Pharmacy Management System

![Dashboard Preview](https://img.shields.io/badge/Status-Version%201.0.0-blue?style=for-the-badge)
![Tech Stack](https://img.shields.io/badge/Stack-PHP%20|%20MySQL%20|%20Tailwind-emerald?style=for-the-badge)

RxPMS is a modern, responsive, and professional Pharmacy Management System designed for high-efficiency pharmaceutical retail. It features a premium UI with glassmorphism aesthetics, real-time analytics, and robust inventory control.

## 🚀 Key Features

### 📊 Intelligent Dashboard

- **Real-time Stats**: Track Today's Sales, Inventory Assets, and Low Stock alerts at a glance.
- **Visual Analytics**: Interactive charts showing 30-day sales trends and payment method breakdowns.
- **Critical Alerts**: Instant notification of out-of-stock or expiring medicines.

### 💊 Advanced Inventory Control

- **Receive Products**: Dedicated flow for receiving new stock with unit cost price tracking and history logging.
- **Categorization**: Support for 24+ medical categories (Analgesics, Antibiotics, etc.) plus Cosmetics and Skincare.
- **Asset Valuation**: Real-time calculation of total inventory value (MWK).
- **Staggered Entrance**: Premium UI row animations for a fluid user experience.

### 💰 Sales & POS

- **Seamless Checkout**: Rapid POS interface for processing sales via Cash, Card, or Mobile Money.
- **Daily Itemized Reports**: Deep-dive into each day's performance with item-by-item revenue summaries.
- **Invoice Tracking**: Professional invoice management with time-ago indicators.

---

## 🛠️ Tech Stack

- **Backend**: PHP 7.4+ (Modular & Singleton Architecture)
- **Database**: MySQL (Optimized queries for Shared Hosting/InfinityFree)
- **Frontend**: Tailwind CSS (Glassmorphism), Vanilla JavaScript (No heavy frameworks)
- **Icons**: Font Awesome 6 Pro styling
- **Animations**: Custom CSS `animations.css` (Shimmer effects, slide-ins)

---

## 📦 Installation & Setup

1. **Clone the Repository**:

   ```bash
   git clone https://github.com/AK47GT18/Next-Level-Pharmacy.git
   ```

2. **Configure Database**:

   - Create a MySQL database (e.g., `rxpms_db`).
   - Import the schema (SQL files).
   - Update `config/config.php` with your credentials:
     ```php
     define('DB_HOST', 'your_host');
     define('DB_NAME', 'your_db_name');
     define('DB_USER', 'your_user');
     define('DB_PASS', 'your_pass');
     ```

3. **Sync Medicine Categories**:

   - Visit the setup script in your browser to populate the 24+ medical categories:
     `http://yourdomain.com/scripts/setup-db.php`

4. **Deploy**:
   - Works perfectly on standard PHP hosting (XAMPP, InfinityFree, Laragon).

---

## 📂 Project Structure

```text
├── api/            # REST-like endpoints (JSON)
├── assets/         # CSS, JS, Fonts, and Animations
├── classes/        # OOP Models (Product, Sale, StockLog)
├── components/     # Reusable UI Blocks (Modals, StatCards)
├── config/         # Constants and Database Singleton
├── includes/       # PathHelpers and Global Utilities
├── pages/          # View Controllers (Dashboard, Inventory, Reports)
└── scripts/        # Setup and Maintenance scripts
```

## 📜 License

Proprietary - Developed by AK47GT for Next Level Pharmacy. All rights reserved.

-- ==============================================
-- Next-Level Pharmacy - Performance Indexes
-- Run this in phpMyAdmin on InfinityFree
-- ==============================================

-- Products: filtered by is_deleted on almost every query
ALTER TABLE products ADD INDEX idx_is_deleted (is_deleted);
-- Products: composite index for stock alerts
ALTER TABLE products ADD INDEX idx_stock_alert (is_deleted, stock, low_stock_threshold);
-- Products: expiry lookups
ALTER TABLE products ADD INDEX idx_expiry (is_deleted, has_expiry, expiry_date);
-- Products: name search
ALTER TABLE products ADD INDEX idx_name (name);
-- Sales: date-based queries (dashboard, reports)
ALTER TABLE sales ADD INDEX idx_created_at (created_at);
-- Stock logs: product history lookups
ALTER TABLE stock_logs ADD INDEX idx_product_created (product_id, created_at);
-- Notifications: user + read status
ALTER TABLE notifications ADD INDEX idx_user_read (user_id, `read`);

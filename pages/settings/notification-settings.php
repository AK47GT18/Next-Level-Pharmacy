<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\settings\notification-settings.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../components/shared/toggle-switch.php';
require_once __DIR__ . '/../../config/database.php';

// ✅ Fetch user's notification settings from backend
$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Default settings if none exist
$notificationSettings = [
    'email_notifications' => (bool)($settings['email_notifications'] ?? true),
    'low_stock_alerts' => (bool)($settings['low_stock_alerts'] ?? true),
    'expiring_soon_alerts' => (bool)($settings['expiring_soon_alerts'] ?? true),
    'daily_sales_summary' => (bool)($settings['daily_sales_summary'] ?? false),
    'system_updates' => (bool)($settings['system_updates'] ?? true),
];
?>

<div class="space-y-6 animate-slide-in">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Notification Settings</h1>
            <p class="text-gray-600">Manage how you receive notifications from the system.</p>
        </div>
        <div>
            <a href="?page=settings" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </div>
    </div>

    <div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100">
        <form id="notificationSettingsForm">
            <div class="space-y-2 divide-y divide-gray-100">
                <?= (new ToggleSwitch('Enable Email Notifications', 'email_notifications', $notificationSettings['email_notifications'], 'Master switch for all email alerts.'))->render() ?>
                <?= (new ToggleSwitch('Low Stock Alerts', 'low_stock_alerts', $notificationSettings['low_stock_alerts'], 'Get notified when inventory items are running low.'))->render() ?>
                <?= (new ToggleSwitch('Expiring Soon Alerts', 'expiring_soon_alerts', $notificationSettings['expiring_soon_alerts'], 'Receive alerts for medicines approaching their expiry date.'))->render() ?>
                <?= (new ToggleSwitch('Daily Sales Summary', 'daily_sales_summary', $notificationSettings['daily_sales_summary'], 'Receive a summary of sales at the end of each day.'))->render() ?>
                <?= (new ToggleSwitch('System Updates & Announcements', 'system_updates', $notificationSettings['system_updates'], 'Stay informed about new features and important announcements.'))->render() ?>
            </div>

            <div class="flex justify-end mt-6 pt-6 border-t border-gray-100">
                <?= (new Button('Save Notification Settings', 'submit', 'blue'))->render() ?>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('notificationSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    // ✅ Convert checkboxes to boolean
    const settings = {};
    ['email_notifications', 'low_stock_alerts', 'expiring_soon_alerts', 'daily_sales_summary', 'system_updates'].forEach(key => {
        settings[key] = data[key] === 'on';
    });

    try {
        const response = await fetch('/Next-Level/rxpms/api/notification-settings.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });

        const result = await response.json();

        if (response.ok) {
            // ✅ Show success message
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in';
            successMsg.innerHTML = '<i class="fas fa-check-circle"></i><span>Notification settings saved successfully!</span>';
            document.body.appendChild(successMsg);
            
            setTimeout(() => successMsg.remove(), 3000);
        } else {
            alert('Error: ' + (result.message || 'Failed to save settings'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save notification settings');
    }
});
</script>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
</style>
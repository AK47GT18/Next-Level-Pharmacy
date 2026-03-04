<?php
// filepath: c:\xampp5\htdocs\Next-Level\pages\settings\notification-settings.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../components/shared/toggle-switch.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/PathHelper.php';

// ✅ Fetch user's notification settings from backend
$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ If settings don't exist, create defaults
if (!$settings) {
    $insertStmt = $conn->prepare("
        INSERT INTO user_notification_settings 
        (user_id, email_notifications, low_stock_alerts, expiring_soon_alerts, daily_sales_summary, system_updates) 
        VALUES (?, 0, 1, 1, 0, 1)
    ");
    $insertStmt->execute([$userId]);
    
    // Fetch again after insert
    $stmt->execute([$userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ✅ Default settings (bell notifications only)
$notificationSettings = [
    'low_stock_alerts' => (bool) ($settings['low_stock_alerts'] ?? true),
    'expiring_soon_alerts' => (bool) ($settings['expiring_soon_alerts'] ?? true),
    'daily_sales_summary' => (bool) ($settings['daily_sales_summary'] ?? false),
    'system_updates' => (bool) ($settings['system_updates'] ?? true),
];

$baseUrl = PathHelper::getBaseUrl();
?>

<style>
.toggle-row {
    border-bottom: 1px solid #f1f5f9;
}
.toggle-row:last-child {
    border-bottom: none;
}
.toggle-row:hover {
    background-color: #f8fafc;
}
</style>

<div class="space-y-6 animate-slide-in">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Notification Settings</h1>
            <p class="text-gray-600">Manage which notifications appear in your notification bell.</p>
        </div>
        <div>
            <a href="?page=settings"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-slate-50 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-bell text-blue-600"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Bell Notifications</h2>
                    <p class="text-sm text-gray-500">Configure which alerts appear in your notification bell</p>
                </div>
            </div>
        </div>
        
        <form id="notificationSettingsForm">
            <div class="px-4">
                <?= (new ToggleSwitch('Low Stock Alerts', 'low_stock_alerts', $notificationSettings['low_stock_alerts'], 'Get notified when inventory items are running low.'))->render() ?>
                <?= (new ToggleSwitch('Expiring Soon Alerts', 'expiring_soon_alerts', $notificationSettings['expiring_soon_alerts'], 'Receive alerts for medicines approaching their expiry date.'))->render() ?>
                <?= (new ToggleSwitch('Daily Sales Summary', 'daily_sales_summary', $notificationSettings['daily_sales_summary'], 'Receive a summary of sales at the end of each day.'))->render() ?>
                <?= (new ToggleSwitch('System Updates & Announcements', 'system_updates', $notificationSettings['system_updates'], 'Stay informed about new features and important announcements.'))->render() ?>
            </div>

            <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-100 flex-wrap gap-4">
                <button type="button" id="sendTestBtn"
                    class="px-5 py-2.5 text-blue-600 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-2 font-semibold border border-blue-200">
                    <i class="fas fa-bell"></i> Send Test Notification
                </button>
                <button type="submit" id="saveSettingsBtn"
                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all flex items-center gap-2 font-semibold shadow-lg shadow-blue-200">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
    
    <!-- Info card -->
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-3">
        <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
        <div class="text-sm text-blue-700">
            <p class="font-semibold">How notifications work</p>
            <p class="mt-1">When enabled, alerts will appear in the notification bell icon at the top of the page. Click the bell to view and manage your notifications.</p>
        </div>
    </div>
</div>

<?= ToggleSwitch::renderScript() ?>

<script>
    const BASE_URL = '<?= $baseUrl ?>';
    
    // ✅ Handle form submission
    document.getElementById('notificationSettingsForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const saveBtn = document.getElementById('saveSettingsBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        // ✅ Get checkbox states
        const settings = {
            email_notifications: false, // Disabled
            low_stock_alerts: document.querySelector('input[name="low_stock_alerts"]')?.checked || false,
            expiring_soon_alerts: document.querySelector('input[name="expiring_soon_alerts"]')?.checked || false,
            daily_sales_summary: document.querySelector('input[name="daily_sales_summary"]')?.checked || false,
            system_updates: document.querySelector('input[name="system_updates"]')?.checked || false
        };

        try {
            const response = await fetch(BASE_URL + '/api/notification-settings.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast('Success', 'Notification settings saved successfully!', 'success');
            } else {
                showToast('Error', result.message || 'Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error', 'Failed to save notification settings. Please try again.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    });

    // ✅ Handle test notification (bell only)
    document.getElementById('sendTestBtn').addEventListener('click', async function () {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

        try {
            const response = await fetch(BASE_URL + '/api/notifications/test.php');
            const data = await response.json();

            if (data.success) {
                showToast('Test Sent', data.message, 'success');
                // Refresh to show the notification in the bell
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast('Failed', data.message || 'Could not send test notification', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error', 'Could not reach the server. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    function showToast(title, message, type = 'success') {
        // Remove any existing toasts
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());
        
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-emerald-500' : 'bg-rose-500';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        toast.className = `toast-notification fixed bottom-8 right-8 ${bgColor} text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 z-[200] max-w-md transform translate-y-4 opacity-0 transition-all duration-300`;
        toast.innerHTML = `<i class="fas ${icon} text-xl"></i><div><p class="font-bold">${title}</p><p class="text-sm opacity-90">${message}</p></div>`;
        document.body.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        });

        // Animate out after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
</script>
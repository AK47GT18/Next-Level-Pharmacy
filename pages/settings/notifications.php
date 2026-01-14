<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\pages\settings\notifications.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Notification.php';

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$notificationHandler = new Notification($db);
$notifications = $notificationHandler->getByUserId($userId, 50); // Get last 50 notifications

function getNotificationIcon(string $type): array {
    return match($type) {
        'alert', 'low_stock' => ['icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
        'success', 'sale' => ['icon' => 'fa-check-circle', 'color' => 'emerald'],
        'error' => ['icon' => 'fa-times-circle', 'color' => 'rose'],
        'info' => ['icon' => 'fa-info-circle', 'color' => 'blue'],
        default => ['icon' => 'fa-bell', 'color' => 'gray']
    };
}

function getTimeAgo(string $datetime): string {
    try {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        return match(true) {
            $difference < 60 => 'Just now',
            $difference < 3600 => floor($difference / 60) . ' mins ago',
            $difference < 86400 => floor($difference / 3600) . ' hours ago',
            default => date('M j, Y \a\t H:i', $timestamp)
        };
    } catch (Exception $e) {
        return 'Recently';
    }
}
?>

<div class="space-y-6 animate-slide-in">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">All Notifications</h1>
            <p class="text-gray-600">Here is a history of your recent notifications.</p>
        </div>
        <div>
            <a href="?page=settings" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Back to Settings
            </a>
        </div>
    </div>

    <div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Notification History</h3>
            <button id="markAllReadBtn" class="text-sm text-blue-600 font-semibold hover:underline">Mark all as read</button>
        </div>

        <div class="space-y-3">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-16">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bell-slash text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="font-bold text-gray-800">No Notifications Yet</h3>
                    <p class="text-sm text-gray-500">We'll let you know when something important happens.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): 
                    $icon = getNotificationIcon($notification['type']);
                    $timeAgo = getTimeAgo($notification['created_at']);
                    $unreadClass = !$notification['read'] ? 'bg-blue-50/50 border-blue-200' : 'border-transparent';
                ?>
                <div class="p-4 border-l-4 flex items-start gap-4 rounded-lg transition hover:bg-gray-50 <?= $unreadClass ?>" data-notification-id="<?= $notification['id'] ?>">
                    <div class="w-10 h-10 rounded-xl bg-<?= $icon['color'] ?>-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas <?= $icon['icon'] ?> text-<?= $icon['color'] ?>-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($notification['title']) ?></p>
                        <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($notification['message']) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="text-xs text-gray-400"><?= $timeAgo ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('markAllReadBtn')?.addEventListener('click', () => {
    fetch('/Next-Level/rxpms/api/notifications/mark-all-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('[data-notification-id]').forEach(item => {
                item.classList.remove('bg-blue-50/50', 'border-blue-200');
                item.classList.add('border-transparent');
            });
        }
    })
    .catch(err => console.error('Error marking all as read:', err));
});
</script>
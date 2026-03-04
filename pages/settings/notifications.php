<?php
// filepath: c:\xampp5\htdocs\Next-Level\pages\settings\notifications.php

require_once __DIR__ . '/../../includes/check-auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Notification.php';
require_once __DIR__ . '/../../includes/PathHelper.php';

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$notificationHandler = new Notification($db);
$notifications = $notificationHandler->getByUserId($userId, 50); // Get last 50 notifications

$baseUrl = PathHelper::getBaseUrl();

function getNotificationIcon(string $type): array
{
    return match ($type) {
        'alert', 'low_stock' => ['icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
        'success', 'sale' => ['icon' => 'fa-check-circle', 'color' => 'emerald'],
        'error' => ['icon' => 'fa-times-circle', 'color' => 'rose'],
        'info' => ['icon' => 'fa-info-circle', 'color' => 'blue'],
        default => ['icon' => 'fa-bell', 'color' => 'gray']
    };
}

function getTimeAgo(string $datetime): string
{
    try {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;

        return match (true) {
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
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">All Notifications</h1>
            <p class="text-gray-600">Here is a history of your recent notifications.</p>
        </div>
        <div>
            <a href="?page=settings"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Back to Settings
            </a>
        </div>
    </div>

    <div class="glassmorphism rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-bell text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-800">Notification History</h2>
                        <p class="text-sm text-gray-500"><?= count($notifications) ?> notification(s)</p>
                    </div>
                </div>
                <?php if (!empty($notifications)): ?>
                    <button id="clearAllBtn"
                        class="px-4 py-2 text-sm text-rose-600 font-semibold hover:bg-rose-50 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="divide-y divide-gray-50" id="notificationsList">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-16">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bell-slash text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="font-bold text-gray-800">No Notifications Yet</h3>
                    <p class="text-sm text-gray-500 mt-1">We'll let you know when something important happens.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification):
                    $icon = getNotificationIcon($notification['type']);
                    $timeAgo = getTimeAgo($notification['created_at']);
                    $unreadClass = !$notification['read'] ? 'bg-blue-50/60 border-l-blue-500' : 'bg-white border-l-transparent';
                    ?>
                    <div class="p-4 border-l-4 flex items-start gap-4 transition-all duration-200 hover:bg-gray-50 <?= $unreadClass ?>"
                        data-notification-id="<?= $notification['id'] ?>"
                        style="transition: opacity 0.2s, transform 0.2s, max-height 0.3s;">
                        <div
                            class="w-10 h-10 rounded-xl bg-<?= $icon['color'] ?>-100 flex items-center justify-center flex-shrink-0 shadow-sm">
                            <i class="fas <?= $icon['icon'] ?> text-<?= $icon['color'] ?>-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-800 font-semibold"><?= htmlspecialchars($notification['title']) ?></p>
                            <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($notification['message']) ?></p>
                            <span class="text-xs text-gray-400 mt-2 inline-block"><?= $timeAgo ?></span>
                        </div>
                        <div class="flex-shrink-0 flex items-center gap-2">
                            <button onclick="deleteNotification('<?= $notification['id'] ?>')"
                                class="w-8 h-8 flex items-center justify-center hover:bg-rose-50 rounded-lg transition-colors group"
                                title="Delete notification">
                                <i class="fas fa-times text-gray-400 group-hover:text-rose-500 transition-colors"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?= $baseUrl ?>';

    function deleteNotification(notificationId) {
        fetch(BASE_URL + '/api/notifications/mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector('[data-notification-id="' + notificationId + '"]');
                    if (item) {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            item.remove();
                            checkEmptyState();
                        }, 200);
                    }
                }
            })
            .catch(err => console.error('Error deleting notification:', err));
    }

    document.getElementById('clearAllBtn')?.addEventListener('click', () => {
        if (!confirm('Are you sure you want to clear all notifications?')) return;

        fetch(BASE_URL + '/api/notifications/mark-all-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const items = document.querySelectorAll('[data-notification-id]');
                    items.forEach((item, index) => {
                        setTimeout(() => {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(20px)';
                        }, index * 50);
                    });
                    setTimeout(() => {
                        checkEmptyState();
                    }, items.length * 50 + 200);
                }
            })
            .catch(err => console.error('Error clearing notifications:', err));
    });

    function checkEmptyState() {
        const list = document.getElementById('notificationsList');
        const remaining = list.querySelectorAll('[data-notification-id]').length;

        if (remaining === 0) {
            list.innerHTML = `
            <div class="text-center py-16">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bell-slash text-4xl text-gray-400"></i>
                </div>
                <h3 class="font-bold text-gray-800">All Caught Up!</h3>
                <p class="text-sm text-gray-500 mt-1">You have no notifications.</p>
            </div>
        `;
            // Hide the clear all button
            const clearBtn = document.getElementById('clearAllBtn');
            if (clearBtn) clearBtn.style.display = 'none';
        }
    }
</script>
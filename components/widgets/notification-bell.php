<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\components\widgets\notification-bell.php

require_once __DIR__ . '/../../config/database.php';

class NotificationBell {
    private array $notifications;
    private array $options;
    private $conn;
    private $userId;

    public function __construct(array $notifications = [], array $options = [], $userId = null) {
        $this->notifications = $notifications;
        $this->options = array_merge([
            'maxCount' => 99,
            'showCount' => true,
            'animated' => true
        ], $options);
        
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        
        try {
            $db = Database::getInstance();
            $this->conn = $db->getConnection();
            
            // ✅ Load notifications from database if userId exists
            if ($this->userId) {
                $this->loadNotificationsFromDb();
            }
        } catch (Exception $e) {
            error_log('NotificationBell DB Error: ' . $e->getMessage());
        }
    }

    private function loadNotificationsFromDb(): void {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, title, message, type, created_at, `read`
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$this->userId]);
            $this->notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to load notifications: ' . $e->getMessage());
        }
    }

    public function render(): string {
        $count = count($this->notifications);
        $countBadge = $this->renderCountBadge($count);
        $dropdown = $this->renderDropdown();

        return <<<HTML
        <div class="relative" data-notification-bell>
            <button class="relative p-3 hover:bg-blue-50 rounded-xl transition group" 
                    onclick="toggleNotificationDropdown(event)">
                <i class="fas fa-bell text-gray-600 text-xl group-hover:text-blue-600 transition"></i>
                {$countBadge}
            </button>

            <!-- Notification Dropdown -->
            {$dropdown}
        </div>
        HTML;
    }

    private function renderCountBadge(int $count): string {
        if (!$this->options['showCount'] || $count === 0) {
            return '';
        }

        $displayCount = $count > $this->options['maxCount'] ? $this->options['maxCount'].'+' : $count;

        return <<<HTML
        <span class="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full"></span>
        <span class="absolute top-1.5 right-1.5 w-3 h-3 bg-rose-500 rounded-full animate-ping"></span>
        HTML;
    }

    private function renderDropdown(): string {
        $notificationItems = $this->renderNotificationItems();
        
        return <<<HTML
        <div id="notificationDropdown" 
             class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 
                    hidden z-50 transform origin-top-right transition-transform">
            <div class="p-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Notifications</h3>
                    <button onclick="markAllAsRead()" class="text-xs text-blue-600 hover:text-blue-700 font-semibold">
                        Mark all as read
                    </button>
                </div>
            </div>

            <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
                {$notificationItems}
            </div>

            <div class="p-4 border-t border-gray-100">
                <a href="/Next-Level/rxpms/index.php?page=settings&view=notifications" 
                   class="block text-center text-sm text-blue-600 hover:text-blue-700 font-semibold">
                    View all notifications
                </a>
            </div>
        </div>

        <script>
        function toggleNotificationDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        }

        function markNotificationAsRead(notificationId) {
            fetch('/Next-Level/rxpms/api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector('[data-notification-id="' + notificationId + '"]');
                    if (item) {
                        item.classList.remove('bg-blue-50/50');
                        const readBtn = item.querySelector('[data-read-btn]');
                        if (readBtn) readBtn.style.display = 'none';
                    }
                }
            })
            .catch(err => console.error('Error marking as read:', err));
        }

        function markAllAsRead() {
            fetch('/Next-Level/rxpms/api/notifications/mark-all-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('[data-notification-id]').forEach(item => {
                        item.classList.remove('bg-blue-50/50');
                        const readBtn = item.querySelector('[data-read-btn]');
                        if (readBtn) readBtn.style.display = 'none';
                    });
                }
            })
            .catch(err => console.error('Error marking all as read:', err));
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[data-notification-bell]')) {
                document.getElementById('notificationDropdown').classList.add('hidden');
            }
        });
        </script>
        HTML;
    }

    private function renderNotificationItems(): string {
        if (empty($this->notifications)) {
            return <<<HTML
            <div class="p-4 text-center text-gray-500">
                <i class="fas fa-bell-slash text-3xl mb-2"></i>
                <p class="text-sm">No new notifications</p>
            </div>
            HTML;
        }

        $items = '';
        foreach ($this->notifications as $notification) {
            $items .= $this->renderNotificationItem($notification);
        }
        return $items;
    }

    private function renderNotificationItem(array $notification): string {
        $icon = $this->getNotificationIcon($notification['type'] ?? 'info');
        $timeAgo = $this->getTimeAgo($notification['created_at']);
        $unreadClass = !($notification['read'] ?? false) ? 'bg-blue-50/50' : '';
        $notificationId = $notification['id'] ?? uniqid();

        return <<<HTML
        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 transition cursor-pointer {$unreadClass}"
             data-notification-id="{$notificationId}">
            <div class="flex gap-3">
                <div class="w-10 h-10 rounded-xl bg-{$icon['color']}-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas {$icon['icon']} text-{$icon['color']}-600"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-900 font-medium">{$this->sanitize($notification['title'])}</p>
                    <p class="text-xs text-gray-500 mt-1">{$this->sanitize($notification['message'])}</p>
                    <span class="text-xs text-gray-400 mt-2 block">{$timeAgo}</span>
                </div>
                {$this->renderNotificationActions($notification)}
            </div>
        </div>
        HTML;
    }

    private function getNotificationIcon(string $type): array {
        return match($type) {
            'alert' => ['icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
            'success' => ['icon' => 'fa-check-circle', 'color' => 'emerald'],
            'error' => ['icon' => 'fa-times-circle', 'color' => 'rose'],
            'low_stock' => ['icon' => 'fa-box', 'color' => 'amber'],
            'sale' => ['icon' => 'fa-shopping-cart', 'color' => 'emerald'],
            'info' => ['icon' => 'fa-info-circle', 'color' => 'blue'],
            default => ['icon' => 'fa-bell', 'color' => 'gray']
        };
    }

    private function getTimeAgo(string $datetime): string {
        try {
            $timestamp = strtotime($datetime);
            $difference = time() - $timestamp;
            
            return match(true) {
                $difference < 60 => 'Just now',
                $difference < 3600 => floor($difference / 60) . ' mins ago',
                $difference < 86400 => floor($difference / 3600) . ' hours ago',
                $difference < 604800 => floor($difference / 86400) . ' days ago',
                default => date('M j', $timestamp)
            };
        } catch (Exception $e) {
            return 'Recently';
        }
    }

    private function renderNotificationActions(array $notification): string {
        if ($notification['read'] ?? false) {
            return '';
        }

        $notificationId = $notification['id'] ?? '';
        
        return <<<HTML
        <button class="p-1.5 hover:bg-gray-100 rounded-lg transition" 
                onclick="markNotificationAsRead('{$notificationId}')"
                data-read-btn
                title="Mark as read">
            <i class="fas fa-circle text-xs text-blue-600"></i>
        </button>
        HTML;
    }

    private function sanitize(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
?>
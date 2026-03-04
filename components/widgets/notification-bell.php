<?php
// filepath: c:\xampp5\htdocs\Next-Level\components\widgets\notification-bell.php

require_once __DIR__ . '/../../config/database.php';

class NotificationBell
{
    private array $notifications;
    private array $options;
    private $conn;
    private $userId;

    public function __construct(array $notifications = [], array $options = [], $userId = null)
    {
        $this->notifications = $notifications;
        $this->options = array_merge([
            'maxCount' => 10,
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

    private function loadNotificationsFromDb(): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, title, message, type, created_at, `read`
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$this->userId]);
            $this->notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to load notifications: ' . $e->getMessage());
        }
    }

    public function render(): string
    {
        // Count only UNREAD notifications
        $unreadCount = count(array_filter($this->notifications, fn($n) => !($n['read'] ?? false)));
        $countBadge = $this->renderCountBadge($unreadCount);
        $dropdown = $this->renderDropdown();

        return <<<HTML
        <div class="relative" data-notification-bell>
            <button class="relative flex items-center justify-center w-11 h-11 bg-gradient-to-br from-blue-50 to-slate-50 hover:from-blue-100 hover:to-slate-100 rounded-xl transition-all duration-200 group shadow-sm hover:shadow-md border border-slate-100" 
                    onclick="toggleNotificationDropdown(event)"
                    aria-label="Notifications">
                <i class="fas fa-bell text-slate-500 text-lg group-hover:text-blue-600 transition-colors"></i>
                {$countBadge}
            </button>

            <!-- Notification Dropdown -->
            {$dropdown}
        </div>
        HTML;
    }

    private function renderCountBadge(int $count): string
    {
        if (!$this->options['showCount'] || $count === 0) {
            return '';
        }

        $displayCount = $count > $this->options['maxCount'] ? $this->options['maxCount'] . '+' : $count;

        return <<<HTML
        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 shadow-md ring-2 ring-white z-10">{$displayCount}</span>
        HTML;
    }

    private function renderDropdown(): string
    {
        $notificationItems = $this->renderNotificationItems();

        return <<<HTML
        <div id="notificationDropdown" 
             class="absolute right-0 top-full mt-3 w-96 bg-white rounded-2xl shadow-2xl shadow-slate-200/50 border border-slate-100 
                    hidden z-50 transform origin-top-right transition-all duration-200 overflow-hidden">
            
            <!-- Header -->
            <div class="px-5 py-4 bg-gradient-to-r from-slate-50 to-blue-50 border-b border-slate-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bell text-blue-600 text-sm"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">Notifications</h3>
                    </div>
                    <button onclick="markAllAsRead()" class="text-xs text-blue-600 hover:text-blue-700 font-semibold px-3 py-1.5 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        Clear all
                    </button>
                </div>
            </div>

            <!-- Notification List -->
            <div class="max-h-[480px] overflow-y-auto custom-scrollbar">
                {$notificationItems}
            </div>

            <!-- Footer -->
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100">
                <a href="index.php?page=settings&view=notifications" 
                   class="flex items-center justify-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-semibold py-2 hover:bg-blue-50 rounded-lg transition-colors">
                    <span>View all notifications</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>

        <script>
        const NOTIFICATION_API_BASE = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '';
        
        function toggleNotificationDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        }

        function markNotificationAsRead(notificationId) {
            fetch(NOTIFICATION_API_BASE + '/api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification item from the DOM
                    const item = document.querySelector('[data-notification-id="' + notificationId + '"]');
                    if (item) {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            item.remove();
                            updateNotificationBadge();
                        }, 200);
                    }
                }
            })
            .catch(err => console.error('Error deleting notification:', err));
        }

        function markAllAsRead() {
            fetch(NOTIFICATION_API_BASE + '/api/notifications/mark-all-read.php', {
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
                        document.querySelector('#notificationDropdown .max-h-\\[480px\\]').innerHTML = `
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-bell-slash text-2xl text-slate-400"></i>
                                </div>
                                <p class="text-slate-500 font-medium">All caught up!</p>
                                <p class="text-xs text-slate-400 mt-1">No new notifications</p>
                            </div>
                        `;
                        updateNotificationBadge();
                    }, items.length * 50 + 200);
                }
            })
            .catch(err => console.error('Error clearing notifications:', err));
        }

        function updateNotificationBadge() {
            const badge = document.querySelector('[data-notification-bell] .absolute.min-w-\\[22px\\]');
            const count = document.querySelectorAll('[data-notification-id]').length;
            if (badge) {
                if (count === 0) {
                    badge.style.display = 'none';
                } else {
                    badge.style.display = 'flex';
                    badge.textContent = count > 10 ? '10+' : count;
                }
            }
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

    private function renderNotificationItems(): string
    {
        if (empty($this->notifications)) {
            return <<<HTML
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bell-slash text-2xl text-slate-400"></i>
                </div>
                <p class="text-slate-500 font-medium">All caught up!</p>
                <p class="text-xs text-slate-400 mt-1">No new notifications</p>
            </div>
            HTML;
        }

        $items = '';
        foreach ($this->notifications as $notification) {
            $items .= $this->renderNotificationItem($notification);
        }
        return $items;
    }

    private function renderNotificationItem(array $notification): string
    {
        $icon = $this->getNotificationIcon($notification['type'] ?? 'info');
        $timeAgo = $this->getTimeAgo($notification['created_at']);
        $unreadClass = !($notification['read'] ?? false) ? 'bg-blue-50/60 border-l-blue-500' : 'bg-white border-l-transparent';
        $notificationId = $notification['id'] ?? uniqid();

        return <<<HTML
        <div class="px-4 py-3.5 border-l-4 border-b border-gray-50 hover:bg-slate-50 transition-all duration-200 cursor-pointer {$unreadClass}"
             data-notification-id="{$notificationId}"
             style="transition: opacity 0.2s, transform 0.2s;">
            <div class="flex gap-3">
                <div class="w-10 h-10 rounded-xl bg-{$icon['color']}-100 flex items-center justify-center flex-shrink-0 shadow-sm">
                    <i class="fas {$icon['icon']} text-{$icon['color']}-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-800 font-semibold truncate">{$this->sanitize($notification['title'])}</p>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{$this->sanitize($notification['message'])}</p>
                    <span class="text-[11px] text-slate-400 mt-1.5 inline-block">{$timeAgo}</span>
                </div>
                {$this->renderNotificationActions($notification)}
            </div>
        </div>
        HTML;
    }

    private function getNotificationIcon(string $type): array
    {
        return match ($type) {
            'alert' => ['icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
            'success' => ['icon' => 'fa-check-circle', 'color' => 'emerald'],
            'error' => ['icon' => 'fa-times-circle', 'color' => 'rose'],
            'low_stock' => ['icon' => 'fa-box', 'color' => 'amber'],
            'sale' => ['icon' => 'fa-shopping-cart', 'color' => 'emerald'],
            'info' => ['icon' => 'fa-info-circle', 'color' => 'blue'],
            default => ['icon' => 'fa-bell', 'color' => 'gray']
        };
    }

    private function getTimeAgo(string $datetime): string
    {
        try {
            $timestamp = strtotime($datetime);
            $difference = time() - $timestamp;

            return match (true) {
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

    private function renderNotificationActions(array $notification): string
    {
        $notificationId = $notification['id'] ?? '';

        return <<<HTML
        <button class="flex-shrink-0 w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg transition-colors group" 
                onclick="event.stopPropagation(); markNotificationAsRead('{$notificationId}')"
                data-read-btn
                title="Dismiss">
            <i class="fas fa-times text-xs text-slate-400 group-hover:text-rose-500 transition-colors"></i>
        </button>
        HTML;
    }

    private function sanitize(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
?>
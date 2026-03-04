<?php
// filepath: c:\xampp5\htdocs\Next-Level\classes\Notification.php

class Notification
{
    private $conn;
    private $table = 'notifications';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Create a new notification for a user (bell notification only).
     */
    public function create(int $userId, string $title, string $message, string $type, ?string $link = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $query = "INSERT INTO " . $this->table . " (user_id, title, message, type, link, created_at) VALUES (:user_id, :title, :message, :type, :link, :created_at)";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':link', $link, PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notifications for a specific user.
     */
    public function getByUserId(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $query = "SELECT id, title, message, type, link, `read`, created_at FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at DESC";

        if ($limit !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

            if ($limit !== null) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Notification getByUserId error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        $query = "SELECT COUNT(*) FROM " . $this->table . " WHERE user_id = :user_id AND `read` = 0";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Notification getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        $query = "UPDATE " . $this->table . " SET `read` = 1, updated_at = :updated_at WHERE id = :notification_id AND user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':notification_id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':updated_at', $now, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Notification markAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all unread notifications for a user as read.
     */
    public function markAllAsRead(int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        $query = "UPDATE " . $this->table . " SET `read` = 1, updated_at = :updated_at WHERE user_id = :user_id AND `read` = 0";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':updated_at', $now, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Notification markAllAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a specific notification.
     */
    public function delete(int $notificationId, int $userId): bool
    {
        $query = "DELETE FROM " . $this->table . " WHERE id = :notification_id AND user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':notification_id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Notification delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all read notifications for a user.
     */
    public function deleteAllRead(int $userId): bool
    {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id AND `read` = 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Notification deleteAllRead error: " . $e->getMessage());
            return false;
        }
    }
}
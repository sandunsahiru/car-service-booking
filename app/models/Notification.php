<?php

declare(strict_types=1);

class Notification
{
    private PDO $db;

    /**
     * Initialize Notification model with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new notification
     * 
     * @param array $data Notification data
     * @return int|false Notification ID on success, false on failure
     */
    public function create(array $data): int|false
    {
        try {
            $sql = "INSERT INTO notifications (
                        user_id, 
                        title, 
                        message, 
                        type, 
                        related_id
                    ) VALUES (
                        :user_id, 
                        :title, 
                        :message, 
                        :type, 
                        :related_id
                    )";

            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':user_id' => $data['user_id'],
                ':title' => $data['title'],
                ':message' => $data['message'],
                ':type' => $data['type'],
                ':related_id' => $data['related_id'] ?? null
            ];

            if (!$stmt->execute($params)) {
                $error = $stmt->errorInfo();
                error_log("Notification creation failed: " . print_r($error, true));
                return false;
            }

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database error creating notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user notifications
     * 
     * @param int $userId User ID
     * @param int $limit Limit number of notifications
     * @return array Notifications
     */
    public function getUserNotifications(int $userId, int $limit = 10): array
    {
        try {
            $sql = "SELECT * FROM notifications 
                   WHERE user_id = :user_id 
                   ORDER BY created_at DESC 
                   LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Database error getting notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread notifications count
     * 
     * @param int $userId User ID
     * @return int Count of unread notifications
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM notifications 
                   WHERE user_id = :user_id AND is_read = 0";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database error counting unread notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for verification)
     * @return bool Success status
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $sql = "UPDATE notifications 
                   SET is_read = 1, updated_at = NOW()
                   WHERE id = :id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            $sql = "UPDATE notifications 
                   SET is_read = 1, updated_at = NOW()
                   WHERE user_id = :user_id AND is_read = 0";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete notification
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for verification)
     * @return bool Success status
     */
    public function deleteNotification(int $notificationId, int $userId): bool
    {
        try {
            $sql = "DELETE FROM notifications 
                   WHERE id = :id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error deleting notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create service reminder notification
     * 
     * @param int $userId User ID
     * @param int $carId Car ID
     * @param int $daysRemaining Days remaining until service
     * @return int|false Notification ID on success, false on failure
     */
    public function createServiceReminder(int $userId, int $carId, string $carDetails, int $daysRemaining): int|false
    {
        $title = 'Service Reminder';
        $message = "Your {$carDetails} is due for service in {$daysRemaining} " . 
                  ($daysRemaining == 1 ? 'day' : 'days') . ".";
        
        return $this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'service_reminder',
            'related_id' => $carId
        ]);
    }

    /**
     * Create booking confirmation notification
     * 
     * @param int $userId User ID
     * @param int $bookingId Booking ID
     * @param array $bookingDetails Booking details
     * @return int|false Notification ID on success, false on failure
     */
    public function createBookingConfirmation(int $userId, int $bookingId, array $bookingDetails): int|false
    {
        $title = 'Booking Confirmed';
        $message = "Your booking for {$bookingDetails['service_name']} on " . 
                  date('F j, Y', strtotime($bookingDetails['booking_date'])) . " at " .
                  date('g:i A', strtotime($bookingDetails['booking_time'])) . " has been confirmed.";
        
        return $this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'booking_confirmation',
            'related_id' => $bookingId
        ]);
    }

    /**
     * Create service complete notification
     * 
     * @param int $userId User ID
     * @param int $serviceHistoryId Service history ID
     * @param array $serviceDetails Service details
     * @return int|false Notification ID on success, false on failure
     */
    public function createServiceComplete(int $userId, int $serviceHistoryId, array $serviceDetails): int|false
    {
        $title = 'Service Completed';
        $message = "Your {$serviceDetails['service_name']} for {$serviceDetails['car_details']} " .
                  "has been completed successfully.";
        
        return $this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'service_complete',
            'related_id' => $serviceHistoryId
        ]);
    }
}
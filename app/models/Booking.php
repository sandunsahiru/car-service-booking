<?php
declare(strict_types=1);

class Booking {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get upcoming bookings for a user
     */
    public function getUpcomingBookings(int $userId, int $limit = 5): array {
        try {
            $sql = "SELECT b.*, c.make, c.model, c.reg_number, s.name as service_name, s.duration_minutes 
                   FROM bookings b 
                   JOIN cars c ON b.car_id = c.id 
                   JOIN services s ON b.service_id = s.id 
                   WHERE b.user_id = :user_id 
                   AND b.booking_date >= CURDATE() 
                   AND b.status IN ('pending', 'confirmed') 
                   ORDER BY b.booking_date, b.booking_time 
                   LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting upcoming bookings: " . $e->getMessage());
            throw new Exception('Failed to get upcoming bookings');
        }
    }

    /**
     * Get total number of bookings for a user
     */
    public function getTotalBookings(int $userId): int {
        try {
            $sql = "SELECT COUNT(*) FROM bookings WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total bookings: " . $e->getMessage());
            throw new Exception('Failed to get total bookings');
        }
    }

    /**
     * Get booking statistics for a user
     */
    public function getBookingStats(int $userId): array {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings
                   FROM bookings 
                   WHERE user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting booking stats: " . $e->getMessage());
            throw new Exception('Failed to get booking statistics');
        }
    }
}
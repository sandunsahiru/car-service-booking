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

    /**
     * Create a new booking
     * 
     * @param array $data Booking data (user_id, car_id, service_id, booking_date, booking_time, status, notes)
     * @return int|false The booking ID on success, false on failure
     */
    public function createBooking(array $data): int|false {
        try {
            // Validate required fields
            $required = ['user_id', 'car_id', 'service_id', 'booking_date', 'booking_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("$field is required");
                }
            }

            // Format booking time if needed
            $bookingTime = $data['booking_time'];
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $bookingTime)) {
                $bookingTime = date('H:i:s', strtotime($bookingTime));
            }

            // Check if slot is available
            if (!$this->isTimeSlotAvailable($data['service_id'], $data['booking_date'], $bookingTime)) {
                throw new Exception('This time slot is not available. Please select another time.');
            }

            // Start transaction
            $this->db->beginTransaction();

            // Insert booking
            $sql = "INSERT INTO bookings (
                        user_id, 
                        car_id, 
                        service_id, 
                        booking_date, 
                        booking_time, 
                        status, 
                        notes, 
                        created_at
                   ) VALUES (
                        :user_id, 
                        :car_id, 
                        :service_id, 
                        :booking_date, 
                        :booking_time, 
                        :status, 
                        :notes, 
                        NOW()
                   )";

            $stmt = $this->db->prepare($sql);
            $params = [
                ':user_id' => $data['user_id'],
                ':car_id' => $data['car_id'],
                ':service_id' => $data['service_id'],
                ':booking_date' => $data['booking_date'],
                ':booking_time' => $bookingTime,
                ':status' => $data['status'] ?? 'pending',
                ':notes' => $data['notes'] ?? ''
            ];

            $stmt->execute($params);
            $bookingId = (int)$this->db->lastInsertId();

            // Mark recommendation as booked if it exists
            if (isset($data['recommendation_id'])) {
                $this->markRecommendationBooked($data['recommendation_id'], $bookingId);
            }

            // Commit transaction
            $this->db->commit();

            return $bookingId;
        } catch (PDOException $e) {
            // Rollback on error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log("Error creating booking: " . $e->getMessage());
            throw new Exception('Failed to create booking: ' . $e->getMessage());
        }
    }

    /**
     * Check if a time slot is available
     * 
     * @param int $serviceId The service ID
     * @param string $date The booking date
     * @param string $time The booking time
     * @return bool True if available, false otherwise
     */
    public function isTimeSlotAvailable(int $serviceId, string $date, string $time): bool {
        try {
            // Get service duration
            $durationSql = "SELECT duration_minutes FROM services WHERE id = :service_id";
            $durationStmt = $this->db->prepare($durationSql);
            $durationStmt->execute([':service_id' => $serviceId]);
            $duration = (int)$durationStmt->fetchColumn();

            if (!$duration) {
                $duration = 60; // Default to 1 hour if not specified
            }

            // Calculate end time for the requested booking
            $startTime = strtotime("$date $time");
            $endTime = strtotime("+$duration minutes", $startTime);
            $requestedEndTime = date('H:i:s', $endTime);

            // Check for conflicts with existing bookings
            $sql = "SELECT b.*, s.duration_minutes 
                   FROM bookings b
                   JOIN services s ON b.service_id = s.id 
                   WHERE b.booking_date = :date 
                   AND b.status IN ('pending', 'confirmed')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':date' => $date]);
            $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if the requested time slot conflicts with any existing booking
            foreach ($existingBookings as $booking) {
                $existingStartTime = strtotime("$date {$booking['booking_time']}");
                $existingDuration = (int)$booking['duration_minutes'] ?: 60;
                $existingEndTime = strtotime("+$existingDuration minutes", $existingStartTime);

                // Check for overlap
                if (
                    ($startTime >= $existingStartTime && $startTime < $existingEndTime) ||
                    ($endTime > $existingStartTime && $endTime <= $existingEndTime) ||
                    ($startTime <= $existingStartTime && $endTime >= $existingEndTime)
                ) {
                    return false; // Conflict found
                }
            }

            return true; // No conflicts
        } catch (PDOException $e) {
            error_log("Error checking time slot availability: " . $e->getMessage());
            throw new Exception('Failed to check time slot availability');
        }
    }

    /**
     * Mark a recommendation as booked
     */
    private function markRecommendationBooked(int $recommendationId, int $bookingId): bool {
        try {
            $sql = "UPDATE service_recommendations 
                   SET status = 'booked', booking_id = :booking_id, updated_at = NOW() 
                   WHERE id = :recommendation_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':recommendation_id' => $recommendationId,
                ':booking_id' => $bookingId
            ]);
        } catch (PDOException $e) {
            error_log("Error marking recommendation as booked: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a booking
     * 
     * @param int $bookingId The booking ID
     * @param int $userId The user ID (for verification)
     * @return bool True on success, false on failure
     */
    public function cancelBooking(int $bookingId, int $userId): bool {
        try {
            // Check if booking exists and belongs to user
            $sql = "SELECT * FROM bookings WHERE id = :booking_id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':booking_id' => $bookingId,
                ':user_id' => $userId
            ]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                throw new Exception('Booking not found or not authorized');
            }

            // Check if booking can be cancelled (not completed or already cancelled)
            if ($booking['status'] === 'completed' || $booking['status'] === 'cancelled') {
                throw new Exception('This booking cannot be cancelled');
            }

            // Calculate cancellation deadline (24 hours before appointment)
            $appointmentTime = strtotime("{$booking['booking_date']} {$booking['booking_time']}");
            $cancellationDeadline = strtotime('-24 hours', $appointmentTime);

            // Check if cancellation is within allowed time frame
            if (time() > $cancellationDeadline) {
                // Late cancellation - store as cancelled but may apply fee
                $status = 'cancelled_late';
            } else {
                $status = 'cancelled';
            }

            // Update booking status
            $sql = "UPDATE bookings SET 
                    status = :status,
                    updated_at = NOW(),
                    cancellation_reason = :reason
                   WHERE id = :booking_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':booking_id' => $bookingId,
                ':user_id' => $userId,
                ':status' => $status,
                ':reason' => 'Cancelled by user'
            ]);
        } catch (PDOException $e) {
            error_log("Error cancelling booking: " . $e->getMessage());
            throw new Exception('Failed to cancel booking: ' . $e->getMessage());
        }
    }

    /**
     * Get booking by ID
     * 
     * @param int $bookingId The booking ID
     * @return array|null The booking data or null if not found
     */
    public function getById(int $bookingId): ?array {
        try {
            $sql = "SELECT b.*, c.make, c.model, c.reg_number, s.name as service_name, 
                           s.duration_minutes, s.price
                   FROM bookings b 
                   JOIN cars c ON b.car_id = c.id 
                   JOIN services s ON b.service_id = s.id 
                   WHERE b.id = :booking_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':booking_id' => $bookingId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error getting booking details: " . $e->getMessage());
            throw new Exception('Failed to get booking details');
        }
    }

    /**
     * Update booking status
     * 
     * @param int $bookingId The booking ID
     * @param string $status The new status (pending, confirmed, completed, cancelled)
     * @return bool True on success, false on failure
     */
    public function updateStatus(int $bookingId, string $status): bool {
        try {
            // Validate status
            $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'cancelled_late'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid booking status');
            }

            $sql = "UPDATE bookings SET 
                    status = :status,
                    updated_at = NOW()
                   WHERE id = :booking_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':booking_id' => $bookingId,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Error updating booking status: " . $e->getMessage());
            throw new Exception('Failed to update booking status');
        }
    }

    /**
     * Reschedule a booking
     * 
     * @param int $bookingId The booking ID
     * @param int $userId The user ID (for verification)
     * @param string $newDate The new booking date
     * @param string $newTime The new booking time
     * @return bool True on success, false on failure
     */
    public function rescheduleBooking(int $bookingId, int $userId, string $newDate, string $newTime): bool {
        try {
            // Get booking and service details
            $sql = "SELECT b.*, s.id as service_id
                   FROM bookings b
                   JOIN services s ON b.service_id = s.id
                   WHERE b.id = :booking_id AND b.user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':booking_id' => $bookingId,
                ':user_id' => $userId
            ]);
            
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                throw new Exception('Booking not found or not authorized');
            }
            
            // Check if new time slot is available
            if (!$this->isTimeSlotAvailable((int)$booking['service_id'], $newDate, $newTime)) {
                throw new Exception('The requested time slot is not available');
            }
            
            // Update booking
            $sql = "UPDATE bookings SET 
                    booking_date = :booking_date,
                    booking_time = :booking_time,
                    updated_at = NOW(),
                    rescheduled = 1
                   WHERE id = :booking_id AND user_id = :user_id";
                   
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':booking_id' => $bookingId,
                ':user_id' => $userId,
                ':booking_date' => $newDate,
                ':booking_time' => $newTime
            ]);
        } catch (PDOException $e) {
            error_log("Error rescheduling booking: " . $e->getMessage());
            throw new Exception('Failed to reschedule booking: ' . $e->getMessage());
        }
    }

    /**
     * Get available time slots for a specific date and service
     * 
     * @param int $serviceId The service ID
     * @param string $date The booking date
     * @return array The available time slots
     */
    public function getAvailableTimeSlots(int $serviceId, string $date): array {
        try {
            // Define all possible time slots (9 AM to 5 PM, hourly)
            $allTimeSlots = [
                '09:00:00', '10:00:00', '11:00:00', '12:00:00',
                '13:00:00', '14:00:00', '15:00:00', '16:00:00'
            ];
            
            $availableSlots = [];
            
            // Check each slot for availability
            foreach ($allTimeSlots as $time) {
                if ($this->isTimeSlotAvailable($serviceId, $date, $time)) {
                    $availableSlots[] = [
                        'time' => $time,
                        'formatted_time' => date('h:i A', strtotime($time))
                    ];
                }
            }
            
            return $availableSlots;
        } catch (PDOException $e) {
            error_log("Error getting available time slots: " . $e->getMessage());
            throw new Exception('Failed to get available time slots');
        }
    }

    /**
     * Get booking history for a user
     * 
     * @param int $userId The user ID
     * @param int $limit Optional limit on number of records
     * @param int $offset Optional offset for pagination
     * @return array The booking history
     */
    public function getBookingHistory(int $userId, int $limit = 10, int $offset = 0): array {
        try {
            $sql = "SELECT b.*, c.make, c.model, c.reg_number, s.name as service_name, 
                           s.duration_minutes, s.price
                   FROM bookings b 
                   JOIN cars c ON b.car_id = c.id 
                   JOIN services s ON b.service_id = s.id 
                   WHERE b.user_id = :user_id 
                   ORDER BY b.booking_date DESC, b.booking_time DESC
                   LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting booking history: " . $e->getMessage());
            throw new Exception('Failed to get booking history');
        }
    }

    /**
     * Get total count of booking history for pagination
     * 
     * @param int $userId The user ID
     * @return int The total count
     */
    public function getBookingHistoryCount(int $userId): int {
        try {
            $sql = "SELECT COUNT(*) FROM bookings WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting booking history count: " . $e->getMessage());
            throw new Exception('Failed to get booking history count');
        }
    }
}
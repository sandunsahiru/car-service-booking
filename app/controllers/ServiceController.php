<?php
declare(strict_types=1);

// Include all required model files
require_once(__DIR__ . '/../models/Service.php');
require_once(__DIR__ . '/../models/ServiceHistory.php');
require_once(__DIR__ . '/../models/Car.php');
require_once(__DIR__ . '/../models/User.php');
require_once(__DIR__ . '/../models/ServiceRecommendation.php');
require_once(__DIR__ . '/../models/Booking.php');

/**
 * Service Controller
 * 
 * Handles all service-related functionality including displaying available services,
 * processing service bookings, and managing service history.
 */
class ServiceController {
    private PDO $db;
    private Service $serviceModel;
    private ServiceHistory $serviceHistoryModel;
    private Car $carModel;
    private User $userModel;
    private ServiceRecommendation $recommendationModel;

    /**
     * Initialize controller with database connection and required models
     * 
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->serviceModel = new Service($db);
        $this->serviceHistoryModel = new ServiceHistory($db);
        $this->carModel = new Car($db);
        $this->userModel = new User($db);
        $this->recommendationModel = new ServiceRecommendation($db);
    }

    /**
     * Display available services
     * 
     * @return array Array of available services
     */
    public function getAvailableServices(): array {
        try {
            return $this->serviceModel->getAllServices();
        } catch (Exception $e) {
            error_log("Error getting available services: " . $e->getMessage());
            throw new Exception('Could not retrieve available services');
        }
    }

    /**
     * Get average service frequency for a user
     * 
     * @param int $userId User ID
     * @return float|null Average service frequency in days
     */
    public function getAverageServiceFrequency(int $userId): ?float {
        try {
            return $this->serviceHistoryModel->getAverageServiceFrequency($userId);
        } catch (Exception $e) {
            error_log("Error getting average service frequency: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get details for a specific service
     * 
     * @param int $serviceId Service ID to retrieve
     * @return array|null Service details or null if not found
     */
    public function getServiceDetails(int $serviceId): ?array {
        try {
            return $this->serviceModel->getById($serviceId);
        } catch (Exception $e) {
            error_log("Error getting service details: " . $e->getMessage());
            throw new Exception('Could not retrieve service details');
        }
    }

    /**
     * Book a new service appointment
     * 
     * @param array $bookingData Booking information
     * @param int $userId User ID making the booking
     * @return int Booking ID
     */
    public function bookService(array $bookingData, int $userId): int {
        try {
            // Validate car belongs to user
            $userCars = $this->carModel->getByUserId($userId);
            $carBelongsToUser = false;
            
            foreach ($userCars as $car) {
                if ((int)$car['id'] === (int)$bookingData['car_id']) {
                    $carBelongsToUser = true;
                    break;
                }
            }
            
            if (!$carBelongsToUser) {
                throw new Exception('Unauthorized access to vehicle');
            }
            
            // Validate service exists
            $service = $this->serviceModel->getById((int)$bookingData['service_id']);
            if (!$service) {
                throw new Exception('Invalid service selected');
            }
            
            // Validate time slot is available
            if (!$this->isTimeSlotAvailable($bookingData['date'], $bookingData['time_slot'])) {
                throw new Exception('Selected time slot is not available');
            }
            
            // Create booking
            $bookingModel = new Booking($this->db);
            
            return $bookingModel->createBooking($bookingData);
        } catch (Exception $e) {
            error_log("Error booking service: " . $e->getMessage());
            throw new Exception('Failed to book service: ' . $e->getMessage());
        }
    }

    /**
     * Check if a time slot is available
     * 
     * @param string $date Date in Y-m-d format
     * @param string $timeSlot Time slot identifier
     * @return bool True if time slot is available
     */
    public function isTimeSlotAvailable(string $date, string $timeSlot): bool {
        try {
            $sql = "SELECT COUNT(*) FROM bookings 
                   WHERE date = :date AND time_slot = :time_slot";
                   
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':date', $date, PDO::PARAM_STR);
            $stmt->bindValue(':time_slot', $timeSlot, PDO::PARAM_STR);
            $stmt->execute();
            
            $count = (int)$stmt->fetchColumn();
            
            // Check against maximum bookings per slot (could be configurable)
            $maxBookingsPerSlot = 2;
            
            return $count < $maxBookingsPerSlot;
        } catch (Exception $e) {
            error_log("Error checking time slot availability: " . $e->getMessage());
            // Default to not available if there's an error checking
            return false;
        }
    }

    /**
     * Get available time slots for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @return array Available time slots
     */
    public function getAvailableTimeSlots(string $date): array {
        try {
            // Define all possible time slots
            $allTimeSlots = [
                '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
                '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
            ];
            
            // Get booked time slots for this date
            $sql = "SELECT time_slot, COUNT(*) as booking_count 
                   FROM bookings 
                   WHERE date = :date 
                   GROUP BY time_slot";
                   
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':date', $date, PDO::PARAM_STR);
            $stmt->execute();
            
            $bookedSlots = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Maximum bookings per slot
            $maxBookingsPerSlot = 2;
            
            $availableSlots = [];
            foreach ($allTimeSlots as $slot) {
                $bookingCount = $bookedSlots[$slot] ?? 0;
                $availableSlots[$slot] = [
                    'time_slot' => $slot,
                    'available' => ($bookingCount < $maxBookingsPerSlot),
                    'remaining_spots' => $maxBookingsPerSlot - $bookingCount
                ];
            }
            
            return $availableSlots;
        } catch (Exception $e) {
            error_log("Error getting available time slots: " . $e->getMessage());
            throw new Exception('Failed to get available time slots');
        }
    }

    /**
     * Record a completed service in the service history
     * 
     * @param array $serviceData Service completion data
     * @param int $userId User ID for validation
     * @return int Service history entry ID
     */
    public function recordServiceCompletion(array $serviceData, int $userId): int {
        try {
            // Validate car belongs to user
            $userCars = $this->carModel->getByUserId($userId);
            $carBelongsToUser = false;
            
            foreach ($userCars as $car) {
                if ((int)$car['id'] === (int)$serviceData['car_id']) {
                    $carBelongsToUser = true;
                    break;
                }
            }
            
            if (!$carBelongsToUser) {
                throw new Exception('Unauthorized access to vehicle');
            }
            
            // Add service history entry
            $historyId = $this->serviceHistoryModel->addEntry($serviceData);
            
            // Update car's last service date
            $this->carModel->updateLastServiceDate((int)$serviceData['car_id'], $serviceData['service_date']);
            
            // If this service was from a recommendation, mark it as completed
            if (isset($serviceData['recommendation_id']) && (int)$serviceData['recommendation_id'] > 0) {
                $this->recommendationModel->markAsCompleted((int)$serviceData['recommendation_id']);
            }
            
            // Send notification to the user about service completion
            $this->sendServiceCompletionNotification($userId, $serviceData);
            
            return $historyId;
        } catch (Exception $e) {
            error_log("Error recording service completion: " . $e->getMessage());
            throw new Exception('Failed to record service completion');
        }
    }

    /**
     * Send notification about service completion
     * 
     * @param int $userId User ID
     * @param array $serviceData Service data
     * @return bool Success status
     */
    protected function sendServiceCompletionNotification(int $userId, array $serviceData): bool {
        try {
            // Get user details
            $user = $this->userModel->getById($userId);
            
            // Get car details
            $car = $this->carModel->getById((int)$serviceData['car_id']);
            
            // Get service details
            $service = $this->serviceModel->getById((int)$serviceData['service_id']);
            
            // Prepare notification message
            $notificationData = [
                'user_id' => $userId,
                'title' => 'Service Completed',
                'message' => "Your {$car['make']} {$car['model']} has completed its {$service['name']} service.",
                'type' => 'service_completion',
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Save notification (assuming NotificationModel exists)
            // For now, just return true as this is a stub implementation
            // In a real implementation, you would:
            // 1. Save to database
            // 2. Send email/SMS if configured
            // 3. Push notification if mobile app is used
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending service completion notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate AI-driven service recommendations for a user's vehicles
     * 
     * @param int $userId User to generate recommendations for
     * @return array Recommendations for each of the user's vehicles
     */
    public function generateServiceRecommendations(int $userId): array {
        try {
            $recommendations = [];
            $userCars = $this->carModel->getByUserId($userId);
            
            foreach ($userCars as $car) {
                $carId = (int)$car['id'];
                
                // Get car details with maintenance info
                $carDetails = $this->carModel->getById($carId);
                $maintenanceSchedule = $this->carModel->getMaintenanceSchedule($carId);
                
                // Get service history for this car
                $carHistory = $this->getCarServiceHistory($carId);
                
                // Calculate days since last service
                $daysSinceLastService = null;
                $lastServiceDate = $carDetails['last_service_date'] ?? null;
                
                if ($lastServiceDate) {
                    $lastServiceDateTime = new DateTime($lastServiceDate);
                    $currentDateTime = new DateTime();
                    $interval = $currentDateTime->diff($lastServiceDateTime);
                    $daysSinceLastService = $interval->days;
                }
                
                // Generate recommendations based on maintenance schedule and history
                $carRecommendations = $this->analyzeMaintenance(
                    $carId,
                    (int)($carDetails['year'] ?? date('Y')),
                    $carDetails['make'] ?? '',
                    $carDetails['model'] ?? '',
                    (int)($carDetails['current_mileage'] ?? 0),
                    $daysSinceLastService,
                    $maintenanceSchedule ?? [],
                    $carHistory
                );
                
                // Process through AI model for enhanced recommendations
                $enhancedRecommendations = $this->enhanceRecommendationsWithAI($carRecommendations, $carDetails, $carHistory);
                
                $recommendations[$carId] = $enhancedRecommendations;
            }
            
            // Save recommendations to database
            $this->saveServiceRecommendations($recommendations, $userId);
            
            return $recommendations;
        } catch (Exception $e) {
            error_log("Error generating service recommendations: " . $e->getMessage());
            throw new Exception('Failed to generate service recommendations');
        }
    }

    /**
     * Enhance service recommendations using AI
     * 
     * @param array $baseRecommendations Basic recommendations from business rules
     * @param array $carDetails Car details
     * @param array $serviceHistory Service history
     * @return array Enhanced recommendations
     */
    protected function enhanceRecommendationsWithAI(array $baseRecommendations, array $carDetails, array $serviceHistory): array {
        try {
            // This is where you would integrate with an AI model
            // For now, this is a placeholder that just returns the base recommendations
            
            // In a real implementation, you might:
            // 1. Prepare data for the AI model
            // 2. Call the AI service (e.g., via API to TensorFlow, scikit-learn, or OpenAI)
            // 3. Process and integrate the AI recommendations
            
            // Example implementation with a mock AI enhancement
            foreach ($baseRecommendations as &$recommendation) {
                // Add personalized message based on car details and history
                $recommendation['ai_enhanced'] = true;
                $recommendation['personalized_message'] = $this->generatePersonalizedMessage(
                    $recommendation,
                    $carDetails,
                    $serviceHistory
                );
            }
            
            return $baseRecommendations;
        } catch (Exception $e) {
            error_log("Error enhancing recommendations with AI: " . $e->getMessage());
            // If AI enhancement fails, fall back to base recommendations
            return $baseRecommendations;
        }
    }

    /**
     * Generate personalized message for a recommendation
     * 
     * @param array $recommendation The recommendation
     * @param array $carDetails Car details
     * @param array $serviceHistory Service history
     * @return string Personalized message
     */
    protected function generatePersonalizedMessage(array $recommendation, array $carDetails, array $serviceHistory): string {
        $carAge = (int)date('Y') - (int)($carDetails['year'] ?? date('Y'));
        $mileage = (int)($carDetails['current_mileage'] ?? 0);
        $make = $carDetails['make'] ?? '';
        $model = $carDetails['model'] ?? '';
        
        $serviceName = $recommendation['service_name'] ?? '';
        $urgency = $recommendation['urgency'] ?? 'medium';
        
        // Base message template
        $message = "Based on your {$make} {$model}'s ";
        
        // Add context based on recommendation type
        if ($recommendation['mileage_based'] ?? false) {
            $message .= "current mileage of " . number_format($mileage) . " km";
        } elseif ($recommendation['time_based'] ?? false) {
            $message .= "service history and age of {$carAge} years";
        } else {
            $message .= "maintenance needs";
        }
        
        // Add service recommendation
        $message .= ", we recommend scheduling a {$serviceName} service";
        
        // Add urgency
        if ($urgency === 'high') {
            $message .= " as soon as possible to prevent potential damage and expensive repairs.";
        } elseif ($urgency === 'medium') {
            $message .= " within the next few weeks to maintain optimal performance.";
        } else {
            $message .= " during your next convenient opportunity to keep your vehicle in top condition.";
        }
        
        return $message;
    }

    /**
     * Get service history for a specific car
     * 
     * @param int $carId Car ID to get history for
     * @return array Service history entries for this car
     */
    public function getCarServiceHistory(int $carId): array {
        try {
            $sql = "SELECT sh.*, s.name as service_name 
                   FROM service_history sh 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE sh.car_id = :car_id 
                   ORDER BY sh.service_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':car_id', $carId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting car service history: " . $e->getMessage());
            throw new Exception('Failed to get car service history');
        }
    }

    /**
     * Analyze car maintenance needs and generate recommendations
     * 
     * @param int $carId Car ID
     * @param int $year Car year
     * @param string $make Car make
     * @param string $model Car model
     * @param int $mileage Current mileage
     * @param int|null $daysSinceService Days since last service
     * @param array $maintenanceSchedule Maintenance schedule data
     * @param array $serviceHistory Service history entries
     * @return array Recommendations for this vehicle
     */
    public function analyzeMaintenance(
        int $carId,
        int $year,
        string $make,
        string $model,
        int $mileage,
        ?int $daysSinceService,
        array $maintenanceSchedule,
        array $serviceHistory
    ): array {
        $recommendations = [];
        $allServices = $this->serviceModel->getAllServices();
        
        // Standard maintenance intervals based on mileage
        $standardIntervals = [
            'Oil Change' => 5000,
            'Tire Rotation' => 7500,
            'Air Filter Replacement' => 15000,
            'Brake Inspection' => 10000,
            'Full Inspection' => 15000,
            'Transmission Service' => 30000,
            'Coolant Flush' => 30000,
            'Spark Plugs' => 60000,
            'Timing Belt' => 100000
        ];
        
        // Check if scheduled maintenance is due based on mileage
        foreach ($allServices as $service) {
            $serviceName = $service['name'];
            $serviceId = (int)$service['id'];
            
            // Skip if service doesn't have a standard interval
            if (!isset($standardIntervals[$serviceName])) {
                continue;
            }
            
            $interval = $standardIntervals[$serviceName];
            
            // Get the last time this particular service was performed
            $lastServiceOfType = null;
            $mileageAtLastService = 0;
            
            foreach ($serviceHistory as $historyEntry) {
                if ((int)$historyEntry['service_id'] === $serviceId) {
                    $lastServiceOfType = $historyEntry;
                    $mileageAtLastService = (int)$historyEntry['mileage'];
                    break;
                }
            }
            
            // Calculate if service is due
            $isDue = false;
            $urgency = 'low';
            $reason = '';
            
            if ($lastServiceOfType === null) {
                // Service has never been performed
                if ($mileage > $interval) {
                    $isDue = true;
                    $urgency = 'medium';
                    $reason = "This service has never been recorded and your vehicle has exceeded the recommended interval of " . number_format($interval) . " km.";
                }
            } else {
                // Calculate mileage since last service
                $mileageSinceService = $mileage - $mileageAtLastService;
                
                if ($mileageSinceService >= $interval) {
                    $isDue = true;
                    $percentOverdue = ($mileageSinceService - $interval) / $interval * 100;
                    
                    if ($percentOverdue > 50) {
                        $urgency = 'high';
                        $reason = "Significantly overdue by " . number_format($mileageSinceService - $interval) . " km since last service.";
                    } elseif ($percentOverdue > 10) {
                        $urgency = 'medium';
                        $reason = "Overdue by " . number_format($mileageSinceService - $interval) . " km since last service.";
                    } else {
                        $urgency = 'low';
                        $reason = "Due based on mileage interval of " . number_format($interval) . " km.";
                    }
                }
            }
            
            // Add recommendation if service is due
            if ($isDue) {
                $recommendations[] = [
                    'car_id' => $carId,
                    'service_id' => $serviceId,
                    'service_name' => $serviceName,
                    'urgency' => $urgency,
                    'reason' => $reason,
                    'mileage_based' => true,
                    'time_based' => false
                ];
            }
        }
        
        // Add time-based recommendations
        if ($daysSinceService !== null && $daysSinceService > 180) {
            // It's been over 6 months since any service
            $recommendations[] = [
                'car_id' => $carId,
                'service_id' => 1, // Assume ID 1 is general inspection
                'service_name' => 'General Inspection',
                'urgency' => $daysSinceService > 365 ? 'high' : 'medium',
                'reason' => "It's been " . $daysSinceService . " days since your last service. We recommend a general inspection.",
                'mileage_based' => false,
                'time_based' => true
            ];
        }
        
        // Check seasonal recommendations
        $currentMonth = (int)date('n');
        
        // Winter preparation (fall months)
        if ($currentMonth >= 9 && $currentMonth <= 11) {
            $hasWinterPrep = false;
            
            // Check if winter prep was done in the last 3 months
            foreach ($serviceHistory as $history) {
                if (
                    strpos(strtolower($history['service_name']), 'winter') !== false ||
                    strpos(strtolower($history['description'] ?? ''), 'winter') !== false
                ) {
                    $serviceDate = new DateTime($history['service_date']);
                    $currentDate = new DateTime();
                    $interval = $currentDate->diff($serviceDate);
                    
                    if ($interval->days < 90) {
                        $hasWinterPrep = true;
                        break;
                    }
                }
            }
            
            if (!$hasWinterPrep) {
                $recommendations[] = [
                    'car_id' => $carId,
                    'service_id' => 6, // Assume ID 6 is seasonal checkup
                    'service_name' => 'Winter Preparation',
                    'urgency' => 'medium',
                    'reason' => "Fall is here! Prepare your " . $year . " " . $make . " " . $model . " for winter with our seasonal service package.",
                    'mileage_based' => false,
                    'time_based' => true
                ];
            }
        }
        
        // Sort recommendations by urgency
        usort($recommendations, function($a, $b) {
            $urgencyValue = [
                'high' => 3,
                'medium' => 2,
                'low' => 1
            ];
            
            return $urgencyValue[$b['urgency']] - $urgencyValue[$a['urgency']];
        });
        
        return $recommendations;
    }

    /**
     * Get user's service history with optional filtering
     * 
     * @param int $userId User ID to get history for
     * @param array $filters Optional filters (car_id, start_date, end_date, service_type)
     * @return array Filtered service history
     */
    public function getUserServiceHistory(int $userId, array $filters = []): array {
        try {
            $carId = $filters['car_id'] ?? null;
            $startDate = $filters['start_date'] ?? null;
            $endDate = $filters['end_date'] ?? null;
            $serviceType = $filters['service_type'] ?? null;
            
            return $this->serviceHistoryModel->getFilteredHistory(
                $userId,
                $carId ? (int)$carId : null,
                $startDate,
                $endDate,
                $serviceType ? (int)$serviceType : null
            );
        } catch (Exception $e) {
            error_log("Error getting user service history: " . $e->getMessage());
            throw new Exception('Failed to retrieve service history');
        }
    }

    /**
     * Get service history statistics for a user
     * 
     * @param int $userId User ID
     * @return array Service statistics
     */
    public function getServiceStatistics(int $userId): array {
        try {
            return $this->serviceHistoryModel->getServiceStatistics($userId);
        } catch (Exception $e) {
            error_log("Error getting service statistics: " . $e->getMessage());
            throw new Exception('Failed to retrieve service statistics');
        }
    }

    /**
     * Save AI-generated service recommendations
     * 
     * @param array $recommendations Recommendations to save
     * @param int $userId User ID
     * @return int Number of recommendations saved
     */
    public function saveServiceRecommendations(array $recommendations, int $userId): int {
        try {
            $count = 0;
            
            foreach ($recommendations as $carId => $carRecommendations) {
                // Verify car belongs to user
                $car = $this->carModel->getById((int)$carId);
                
                if ((int)($car['user_id'] ?? 0) !== $userId) {
                    continue; // Skip if car doesn't belong to user
                }
                
                foreach ($carRecommendations as $recommendation) {
                    $data = [
                        'car_id' => $carId,
                        'service_id' => $recommendation['service_id'],
                        'urgency' => $recommendation['urgency'],
                        'reason' => $recommendation['reason'],
                        'created_date' => date('Y-m-d'),
                        'status' => 'pending'
                    ];
                    
                    // Add personalized message if available
                    if (isset($recommendation['personalized_message'])) {
                        $data['personalized_message'] = $recommendation['personalized_message'];
                    }
                    
                    $this->recommendationModel->addRecommendation($data);
                    $count++;
                }
            }
            
            return $count;
        } catch (Exception $e) {
            error_log("Error saving service recommendations: " . $e->getMessage());
            throw new Exception('Failed to save service recommendations');
        }
    }

   /**
 * Get pending service recommendations for a user
 * 
 * @param int $userId User ID
 * @return array Pending recommendations
 */
public function getUserRecommendations(int $userId): array {
    try {
        return $this->recommendationModel->getPendingRecommendations($userId);
    } catch (Exception $e) {
        error_log("Error getting user recommendations: " . $e->getMessage());
        throw new Exception('Failed to retrieve service recommendations');
    }
}

/**
 * Get admin dashboard statistics
 * 
 * @return array Dashboard statistics
 */
public function getAdminDashboardStatistics(): array {
    try {
        $stats = [
            'total_active_users' => $this->userModel->getActiveUserCount(),
            'total_cars' => $this->carModel->getTotalCarCount(),
            'total_bookings' => $this->getBookingCount(),
            'pending_bookings' => $this->getPendingBookingCount(),
            'services_by_month' => $this->getServiceCountByMonth(),
            'revenue_by_month' => $this->getRevenueByMonth(),
            'popular_services' => $this->getPopularServices()
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting admin dashboard statistics: " . $e->getMessage());
        throw new Exception('Failed to retrieve dashboard statistics');
    }
}

/**
 * Get count of bookings
 * 
 * @param string|null $status Optional status filter
 * @return int Number of bookings
 */
private function getBookingCount(?string $status = null): int {
    try {
        $sql = "SELECT COUNT(*) FROM bookings";
        $params = [];
        
        if ($status !== null) {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting booking count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of pending bookings
 * 
 * @return int Number of pending bookings
 */
private function getPendingBookingCount(): int {
    return $this->getBookingCount('pending');
}

/**
 * Get service count by month
 * 
 * @param int $months Number of months to include
 * @return array Monthly service counts
 */
private function getServiceCountByMonth(int $months = 12): array {
    try {
        $sql = "SELECT 
                   DATE_FORMAT(service_date, '%Y-%m') as month,
                   COUNT(*) as service_count
               FROM service_history
               WHERE service_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :months MONTH)
               GROUP BY DATE_FORMAT(service_date, '%Y-%m')
               ORDER BY month ASC";
               
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("Error getting service count by month: " . $e->getMessage());
        return [];
    }
}

/**
 * Get revenue by month
 * 
 * @param int $months Number of months to include
 * @return array Monthly revenue
 */
private function getRevenueByMonth(int $months = 12): array {
    try {
        $sql = "SELECT 
                   DATE_FORMAT(service_date, '%Y-%m') as month,
                   SUM(cost) as revenue
               FROM service_history
               WHERE service_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :months MONTH)
               GROUP BY DATE_FORMAT(service_date, '%Y-%m')
               ORDER BY month ASC";
               
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Convert revenue values to float
        foreach ($result as $month => $revenue) {
            $result[$month] = (float)$revenue;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error getting revenue by month: " . $e->getMessage());
        return [];
    }
}

/**
 * Get most popular services
 * 
 * @param int $limit Number of services to return
 * @return array Popular services with count
 */
private function getPopularServices(int $limit = 5): array {
    try {
        $sql = "SELECT 
                   s.id, s.name, COUNT(*) as service_count
               FROM service_history sh
               JOIN services s ON sh.service_id = s.id
               GROUP BY s.id, s.name
               ORDER BY service_count DESC
               LIMIT :limit";
               
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting popular services: " . $e->getMessage());
        return [];
    }
}

/**
 * Send service reminder notifications
 * 
 * @return int Number of notifications sent
 */
public function sendServiceReminders(): int {
    try {
        // Get cars due for service based on mileage and time intervals
        $sql = "SELECT 
                   c.id as car_id, c.user_id, c.make, c.model, c.year, c.current_mileage,
                   c.last_service_date, u.email, u.phone, u.notification_preference
               FROM cars c
               JOIN users u ON c.user_id = u.id
               WHERE 
                   c.last_service_date < DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                   OR (
                       c.next_service_mileage IS NOT NULL 
                       AND c.current_mileage >= c.next_service_mileage
                   )";
                   
        $stmt = $this->db->query($sql);
        $dueCars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notificationCount = 0;
        
        foreach ($dueCars as $car) {
            $userId = (int)$car['user_id'];
            $carId = (int)$car['car_id'];
            
            // Generate personalized recommendation
            $recommendations = $this->generateServiceRecommendations($userId);
            
            if (isset($recommendations[$carId]) && !empty($recommendations[$carId])) {
                // Get highest priority recommendation
                $highestPriority = $recommendations[$carId][0];
                
                // Prepare notification data
                $notificationData = [
                    'user_id' => $userId,
                    'car_id' => $carId,
                    'title' => 'Service Reminder',
                    'message' => "Your {$car['make']} {$car['model']} is due for {$highestPriority['service_name']}. {$highestPriority['reason']}",
                    'type' => 'service_reminder',
                    'status' => 'unread',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Send notification based on user preference
                $preference = $car['notification_preference'] ?? 'email';
                
                if ($preference === 'email' && !empty($car['email'])) {
                    // Send email notification
                    // In a real implementation, you would send an actual email
                    // For now, just log it
                    error_log("Sending email reminder to user {$userId} for car {$carId}");
                    $notificationCount++;
                } elseif ($preference === 'sms' && !empty($car['phone'])) {
                    // Send SMS notification
                    // In a real implementation, you would send an actual SMS
                    // For now, just log it
                    error_log("Sending SMS reminder to user {$userId} for car {$carId}");
                    $notificationCount++;
                } else {
                    // Send in-app notification
                    // In a real implementation, you would save to notification table
                    // For now, just log it
                    error_log("Sending in-app reminder to user {$userId} for car {$carId}");
                    $notificationCount++;
                }
            }
        }
        
        return $notificationCount;
    } catch (Exception $e) {
        error_log("Error sending service reminders: " . $e->getMessage());
        return 0;
    }
}
}
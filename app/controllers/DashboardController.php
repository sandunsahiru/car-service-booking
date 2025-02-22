<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/ServiceHistory.php';
require_once __DIR__ . '/../models/ServiceRecommendation.php';

class DashboardController {
    private PDO $db;
    private User $user;
    private Car $car;
    private Booking $booking;
    private ServiceHistory $serviceHistory;
    private ServiceRecommendation $recommendation;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->car = new Car($db);
        $this->booking = new Booking($db);
        $this->serviceHistory = new ServiceHistory($db);
        $this->recommendation = new ServiceRecommendation($db);
    }

    /**
     * Get dashboard data for a user
     */
    public function getDashboardData(int $userId): array {
        try {
            $userData = $this->user->getUserById($userId);
            if (!$userData) {
                throw new Exception('User not found');
            }

            // Start transaction for consistent data
            $this->db->beginTransaction();

            // Get all required data with empty data handling
            $data = [
                'user' => $userData,
                'cars' => $this->car->getByUserId($userId) ?: [],
                'upcomingBookings' => $this->booking->getUpcomingBookings($userId, 5) ?: [],
                'recentServices' => $this->serviceHistory->getRecentHistory($userId, 3) ?: [],
                'recommendations' => $this->recommendation->getPendingRecommendations($userId) ?: [],
                'stats' => [
                    'totalCars' => count($this->car->getByUserId($userId)),
                    'totalBookings' => $this->booking->getTotalBookings($userId) ?: 0,
                    'totalServiceCost' => $this->serviceHistory->getTotalServiceCost($userId) ?: 0.00,
                    'highPriorityRecommendations' => $this->recommendation->getHighPriorityCount($userId) ?: 0
                ]
            ];

            // Add maintenance schedule for each car if any exists
            if (!empty($data['cars'])) {
                foreach ($data['cars'] as &$car) {
                    try {
                        $car['maintenanceSchedule'] = $this->car->getMaintenanceSchedule($car['id']);
                    } catch (Exception $e) {
                        $car['maintenanceSchedule'] = null;
                        error_log("Error getting maintenance schedule for car ID {$car['id']}: " . $e->getMessage());
                    }
                }
            }

            $this->db->commit();
            return $data;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error getting dashboard data: " . $e->getMessage());
            throw new Exception('Failed to load dashboard data');
        }
    }

    /**
     * Send JSON response
     */
    private function sendResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Process AJAX requests for dashboard data
     */
    public function handleAjaxRequest(): void {
        try {
            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            // Validate that user is logged in
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('User not authenticated');
            }

            // Validate CSRF token
            if (
                empty($_POST['csrf_token']) ||
                empty($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            // Get dashboard data
            $dashboardData = $this->getDashboardData($_SESSION['user_id']);
            
            $this->sendResponse([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (Exception $e) {
            $this->sendResponse([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
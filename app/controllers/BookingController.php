<?php

declare(strict_types=1);

// Check for direct access
if (!defined('PREVENT_DIRECT_ACCESS')) {
    die('Direct access is not allowed.');
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/ServiceRecommendation.php';

/**
 * Controller for handling booking-related operations
 */
class BookingController
{
    private PDO $db;
    private User $user;
    private Car $car;
    private Booking $booking;
    private Service $service;
    private ServiceRecommendation $recommendation;

    /**
     * Initialize controller with database connection and models
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->user = new User($db);
        $this->car = new Car($db);
        $this->booking = new Booking($db);
        $this->service = new Service($db);
        $this->recommendation = new ServiceRecommendation($db);
    }

    /**
     * Get data for the booking page
     */
    public function getBookingPageData(int $userId, ?int $selectedCarId = null): array
    {
        try {
            return [
                'userData' => $this->user->getUserById($userId),
                'userCars' => $this->car->getByUserId($userId),
                'upcomingBookings' => $this->booking->getUpcomingBookings($userId),
                'bookingStats' => $this->booking->getBookingStats($userId),
                'availableServices' => $this->service->getAllServices(),
                'recommendations' => $this->recommendation->getPendingRecommendations($userId),
                'selectedCarId' => $selectedCarId
            ];
        } catch (Exception $e) {
            error_log("Error getting booking page data: " . $e->getMessage());
            throw new Exception('Failed to load booking page data');
        }
    }

    /**
     * Create a new booking
     */
    public function createBooking(array $data): int|false
    {
        try {
            // Validate CSRF token
            if (
                empty($data['csrf_token']) ||
                empty($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            // Validate user ID matches session
            if ((int)$data['user_id'] !== (int)$_SESSION['user_id']) {
                throw new Exception('Unauthorized action');
            }

            // Validate required fields
            $required = ['car_id', 'service_id', 'booking_date', 'booking_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
                }
            }

            // Prepare booking data
            $bookingData = [
                'user_id' => (int)$data['user_id'],
                'car_id' => (int)$data['car_id'],
                'service_id' => (int)$data['service_id'],
                'booking_date' => date('Y-m-d', strtotime($data['booking_date'])),
                'booking_time' => $data['booking_time'],
                'status' => 'pending',
                'notes' => isset($data['notes']) ? trim($data['notes']) : ''
            ];

            // If recommendation ID is provided, include it
            if (!empty($data['recommendation_id'])) {
                $bookingData['recommendation_id'] = (int)$data['recommendation_id'];
            }

            return $this->booking->createBooking($bookingData);
        } catch (Exception $e) {
            error_log("Error creating booking: " . $e->getMessage());
            throw new Exception('Failed to create booking: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(array $data): bool
    {
        try {
            // Validate CSRF token
            if (
                empty($data['csrf_token']) ||
                empty($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            if (empty($data['booking_id'])) {
                throw new Exception('Booking ID is required');
            }

            return $this->booking->cancelBooking((int)$data['booking_id'], (int)$_SESSION['user_id']);
        } catch (Exception $e) {
            error_log("Error cancelling booking: " . $e->getMessage());
            throw new Exception('Failed to cancel booking: ' . $e->getMessage());
        }
    }

    /**
     * Reschedule a booking
     */
    public function rescheduleBooking(array $data): bool
    {
        try {
            // Validate CSRF token
            if (
                empty($data['csrf_token']) ||
                empty($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            // Validate required fields
            $required = ['booking_id', 'booking_date', 'booking_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
                }
            }

            return $this->booking->rescheduleBooking(
                (int)$data['booking_id'],
                (int)$_SESSION['user_id'],
                date('Y-m-d', strtotime($data['booking_date'])),
                $data['booking_time']
            );
        } catch (Exception $e) {
            error_log("Error rescheduling booking: " . $e->getMessage());
            throw new Exception('Failed to reschedule booking: ' . $e->getMessage());
        }
    }

    /**
     * Get available time slots for a given date and service
     */
    public function getAvailableTimeSlots(int $serviceId, string $date): array
    {
        try {
            return $this->booking->getAvailableTimeSlots($serviceId, date('Y-m-d', strtotime($date)));
        } catch (Exception $e) {
            error_log("Error getting available time slots: " . $e->getMessage());
            throw new Exception('Failed to get available time slots');
        }
    }

    /**
     * Get booking history with pagination
     */
    public function getBookingHistory(int $userId, int $page = 1, int $limit = 10): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $bookings = $this->booking->getBookingHistory($userId, $limit, $offset);
            $totalCount = $this->booking->getBookingHistoryCount($userId);
            $totalPages = ceil($totalCount / $limit);

            return [
                'bookings' => $bookings,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalCount,
                    'limit' => $limit
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting booking history: " . $e->getMessage());
            throw new Exception('Failed to get booking history');
        }
    }

    /**
     * Get booking details by ID
     */
    public function getBookingDetails(int $bookingId, int $userId): array
    {
        try {
            $booking = $this->booking->getById($bookingId);

            if (!$booking || (int)$booking['user_id'] !== $userId) {
                throw new Exception('Booking not found or not authorized');
            }

            return $booking;
        } catch (Exception $e) {
            error_log("Error getting booking details: " . $e->getMessage());
            throw new Exception('Failed to get booking details');
        }
    }

    /**
     * Handle AJAX requests for booking operations
     */
    public function handleAjaxRequest(): void
    {
        try {
            // Set up response headers
            header('Content-Type: application/json');

            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            // Validate AJAX header
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                throw new Exception('Invalid request type');
            }

            // Validate user is logged in
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

            // Get action
            if (empty($_POST['action'])) {
                throw new Exception('No action specified');
            }

            // Execute requested action
            switch ($_POST['action']) {
                case 'create_booking':
                    $bookingId = $this->createBooking($_POST);
                    echo json_encode([
                        'success' => true,
                        'booking_id' => $bookingId,
                        'message' => 'Booking created successfully'
                    ]);
                    break;

                case 'cancel_booking':
                    $result = $this->cancelBooking($_POST);
                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Booking cancelled successfully' : 'Failed to cancel booking'
                    ]);
                    break;

                case 'reschedule_booking':
                    $result = $this->rescheduleBooking($_POST);
                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Booking rescheduled successfully' : 'Failed to reschedule booking'
                    ]);
                    break;

                case 'get_available_slots':
                    if (empty($_POST['service_id']) || empty($_POST['date'])) {
                        throw new Exception('Service ID and date are required');
                    }
                    $slots = $this->getAvailableTimeSlots((int)$_POST['service_id'], $_POST['date']);
                    echo json_encode([
                        'success' => true,
                        'slots' => $slots
                    ]);
                    break;

                case 'get_booking_details':
                    if (empty($_POST['booking_id'])) {
                        throw new Exception('Booking ID is required');
                    }
                    $booking = $this->getBookingDetails((int)$_POST['booking_id'], (int)$_SESSION['user_id']);
                    echo json_encode([
                        'success' => true,
                        'booking' => $booking
                    ]);
                    break;

                case 'get_booking_history':
                    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
                    $history = $this->getBookingHistory((int)$_SESSION['user_id'], $page, $limit);
                    echo json_encode([
                        'success' => true,
                        'data' => $history
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            error_log("BookingController AJAX error: " . $e->getMessage());
        }
    }

    /**
     * Process form submission for booking page
     */
    public function processBookingForm(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'redirect' => null
        ];

        try {
            // Process create booking request
            if (isset($_POST['book_service'])) {
                $_POST['user_id'] = $_SESSION['user_id'];
                $bookingId = $this->createBooking($_POST);

                if ($bookingId) {
                    $result['success'] = true;
                    $result['message'] = 'Service booked successfully! You will receive confirmation shortly.';
                    // After successful booking
                    $notification = new Notification($this->db);
                    $serviceInfo = $this->service->getById((int)$_POST['service_id']);
                    $notification->createBookingConfirmation(
                        $_SESSION['user_id'],
                        $bookingId,
                        [
                            'service_name' => $serviceInfo['name'],
                            'booking_date' => $_POST['booking_date'],
                            'booking_time' => $_POST['booking_time']
                        ]
                    );
                } else {
                    throw new Exception('Failed to create booking.');
                }
            }

            // Process cancel booking request
            else if (isset($_POST['cancel_booking'])) {
                $cancelled = $this->cancelBooking($_POST);

                if ($cancelled) {
                    $result['success'] = true;
                    $result['message'] = 'Booking cancelled successfully.';
                } else {
                    throw new Exception('Failed to cancel booking.');
                }
            }

            // Process reschedule booking request
            else if (isset($_POST['reschedule_booking'])) {
                $rescheduled = $this->rescheduleBooking($_POST);

                if ($rescheduled) {
                    $result['success'] = true;
                    $result['message'] = 'Booking rescheduled successfully.';
                } else {
                    throw new Exception('Failed to reschedule booking.');
                }
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
            error_log("Error processing booking form: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get service recommendations for a specific vehicle
     */
    public function getRecommendationsForVehicle(int $carId, int $userId): array
    {
        try {
            // Validate the car belongs to the user
            $userCars = $this->car->getByUserId($userId);
            $carFound = false;

            foreach ($userCars as $car) {
                if ((int)$car['id'] === $carId) {
                    $carFound = true;
                    break;
                }
            }

            if (!$carFound) {
                throw new Exception('Vehicle not found or not authorized');
            }

            return $this->recommendation->getRecommendationsForCar($carId);
        } catch (Exception $e) {
            error_log("Error getting recommendations for vehicle: " . $e->getMessage());
            throw new Exception('Failed to get recommendations');
        }
    }

    /**
     * Check if a user has any active bookings
     */
    public function hasActiveBookings(int $userId): bool
    {
        try {
            $upcomingBookings = $this->booking->getUpcomingBookings($userId, 1);
            return !empty($upcomingBookings);
        } catch (Exception $e) {
            error_log("Error checking active bookings: " . $e->getMessage());
            return false;
        }
    }
}

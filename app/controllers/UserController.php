<?php

declare(strict_types=1);

// Start output buffering
ob_start();

define('PREVENT_DIRECT_ACCESS', true);

// Clear any previous output and set JSON headers
while (ob_get_level()) {
    ob_end_clean();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php-error.log');

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('X-Content-Type-Options: nosniff');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * UserController handles user-related operations
 */
class UserController
{
    private $db;
    private $user;
    private $car;

    /**
     * Initialize controller with database connection and models
     */
    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
        $this->user = new User($this->db);
        $this->car = new Car($this->db);
    }

    /**
     * Send JSON response
     */
    private function sendResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Validate CSRF token
     */
    private function validateCSRFToken(): void
    {
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            error_log("CSRF validation failed");
            $this->sendResponse(['error' => 'Invalid security token'], 403);
        }
    }

    /**
     * Validate request headers
     */
    private function validateRequestHeaders(): void
    {
        if (
            !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
        ) {
            error_log("Invalid request headers: Missing or invalid X-Requested-With");
            $this->sendResponse(['error' => 'Invalid request'], 400);
        }
    }

    /**
     * Handle user registration
     */
    public function register(): void
    {
        try {
            error_log("Starting registration process");

            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
                $this->sendResponse(['error' => 'Invalid request method'], 405);
            }

            // Log request data (excluding password)
            $logData = $_POST;
            unset($logData['password']);
            error_log("Registration request data: " . print_r($logData, true));

            // Validate request and CSRF
            $this->validateRequestHeaders();
            $this->validateCSRFToken();

            // Validate required fields
            $required = ['name', 'email', 'phone', 'password'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    error_log("Missing required field: " . $field);
                    $this->sendResponse(['error' => ucfirst($field) . ' is required'], 400);
                }
            }

            // Validate email
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                error_log("Invalid email format: " . $_POST['email']);
                $this->sendResponse(['error' => 'Invalid email format'], 400);
            }

            // Validate phone number
            if (!preg_match('/^[0-9]{10}$/', $_POST['phone'])) {
                error_log("Invalid phone format: " . $_POST['phone']);
                $this->sendResponse(['error' => 'Invalid phone number format'], 400);
            }

            // Validate password
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $_POST['password'])) {
                error_log("Password validation failed");
                $this->sendResponse(['error' => 'Password must be at least 8 characters with letters, numbers, and special characters'], 400);
            }

            // Check if email exists
            if ($this->user->emailExists($_POST['email'])) {
                error_log("Email already exists: " . $_POST['email']);
                $this->sendResponse(['error' => 'Email already registered'], 400);
            }

            // Start transaction
            $this->db->beginTransaction();


            // Create user
            $userData = [
                'name' => strip_tags(trim($_POST['name'])),
                'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
                'phone' => preg_replace('/[^0-9]/', '', $_POST['phone']),
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT)
            ];

            $userId = $this->user->create($userData);
            if (!$userId) {
                throw new Exception('Failed to create account');
            }

            error_log("User created successfully with ID: " . $userId);

            // Register vehicle if provided
            if (empty($_POST['addLater']) && !empty($_POST['make'])) {
                $this->registerVehicle($userId);
            }

            // Commit transaction
            $this->db->commit();

            // Set session
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $userData['name'];
            $_SESSION['last_activity'] = time();

            error_log("Registration completed successfully for user ID: " . $userId);

            // Send success response
            $this->sendResponse([
                'success' => true,
                'redirect' => BASE_PATH . '/public/dashboard.php'
            ]);
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

            // Rollback transaction if active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle vehicle registration
     */
    private function registerVehicle(int $userId): void
    {
        try {
            error_log("Starting vehicle registration for user ID: " . $userId);

            // Validate vehicle fields
            $required = ['make', 'model', 'year', 'reg_number', 'mileage'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
                }
            }

            // Validate year
            $year = (int)$_POST['year'];
            $currentYear = (int)date('Y');
            if ($year < 1900 || $year > ($currentYear + 1)) {
                throw new Exception('Invalid vehicle year');
            }

            // Validate mileage
            $mileage = (int)$_POST['mileage'];
            if ($mileage < 0 || $mileage > 999999) {
                throw new Exception('Invalid mileage value');
            }

            // Create vehicle
            $carData = [
                'user_id' => $userId,
                'make' => strip_tags(trim($_POST['make'])),
                'model' => strip_tags(trim($_POST['model'])),
                'year' => $year,
                'reg_number' => strtoupper(trim($_POST['reg_number'])),
                'mileage' => $mileage,
                'last_service' => !empty($_POST['last_service']) ? date('Y-m-d', strtotime($_POST['last_service'])) : null
            ];

            if (!$this->car->create($carData)) {
                throw new Exception('Failed to register vehicle');
            }

            error_log("Vehicle registered successfully for user ID: " . $userId);
        } catch (Exception $e) {
            error_log("Vehicle registration error: " . $e->getMessage());
            throw $e; // Re-throw to be handled by the main registration process
        }
    }
}

// Process request
try {
    if (!isset($_POST['action'])) {
        throw new Exception('No action specified');
    }

    $controller = new UserController();

    switch ($_POST['action']) {
        case 'register':
            $controller->register();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Controller error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
// Prevent direct access
if (!defined('PREVENT_DIRECT_ACCESS')) {
    die('Direct access is not allowed.');
}

// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'car_service_booking');

// Application configuration
define('BASE_PATH', '/car-service-booking');
define('APP_URL', 'http://localhost' . BASE_PATH);
define('APP_NAME', 'Fix It');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('UTC');

// Security
define('CSRF_TOKEN_SECRET', 'NE60hAlMyF6wVlOt5+VDKpaU/I6FJ4Oa5df1gpG/MTg=');

// Now include database connection
require_once __DIR__ . '/database.php';
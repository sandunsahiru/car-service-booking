<?php
declare(strict_types=1);

// Prevent direct access
if (!defined('PREVENT_DIRECT_ACCESS')) {
    die('Direct access is not allowed.');
}

// Check if required constants are defined
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    die('Database configuration constants are not defined.');
}

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                error_log("Attempting database connection with host: " . DB_HOST);
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                error_log("Database connection successful");
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
}

// Global PDO instance
try {
    $pdo = Database::getInstance();
} catch (Exception $e) {
    error_log("Failed to initialize database connection: " . $e->getMessage());
    die("Service temporarily unavailable");
}
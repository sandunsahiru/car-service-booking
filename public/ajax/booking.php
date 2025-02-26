<?php
// Save this file as: /public/ajax/booking.php

// Set constant to prevent direct access
define('PREVENT_DIRECT_ACCESS', true);
// Define debug mode (set to true for development, false for production)
define('DEBUG_MODE', true);

// Include necessary files
require_once(__DIR__ . '/../../app/config/config.php');
require_once(__DIR__ . '/../../app/config/session.php');
require_once(__DIR__ . '/../../app/controllers/BookingController.php');

// Security headers
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Create controller instance
    $bookingController = new BookingController($pdo);

    // Handle the AJAX request
    $bookingController->handleAjaxRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Server error processing request',
        'debug' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : null
    ]);
    
    // Log error
    error_log("AJAX Booking error: " . $e->getMessage());
}
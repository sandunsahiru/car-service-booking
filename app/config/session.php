<?php
// Prevent direct access
if (!defined('PREVENT_DIRECT_ACCESS')) {
    die('Direct access is not allowed.');
}

// Only set session parameters if session hasn't started
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
    ini_set('session.use_strict_mode', '1');
    
    session_start();
}
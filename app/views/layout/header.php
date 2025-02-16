<?php
declare(strict_types=1);

// Prevent direct access and ensure configuration is loaded
if (!defined('PREVENT_DIRECT_ACCESS')) {
    die('Direct access is not allowed.');
}

// Include config and session if not already included
if (!defined('BASE_PATH')) {
    require_once(__DIR__ . '/../../config/config.php');
}

require_once(__DIR__ . '/../../config/session.php');

// Remove any output before this point
ob_start();

// Security headers
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self' https:; frame-src 'none'; object-src 'none'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// Clear any potentially invalid session data
if (!isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
    unset($_SESSION['user_name']);
}

// Check session expiration
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_PATH . '/public/login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Define login state
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Get current page
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Fix It - Smart car service booking system">
    <meta name="keywords" content="car service, auto repair, vehicle maintenance">
    <meta name="author" content="Fix It">
    <meta name="theme-color" content="#0d6efd">
    
    <title>Fix It - Smart Car Service<?php echo isset($pageTitle) ? " - $pageTitle" : ''; ?></title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    
    <!-- Critical CSS inline -->
    <style>
        .content-wrapper {
            margin-top: 76px;
            min-height: calc(100vh - 76px);
            display: flex;
            flex-direction: column;
        }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .dropdown-menu { margin-top: 0.5rem; }
        @media (max-width: 991.98px) {
            .navbar-collapse { padding: 1rem 0; }
            .dropdown-menu { border: none; padding-left: 1rem; }
        }
    </style>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo BASE_PATH; ?>/public/assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="<?php echo BASE_PATH; ?>/public/index.php">
                Fix It
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_PATH; ?>/public/services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'how-it-works' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_PATH; ?>/public/how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'about' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_PATH; ?>/public/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'contact' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_PATH; ?>/public/contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" 
                                       href="<?php echo BASE_PATH; ?>/public/dashboard.php">
                                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" 
                                       href="<?php echo BASE_PATH; ?>/public/profile.php">
                                        <i class="bi bi-person me-2"></i>Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $currentPage === 'my-vehicles' ? 'active' : ''; ?>" 
                                       href="<?php echo BASE_PATH; ?>/public/my-vehicles.php">
                                        <i class="bi bi-car-front me-2"></i>My Vehicles
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $currentPage === 'bookings' ? 'active' : ''; ?>" 
                                       href="<?php echo BASE_PATH; ?>/public/bookings.php">
                                        <i class="bi bi-calendar-check me-2"></i>My Bookings
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo BASE_PATH; ?>/public/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_PATH; ?>/public/login.php" 
                           class="btn btn-outline-primary me-2 <?php echo $currentPage === 'login' ? 'active' : ''; ?>">
                            Login
                        </a>
                        <a href="<?php echo BASE_PATH; ?>/public/register.php" 
                           class="btn btn-primary <?php echo $currentPage === 'register' ? 'active' : ''; ?>">
                            Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content Wrapper -->
    <main class="content-wrapper">
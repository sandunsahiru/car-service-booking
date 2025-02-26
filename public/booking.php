<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set constant to prevent direct access
define('PREVENT_DIRECT_ACCESS', true);

// Include configuration
require_once(__DIR__ . '/../app/config/config.php');
require_once(__DIR__ . '/../app/config/session.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/public/login.php');
    exit;
}

// Security headers
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self' https:; frame-src 'none'; object-src 'none'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

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
require_once(__DIR__ . '/../app/controllers/BookingController.php');
$bookingController = new BookingController($pdo);

// Get user data
require_once(__DIR__ . '/../app/models/User.php');
require_once(__DIR__ . '/../app/models/Car.php');
require_once(__DIR__ . '/../app/models/Booking.php');
require_once(__DIR__ . '/../app/models/Service.php');
require_once(__DIR__ . '/../app/models/ServiceRecommendation.php');

$user = new User($pdo);
$car = new Car($pdo);
$booking = new Booking($pdo);
$service = new Service($pdo);
$recommendation = new ServiceRecommendation($pdo);

try {
    $userData = $user->getUserById($_SESSION['user_id']);
    $userCars = $car->getByUserId($_SESSION['user_id']);
    $upcomingBookings = $booking->getUpcomingBookings($_SESSION['user_id']);
    $bookingStats = $booking->getBookingStats($_SESSION['user_id']);
    $availableServices = $service->getAllServices();

    // Get the selected car ID from URL if present
    $selectedCarId = isset($_GET['car']) ? (int)$_GET['car'] : null;
} catch (Exception $e) {
    error_log("Booking page data loading error: " . $e->getMessage());
    // Handle error appropriately
}

// Handle form submission for new booking
$bookingSuccess = false;
$bookingError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process booking creation
    if (isset($_POST['book_service'])) {
        try {
            // Validate CSRF token
            if (
                empty($_POST['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            // Add user_id to the post data
            $_POST['user_id'] = $_SESSION['user_id'];
            
            // Process the booking using the controller
            $result = $bookingController->createBooking($_POST);
            
            if ($result) {
                $bookingSuccess = true;
                $bookingMessage = 'Service booked successfully! You will receive confirmation shortly.';
                
                // Refresh the bookings list
                $upcomingBookings = $booking->getUpcomingBookings($_SESSION['user_id']);
                $bookingStats = $booking->getBookingStats($_SESSION['user_id']);
            } else {
                throw new Exception('Failed to create booking');
            }
        } catch (Exception $e) {
            $bookingError = $e->getMessage();
            error_log("Booking creation error: " . $e->getMessage());
        }
    }
    
    // Process cancellation requests
    else if (isset($_POST['cancel_booking'])) {
        try {
            // Validate CSRF token
            if (
                empty($_POST['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
            ) {
                throw new Exception('Invalid security token');
            }

            // Process the cancellation using the controller
            $result = $bookingController->cancelBooking($_POST);
            
            if ($result['success']) {
                $bookingSuccess = true;
                $bookingMessage = 'Booking cancelled successfully.';
                
                // Refresh the bookings list
                $upcomingBookings = $booking->getUpcomingBookings($_SESSION['user_id']);
                $bookingStats = $booking->getBookingStats($_SESSION['user_id']);
            } else {
                throw new Exception($result['message'] ?? 'Failed to cancel booking');
            }
        } catch (Exception $e) {
            $bookingError = $e->getMessage();
            error_log("Booking cancellation error: " . $e->getMessage());
        }
    }
}

// Set page title
$pageTitle = 'Book Service';

// Output any buffered content
ob_end_flush();
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

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/public/assets/css/dashboardstyles.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/public/assets/css/style.css" rel="stylesheet">
</head>

<body class="dashboard-body">
    <!-- Dashboard Layout -->
    <div class="dashboard-container">
        <!-- Sidebar remains the same -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_PATH; ?>/public/index.php" class="logo-text">
                    Fix It
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <h5 class="nav-section-title">Main Menu</h5>
                    <ul>
                        <li class="nav-item">
                            <a href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item active">
                            <a href="booking.php">
                                <i class="bi bi-calendar-check"></i>
                                <span>My Bookings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="vehicles.php">
                                <i class="bi bi-car-front"></i>
                                <span>My Vehicles</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="service-history.php">
                                <i class="bi bi-clock-history"></i>
                                <span>Service History</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Account</h5>
                    <ul>
                        <li class="nav-item">
                            <a href="profile.php">
                                <i class="bi bi-person"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="notifications.php">
                                <i class="bi bi-bell"></i>
                                <span>Notifications</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings.php">
                                <i class="bi bi-gear"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="text-danger">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Top Navigation remains the same -->
            <nav class="dashboard-topnav">
                <div class="d-flex align-items-center gap-3">
                    <button id="mobileSidebarToggle" class="d-lg-none btn btn-icon">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="page-title">Bookings</div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-icon" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <h6 class="dropdown-header">Notifications</h6>
                            <a class="dropdown-item" href="#">
                                <div class="notification-item">
                                    <div class="icon text-primary">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div class="content">
                                        <div class="title">Upcoming Service</div>
                                        <div class="text">Your vehicle service is due in 3 days</div>
                                        <div class="time">2 hours ago</div>
                                    </div>
                                </div>
                            </a>
                            <!-- Add more notification items here -->
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="notifications.php">
                                View All Notifications
                            </a>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-icon" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($userData['name'], 0, 1)); ?>
                            </div>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">
                                Welcome, <?php echo htmlspecialchars($userData['name']); ?>
                            </h6>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i> My Profile
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Alert Messages -->
                <?php if ($bookingSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        Service booked successfully! You'll receive confirmation shortly.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($bookingError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($bookingError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Total Bookings</div>
                                <div class="stat-card-value"><?php echo $bookingStats['total_bookings'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Pending</div>
                                <div class="stat-card-value"><?php echo $bookingStats['pending_bookings'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card-icon bg-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Confirmed</div>
                                <div class="stat-card-value"><?php echo $bookingStats['confirmed_bookings'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card-icon bg-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Completed</div>
                                <div class="stat-card-value"><?php echo $bookingStats['completed_bookings'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card-icon bg-info">
                                <i class="bi bi-flag"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row g-4">
                    <div class="col-lg-5">
                    <div class="card h-100">
    <div class="card-header">
        <h5 class="card-title mb-0">Book a Service</h5>
    </div>
    <div class="card-body">
        <?php if (empty($userCars)): ?>
            <div class="text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-car-front display-4 text-muted"></i>
                </div>
                <h6 class="text-muted">No vehicles registered</h6>
                <p class="text-muted mb-3">You need to register a vehicle before booking a service</p>
                <a href="vehicles.php" class="btn btn-primary">
                    <i class="bi bi-plus"></i> Add Vehicle
                </a>
            </div>
        <?php else: ?>
            <form method="post" action="booking.php" id="bookingForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="book_service" value="1">
                <!-- If passed from AI recommendation, this field will be populated via JavaScript -->
                
                <div class="mb-3">
                    <label for="car_id" class="form-label">Vehicle</label>
                    <select class="form-select" id="car_id" name="car_id" required>
                        <option value="">Select Vehicle</option>
                        <?php foreach ($userCars as $userCar): ?>
                            <option value="<?php echo $userCar['id']; ?>" <?php echo ($selectedCarId && $selectedCarId == $userCar['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($userCar['make']) . ' ' . $userCar['model'] . ' (' . $userCar['year'] . ') - ' . $userCar['reg_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="service_id" class="form-label">Service Type</label>
                    <select class="form-select" id="service_id" name="service_id" required>
                        <option value="">Select Service</option>
                        <?php foreach ($availableServices as $availableService): ?>
                            <option value="<?php echo $availableService['id']; ?>">
                                <?php echo htmlspecialchars($availableService['name'] . ' - $' . number_format($availableService['price'], 2)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="booking_date" class="form-label">Service Date</label>
                    <input type="date" class="form-control" id="booking_date" name="booking_date"
                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="booking_time" class="form-label">Service Time</label>
                    <select class="form-select" id="booking_time" name="booking_time" required>
                        <option value="">Select Time</option>
                    </select>
                    <small class="text-muted">Available times will appear after selecting a date</small>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Additional Notes (Optional)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any specific issues or requests..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Book Service
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
                    </div>

                    <div class="col-lg-7">
                    <div class="card h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Upcoming Services</h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?status=all">All Bookings</a></li>
                <li><a class="dropdown-item" href="?status=pending">Pending</a></li>
                <li><a class="dropdown-item" href="?status=confirmed">Confirmed</a></li>
                <li><a class="dropdown-item" href="?status=completed">Completed</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($upcomingBookings)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-calendar-x display-4 text-muted"></i>
                </div>
                <h6 class="text-muted">No upcoming services</h6>
                <p class="text-muted mb-0">Your upcoming services will appear here</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Service Date</th>
                            <th>Vehicle</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <?php
                            $statusClass = 'secondary';
                            if ($booking['status'] === 'confirmed') $statusClass = 'success';
                            if ($booking['status'] === 'pending') $statusClass = 'warning';
                            if ($booking['status'] === 'cancelled') $statusClass = 'danger';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium">
                                        <?php echo htmlspecialchars(ucfirst($booking['make']) . ' ' . $booking['model']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['reg_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                                        <form method="post" action="booking.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="cancel_booking" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger cancel-booking-btn">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

                    </div>
                </div>

                <!-- Service Recommendations -->
                <div class="row mt-4">
                    <div class="col-12">
                    <div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">AI-Powered Service Recommendations</h5>
    </div>
    <div class="card-body">
        <?php
        try {
            $recommendations = $recommendation->getPendingRecommendations($_SESSION['user_id']);
            if (empty($recommendations)):
        ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-lightbulb display-4 text-muted"></i>
                    </div>
                    <h6 class="text-muted">No recommendations available</h6>
                    <p class="text-muted mb-0">Our AI will analyze your vehicle data and provide personalized service recommendations</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Recommendation</th>
                                <th>Priority</th>
                                <th>Based On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $rec):
                                // Set default values for missing fields
                                $priority = $rec['priority'] ?? 'medium';
                                $priorityClass = 'info';

                                if ($priority === 'high') {
                                    $priorityClass = 'danger';
                                } elseif ($priority === 'medium') {
                                    $priorityClass = 'warning';
                                }

                                // Safely get values with fallbacks
                                $make = isset($rec['make']) ? htmlspecialchars($rec['make']) : 'Unknown';
                                $model = isset($rec['model']) ? htmlspecialchars($rec['model']) : '';
                                $regNumber = isset($rec['reg_number']) ? htmlspecialchars($rec['reg_number']) : '';
                                $recommendation = isset($rec['recommendation']) ? htmlspecialchars($rec['recommendation']) : 'Regular maintenance';
                                $reason = isset($rec['reason']) ? htmlspecialchars($rec['reason']) : 'Vehicle maintenance schedule';
                                $carId = isset($rec['car_id']) ? (int)$rec['car_id'] : 0;
                                $serviceId = isset($rec['service_id']) ? (int)$rec['service_id'] : 0;
                                $recommendationId = isset($rec['id']) ? (int)$rec['id'] : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium">
                                            <?php echo ucfirst($make) . ' ' . $model; ?>
                                        </div>
                                        <small class="text-muted"><?php echo $regNumber; ?></small>
                                    </td>
                                    <td><?php echo $recommendation; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $priorityClass; ?>-subtle text-<?php echo $priorityClass; ?>">
                                            <?php echo ucfirst($priority); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $reason; ?></td>
                                    <td>
                                        <?php if ($carId > 0 && $serviceId > 0): ?>
                                            <button type="button" class="btn btn-sm btn-primary recommendation-book-now"
                                                   data-car-id="<?php echo $carId; ?>"
                                                   data-service-id="<?php echo $serviceId; ?>"
                                                   data-recommendation-id="<?php echo $recommendationId; ?>">
                                                Book Now
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>
                                                Not Available
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php
        } catch (Exception $e) {
            error_log("Error getting recommendations: " . $e->getMessage());
        ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Unable to load recommendations. Please try again later.
            </div>
        <?php } ?>
    </div>
</div>

                    </div>
                </div>

                <!-- Service Information -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0">Our Services</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Oil Change -->
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card h-100 rounded shadow-sm border-0 overflow-hidden">
                            <div class="p-3 bg-primary-subtle d-flex align-items-center">
                                <div class="service-icon rounded-circle bg-white p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-droplet-fill text-primary fs-4"></i>
                                </div>
                                <h5 class="m-0 fw-semibold">Oil Change</h5>
                            </div>
                            <div class="p-3">
                                <p class="text-muted mb-3">Regular oil changes are essential for maintaining your engine's performance and longevity.</p>
                                <div class="service-features d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary me-2"><i class="bi bi-check2"></i></span>
                                        <span>Fresh oil</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary me-2"><i class="bi bi-check2"></i></span>
                                        <span>New filter</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary me-2"><i class="bi bi-check2"></i></span>
                                        <span>Fluid check</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Service -->
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card h-100 rounded shadow-sm border-0 overflow-hidden">
                            <div class="p-3 bg-success-subtle d-flex align-items-center">
                                <div class="service-icon rounded-circle bg-white p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-gear-fill text-success fs-4"></i>
                                </div>
                                <h5 class="m-0 fw-semibold">Full Service</h5>
                            </div>
                            <div class="p-3">
                                <p class="text-muted mb-3">A comprehensive check of your vehicle's key components to ensure optimal performance.</p>
                                <div class="service-features d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-success-subtle text-success me-2"><i class="bi bi-check2"></i></span>
                                        <span>50-point check</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-success-subtle text-success me-2"><i class="bi bi-check2"></i></span>
                                        <span>Filter replacement</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-success-subtle text-success me-2"><i class="bi bi-check2"></i></span>
                                        <span>All fluids topped up</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Brake Service -->
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card h-100 rounded shadow-sm border-0 overflow-hidden">
                            <div class="p-3 bg-warning-subtle d-flex align-items-center">
                                <div class="service-icon rounded-circle bg-white p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-tools text-warning fs-4"></i>
                                </div>
                                <h5 class="m-0 fw-semibold">Brake Service</h5>
                            </div>
                            <div class="p-3">
                                <p class="text-muted mb-3">Ensure your safety with our comprehensive brake inspection and service.</p>
                                <div class="service-features d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-warning-subtle text-warning me-2"><i class="bi bi-check2"></i></span>
                                        <span>Pad inspection</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-warning-subtle text-warning me-2"><i class="bi bi-check2"></i></span>
                                        <span>Rotor check</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill bg-warning-subtle text-warning me-2"><i class="bi bi-check2"></i></span>
                                        <span>Fluid inspection</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_PATH = '<?php echo BASE_PATH; ?>';

        // Initialize date picker min value to tomorrow
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            const dateInput = document.getElementById('booking_date');
            if (dateInput) {
                const formattedDate = tomorrow.toISOString().split('T')[0];
                dateInput.setAttribute('min', formattedDate);
            }

            // Highlight active nav item
            const currentPath = window.location.pathname;
            const navItems = document.querySelectorAll('.nav-item');

            navItems.forEach(item => {
                const link = item.querySelector('a');
                if (link && currentPath.includes(link.getAttribute('href'))) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/booking.js"></script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>
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

// Get user data
require_once(__DIR__ . '/../app/models/User.php');
require_once(__DIR__ . '/../app/models/Car.php');
require_once(__DIR__ . '/../app/models/Booking.php');
require_once(__DIR__ . '/../app/models/ServiceHistory.php');
require_once(__DIR__ . '/../app/models/ServiceRecommendation.php');

$user = new User($pdo);
$car = new Car($pdo);
$booking = new Booking($pdo);
$serviceHistory = new ServiceHistory($pdo);
$recommendation = new ServiceRecommendation($pdo);

try {
    $userData = $user->getUserById($_SESSION['user_id']);
    $userCars = $car->getByUserId($_SESSION['user_id']);
    $upcomingBookings = $booking->getUpcomingBookings($_SESSION['user_id'], 2);
    $recentServices = $serviceHistory->getRecentHistory($_SESSION['user_id'], 3);
    $recommendations = $recommendation->getPendingRecommendations($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Dashboard data loading error: " . $e->getMessage());
    // Handle error appropriately
}

// Set page title
$pageTitle = 'Dashboard';

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
    <?php
    // Calculate statistics
    $totalServices = count($recentServices);
    $upcomingServicesCount = count($upcomingBookings);
    $totalServiceCost = $serviceHistory->getTotalServiceCost($_SESSION['user_id']);
    $highPriorityCount = $recommendation->getHighPriorityCount($_SESSION['user_id']);
    ?>

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
                        <li class="nav-item active">
                            <a href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="bookings.php">
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
                    <div class="page-title">Dashboard</div>
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
                <!-- Quick Stats - Updated with real data -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Active Vehicles</div>
                                <div class="stat-card-value"><?php echo count($userCars); ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-car-front"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Upcoming Services</div>
                                <div class="stat-card-value"><?php echo $upcomingServicesCount; ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Total Services</div>
                                <div class="stat-card-value"><?php echo $totalServices; ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-tools"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Total Spent</div>
                                <div class="stat-card-value">$<?php echo number_format($totalServiceCost, 2); ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-cash"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Overview -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">My Vehicles</h5>
                                <a href="vehicles.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus"></i> Add Vehicle
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($userCars)): ?>
                                    <div class="text-center py-4">
                                        <div class="mb-3">
                                            <i class="bi bi-car-front display-4 text-muted"></i>
                                        </div>
                                        <h6 class="text-muted">No vehicles registered yet</h6>
                                        <p class="text-muted mb-3">Start by adding your first vehicle</p>
                                        <a href="vehicles.php" class="btn btn-primary">
                                            <i class="bi bi-plus"></i> Add Vehicle
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Vehicle</th>
                                                    <th>Registration</th>
                                                    <th>Last Service</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($userCars as $vehicle): ?>
                                                    <?php
                                                    try {
                                                        // Get maintenance schedule with error handling
                                                        $maintenanceSchedule = $car->getMaintenanceSchedule((int)$vehicle['id']);
                                                        $status = isset($maintenanceSchedule['needs_service']) && $maintenanceSchedule['needs_service'] ? 'Service Due' : 'Good';
                                                        $statusClass = $status === 'Service Due' ? 'danger' : 'success';

                                                        // Handle last service date
                                                        $lastServiceDate = isset($vehicle['last_service_date']) && $vehicle['last_service_date']
                                                            ? date('M d, Y', strtotime((string)$vehicle['last_service_date']))
                                                            : 'No service history';
                                                    } catch (Exception $e) {
                                                        error_log("Error getting vehicle maintenance info: " . $e->getMessage());
                                                        $status = 'Unknown';
                                                        $statusClass = 'warning';
                                                        $lastServiceDate = 'Not available';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="vehicle-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"></path>
                                                                        <circle cx="6.5" cy="16.5" r="2.5"></circle>
                                                                        <circle cx="16.5" cy="16.5" r="2.5"></circle>
                                                                    </svg>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-medium">
                                                                        <?php echo htmlspecialchars((string)(ucfirst(trim($vehicle['make'])) . ' ' . trim($vehicle['model']))); ?>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?php echo htmlspecialchars((string)$vehicle['year']); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(strtoupper((string)$vehicle['reg_number'])); ?></td>
                                                        <td><?php echo htmlspecialchars($lastServiceDate); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?>">
                                                                <?php echo htmlspecialchars($status); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="book-service.php?car=<?php echo (int)$vehicle['id']; ?>"
                                                                class="btn btn-sm btn-primary">
                                                                Book Service
                                                            </a>
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

                    <!-- Upcoming Services -->
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Upcoming Services</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingBookings)): ?>
                                    <div class="text-center py-4">
                                        <div class="mb-3">
                                            <i class="bi bi-calendar-check display-4 text-muted"></i>
                                        </div>
                                        <h6 class="text-muted">No upcoming services</h6>
                                        <p class="text-muted mb-3">Schedule your next service</p>
                                        <a href="bookings.php" class="btn btn-primary">
                                            Book Service
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($upcomingBookings as $booking): ?>
                                        <div class="upcoming-service">
                                            <div class="service-date">
                                                <div class="date"><?php echo date('d', strtotime($booking['booking_date'])); ?></div>
                                                <div class="month"><?php echo date('M', strtotime($booking['booking_date'])); ?></div>
                                            </div>
                                            <div class="service-info">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['service_name']); ?></h6>
                                                <p class="text-muted mb-0">
                                                    <?php echo htmlspecialchars(ucfirst($booking['make']) . ' ' . $booking['model'] . ' - ' . $booking['reg_number']); ?>
                                                </p>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="text-center mt-4">
                                        <a href="bookings.php" class="btn btn-primary">
                                            View All Bookings
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Service History -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Service History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentServices)): ?>
                                    <div class="text-center py-4">
                                        <div class="mb-3">
                                            <i class="bi bi-clock-history display-4 text-muted"></i>
                                        </div>
                                        <h6 class="text-muted">No service history yet</h6>
                                        <p class="text-muted mb-3">Your service history will appear here after your first service</p>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($recentServices as $service):
                                            try {
                                                $serviceName = isset($service['service_name']) ? htmlspecialchars((string)$service['service_name']) : 'Unknown Service';
                                                $serviceDate = isset($service['service_date']) ? date('F d, Y', strtotime((string)$service['service_date'])) : 'Date not available';
                                                $vehicleInfo = htmlspecialchars(
                                                    ucfirst((string)($service['make'] ?? '')) . ' ' .
                                                        ((string)($service['model'] ?? '')) . ' - ' .
                                                        ((string)($service['reg_number'] ?? ''))
                                                );
                                                $description = isset($service['description']) ? htmlspecialchars((string)$service['description']) : '';
                                                $cost = isset($service['cost']) ? (float)$service['cost'] : 0.00;
                                            } catch (Exception $e) {
                                                error_log("Error formatting service data: " . $e->getMessage());
                                                continue;
                                            }
                                        ?>
                                            <div class="timeline-item">
                                                <div class="timeline-marker bg-success">
                                                    <i class="bi bi-check"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-1"><?php echo $serviceName; ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo $serviceDate; ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-0">
                                                        <?php echo $vehicleInfo; ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo $description; ?>
                                                        Cost: $<?php echo number_format($cost, 2, '.', ','); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="text-center mt-4">
                                        <a href="service-history.php" class="btn btn-outline-primary">
                                            View Complete History
                                        </a>
                                    </div>
                                <?php endif; ?>
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
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>
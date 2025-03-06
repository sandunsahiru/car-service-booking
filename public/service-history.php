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

// Get user data and required models
require_once(__DIR__ . '/../app/models/Service.php');  // If models are inside app folder
require_once(__DIR__ . '/../app/models/ServiceHistory.php');
require_once(__DIR__ . '/../app/models/Car.php');
require_once(__DIR__ . '/../app/models/User.php');
require_once(__DIR__ . '/../app/models/ServiceRecommendation.php');
// Include the ServiceController
require_once(__DIR__ . '/../app/controllers/ServiceController.php');

$user = new User($pdo);
$car = new Car($pdo);
// Initialize ServiceController
$serviceController = new ServiceController($pdo);

// Initialize variables for filtering
$selectedCarId = null;
$startDate = null;
$endDate = null;
$serviceType = null;
$filters = [];

// Handle filter form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_history'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    // Get filter parameters
    $selectedCarId = !empty($_POST['car_id']) ? (int)$_POST['car_id'] : null;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $serviceType = !empty($_POST['service_type']) ? (int)$_POST['service_type'] : null;
    
    // Build filters array for controller
    if ($selectedCarId) $filters['car_id'] = $selectedCarId;
    if ($startDate) $filters['start_date'] = $startDate;
    if ($endDate) $filters['end_date'] = $endDate;
    if ($serviceType) $filters['service_type'] = $serviceType;
}

try {
    $userData = $user->getUserById($_SESSION['user_id']);
    $userCars = $car->getByUserId($_SESSION['user_id']);
    
    // Get service history using controller
    $historyEntries = $serviceController->getUserServiceHistory($_SESSION['user_id'], $filters);
    
    // Get all service types for filter dropdown
    $serviceTypes = $serviceController->getAvailableServices();
    
    // Get service statistics from controller
    $serviceStats = $serviceController->getServiceStatistics($_SESSION['user_id']);
    $totalSpent = $serviceStats['total_cost'] ?? 0;
    $mostCommonService = $serviceStats['most_common_service'] ?? null;
    $highestCostService = $serviceStats['highest_cost_service'] ?? null;
    
    // Calculate average service frequency
    $averageFrequency = null;
    try {
        $averageFrequency = $serviceController->getAverageServiceFrequency($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Error getting service frequency: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Service history data loading error: " . $e->getMessage());
    // Set default values in case of error
    $historyEntries = [];
    $serviceTypes = [];
    $totalSpent = 0;
    $serviceStats = [];
    $mostCommonService = null;
    $highestCostService = null;
    $averageFrequency = null;
}

// Set page title
$pageTitle = 'Service History';

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
                        <li class="nav-item">
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
                        <li class="nav-item active">
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
                    <div class="page-title">Service History</div>
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

            <!-- Service History Content -->
            <div class="dashboard-content">
                <!-- Summary Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Total Services</div>
                                <div class="stat-card-value"><?php echo count($historyEntries); ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-tools"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Total Spent</div>
                                <div class="stat-card-value">Rs.<?php echo number_format($totalSpent, 2); ?></div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-cash"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-info">
                                <div class="stat-card-title">Average Cost</div>
                                <div class="stat-card-value">
                                    Rs.<?php 
                                        $avgCost = count($historyEntries) > 0 ? $totalSpent / count($historyEntries) : 0;
                                        echo number_format($avgCost, 2); 
                                    ?>
                                </div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="bi bi-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and History -->
                <div class="row g-4">
                    <!-- Filters Card -->
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Filter Service History</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="col-md-3">
                                        <label for="car_id" class="form-label">Vehicle</label>
                                        <select class="form-select" id="car_id" name="car_id">
                                            <option value="">All Vehicles</option>
                                            <?php foreach ($userCars as $userCar): ?>
                                                <option value="<?php echo (int)$userCar['id']; ?>" <?php echo $selectedCarId === (int)$userCar['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(ucfirst($userCar['make']) . ' ' . $userCar['model'] . ' (' . $userCar['reg_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="start_date" class="form-label">From Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate ? htmlspecialchars($startDate) : ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="end_date" class="form-label">To Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate ? htmlspecialchars($endDate) : ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="service_type" class="form-label">Service Type</label>
                                        <select class="form-select" id="service_type" name="service_type">
                                            <option value="">All Service Types</option>
                                            <?php foreach ($serviceTypes as $type): ?>
                                                <option value="<?php echo (int)$type['id']; ?>" <?php echo $serviceType === (int)$type['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" name="filter_history" class="btn btn-primary w-100">
                                            <i class="bi bi-funnel"></i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service History Table -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Service History</h5>
                                <?php if (count($historyEntries) > 0): ?>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                    <a href="export-history.php?format=csv&<?php echo http_build_query($_POST); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Export CSV
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($historyEntries)): ?>
                                    <div class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="bi bi-clock-history display-4 text-muted"></i>
                                        </div>
                                        <h6 class="text-muted">No service history found</h6>
                                        <p class="text-muted mb-4">You don't have any service records that match your filters</p>
                                        <?php if ($selectedCarId || $startDate || $endDate || $serviceType): ?>
                                            <a href="service-history.php" class="btn btn-primary">
                                                <i class="bi bi-arrow-repeat"></i> Clear Filters
                                            </a>
                                        <?php else: ?>
                                            <a href="booking.php" class="btn btn-primary">
                                                <i class="bi bi-calendar-plus"></i> Book Your First Service
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Vehicle</th>
                                                    <th>Service</th>
                                                    <th>Description</th>
                                                    <th>Mileage</th>
                                                    <th>Cost</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historyEntries as $entry):
                                                    try {
                                                        $serviceName = isset($entry['service_name']) ? htmlspecialchars($entry['service_name']) : 'Unknown Service';
                                                        $serviceDate = isset($entry['service_date']) ? date('M d, Y', strtotime($entry['service_date'])) : 'Date not available';
                                                        $vehicleInfo = htmlspecialchars(
                                                            ucfirst(($entry['make'] ?? '')) . ' ' .
                                                                ($entry['model'] ?? '') . ' - ' .
                                                                ($entry['reg_number'] ?? '')
                                                        );
                                                        $description = isset($entry['description']) ? htmlspecialchars($entry['description']) : 'No description available';
                                                        $mileage = isset($entry['mileage']) ? number_format((int)$entry['mileage']) . ' km' : 'N/A';
                                                        $cost = isset($entry['cost']) ? (float)$entry['cost'] : 0.00;
                                                        $entryId = isset($entry['id']) ? (int)$entry['id'] : 0;
                                                    } catch (Exception $e) {
                                                        error_log("Error formatting service data: " . $e->getMessage());
                                                        continue;
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?php echo $serviceDate; ?></td>
                                                        <td><?php echo $vehicleInfo; ?></td>
                                                        <td><?php echo $serviceName; ?></td>
                                                        <td><?php echo $description; ?></td>
                                                        <td><?php echo $mileage; ?></td>
                                                        <td>Rs.<?php echo number_format($cost, 2); ?></td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <a href="view-service-details.php?id=<?php echo $entryId; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <a href="generate-receipt.php?id=<?php echo $entryId; ?>" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="bi bi-receipt"></i>
                                                                </a>
                                                            </div>
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
                
                <!-- Service History Analytics (Optional) -->
                <?php if (count($historyEntries) > 3): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Service Analytics</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Service spending trends over time</p>
                                <!-- This would be a placeholder for charts - you could implement with Chart.js -->
                                <div class="spending-chart-placeholder" style="height: 250px; background-color: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-bar-chart-line display-4"></i>
                                        <p class="mt-2">Service spending chart visualization would appear here</p>
                                    </div>
                                </div>
                                
                                <div class="row mt-4 g-4">
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-light">
                                            <h6>Most Common Service</h6>
                                            <p class="mb-0 fs-5">
                                                <i class="bi bi-tools text-primary me-2"></i>
                                                <?php echo $mostCommonService ? htmlspecialchars($mostCommonService['name']) : 'No data available'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-light">
                                            <h6>Highest Cost Service</h6>
                                            <p class="mb-0 fs-5">
                                                <i class="bi bi-currency-dollar text-danger me-2"></i>
                                                <?php echo $highestCostService ? htmlspecialchars($highestCostService['service_name']) : 'No data available'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-light">
                                            <h6>Service Frequency</h6>
                                            <p class="mb-0 fs-5">
                                                <i class="bi bi-calendar-check text-success me-2"></i>
                                                <?php 
                                                    if ($averageFrequency) {
                                                        // Convert days to months
                                                        $months = round($averageFrequency / 30, 1);
                                                        echo "Every {$months} months";
                                                    } else {
                                                        echo "No data available";
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_PATH = '<?php echo BASE_PATH; ?>';
        
        // Validate date range selection
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            endDateInput.addEventListener('change', function() {
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    
                    if (endDate < startDate) {
                        alert('End date cannot be earlier than start date');
                        endDateInput.value = '';
                    }
                }
            });
            
            startDateInput.addEventListener('change', function() {
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    
                    if (endDate < startDate) {
                        alert('Start date cannot be later than end date');
                        startDateInput.value = '';
                    }
                }
            });
        });
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>
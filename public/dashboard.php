<?php
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

// Set page title
$pageTitle = 'Dashboard';

// Include header
require_once(__DIR__ . '/../app/views/layout/header.php');

// Get user data
require_once(__DIR__ . '/../app/models/User.php');
require_once(__DIR__ . '/../app/models/Car.php');

$user = new User($pdo);
$car = new Car($pdo);

$userData = $user->getUserById($_SESSION['user_id']);
$userCars = $car->getByUserId($_SESSION['user_id']);

// Output any buffered content
ob_end_flush();
?>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-header">
            <a href="<?php echo BASE_PATH; ?>/public/index.php" class="logo-text">
                Fix It
            </a>
            <button id="sidebarToggle" class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
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
        <!-- Top Navigation -->
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
            <!-- Quick Stats -->
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
                            <div class="stat-card-value">2</div>
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
                            <div class="stat-card-value">8</div>
                        </div>
                        <div class="stat-card-icon">
                            <i class="bi bi-tools"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <div class="stat-card-title">Service Points</div>
                            <div class="stat-card-value">250</div>
                        </div>
                        <div class="stat-card-icon">
                            <i class="bi bi-award"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Overview -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">My Vehicles</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userCars)): ?>
                                <div class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="bi bi-car-front display-4 text-muted"></i>
                                    </div>
                                    <h6 class="text-muted">No vehicles registered yet</h6>
                                    <a href="vehicles.php" class="btn btn-primary mt-3">
                                        Add Vehicle
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
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-car-front me-2"></i>
                                                            <div>
                                                                <div class="fw-medium">
                                                                    <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
                                                                </div>
                                                                <small class="text-muted">
                                                                    <?php echo htmlspecialchars($vehicle['year']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($vehicle['reg_number']); ?></td>
                                                    <td>
                                                        <?php echo $vehicle['last_service'] ? date('M d, Y', strtotime($vehicle['last_service'])) : 'No service history'; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $maintenanceSchedule = $car->getMaintenanceSchedule($vehicle['id']);
                                                        $status = $maintenanceSchedule['needs_service'] ? 'Service Due' : 'Good';
                                                        $statusClass = $maintenanceSchedule['needs_service'] ? 'danger' : 'success';
                                                        ?>
                                                        <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?>">
                                                            <?php echo $status; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="book-service.php?car=<?php echo $vehicle['id']; ?>" 
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

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upcoming Services</h5>
                        </div>
                        <div class="card-body">
                            <div class="upcoming-service">
                                <div class="service-date">
                                    <div class="date">28</div>
                                    <div class="month">Feb</div>
                                </div>
                                <div class="service-info">
                                    <h6 class="mb-1">Regular Maintenance</h6>
                                    <p class="text-muted mb-0">Toyota Camry - ABC123</p>
                                    <small class="text-muted">09:30 AM</small>
                                </div>
                            </div>
                            
                            <div class="upcoming-service">
                                <div class="service-date">
                                    <div class="date">15</div>
                                    <div class="month">Mar</div>
                                </div>
                                <div class="service-info">
                                    <h6 class="mb-1">Oil Change</h6>
                                    <p class="text-muted mb-0">Honda Civic - XYZ789</p>
                                    <small class="text-muted">02:00 PM</small>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="bookings.php" class="btn btn-primary">
                                    View All Bookings
                                </a>
                            </div>
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
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success">
                                        <i class="bi bi-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1">Oil Change & Tire Rotation</h6>
                                            <small class="text-muted">January 15, 2024</small>
                                        </div>
                                        <p class="mb-0">Toyota Camry - ABC123</p>
                                        <small class="text-muted">Regular maintenance completed. Next service due in 5000 km.</small>
                                    </div>
                                </div>

                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success">
                                        <i class="bi bi-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1">Brake System Service</h6>
                                            <small class="text-muted">December 28, 2023</small>
                                        </div>
                                        <p class="mb-0">Honda Civic - XYZ789</p>
                                        <small class="text-muted">Brake pads replaced and brake fluid changed.</small>
                                    </div>
                                </div>

                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success">
                                        <i class="bi bi-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1">Annual Inspection</h6>
                                            <small class="text-muted">November 15, 2023</small>
                                        </div>
                                        <p class="mb-0">Toyota Camry - ABC123</p>
                                        <small class="text-muted">Full vehicle inspection completed. All systems functioning normally.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <a href="service-history.php" class="btn btn-outline-primary">
                                    View Complete History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Custom styles for dashboard -->
<style>
/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    padding-top: 76px; /* Header height */
}

/* Sidebar */
.dashboard-sidebar {
    width: 280px;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    height: calc(100vh - 76px);
    position: fixed;
    top: 76px;
    left: 0;
    overflow-y: auto;
    transition: all 0.3s ease;
    z-index: 1000;
}

.sidebar-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
}

.logo-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
}

.nav-section {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.nav-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6B7280;
    margin-bottom: 1rem;
    text-transform: uppercase;
}

.nav-item {
    margin-bottom: 0.5rem;
}

.nav-item a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #374151;
    text-decoration: none;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.nav-item a i {
    font-size: 1.25rem;
    margin-right: 1rem;
}

.nav-item a:hover {
    background: #F3F4F6;
}

.nav-item.active a {
    background: var(--primary-color);
    color: #fff;
}

/* Main Content */
.dashboard-main {
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    background: #F3F4F6;
    min-height: calc(100vh - 76px);
}

/* Top Navigation */
.dashboard-topnav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #111827;
}

/* Stats Cards */
.stat-card {
    background: #fff;
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-card-title {
    font-size: 0.875rem;
    color: #6B7280;
    margin-bottom: 0.5rem;
}

.stat-card-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #111827;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    background: #F3F4F6;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary-color);
}

/* Timeline */
.timeline {
    position: relative;
    padding: 1rem 0;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.timeline-marker i {
    color: #fff;
    font-size: 1rem;
}

.timeline-content {
    flex: 1;
}

/* Responsive */
@media (max-width: 991.98px) {
    .dashboard-sidebar {
        transform: translateX(-100%);
    }

    .dashboard-sidebar.show {
        transform: translateX(0);
    }

    .dashboard-main {
        margin-left: 0;
    }
}

/* Upcoming Services */
.upcoming-service {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.service-date {
    background: var(--primary-color);
    color: #fff;
    padding: 0.5rem;
    border-radius: 0.5rem;
    text-align: center;
    margin-right: 1rem;
    min-width: 60px;
}

.service-date .date {
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1;
}

.service-date .month {
    font-size: 0.875rem;
    text-transform: uppercase;
}

.service-info {
    flex: 1;
}

/* User Avatar */
.user-avatar {
    width: 36px;
    height: 36px;
    background: var(--primary-color);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* Notification Badge */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #EF4444;
    color: #fff;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    min-width: 20px;
    text-align: center;
}
</style>

<!-- Dashboard Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.querySelector('.dashboard-sidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const isClickInside = sidebar.contains(event.target) || 
                            (sidebarToggle && sidebarToggle.contains(event.target)) ||
                            (mobileSidebarToggle && mobileSidebarToggle.contains(event.target));

        if (!isClickInside && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            sidebar.classList.remove('show');
        }
    });
});
</script>

<?php include_once('../app/views/layout/footer.php'); ?>
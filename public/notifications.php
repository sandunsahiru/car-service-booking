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
require_once(__DIR__ . '/../app/models/Notification.php');

$user = new User($pdo);
$notification = new Notification($pdo);

try {
    $userData = $user->getUserById($_SESSION['user_id']);
    $userNotifications = $notification->getUserNotifications($_SESSION['user_id'], 20); // Get latest 20 notifications
    $unreadCount = $notification->getUnreadCount($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Notifications data loading error: " . $e->getMessage());
    // Handle error appropriately
    $userNotifications = [];
    $unreadCount = 0;
}

// Handle mark as read or delete actions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Invalid security token');
        }

        // Mark notification as read
        if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
            $notificationId = (int)$_POST['notification_id'];
            if ($notification->markAsRead($notificationId, $_SESSION['user_id'])) {
                $successMessage = 'Notification marked as read';
                // Refresh notifications
                $userNotifications = $notification->getUserNotifications($_SESSION['user_id'], 20);
                $unreadCount = $notification->getUnreadCount($_SESSION['user_id']);
            } else {
                throw new Exception('Failed to update notification');
            }
        }

        // Mark all as read
        if (isset($_POST['mark_all_read'])) {
            if ($notification->markAllAsRead($_SESSION['user_id'])) {
                $successMessage = 'All notifications marked as read';
                // Refresh notifications
                $userNotifications = $notification->getUserNotifications($_SESSION['user_id'], 20);
                $unreadCount = $notification->getUnreadCount($_SESSION['user_id']);
            } else {
                throw new Exception('Failed to update notifications');
            }
        }

        // Delete notification
        if (isset($_POST['delete']) && !empty($_POST['notification_id'])) {
            $notificationId = (int)$_POST['notification_id'];
            if ($notification->deleteNotification($notificationId, $_SESSION['user_id'])) {
                $successMessage = 'Notification deleted';
                // Refresh notifications
                $userNotifications = $notification->getUserNotifications($_SESSION['user_id'], 20);
                $unreadCount = $notification->getUnreadCount($_SESSION['user_id']);
            } else {
                throw new Exception('Failed to delete notification');
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Set page title
$pageTitle = 'Notifications';

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
    
    <style>
        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: background-color 0.2s ease;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background-color: rgba(13, 110, 253, 0.03);
        }
        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .notification-item .icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--bs-primary);
            border-radius: 50%;
            margin-right: 1rem;
        }
        .notification-item .content {
            flex: 1;
        }
        .notification-item .content .title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .notification-item .content .text {
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        .notification-item .content .time {
            color: #adb5bd;
            font-size: 0.75rem;
        }
        .notification-item .actions {
            display: flex;
            gap: 0.5rem;
        }
        .notification-item .bi {
            font-size: 1.2rem;
        }
        .notification-empty {
            padding: 3rem;
            text-align: center;
        }
        .notification-empty .bi {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        .notification-filters {
            margin-bottom: 1rem;
        }
    </style>
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
                        <li class="nav-item active">
                            <a href="notifications.php">
                                <i class="bi bi-bell"></i>
                                <span>Notifications</span>
                                <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
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
                    <div class="page-title">Notifications</div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-icon" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if (empty($userNotifications)): ?>
                                <div class="p-3 text-center text-muted">
                                    <small>No new notifications</small>
                                </div>
                            <?php else: ?>
                                <?php
                                $count = 0; 
                                foreach ($userNotifications as $notification): 
                                    if ($count >= 3) break; // Show only 3 in dropdown
                                    $count++;
                                    $iconClass = 'bi bi-info-circle';
                                    switch ($notification['type']) {
                                        case 'service_reminder':
                                            $iconClass = 'bi bi-calendar-check';
                                            break;
                                        case 'booking_confirmation':
                                            $iconClass = 'bi bi-check-circle';
                                            break;
                                        case 'system':
                                            $iconClass = 'bi bi-gear';
                                            break;
                                    }
                                ?>
                                <a class="dropdown-item" href="#">
                                    <div class="notification-item">
                                        <div class="icon text-primary">
                                            <i class="<?php echo $iconClass; ?>"></i>
                                        </div>
                                        <div class="content">
                                            <div class="title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                            <div class="text"><?php echo htmlspecialchars($notification['message']); ?></div>
                                            <div class="time"><?php echo timeAgo($notification['created_at']); ?></div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                <!-- Success/Error Messages -->
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($errorMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Notifications Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Notifications</h5>
                                <?php if ($unreadCount > 0): ?>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="mark_all_read" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        Mark All as Read
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body p-0">
                                <!-- Filters -->
                                <div class="notification-filters p-3 border-bottom">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="text" class="form-control" placeholder="Search notifications...">
                                                <button class="btn btn-outline-secondary" type="button">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-flex justify-content-md-end mt-3 mt-md-0">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-primary active">All</button>
                                                <button type="button" class="btn btn-outline-primary">Unread</button>
                                                <button type="button" class="btn btn-outline-primary">Service</button>
                                                <button type="button" class="btn btn-outline-primary">System</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (empty($userNotifications)): ?>
                                    <div class="notification-empty">
                                        <i class="bi bi-bell-slash"></i>
                                        <h5>No Notifications</h5>
                                        <p class="text-muted">You don't have any notifications yet.</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Notification List -->
                                    <div class="notification-list">
                                        <?php foreach ($userNotifications as $notification): 
                                            $iconClass = 'bi bi-info-circle';
                                            $iconColor = 'primary';
                                            
                                            switch ($notification['type']) {
                                                case 'service_reminder':
                                                    $iconClass = 'bi bi-calendar-check';
                                                    $iconColor = 'primary';
                                                    break;
                                                case 'booking_confirmation':
                                                    $iconClass = 'bi bi-check-circle';
                                                    $iconColor = 'success';
                                                    break;
                                                case 'booking_update':
                                                    $iconClass = 'bi bi-pencil-square';
                                                    $iconColor = 'warning';
                                                    break;
                                                case 'service_complete':
                                                    $iconClass = 'bi bi-trophy';
                                                    $iconColor = 'success';
                                                    break;
                                                case 'payment':
                                                    $iconClass = 'bi bi-credit-card';
                                                    $iconColor = 'info';
                                                    break;
                                                case 'system':
                                                    $iconClass = 'bi bi-gear';
                                                    $iconColor = 'secondary';
                                                    break;
                                                case 'alert':
                                                    $iconClass = 'bi bi-exclamation-triangle';
                                                    $iconColor = 'danger';
                                                    break;
                                            }
                                        ?>
                                            <div class="notification-item d-flex align-items-center <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                                <div class="icon text-<?php echo $iconColor; ?>">
                                                    <i class="<?php echo $iconClass; ?>"></i>
                                                </div>
                                                <div class="content">
                                                    <div class="title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                    <div class="text"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                    <div class="time"><?php echo timeAgo($notification['created_at']); ?></div>
                                                </div>
                                                <div class="actions">
                                                    <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                                        <input type="hidden" name="mark_read" value="1">
                                                        <button type="submit" class="btn btn-icon" title="Mark as read">
                                                            <i class="bi bi-check-circle text-primary"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                                        <input type="hidden" name="delete" value="1">
                                                        <button type="submit" class="btn btn-icon" title="Delete">
                                                            <i class="bi bi-trash text-danger"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                                        <div class="text-muted">
                                            Showing <span class="fw-medium"><?php echo count($userNotifications); ?></span> notifications
                                        </div>
                                        <nav aria-label="Notifications pagination">
                                            <ul class="pagination pagination-sm mb-0">
                                                <li class="page-item disabled">
                                                    <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                                </li>
                                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                                <li class="page-item">
                                                    <a class="page-link" href="#">Next</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings Card -->
                    <div class="col-12 mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form id="notificationPreferences">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Email Notifications</h6>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="serviceReminders" checked>
                                                <label class="form-check-label" for="serviceReminders">
                                                    Service Reminders
                                                </label>
                                                <div class="form-text">Get notified about upcoming service appointments</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="bookingUpdates" checked>
                                                <label class="form-check-label" for="bookingUpdates">
                                                    Booking Updates
                                                </label>
                                                <div class="form-text">Receive updates about your service bookings</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="specialOffers">
                                                <label class="form-check-label" for="specialOffers">
                                                    Special Offers
                                                </label>
                                                <div class="form-text">Get notified about promotions and special offers</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="mb-3">SMS Notifications</h6>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="smsReminders" checked>
                                                <label class="form-check-label" for="smsReminders">
                                                    Service Reminders
                                                </label>
                                                <div class="form-text">Receive SMS reminders about upcoming services</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="smsStatusUpdates" checked>
                                                <label class="form-check-label" for="smsStatusUpdates">
                                                    Service Status Updates
                                                </label>
                                                <div class="form-text">Get real-time updates about your vehicle service</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="smsPromotions">
                                                <label class="form-check-label" for="smsPromotions">
                                                    Promotional Messages
                                                </label>
                                                <div class="form-text">Receive promotional offers via SMS</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary mt-3">Save Preferences</button>
                                </form>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Demo function for notification preferences
            const notificationPreferences = document.getElementById('notificationPreferences');
            if (notificationPreferences) {
                notificationPreferences.addEventListener('submit', function(event) {
                    event.preventDefault();
                    alert('Notification preferences saved successfully!');
                });
            }
            
            // Filter buttons functionality (for demo)
            const filterButtons = document.querySelectorAll('.btn-group .btn');
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>

<?php
/**
 * Helper function to convert timestamp to "time ago" format
 */
function timeAgo($timestamp) {
    $timestamp = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    if ($time_difference < 60) {
        return 'Just now';
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 2592000) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 31536000) {
        $months = floor($time_difference / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($time_difference / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}
?>
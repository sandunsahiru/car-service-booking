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
    $unreadCount = $notification->getUnreadCount($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("User data loading error: " . $e->getMessage());
    // Handle error appropriately
    $unreadCount = 0;
}

// Handle form submissions
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

        // Handle password update
        if (isset($_POST['update_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validate inputs
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception('All password fields are required');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match');
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }

            // Verify current password and update
            if ($user->updatePassword($_SESSION['user_id'], $currentPassword, $newPassword)) {
                $successMessage = 'Password updated successfully';
            } else {
                throw new Exception('Current password is incorrect');
            }
        }

        // Handle notification settings update
        if (isset($_POST['update_notification_settings'])) {
            $emailServiceReminders = isset($_POST['email_service_reminders']) ? 1 : 0;
            $emailBookingUpdates = isset($_POST['email_booking_updates']) ? 1 : 0;
            $emailSpecialOffers = isset($_POST['email_special_offers']) ? 1 : 0;
            $smsServiceReminders = isset($_POST['sms_service_reminders']) ? 1 : 0;
            $smsStatusUpdates = isset($_POST['sms_status_updates']) ? 1 : 0;
            $smsPromotions = isset($_POST['sms_promotions']) ? 1 : 0;

            // Update notification preferences
            if ($user->updateNotificationPreferences(
                $_SESSION['user_id'],
                $emailServiceReminders,
                $emailBookingUpdates,
                $emailSpecialOffers,
                $smsServiceReminders,
                $smsStatusUpdates,
                $smsPromotions
            )) {
                $successMessage = 'Notification preferences updated successfully';
            } else {
                throw new Exception('Failed to update notification preferences');
            }
        }

        // Handle account settings update
        if (isset($_POST['update_account_settings'])) {
            $language = $_POST['language'] ?? 'en';
            $timezone = $_POST['timezone'] ?? 'UTC';
            $dateFormat = $_POST['date_format'] ?? 'Y-m-d';
            $darkMode = isset($_POST['dark_mode']) ? 1 : 0;

            // Update account settings
            if ($user->updateAccountSettings(
                $_SESSION['user_id'],
                $language,
                $timezone,
                $dateFormat,
                $darkMode
            )) {
                $successMessage = 'Account settings updated successfully';
            } else {
                throw new Exception('Failed to update account settings');
            }
        }

        // Handle delete account request
        if (isset($_POST['delete_account'])) {
            $confirmDelete = $_POST['confirm_delete'] ?? '';
            $password = $_POST['account_password'] ?? '';

            if ($confirmDelete !== 'DELETE') {
                throw new Exception('Please type DELETE to confirm account deletion');
            }

            if (empty($password)) {
                throw new Exception('Password is required to delete your account');
            }

            // Verify password and delete account
            if ($user->deleteAccount($_SESSION['user_id'], $password)) {
                // Destroy session
                session_unset();
                session_destroy();
                
                // Redirect to login page with message
                header('Location: ' . BASE_PATH . '/public/login.php?msg=account_deleted');
                exit;
            } else {
                throw new Exception('Incorrect password. Account deletion failed');
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Set page title
$pageTitle = 'Settings';

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
        .settings-section {
            margin-bottom: 2rem;
        }
        .settings-section-title {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        .delete-account-section {
            background-color: rgba(220, 53, 69, 0.05);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        .delete-account-section .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .password-toggle {
            cursor: pointer;
        }
        .settings-card {
            margin-bottom: 2rem;
        }
        .section-divider {
            margin: 3rem 0;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="dashboard-body">
    <!-- Dashboard Layout -->
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                        <li class="nav-item">
                            <a href="notifications.php">
                                <i class="bi bi-bell"></i>
                                <span>Notifications</span>
                                <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item active">
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
                    <div class="page-title">Settings</div>
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
                            <!-- Add notification items here -->
                            <div class="p-3 text-center text-muted">
                                <small>No new notifications</small>
                            </div>
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

                <!-- Security & Password Section -->
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i> Security & Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('current_password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                        title="Must contain at least 8 characters, including one number, one uppercase and one lowercase letter" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('new_password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <div class="form-text">
                                    Password must be at least 8 characters and include numbers, uppercase and lowercase letters
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Two-Factor Authentication</h6>
                        <p class="text-muted">Enhance your account security by enabling two-factor authentication.</p>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="enable2FA">
                            <label class="form-check-label" for="enable2FA">
                                Enable Two-Factor Authentication
                            </label>
                        </div>
                        
                        <button class="btn btn-outline-primary" id="setup2FA" disabled>
                            Setup Two-Factor Authentication
                        </button>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Recent Login Activity</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Device</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Feb 26, 2025 10:30 AM</td>
                                        <td>Chrome on Windows</td>
                                        <td>New York, USA</td>
                                        <td><span class="badge bg-success">Success</span></td>
                                    </tr>
                                    <tr>
                                        <td>Feb 25, 2025 3:15 PM</td>
                                        <td>Safari on iPhone</td>
                                        <td>New York, USA</td>
                                        <td><span class="badge bg-success">Success</span></td>
                                    </tr>
                                    <tr>
                                        <td>Feb 23, 2025 7:42 PM</td>
                                        <td>Firefox on MacOS</td>
                                        <td>Boston, USA</td>
                                        <td><span class="badge bg-warning">New location</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Notification Preferences Section -->
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-bell me-2"></i> Notification Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="update_notification_settings" value="1">
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Email Notifications</h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_service_reminders" name="email_service_reminders" checked>
                                        <label class="form-check-label" for="email_service_reminders">
                                            Service Reminders
                                        </label>
                                        <div class="form-text">Get notified about upcoming service appointments</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_booking_updates" name="email_booking_updates" checked>
                                        <label class="form-check-label" for="email_booking_updates">
                                            Booking Updates
                                        </label>
                                        <div class="form-text">Receive updates about your service bookings</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_special_offers" name="email_special_offers">
                                        <label class="form-check-label" for="email_special_offers">
                                            Special Offers
                                        </label>
                                        <div class="form-text">Get notified about promotions and special offers</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="mb-3">SMS Notifications</h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="sms_service_reminders" name="sms_service_reminders" checked>
                                        <label class="form-check-label" for="sms_service_reminders">
                                            Service Reminders
                                        </label>
                                        <div class="form-text">Receive SMS reminders about upcoming services</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="sms_status_updates" name="sms_status_updates" checked>
                                        <label class="form-check-label" for="sms_status_updates">
                                            Service Status Updates
                                        </label>
                                        <div class="form-text">Get real-time updates about your vehicle service</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="sms_promotions" name="sms_promotions">
                                        <label class="form-check-label" for="sms_promotions">
                                            Promotional Messages
                                        </label>
                                        <div class="form-text">Receive promotional offers via SMS</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">Save Preferences</button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Push Notification Settings</h6>
                        <p class="text-muted mb-3">Control which push notifications you receive on your devices</p>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="enable_push" checked>
                            <label class="form-check-label" for="enable_push">
                                Enable Push Notifications
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="push_booking" checked>
                            <label class="form-check-label" for="push_booking">
                                Booking confirmations and reminders
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="push_service" checked>
                            <label class="form-check-label" for="push_service">
                                Service status updates
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="push_offers">
                            <label class="form-check-label" for="push_offers">
                                Promotions and offers
                            </label>
                        </div>
                        
                        <button class="btn btn-outline-primary mt-3">
                            Update Push Settings
                        </button>
                    </div>
                </div>

                <!-- Account Settings Section -->
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-gear me-2"></i> Account Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="update_account_settings" value="1">
                            
                            <div class="mb-3">
                                <label for="language" class="form-label">Language</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="en" selected>English</option>
                                    <option value="es">Español</option>
                                    <option value="fr">Français</option>
                                    <option value="de">Deutsch</option>
                                    <option value="zh">中文</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="UTC" selected>UTC</option>
                                    <option value="America/New_York">Eastern Time (US & Canada)</option>
                                    <option value="America/Chicago">Central Time (US & Canada)</option>
                                    <option value="America/Denver">Mountain Time (US & Canada)</option>
                                    <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
                                    <option value="Europe/London">London</option>
                                    <option value="Europe/Paris">Paris</option>
                                    <option value="Asia/Tokyo">Tokyo</option>
                                    <option value="Australia/Sydney">Sydney</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="date_format" class="form-label">Date Format</label>
                                <select class="form-select" id="date_format" name="date_format">
                                    <option value="Y-m-d" selected>YYYY-MM-DD (2025-02-26)</option>
                                    <option value="m/d/Y">MM/DD/YYYY (02/26/2025)</option>
                                    <option value="d/m/Y">DD/MM/YYYY (26/02/2025)</option>
                                    <option value="d.m.Y">DD.MM.YYYY (26.02.2025)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dark_mode" name="dark_mode">
                                    <label class="form-check-label" for="dark_mode">
                                        Dark Mode
                                    </label>
                                    <div class="form-text">Switch between light and dark display mode</div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Connected Accounts</h6>
                        <p class="text-muted mb-3">Link your accounts to enable seamless login and enhanced features</p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <i class="bi bi-google me-2"></i>
                                <span class="fw-medium">Google</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Connect</button>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <i class="bi bi-facebook me-2"></i>
                                <span class="fw-medium">Facebook</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Connect</button>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-apple me-2"></i>
                                <span class="fw-medium">Apple</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Connect</button>
                        </div>
                    </div>
                </div>

                <!-- Delete Account Section -->
                <div class="card settings-card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-trash me-2"></i> Delete Account</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Warning</h6>
                            <p>Deleting your account is permanent and cannot be undone. All your data, including vehicle profiles, 
                            service history, and booking records will be permanently removed from our system.</p>
                        </div>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirmAccountDeletion()">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="delete_account" value="1">
                            
                            <div class="mb-3">
                                <label for="delete_reason" class="form-label">Reason for leaving (optional)</label>
                                <select class="form-select" id="delete_reason" name="delete_reason">
                                    <option value="">Please select a reason...</option>
                                    <option value="not_using">I'm not using this service anymore</option>
                                    <option value="found_alternative">I found a better alternative</option>
                                    <option value="too_expensive">The service is too expensive</option>
                                    <option value="not_helpful">The service isn't helpful</option>
                                    <option value="too_complicated">The service is too complicated</option>
                                    <option value="data_privacy">Data privacy concerns</option>
                                    <option value="other">Other reason</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="feedback" class="form-label">Additional feedback (optional)</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="3" placeholder="Please share any additional feedback to help us improve..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_delete" class="form-label">To confirm deletion, type "DELETE" below</label>
                                <input type="text" class="form-control" id="confirm_delete" name="confirm_delete" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="account_password" class="form-label">Enter your password to confirm</label>
                                <input type="password" class="form-control" id="account_password" name="account_password" required>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary">Cancel</button>
                                <button type="submit" class="btn btn-danger">Permanently Delete Account</button>
                            </div>
                        </form>
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
            // Handle two-factor authentication toggle
            const enable2FA = document.getElementById('enable2FA');
            const setup2FA = document.getElementById('setup2FA');
            
            if (enable2FA && setup2FA) {
                enable2FA.addEventListener('change', function() {
                    setup2FA.disabled = !this.checked;
                });
            }
            
            // Password visibility toggle
            function togglePassword(inputId) {
                const input = document.getElementById(inputId);
                const icon = input.parentNode.querySelector('.bi');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
            
            // Make togglePassword globally available
            window.togglePassword = togglePassword;
            
            // Confirm account deletion
            window.confirmAccountDeletion = function() {
                const confirmText = document.getElementById('confirm_delete').value;
                if (confirmText !== 'DELETE') {
                    alert('Please type DELETE to confirm account deletion');
                    return false;
                }
                
                return confirm('Are you sure you want to permanently delete your account? This action cannot be undone.');
            };
            
            // New password validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
                
                newPassword.addEventListener('input', function() {
                    if (newPassword.value !== confirmPassword.value && confirmPassword.value !== '') {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }
        });
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>
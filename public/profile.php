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

$user = new User($pdo);
$car = new Car($pdo);

try {
    $userData = $user->getUserById($_SESSION['user_id']);
    $userCars = $car->getByUserId($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Profile data loading error: " . $e->getMessage());
    // Handle error appropriately
}

// Set page title
$pageTitle = 'My Profile';

// Handle form submission for profile update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // Validate CSRF token
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Invalid security token');
        }

        // Validate required fields
        $required = ['name', 'phone'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst($field) . ' is required');
            }
        }

        // Validate phone number
        if (!preg_match('/^[0-9]{10}$/', $_POST['phone'])) {
            throw new Exception('Invalid phone number format');
        }

        // Update profile
        $profileData = [
            'name' => strip_tags(trim($_POST['name'])),
            'phone' => preg_replace('/[^0-9]/', '', $_POST['phone']),
            'email' => $userData['email'] // Keep the same email
        ];

        if ($user->updateProfile($_SESSION['user_id'], $profileData)) {
            $userData = $user->getUserById($_SESSION['user_id']); // Refresh user data
            $successMessage = 'Profile updated successfully';
            $_SESSION['user_name'] = $profileData['name']; // Update session
        } else {
            throw new Exception('Failed to update profile');
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Handle form submission for password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        // Validate CSRF token
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Invalid security token');
        }

        // Validate required fields
        $required = ['current_password', 'new_password', 'confirm_password'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(str_replace('_', ' ', ucfirst($field)) . ' is required');
            }
        }

        // Check if new password and confirmation match
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new Exception('New passwords do not match');
        }

        // Change password
        if ($user->changePassword(
            $_SESSION['user_id'],
            $_POST['current_password'],
            $_POST['new_password']
        )) {
            $successMessage = 'Password changed successfully';
        } else {
            throw new Exception('Failed to change password');
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

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
                        <li class="nav-item active">
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
                    <div class="page-title">My Profile</div>
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

                <!-- Profile Information -->
                <div class="row g-4">
                    <!-- User Profile -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Personal Information</h5>
                                <span class="badge bg-primary-subtle text-primary">Account Active</span>
                            </div>
                            <div class="card-body">
                                <form id="profileForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="update_profile" value="1">
                                    
                                    <div class="profile-header mb-4">
                                        <div class="profile-avatar">
                                            <?php echo strtoupper(substr($userData['name'], 0, 1)); ?>
                                        </div>
                                        <div class="profile-info">
                                            <h4 class="mb-1"><?php echo htmlspecialchars($userData['name']); ?></h4>
                                            <p class="text-muted mb-0">Member since <?php echo date('F Y', strtotime($userData['created_at'])); ?></p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($userData['name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly disabled>
                                        <div class="form-text">Email address cannot be changed</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                            value="<?php echo htmlspecialchars($userData['phone']); ?>" 
                                            pattern="[0-9]{10}" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email Verification</label>
                                        <?php if ($userData['email_verified']): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-success-subtle text-success me-2">Verified</span>
                                                <span class="text-muted">Your email is verified</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-warning-subtle text-warning me-2">Pending</span>
                                                <span class="text-muted">Please check your email to verify your account</span>
                                                <button type="button" class="btn btn-sm btn-link ms-2">Resend Verification</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Last Login</label>
                                        <div class="text-muted">
                                            <?php echo $userData['last_login'] ? date('F d, Y H:i', strtotime($userData['last_login'])) : 'Not available'; ?>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form id="passwordForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="change_password" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                            pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}" required>
                                        <div class="form-text">
                                            Password must be at least 8 characters with letters, numbers, and special characters
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Account Settings -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Account Settings</h5>
                            </div>
                            <div class="card-body">
                                <form id="settingsForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Notifications</h6>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                                <label class="form-check-label" for="emailNotifications">
                                                    Email Notifications
                                                </label>
                                                <div class="form-text">Receive service reminders and updates via email</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="smsNotifications" checked>
                                                <label class="form-check-label" for="smsNotifications">
                                                    SMS Notifications
                                                </label>
                                                <div class="form-text">Receive service reminders and updates via SMS</div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="marketingEmails">
                                                <label class="form-check-label" for="marketingEmails">
                                                    Marketing Emails
                                                </label>
                                                <div class="form-text">Receive promotional offers and newsletters</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Account Options</h6>
                                            
                                            <a href="#" class="btn btn-outline-danger mb-3">
                                                <i class="bi bi-shield-exclamation"></i> Deactivate Account
                                            </a>
                                            
                                            <div class="alert alert-info mb-3">
                                                <h6 class="alert-heading">Data Privacy</h6>
                                                <p class="mb-0">Your data is encrypted and securely stored. You can request a copy of your data or deletion at any time.</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Settings</button>
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
            // Password confirmation validation
            const passwordForm = document.getElementById('passwordForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            passwordForm.addEventListener('submit', function(event) {
                if (newPassword.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match');
                    confirmPassword.focus();
                }
            });
            
            // Settings form (just for demo, not functional yet)
            const settingsForm = document.getElementById('settingsForm');
            if (settingsForm) {
                settingsForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    alert('Settings saved successfully!');
                });
            }
        });
    </script>
    <script src="<?php echo BASE_PATH; ?>/public/assets/js/dashboard.js"></script>
</body>

</html>
<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering
ob_start();

// Set constant to prevent direct access
define('PREVENT_DIRECT_ACCESS', true);

// Include configuration and required files
require_once(__DIR__ . '/../app/config/config.php');
require_once(__DIR__ . '/../app/config/session.php');
require_once(__DIR__ . '/../app/models/User.php');
require_once(__DIR__ . '/../app/models/Car.php');

// Check if user is logged in
if (!session_id()) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/public/login.php');
    exit;
}

// Update session last activity
$_SESSION['last_activity'] = time();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize database connections and models
$user = new User($pdo);
$car = new Car($pdo);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token');
        }

        switch ($_POST['action']) {
            case 'add_vehicle':
                $carData = [
                    'user_id' => $_SESSION['user_id'],
                    'make' => trim($_POST['make']),
                    'model' => trim($_POST['model']),
                    'year' => (int)$_POST['year'],
                    'reg_number' => strtoupper(trim($_POST['reg_number'])),
                    'mileage' => (int)$_POST['mileage'],
                    'last_service' => !empty($_POST['last_service']) ? $_POST['last_service'] : null
                ];

                if ($car->create($carData)) {
                    $message = 'Vehicle added successfully!';
                    $messageType = 'success';
                }
                break;

            case 'update_vehicle':
                $carId = (int)$_POST['car_id'];
                $carData = [
                    'user_id' => $_SESSION['user_id'],
                    'make' => trim($_POST['make']),
                    'model' => trim($_POST['model']),
                    'year' => (int)$_POST['year'],
                    'reg_number' => strtoupper(trim($_POST['reg_number'])),
                    'mileage' => (int)$_POST['mileage'],
                    'last_service' => !empty($_POST['last_service']) ? $_POST['last_service'] : null
                ];

                if ($car->update($carId, $carData)) {
                    $message = 'Vehicle updated successfully!';
                    $messageType = 'success';
                }
                break;

            case 'delete_vehicle':
                $carId = (int)$_POST['car_id'];
                if ($car->delete($carId, $_SESSION['user_id'])) {
                    $message = 'Vehicle deleted successfully!';
                    $messageType = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
        error_log("Vehicle management error: " . $e->getMessage());
    }
}

// Get user's vehicles
try {
    $vehicles = $car->getByUserId($_SESSION['user_id']);
    $userData = $user->getUserById($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Error fetching vehicles: " . $e->getMessage());
    $vehicles = [];
    $message = "Error loading vehicles";
    $messageType = "danger";
}

// Page title
$pageTitle = 'My Vehicles';

// Clear output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix It - <?php echo $pageTitle; ?></title>

    <!-- Meta tags -->
    <meta name="description" content="Manage your vehicles in Fix It - Smart car service booking system">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/public/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/public/assets/css/dashboardstyles.css" rel="stylesheet">
</head>

<body class="dashboard-body">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_PATH; ?>/public/index.php" class="logo-text">Fix It</a>
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
                        <li class="nav-item active">
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
                    <div class="page-title"><?php echo $pageTitle; ?></div>
                </div>

                <div class="d-flex align-items-center gap-3">
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

            <!-- Page Content -->
            <div class="dashboard-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Add Vehicle Button -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Manage Vehicles</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                        <i class="bi bi-plus"></i> Add New Vehicle
                    </button>
                </div>

                <!-- Vehicles List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-5">
                                <div class="mb-3">
                                    <i class="bi bi-car-front display-4 text-muted"></i>
                                </div>
                                <h5 class="text-muted">No Vehicles Found</h5>
                                <p class="text-muted mb-3">Add your first vehicle to get started</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                    <i class="bi bi-plus"></i> Add Vehicle
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Vehicle Details</th>
                                            <th>Registration</th>
                                            <th>Mileage</th>
                                            <th>Last Service</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vehicles as $vehicle): 
                                            try {
                                                $maintenanceInfo = $car->getMaintenanceSchedule((int)$vehicle['id']);
                                                $status = $maintenanceInfo['needs_service'] ? 'Service Due' : 'Good';
                                                $statusClass = $status === 'Service Due' ? 'danger' : 'success';
                                            } catch (Exception $e) {
                                                error_log("Error getting maintenance info: " . $e->getMessage());
                                                $status = 'Unknown';
                                                $statusClass = 'warning';
                                            }

                                            // Ensure all values are properly cast to string
                                            $vehicleMake = isset($vehicle['make']) ? (string)$vehicle['make'] : '';
                                            $vehicleModel = isset($vehicle['model']) ? (string)$vehicle['model'] : '';
                                            $vehicleYear = isset($vehicle['year']) ? (string)$vehicle['year'] : '';
                                            $vehicleRegNumber = isset($vehicle['reg_number']) ? (string)$vehicle['reg_number'] : '';
                                            $vehicleMileage = isset($vehicle['mileage']) ? (int)$vehicle['mileage'] : 0;
                                            $lastServiceDate = isset($vehicle['last_service_date']) && $vehicle['last_service_date'] 
                                                ? (string)$vehicle['last_service_date'] 
                                                : '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="vehicle-icon me-3">
                                                            <i class="bi bi-car-front"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-medium">
                                                                <?php echo htmlspecialchars($vehicleMake . ' ' . $vehicleModel); ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($vehicleYear); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars(strtoupper($vehicleRegNumber)); ?></td>
                                                <td><?php echo number_format($vehicleMileage); ?> km</td>
                                                <td>
                                                    <?php 
                                                        if (!empty($lastServiceDate)) {
                                                            $serviceDateTime = strtotime($lastServiceDate);
                                                            $currentTime = time();
                                                            if ($serviceDateTime && $serviceDateTime <= $currentTime) {
                                                                echo date('M d, Y', $serviceDateTime);
                                                            } else {
                                                                echo 'Not serviced';
                                                            }
                                                        } else {
                                                            echo 'Not serviced';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars((string)$status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-primary"
                                                                onclick="editVehicle(<?php echo htmlspecialchars(json_encode($vehicle), ENT_QUOTES, 'UTF-8'); ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteVehicle(<?php echo (int)$vehicle['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
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
        </main>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addVehicleForm" method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_vehicle">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="make" class="form-label">Make</label>
                            <input type="text" class="form-control" id="make" name="make" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year" 
                                   min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reg_number" class="form-label">Registration Number</label>
                            <input type="text" class="form-control" id="reg_number" name="reg_number" 
                                   pattern="[A-Za-z0-9-]{1,15}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mileage" class="form-label">Current Mileage (km)</label>
                            <input type="number" class="form-control" id="mileage" name="mileage" 
                                   min="0" max="999999" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="last_service" class="form-label">Last Service Date (optional)</label>
                            <input type="date" class="form-control" id="last_service" name="last_service"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editVehicleForm" method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_vehicle">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="car_id" id="edit_car_id">
                        
                        <div class="mb-3">
                            <label for="edit_make" class="form-label">Make</label>
                            <input type="text" class="form-control" id="edit_make" name="make" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="edit_model" name="model" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="edit_year" name="year" 
                                   min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_reg_number" class="form-label">Registration Number</label>
                            <input type="text" class="form-control" id="edit_reg_number" name="reg_number" 
                                   pattern="[A-Za-z0-9-]{1,15}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_mileage" class="form-label">Current Mileage (km)</label>
                            <input type="number" class="form-control" id="edit_mileage" name="mileage" 
                                   min="0" max="999999" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_last_service" class="form-label">Last Service Date</label>
                            <input type="date" class="form-control" id="edit_last_service" name="last_service"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Vehicle Modal -->
    <div class="modal fade" id="deleteVehicleModal" tabindex="-1" aria-labelledby="deleteVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteVehicleModalLabel">Delete Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="deleteVehicleForm" method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_vehicle">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="car_id" id="delete_car_id">
                        <p>Are you sure you want to delete this vehicle? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle editing vehicle
        function editVehicle(vehicle) {
            document.getElementById('edit_car_id').value = vehicle.id;
            document.getElementById('edit_make').value = vehicle.make;
            document.getElementById('edit_model').value = vehicle.model;
            document.getElementById('edit_year').value = vehicle.year;
            document.getElementById('edit_reg_number').value = vehicle.reg_number;
            document.getElementById('edit_mileage').value = vehicle.mileage;
            document.getElementById('edit_last_service').value = vehicle.last_service_date || '';
            
            new bootstrap.Modal(document.getElementById('editVehicleModal')).show();
        }

        // Handle deleting vehicle
        function deleteVehicle(carId) {
            document.getElementById('delete_car_id').value = carId;
            new bootstrap.Modal(document.getElementById('deleteVehicleModal')).show();
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
        });
    </script>
</body>
</html>
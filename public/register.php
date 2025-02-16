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

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/public/dashboard.php');
    exit;
}

// Set page title
$pageTitle = 'Register';

// Include header
require_once(__DIR__ . '/../app/views/layout/header.php');

// Output any buffered content
ob_end_flush();
?>

<div class="content-wrapper">
    <section class="registration-section py-8">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Alert Container -->
                    <div id="alertContainer" class="mb-4"></div>

                    <div class="registration-card">
                        <h2 class="text-gradient mb-4">Create Your Account</h2>
                        <p class="text-muted mb-4">Join Fix It for smart car service management</p>

                        <form id="registrationForm" novalidate>
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <!-- Personal Information -->
                            <div class="section-block">
                                <h3 class="section-title">Personal Information</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                required pattern="^[a-zA-Z ]{2,50}$">
                                            <div class="invalid-feedback">
                                                Please enter a valid name (2-50 characters, letters only)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                                            <div class="invalid-feedback">
                                                Please enter a valid email address
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone"
                                                required pattern="^[0-9]{10}$">
                                            <div class="invalid-feedback">
                                                Please enter a valid 10-digit phone number
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="password" class="form-label">Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password"
                                                    required pattern="^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$">
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">
                                                Password must be at least 8 characters with numbers and special characters
                                            </div>
                                            <small class="text-muted">Minimum 8 characters, include numbers and special characters</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Information -->
                            <div class="section-block mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="section-title mb-0">Vehicle Information</h3>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="addLater" name="addLater">
                                        <label class="form-check-label" for="addLater">
                                            I'll add my vehicle later
                                        </label>
                                    </div>
                                </div>

                                <div id="vehicleForm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="make" class="form-label">Make</label>
                                                <input type="text" class="form-control" id="make" name="make">
                                                <div class="invalid-feedback">
                                                    Please enter the vehicle make
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="model" class="form-label">Model</label>
                                                <input type="text" class="form-control" id="model" name="model">
                                                <div class="invalid-feedback">
                                                    Please enter the vehicle model
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="year" class="form-label">Year</label>
                                                <input type="number" class="form-control" id="year" name="year"
                                                    min="1900" max="<?php echo date('Y') + 1; ?>">
                                                <div class="invalid-feedback">
                                                    Please enter a valid year between 1900 and <?php echo date('Y') + 1; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="reg_number" class="form-label">Registration Number</label>
                                                <input type="text" class="form-control" id="reg_number" name="reg_number">
                                                <div class="invalid-feedback">
                                                    Please enter the registration number
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="mileage" class="form-label">Current Mileage</label>
                                                <input type="number" class="form-control" id="mileage" name="mileage"
                                                    min="0" max="999999">
                                                <div class="invalid-feedback">
                                                    Please enter a valid mileage (0-999,999)
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="last_service" class="form-label">Last Service Date (if known)</label>
                                                <input type="date" class="form-control" id="last_service" name="last_service"
                                                    max="<?php echo date('Y-m-d'); ?>">
                                                <div class="invalid-feedback">
                                                    Please enter a valid date not in the future
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="section-block mt-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and
                                        <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                    </label>
                                    <div class="invalid-feedback">
                                        You must agree to the terms and conditions
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg w-100 pulse-animation">
                                    Create Account
                                </button>
                            </div>

                            <div class="text-center mt-4">
                                <p class="mb-0">Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Form validation and submission script -->
<script>
    const BASE_PATH = '<?php echo BASE_PATH; ?>';

    document.addEventListener('DOMContentLoaded', function() {
        const registrationForm = document.getElementById('registrationForm');
        const addLaterCheckbox = document.getElementById('addLater');
        const vehicleForm = document.getElementById('vehicleForm');
        const vehicleInputs = vehicleForm.querySelectorAll('input');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        // Password visibility toggle
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        // Vehicle form toggle functionality
        addLaterCheckbox.addEventListener('change', function() {
            vehicleForm.style.opacity = this.checked ? '0.5' : '1';
            vehicleForm.style.pointerEvents = this.checked ? 'none' : 'auto';
            vehicleInputs.forEach(input => {
                input.required = !this.checked && !['last_service'].includes(input.id);
                if (this.checked) {
                    input.value = '';
                    input.classList.remove('is-invalid');
                }
            });
        });

        // Form validation and submission
        registrationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating account...';

            // Remove existing alerts
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = '';

            try {
                const formData = new FormData(this);

                console.log('Form data:', Object.fromEntries(formData));

                const response = await fetch(BASE_PATH + '/app/controllers/UserController.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                console.log('Response status:', response.status);
                const responseText = await response.text();
                console.log('Raw response:', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Error parsing JSON:', parseError);
                    throw new Error('Invalid server response format');
                }

                if (data.error) {
                    throw new Error(data.error);
                }

                if (data.success) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.role = 'alert';
                    alertDiv.innerHTML = `
                    Account created successfully! Redirecting...
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                    alertContainer.appendChild(alertDiv);
                    alertDiv.scrollIntoView({
                        behavior: 'smooth'
                    });

                    setTimeout(() => {
                        window.location.href = BASE_PATH + '/public/dashboard.php';
                    }, 1500);
                }
            } catch (error) {
                console.error('Registration Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.role = 'alert';
                alertDiv.innerHTML = `
                ${error.message || 'An error occurred during registration. Please try again.'}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
                alertContainer.appendChild(alertDiv);
                alertDiv.scrollIntoView({
                    behavior: 'smooth'
                });
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });

        // Custom form validation
        const inputs = registrationForm.querySelectorAll('input[pattern]');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.pattern && this.value) {
                    const regex = new RegExp(this.pattern);
                    if (!regex.test(this.value)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
            });
        });
    });
</script>

<!-- Add necessary CSS -->
<style>
    .content-wrapper {
        margin-top: 76px;
        min-height: calc(100vh - 76px);
        display: flex;
        flex-direction: column;
    }

    .registration-card {
        background: #fff;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .section-block {
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 10px;
    }

    .pulse-animation {
        position: relative;
        overflow: hidden;
    }

    .pulse-animation:after {
        content: '';
        display: block;
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        pointer-events: none;
        background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
        background-repeat: no-repeat;
        background-position: 50%;
        transform: scale(10, 10);
        opacity: 0;
        transition: transform .5s, opacity 1s;
    }

    .pulse-animation:active:after {
        transform: scale(0, 0);
        opacity: .2;
        transition: 0s;
    }

    .text-gradient {
        background: linear-gradient(45deg, #2196F3, #1976D2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-check-input:checked {
        background-color: #2196F3;
        border-color: #2196F3;
    }

    .form-control:focus {
        border-color: #2196F3;
        box-shadow: 0 0 0 0.25rem rgba(33, 150, 243, 0.25);
    }

    .btn-primary {
        background-color: #2196F3;
        border-color: #2196F3;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #1976D2;
        border-color: #1976D2;
        transform: translateY(-2px);
    }

    .was-validated .form-control:invalid,
    .form-control.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }

    .was-validated .form-control:valid,
    .form-control.is-valid {
        border-color: #198754;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }

    @media (max-width: 768px) {
        .registration-card {
            padding: 1rem;
        }

        .section-block {
            padding: 1rem;
        }

        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
    }
</style>

<?php include_once('../app/views/layout/footer.php'); ?>
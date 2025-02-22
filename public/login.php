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
$pageTitle = 'Login';

// Include header
require_once(__DIR__ . '/../app/views/layout/header.php');

// Output any buffered content
ob_end_flush();
?>

<div class="content-wrapper">
    <section class="login-section py-8">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <!-- Alert Container -->
                    <div id="alertContainer" class="mb-4"></div>

                    <div class="login-card">
                        <h2 class="text-gradient mb-4">Welcome Back</h2>
                        <p class="text-muted mb-4">Log in to manage your car service</p>

                        <form id="loginForm" novalidate>
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="form-group mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                                <div class="invalid-feedback">
                                    Please enter a valid email address
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Please enter your password
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot-password.php" class="text-primary">Forgot Password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 pulse-animation">
                                Log In
                            </button>

                            <div class="text-center mt-4">
                                <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Register here</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    const BASE_PATH = '<?php echo BASE_PATH; ?>';

    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        // Password visibility toggle
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        // Form validation and submission
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';

            // Remove existing alerts
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = '';

            try {
                const formData = new FormData(this);
                const response = await fetch(BASE_PATH + '/app/controllers/UserController.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                if (data.success) {
                    window.location.href = data.redirect || (BASE_PATH + '/public/dashboard.php');
                }
            } catch (error) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.role = 'alert';
                alertDiv.innerHTML = `
                    ${error.message || 'Invalid email or password'}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                alertContainer.appendChild(alertDiv);
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
    });
</script>

<style>
    .content-wrapper {
        margin-top: 76px;
        min-height: calc(100vh - 76px);
        display: flex;
        flex-direction: column;
    }

    .login-card {
        background: #fff;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .text-gradient {
        background: linear-gradient(45deg, #2196F3, #1976D2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
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
</style>

<?php include_once('../app/views/layout/footer.php'); ?>
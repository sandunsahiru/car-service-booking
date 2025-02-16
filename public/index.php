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

// Include header
require_once(__DIR__ . '/../app/views/layout/header.php');

// Output any buffered content
ob_end_flush();
?>

<!-- Hero Section -->
<section class="hero min-h-screen position-relative overflow-hidden">
    <div class="hero-bg"></div>
    <div class="container position-relative">
        <div class="row min-vh-100 align-items-center">
            <div class="col-lg-6 hero-content">
                <h1 class="display-3 fw-bold mb-4 text-gradient">
                    Smart Car Service
                    <span class="d-block">Made Simple</span>
                </h1>
                <p class="lead mb-5 text-muted">
                    AI-powered car service recommendations tailored to your vehicle's needs. 
                    Book appointments easily and get real-time updates.
                </p>
                <div class="d-flex gap-3">
                    <a href="register.php" class="btn btn-primary btn-lg pulse-animation">Get Started</a>
                    <a href="#how-it-works" class="btn btn-outline-dark btn-lg">Learn More</a>
                </div>
            </div>
            <div class="col-lg-6 position-relative hero-image-container">
                <div class="hero-image-wrapper">
                    <img src="assets/images/hero-car.jpg" alt="Modern car service" class="hero-image">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features py-8">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-4 fw-bold mb-4">Why Choose Fix It?</h2>
            <p class="lead text-muted mb-5">Experience the future of car maintenance</p>
        </div>
        
        <div class="row g-4 features-grid">
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <h3>Smart Diagnostics</h3>
                    <p>AI-powered recommendations based on your car's history</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="bi bi-tools"></i>
                    </div>
                    <h3>Expert Service</h3>
                    <p>Certified mechanics and state-of-the-art equipment</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h3>Easy Booking</h3>
                    <p>Book appointments online with real-time availability</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h3>Live Updates</h3>
                    <p>Get real-time notifications about your service</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-8 bg-gradient">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-4 fw-bold mb-4">How It Works</h2>
            <p class="lead text-muted mb-5">Three simple steps to get your car serviced</p>
        </div>
        
        <div class="row steps-container">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Register Your Car</h3>
                    <p>Add your car details and get personalized recommendations</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Book Service</h3>
                    <p>Choose from available slots and book your preferred time</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Get Service</h3>
                    <p>Drop off your car and track service progress in real-time</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once('../app/views/layout/footer.php'); ?>
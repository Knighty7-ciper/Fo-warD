<?php
/**
 * Forward LMS - Main Entry Point
 * InfinityFree Hosting Optimized
 */

// Start session
session_start();

// Set timezone
date_default_timezone_set('UTC');

// Include configuration
require_once 'backend/config/db.php';
require_once 'backend/config/auth.php';

// Initialize authentication
Auth::init();

// Check if user is logged in and redirect appropriately
if (Auth::isAuthenticated()) {
    $user_role = Auth::getUserRole();
    $redirect = match($user_role) {
        'admin' => '/frontend/admin/dashboard.php',
        'teacher' => '/frontend/teacher/dashboard.php', 
        'student' => '/frontend/student/dashboard.php',
        default => '/frontend/index.php'
    };
    header("Location: {$redirect}");
    exit();
}

// User is not logged in, show the homepage
$page_title = 'Forward LMS - Community-Driven Learning Platform';
$body_class = 'homepage';

require_once 'shared/templates/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome to Forward LMS</h1>
            <p class="hero-subtitle">Empower your learning journey with our community-driven platform</p>
            <div class="hero-buttons">
                <a href="/frontend/register.php" class="btn btn-primary">Get Started</a>
                <a href="/frontend/courses.php" class="btn btn-secondary">Browse Courses</a>
                <a href="/frontend/login.php" class="btn btn-outline">Login</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="/frontend/assets/images/bg-campus.jpg" alt="Learning Platform" onerror="this.style.display='none';">
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2>Platform Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Rich Course Library</h3>
                <p>Access thousands of courses across various subjects and skill levels</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎓</div>
                <h3>Certified Learning</h3>
                <p>Earn certificates upon course completion with verification</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Live Classes</h3>
                <p>Attend real-time classes with interactive sessions</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Gamification</h3>
                <p>Earn points, badges, and rewards for your progress</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Progress Tracking</h3>
                <p>Monitor your learning journey with detailed analytics</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Community Forums</h3>
                <p>Engage with peers and instructors in discussion forums</p>
            </div>
        </div>
    </div>
</section>

<section class="stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50+</div>
                <div class="stat-label">Expert Teachers</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">200+</div>
                <div class="stat-label">Quality Courses</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">95%</div>
                <div class="stat-label">Student Satisfaction</div>
            </div>
        </div>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2>Ready to Start Learning?</h2>
        <p>Join thousands of students already learning on Forward LMS</p>
        <div class="cta-buttons">
            <a href="/frontend/register.php" class="btn btn-primary btn-large">Create Free Account</a>
            <a href="/frontend/courses.php" class="btn btn-secondary btn-large">Explore Courses</a>
        </div>
    </div>
</section>

<?php require_once 'shared/templates/footer.php'; ?>
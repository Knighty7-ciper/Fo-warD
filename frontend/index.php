<?php
$page_title = 'Forward LMS - Community-Driven Learning Platform';
$body_class = 'homepage';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome to Forward LMS</h1>
            <p class="hero-subtitle">Empower your learning journey with our community-driven platform</p>
            <div class="hero-buttons">
                <?php if (!$is_logged_in): ?>
                    <a href="/frontend/register.php" class="btn btn-primary">Get Started</a>
                    <a href="/frontend/courses.php" class="btn btn-secondary">Browse Courses</a>
                <?php else: ?>
                    <?php if ($user_role === 'student'): ?>
                        <a href="/frontend/student/dashboard.php" class="btn btn-primary">My Dashboard</a>
                    <?php elseif ($user_role === 'teacher'): ?>
                        <a href="/frontend/teacher/dashboard.php" class="btn btn-primary">My Dashboard</a>
                    <?php endif; ?>
                    <a href="/frontend/courses.php" class="btn btn-secondary">Browse Courses</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <img src="/frontend/assets/images/bg-campus.jpg" alt="Learning Platform">
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
                <p>Earn blockchain-backed certificates upon course completion</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Live Classes</h3>
                <p>Attend real-time classes with WebRTC-powered video sessions</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Rewards System</h3>
                <p>Earn points for achievements and redeem them for perks</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌐</div>
                <h3>Metaverse Campus</h3>
                <p>Immersive 3D learning environment with virtual classrooms</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Discussion Forums</h3>
                <p>Collaborate with peers and engage in meaningful discussions</p>
            </div>
        </div>
    </div>
</section>

<section class="stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <h3 class="stat-number">1000+</h3>
                <p>Active Students</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-number">200+</h3>
                <p>Expert Teachers</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-number">500+</h3>
                <p>Courses Available</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-number">5000+</h3>
                <p>Certificates Issued</p>
            </div>
        </div>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2>Ready to Start Learning?</h2>
        <p>Join our community today and unlock your potential</p>
        <?php if (!$is_logged_in): ?>
            <a href="/frontend/register.php" class="btn btn-primary btn-large">Sign Up Now</a>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>

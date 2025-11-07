<?php
$page_title = 'Forward LMS - Learning Management System';
$body_class = 'homepage';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome to Forward LMS</h1>
            <p class="hero-subtitle">A learning management system for education</p>
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
                <h3>Course Management</h3>
                <p>Create and organize courses with lessons, assignments, and materials</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎓</div>
                <h3>Student Progress</h3>
                <p>Track student learning and issue completion certificates</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Live Sessions</h3>
                <p>Host video classes and online meetings with students</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Grading System</h3>
                <p>Grade assignments and track student performance</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Reports</h3>
                <p>View detailed reports on course performance and student progress</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Discussion Boards</h3>
                <p>Enable students to ask questions and discuss course content</p>
            </div>
        </div>
    </div>
</section>



<section class="cta">
    <div class="container">
        <h2>Get Started</h2>
        <p>Create an account to begin using the learning management system</p>
        <?php if (!$is_logged_in): ?>
            <a href="/frontend/register.php" class="btn btn-primary btn-large">Sign Up Now</a>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>

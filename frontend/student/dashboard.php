<?php
session_start();

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/auth.php';

Auth::requireRole('student');

$user_id = Auth::getUserId();
$db = Database::getInstance();
$db->setUserContext($user_id);

$enrolled_courses = $db->select("
    SELECT c.*, e.progress, e.enrolled_at,
           u.first_name || ' ' || u.last_name as teacher_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.student_id = :user_id
    ORDER BY e.enrolled_at DESC
    LIMIT 10
", [':user_id' => $user_id]);

$rewards_total = $db->selectOne("
    SELECT COALESCE(SUM(points), 0) as total_points
    FROM rewards
    WHERE student_id = :user_id
", [':user_id' => $user_id])['total_points'] ?? 0;

$certificates_count = $db->selectOne("
    SELECT COUNT(*) as count
    FROM certificates
    WHERE student_id = :user_id
", [':user_id' => $user_id])['count'] ?? 0;

$page_title = 'Student Dashboard - Forward LMS';
$body_class = 'student-dashboard';
$additional_css = ['/frontend/assets/css/student-profile.css'];

require_once __DIR__ . '/../../shared/templates/header.php';
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="/frontend/student/dashboard.php" class="nav-item active">
                <span class="icon">📊</span> Dashboard
            </a>
            <a href="/frontend/student/enrolled-courses.php" class="nav-item">
                <span class="icon">📚</span> My Courses
            </a>
            <a href="/frontend/student/course-enroll.php" class="nav-item">
                <span class="icon">🔍</span> Browse Courses
            </a>
            <a href="/frontend/student/certificates.php" class="nav-item">
                <span class="icon">🎓</span> Certificates
            </a>
            <a href="/frontend/student/rewards.php" class="nav-item">
                <span class="icon">🏆</span> Rewards
            </a>
            <a href="/frontend/student/peer-collaboration.php" class="nav-item">
                <span class="icon">👥</span> Community
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <h1>Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
            <p>Continue your learning journey</p>
        </header>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo count($enrolled_courses); ?></h3>
                    <p>Enrolled Courses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-info">
                    <h3><?php echo $certificates_count; ?></h3>
                    <p>Certificates Earned</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-info">
                    <h3><?php echo $rewards_total; ?></h3>
                    <p>Reward Points</p>
                </div>
            </div>
        </div>

        <section class="courses-section">
            <div class="section-header">
                <h2>My Courses</h2>
                <a href="/frontend/student/enrolled-courses.php" class="btn btn-secondary">View All</a>
            </div>

            <div class="courses-grid">
                <?php if (empty($enrolled_courses)): ?>
                    <div class="empty-state">
                        <p>You haven't enrolled in any courses yet.</p>
                        <a href="/frontend/student/course-enroll.php" class="btn btn-primary">Browse Courses</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card">
                            <?php if (!empty($course['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="course-thumbnail">
                            <?php else: ?>
                                <div class="course-thumbnail-placeholder">📚</div>
                            <?php endif; ?>

                            <div class="course-content">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="course-teacher">by <?php echo htmlspecialchars($course['teacher_name']); ?></p>

                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <p class="progress-text"><?php echo $course['progress']; ?>% Complete</p>

                                <a href="/frontend/student/lesson-player.php?course=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">Continue Learning</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

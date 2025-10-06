<?php
session_start();

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/auth.php';

Auth::requireRole('teacher');

$user_id = Auth::getUserId();
$db = Database::getInstance();
$db->setUserContext($user_id);

$my_courses = $db->select("
    SELECT c.*, COUNT(DISTINCT e.student_id) as student_count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE c.teacher_id = :user_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 10
", [':user_id' => $user_id]);

$total_students = $db->selectOne("
    SELECT COUNT(DISTINCT e.student_id) as count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = :user_id
", [':user_id' => $user_id])['count'] ?? 0;

$certificates_issued = $db->selectOne("
    SELECT COUNT(*) as count
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.id
    WHERE c.teacher_id = :user_id
", [':user_id' => $user_id])['count'] ?? 0;

$page_title = 'Teacher Dashboard - Forward LMS';
$body_class = 'teacher-dashboard';
$additional_css = ['/frontend/assets/css/teacher-dashboard.css'];

require_once __DIR__ . '/../../shared/templates/header.php';
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="/frontend/teacher/dashboard.php" class="nav-item active">
                <span class="icon">📊</span> Dashboard
            </a>
            <a href="/frontend/teacher/create-course.php" class="nav-item">
                <span class="icon">➕</span> Create Course
            </a>
            <a href="/frontend/teacher/courses.php" class="nav-item">
                <span class="icon">📚</span> My Courses
            </a>
            <a href="/frontend/teacher/schedule.php" class="nav-item">
                <span class="icon">📅</span> Schedule
            </a>
            <a href="/frontend/teacher/gradebook.php" class="nav-item">
                <span class="icon">📝</span> Gradebook
            </a>
            <a href="/frontend/teacher/certificates.php" class="nav-item">
                <span class="icon">🎓</span> Certificates
            </a>
            <a href="/frontend/teacher/reports.php" class="nav-item">
                <span class="icon">📈</span> Reports
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <h1>Welcome, Professor <?php echo htmlspecialchars($current_user['last_name']); ?>!</h1>
            <p>Manage your courses and students</p>
        </header>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo count($my_courses); ?></h3>
                    <p>Total Courses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-info">
                    <h3><?php echo $certificates_issued; ?></h3>
                    <p>Certificates Issued</p>
                </div>
            </div>
        </div>

        <section class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="actions-grid">
                <a href="/frontend/teacher/create-course.php" class="action-card">
                    <span class="action-icon">➕</span>
                    <h3>Create New Course</h3>
                    <p>Start building a new course</p>
                </a>
                <a href="/frontend/teacher/schedule.php" class="action-card">
                    <span class="action-icon">📅</span>
                    <h3>Schedule Class</h3>
                    <p>Set up a live class session</p>
                </a>
                <a href="/frontend/teacher/gradebook.php" class="action-card">
                    <span class="action-icon">📝</span>
                    <h3>Grade Assignments</h3>
                    <p>Review student submissions</p>
                </a>
                <a href="/frontend/teacher/certificates.php" class="action-card">
                    <span class="action-icon">🎓</span>
                    <h3>Issue Certificate</h3>
                    <p>Award certificates to students</p>
                </a>
            </div>
        </section>

        <section class="courses-section">
            <div class="section-header">
                <h2>My Courses</h2>
                <a href="/frontend/teacher/create-course.php" class="btn btn-primary">Create New Course</a>
            </div>

            <div class="courses-list">
                <?php if (empty($my_courses)): ?>
                    <div class="empty-state">
                        <p>You haven't created any courses yet.</p>
                        <a href="/frontend/teacher/create-course.php" class="btn btn-primary">Create Your First Course</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_courses as $course): ?>
                        <div class="course-item">
                            <div class="course-info">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($course['description'], 0, 150)); ?>...</p>
                                <div class="course-meta">
                                    <span class="badge badge-<?php echo $course['status']; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                    <span>👥 <?php echo $course['student_count']; ?> students</span>
                                </div>
                            </div>
                            <div class="course-actions">
                                <a href="/frontend/teacher/edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="/frontend/courses/view-course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

<?php
/**
 * Student Dashboard
 * Forward LMS Student Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require student authentication
$auth->requireRole('student');

$user = $auth->getCurrentUser();

// Get student's enrolled courses
$enrolledCourses = $db->fetchAll(
    "SELECT 
        c.id, c.title, c.description, c.thumbnail, c.category, c.level,
        c.price, c.duration, c.rating, c.enrollment_count,
        e.progress, e.enrolled_at, e.status as enrollment_status,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count,
        (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id 
         WHERE qa.student_id = ? AND q.course_id = c.id) as attempts_count
     FROM enrollments e
     JOIN courses c ON e.course_id = c.id
     JOIN users u ON c.teacher_id = u.id
     WHERE e.student_id = ?
     ORDER BY e.enrolled_at DESC",
    [$user['id'], $user['id']]
);

// Get available courses (not enrolled)
$availableCourses = $db->fetchAll(
    "SELECT 
        c.id, c.title, c.description, c.thumbnail, c.category, c.level,
        c.price, c.duration, c.rating, c.enrollment_count,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count
     FROM courses c
     JOIN users u ON c.teacher_id = u.id
     WHERE c.status = 'published' 
     AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
     ORDER BY c.created_at DESC
     LIMIT 6",
    [$user['id']]
);

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT 
        'lesson_completion' as activity_type,
        lp.completed_at as activity_date,
        l.title as activity_title,
        c.title as course_title
     FROM lesson_progress lp
     JOIN lessons l ON lp.lesson_id = l.id
     JOIN courses c ON l.course_id = c.id
     WHERE lp.student_id = ? AND lp.completed = 1
     ORDER BY lp.completed_at DESC
     LIMIT 3
    
     UNION ALL
     
     SELECT 
        'quiz_completion' as activity_type,
        qa.submitted_at as activity_date,
        q.title as activity_title,
        c.title as course_title
     FROM quiz_attempts qa
     JOIN quizzes q ON qa.quiz_id = q.id
     JOIN courses c ON q.course_id = c.id
     WHERE qa.student_id = ?
     ORDER BY qa.submitted_at DESC
     LIMIT 2
     
     ORDER BY activity_date DESC
     LIMIT 5",
    [$user['id'], $user['id']]
);

// Get student statistics
$stats = [
    'enrolled_courses' => count($enrolledCourses),
    'completed_courses' => count(array_filter($enrolledCourses, fn($course) => $course['enrollment_status'] === 'completed')),
    'total_progress' => !empty($enrolledCourses) ? round(array_sum(array_column($enrolledCourses, 'progress')) / count($enrolledCourses), 1) : 0,
    'certificates_earned' => $db->fetch(
        "SELECT COUNT(*) as count FROM certificates WHERE student_id = ?",
        [$user['id']]
    )['count'] ?? 0
];

$pageTitle = 'Student Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Forward LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #34495e;
            color: #fff;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .course-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .course-thumbnail {
            height: 150px;
            background: linear-gradient(45deg, #007bff, #6f42c1);
            border-radius: 0.5rem 0.5rem 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        .progress-custom {
            height: 8px;
        }
        .activity-item {
            border-left: 3px solid #28a745;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .continue-btn {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column">
                    <div class="p-3">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>
                            Forward LMS
                        </h4>
                        <small class="text-light">Student Portal</small>
                    </div>
                    
                    <nav class="nav flex-column flex-grow-1">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="enrolled-courses.php">
                            <i class="fas fa-book me-2"></i>
                            My Courses
                        </a>
                        <a class="nav-link" href="browse-courses.php">
                            <i class="fas fa-search me-2"></i>
                            Browse Courses
                        </a>
                        <a class="nav-link" href="grades.php">
                            <i class="fas fa-chart-line me-2"></i>
                            My Grades
                        </a>
                        <a class="nav-link" href="certificates.php">
                            <i class="fas fa-certificate me-2"></i>
                            Certificates
                        </a>
                        <a class="nav-link" href="rewards.php">
                            <i class="fas fa-trophy me-2"></i>
                            Rewards
                        </a>
                    </nav>
                    
                    <div class="p-3 border-top border-secondary">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-success rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <div class="text-white small"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="text-light small">Student</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                            <p class="text-muted mb-0">Continue your learning journey and track your progress.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="browse-courses.php" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>
                                Explore Courses
                            </a>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3 class="mb-0"><?php echo $stats['enrolled_courses']; ?></h3>
                                            <small>Enrolled Courses</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-book fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3 class="mb-0"><?php echo $stats['completed_courses']; ?></h3>
                                            <small>Completed</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3 class="mb-0"><?php echo $stats['total_progress']; ?>%</h3>
                                            <small>Overall Progress</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-chart-pie fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3 class="mb-0"><?php echo $stats['certificates_earned']; ?></h3>
                                            <small>Certificates</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-certificate fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Continue Learning -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Continue Learning</h5>
                                    <a href="enrolled-courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($enrolledCourses)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No courses yet</h5>
                                            <p class="text-muted">Start learning by enrolling in a course.</p>
                                            <a href="browse-courses.php" class="btn btn-primary">Browse Courses</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php 
                                            $continueCourses = array_filter($enrolledCourses, fn($course) => $course['enrollment_status'] === 'active');
                                            $continueCourses = array_slice($continueCourses, 0, 4);
                                            
                                            if (empty($continueCourses)): 
                                                $continueCourses = array_slice($enrolledCourses, 0, 1);
                                            endif;
                                            
                                            foreach ($continueCourses as $course): 
                                            ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card course-card h-100 position-relative">
                                                        <div class="course-thumbnail">
                                                            <i class="fas fa-<?php 
                                                                echo $course['category'] === 'Programming' ? 'code' : 
                                                                     ($course['category'] === 'Design' ? 'palette' : 
                                                                     ($course['category'] === 'Business' ? 'briefcase' : 'book')); 
                                                            ?>"></i>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                                <span class="badge bg-secondary"><?php echo $course['level']; ?></span>
                                                            </div>
                                                            
                                                            <p class="card-text text-muted small mb-3">
                                                                by <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                            </p>
                                                            
                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between small mb-1">
                                                                    <span>Progress</span>
                                                                    <span><?php echo round($course['progress'], 1); ?>%</span>
                                                                </div>
                                                                <div class="progress progress-custom">
                                                                    <div class="progress-bar" 
                                                                         style="width: <?php echo $course['progress']; ?>%"></div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="d-flex justify-content-between text-muted small">
                                                                <span><i class="fas fa-play me-1"></i> <?php echo $course['lesson_count']; ?> lessons</span>
                                                                <span><i class="fas fa-clock me-1"></i> <?php echo $course['duration']; ?>h</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="continue-btn">
                                                            <a href="course-player.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-play me-1"></i>
                                                                Continue
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity & Available Courses -->
                        <div class="col-lg-4 mb-4">
                            <!-- Recent Activity -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentActivities)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No recent activity</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['activity_title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activity['course_title']); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M j', strtotime($activity['activity_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="browse-courses.php" class="btn btn-outline-primary">
                                            <i class="fas fa-search me-2"></i>
                                            Browse Courses
                                        </a>
                                        <a href="grades.php" class="btn btn-outline-info">
                                            <i class="fas fa-chart-line me-2"></i>
                                            View Grades
                                        </a>
                                        <a href="certificates.php" class="btn btn-outline-success">
                                            <i class="fas fa-certificate me-2"></i>
                                            My Certificates
                                        </a>
                                        <a href="rewards.php" class="btn btn-outline-warning">
                                            <i class="fas fa-trophy me-2"></i>
                                            Rewards & Badges
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recommended Courses -->
                    <?php if (!empty($availableCourses)): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Recommended for You</h5>
                                        <a href="browse-courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach (array_slice($availableCourses, 0, 3) as $course): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card course-card h-100">
                                                        <div class="course-thumbnail">
                                                            <i class="fas fa-<?php 
                                                                echo $course['category'] === 'Programming' ? 'code' : 
                                                                     ($course['category'] === 'Design' ? 'palette' : 
                                                                     ($course['category'] === 'Business' ? 'briefcase' : 'book')); 
                                                            ?>"></i>
                                                        </div>
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                            <p class="card-text text-muted small mb-2">
                                                                by <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                            </p>
                                                            <p class="card-text text-muted small">
                                                                <?php echo htmlspecialchars(substr($course['description'], 0, 80)) . '...'; ?>
                                                            </p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-<?php echo $course['level'] === 'beginner' ? 'success' : ($course['level'] === 'intermediate' ? 'warning' : 'danger'); ?>">
                                                                    <?php echo ucfirst($course['level']); ?>
                                                                </span>
                                                                <small class="text-muted"><?php echo $course['lesson_count']; ?> lessons</small>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer">
                                                            <a href="course-details.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                Learn More
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any dashboard JavaScript functionality here
            console.log('Student dashboard loaded');
            
            // Auto-refresh progress bars animation
            setTimeout(function() {
                const progressBars = document.querySelectorAll('.progress-bar');
                progressBars.forEach(function(bar) {
                    bar.style.transition = 'width 1s ease-in-out';
                });
            }, 500);
        });
    </script>
</body>
</html>
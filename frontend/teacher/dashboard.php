<?php
/**
 * Teacher Dashboard
 * Forward LMS Teacher Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require teacher authentication
$auth->requireRole('teacher');

$user = $auth->getCurrentUser();

// Get teacher's courses
$courses = $db->fetchAll(
    "SELECT 
        c.*,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrolled_students,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count,
        (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) as quiz_count,
        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignment_count
     FROM courses c 
     WHERE c.teacher_id = ? 
     ORDER BY c.created_at DESC",
    [$user['id']]
);

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT 
        e.id, e.enrolled_at, 'enrollment' as activity_type,
        u.name as student_name, c.title as course_title
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     JOIN courses c ON e.course_id = c.id
     WHERE c.teacher_id = ?
     ORDER BY e.enrolled_at DESC
     LIMIT 10",
    [$user['id']]
);

// Get system statistics
$stats = [
    'total_courses' => count($courses),
    'total_students' => array_sum(array_column($courses, 'enrolled_students')),
    'active_courses' => count(array_filter($courses, fn($course) => $course['status'] === 'published')),
    'draft_courses' => count(array_filter($courses, fn($course) => $course['status'] === 'draft'))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Forward LMS</title>
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
        .status-badge {
            font-size: 0.75rem;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 1rem;
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
                        <small class="text-light">Teacher Portal</small>
                    </div>
                    
                    <nav class="nav flex-column flex-grow-1">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="create-course.php">
                            <i class="fas fa-plus-circle me-2"></i>
                            Create Course
                        </a>
                        <a class="nav-link" href="manage-courses.php">
                            <i class="fas fa-book me-2"></i>
                            My Courses
                        </a>
                        <a class="nav-link" href="create-assessment.php">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Create Assessment
                        </a>
                        <a class="nav-link" href="gradebook.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            Gradebook
                        </a>
                        <a class="nav-link" href="content-library.php">
                            <i class="fas fa-folder me-2"></i>
                            Content Library
                        </a>
                    </nav>
                    
                    <div class="p-3 border-top border-secondary">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <div class="text-white small"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="text-light small">Teacher</div>
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
                            <p class="text-muted mb-0">Here's what's happening with your courses today.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="create-course.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                New Course
                            </a>
                            <a href="create-assessment.php" class="btn btn-outline-primary">
                                <i class="fas fa-clipboard-list me-1"></i>
                                New Assessment
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
                                            <h3 class="mb-0"><?php echo $stats['total_courses']; ?></h3>
                                            <small>Total Courses</small>
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
                                            <h3 class="mb-0"><?php echo $stats['total_students']; ?></h3>
                                            <small>Total Students</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x opacity-75"></i>
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
                                            <h3 class="mb-0"><?php echo $stats['active_courses']; ?></h3>
                                            <small>Active Courses</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-play-circle fa-2x opacity-75"></i>
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
                                            <h3 class="mb-0"><?php echo $stats['draft_courses']; ?></h3>
                                            <small>Draft Courses</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-edit fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- My Courses -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">My Courses</h5>
                                    <a href="manage-courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($courses)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No courses yet</h5>
                                            <p class="text-muted">Create your first course to get started.</p>
                                            <a href="create-course.php" class="btn btn-primary">Create Course</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach (array_slice($courses, 0, 4) as $course): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card course-card h-100">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                                <span class="badge status-badge bg-<?php 
                                                                    echo $course['status'] === 'published' ? 'success' : 
                                                                        ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                                                                ?>">
                                                                    <?php echo ucfirst($course['status']); ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <p class="card-text text-muted small mb-3">
                                                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                                                            </p>
                                                            
                                                            <div class="d-flex justify-content-between text-muted small">
                                                                <span><i class="fas fa-users me-1"></i> <?php echo $course['enrolled_students']; ?> students</span>
                                                                <span><i class="fas fa-book me-1"></i> <?php echo $course['lesson_count']; ?> lessons</span>
                                                            </div>
                                                            
                                                            <div class="mt-3 d-flex gap-2">
                                                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-edit me-1"></i> Edit
                                                                </a>
                                                                <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="fas fa-eye me-1"></i> View
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
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
                                                        <strong><?php echo htmlspecialchars($activity['student_name']); ?></strong>
                                                        enrolled in
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activity['course_title']); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M j', strtotime($activity['enrolled_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any dashboard JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh activity feed every 30 seconds
            setInterval(function() {
                // You can add AJAX calls here to refresh the activity feed
                console.log('Refreshing activity feed...');
            }, 30000);
        });
    </script>
</body>
</html>
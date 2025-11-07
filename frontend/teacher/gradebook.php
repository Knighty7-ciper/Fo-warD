<?php
/**
 * Gradebook Page
 * Forward LMS Grade Management Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require teacher authentication
$auth->requireRole('teacher');

$user = $auth->getCurrentUser();

// Get teacher's courses
$courses = $db->fetchAll(
    "SELECT id, title, status FROM courses WHERE teacher_id = ? ORDER BY title",
    [$user['id']]
);

// Get selected course from URL
$selectedCourseId = intval($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

$grades = [];
$courseStats = [];
$recentActivities = [];

if ($selectedCourseId > 0) {
    // Get course statistics
    $courseStats = $db->fetch(
        "SELECT 
            COUNT(DISTINCT e.student_id) as total_students,
            COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.student_id END) as active_students,
            COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.student_id END) as completed_students,
            AVG(e.progress) as average_progress
         FROM enrollments e 
         WHERE e.course_id = ?",
        [$selectedCourseId]
    ) ?: [];
    
    // Get student grades and progress
    $grades = $db->fetchAll(
        "SELECT 
            u.id as student_id,
            u.name as student_name,
            u.email as student_email,
            e.enrolled_at,
            e.progress,
            e.status as enrollment_status,
            (SELECT AVG(sg.percentage) 
             FROM student_grades sg 
             JOIN grade_items gi ON sg.grade_item_id = gi.id 
             WHERE sg.student_id = u.id AND gi.course_id = ?) as average_grade,
            (SELECT COUNT(*) 
             FROM quiz_attempts qa 
             JOIN quizzes q ON qa.quiz_id = q.id 
             WHERE qa.student_id = u.id AND q.course_id = ?) as total_attempts
         FROM users u
         JOIN enrollments e ON u.id = e.student_id
         WHERE e.course_id = ?
         ORDER BY u.name",
        [$selectedCourseId, $selectedCourseId, $selectedCourseId]
    );
    
    // Get recent activities
    $recentActivities = $db->fetchAll(
        "SELECT 
            'quiz_completion' as activity_type,
            qa.submitted_at as activity_date,
            u.name as student_name,
            q.title as assessment_title,
            qa.score,
            qa.percentage
         FROM quiz_attempts qa
         JOIN users u ON qa.student_id = u.id
         JOIN quizzes q ON qa.quiz_id = q.id
         WHERE q.course_id = ?
         ORDER BY qa.submitted_at DESC
         LIMIT 5",
        [$selectedCourseId]
    );
}

$pageTitle = 'Gradebook';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Forward LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .grade-table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .grade-badge {
            font-size: 0.75rem;
            font-weight: 500;
        }
        .progress-bar-custom {
            height: 8px;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .student-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75rem;
        }
        .grade-legend {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .table-responsive {
            border-radius: 0.5rem;
        }
        .dataTables_wrapper {
            border-radius: 0.5rem;
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="gradebook.php">
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
                            <h1 class="h3 mb-1"><?php echo $pageTitle; ?></h1>
                            <p class="text-muted mb-0">Track student progress and manage grades across your courses.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="export-grades.php?course_id=<?php echo $selectedCourseId; ?>" class="btn btn-outline-success">
                                <i class="fas fa-download me-1"></i>
                                Export Grades
                            </a>
                        </div>
                    </div>
                    
                    <!-- Course Selection -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <label for="courseSelect" class="form-label">Select Course</label>
                                    <select class="form-select" id="courseSelect" onchange="changeCourse(this.value)">
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>" 
                                                    <?php echo $selectedCourseId == $course['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['title']); ?>
                                                <?php if ($course['status'] !== 'published'): ?>
                                                    (<?php echo ucfirst($course['status']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-end align-items-end h-100">
                                        <div class="text-muted small">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Select a course to view detailed grade information
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($selectedCourseId === 0 || empty($courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No Courses Available</h4>
                            <p class="text-muted">You need to create and publish a course to access the gradebook.</p>
                            <a href="create-course.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Create Your First Course
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h3 class="mb-0"><?php echo $courseStats['total_students'] ?? 0; ?></h3>
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
                                <div class="card stat-card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h3 class="mb-0"><?php echo $courseStats['active_students'] ?? 0; ?></h3>
                                                <small>Active Students</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-user-check fa-2x opacity-75"></i>
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
                                                <h3 class="mb-0"><?php echo $courseStats['completed_students'] ?? 0; ?></h3>
                                                <small>Completed</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-graduation-cap fa-2x opacity-75"></i>
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
                                                <h3 class="mb-0"><?php echo round($courseStats['average_progress'] ?? 0, 1); ?>%</h3>
                                                <small>Avg. Progress</small>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-chart-line fa-2x opacity-75"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Grade Table -->
                            <div class="col-lg-8 mb-4">
                                <div class="grade-table">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Student Grades</h5>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshTable()">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportGrades()">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="gradesTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Progress</th>
                                                        <th>Average Grade</th>
                                                        <th>Status</th>
                                                        <th>Enrolled</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($grades)): ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                                                                <br>
                                                                <span class="text-muted">No students enrolled yet</span>
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($grades as $grade): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="student-avatar me-2">
                                                                            <?php echo strtoupper(substr($grade['student_name'], 0, 1)); ?>
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-semibold"><?php echo htmlspecialchars($grade['student_name']); ?></div>
                                                                            <div class="text-muted small"><?php echo htmlspecialchars($grade['student_email']); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="progress progress-bar-custom me-2" style="width: 100px;">
                                                                            <div class="progress-bar" 
                                                                                 style="width: <?php echo $grade['progress']; ?>%"></div>
                                                                        </div>
                                                                        <span class="small"><?php echo round($grade['progress'], 1); ?>%</span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php if ($grade['average_grade'] !== null): ?>
                                                                        <span class="badge grade-badge bg-<?php 
                                                                            echo $grade['average_grade'] >= 70 ? 'success' : 
                                                                                ($grade['average_grade'] >= 50 ? 'warning' : 'danger'); 
                                                                        ?>">
                                                                            <?php echo round($grade['average_grade'], 1); ?>%
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">No grades yet</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php 
                                                                        echo $grade['enrollment_status'] === 'active' ? 'success' : 
                                                                            ($grade['enrollment_status'] === 'completed' ? 'primary' : 'secondary'); 
                                                                    ?>">
                                                                        <?php echo ucfirst($grade['enrollment_status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="text-muted small">
                                                                        <?php echo date('M j, Y', strtotime($grade['enrolled_at'])); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                                onclick="viewStudentDetails(<?php echo $grade['student_id']; ?>)">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                                onclick="addGrade(<?php echo $grade['student_id']; ?>)">
                                                                            <i class="fas fa-plus"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Grade Legend -->
                                <div class="grade-legend mt-3">
                                    <h6 class="mb-2">Grade Scale</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <span class="badge bg-success me-2">A (90-100%)</span>
                                            <small class="text-muted">Excellent</small>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-info me-2">B (80-89%)</span>
                                            <small class="text-muted">Good</small>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-warning me-2">C (70-79%)</span>
                                            <small class="text-muted">Satisfactory</small>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-danger me-2">F (Below 70%)</span>
                                            <small class="text-muted">Needs Improvement</small>
                                        </div>
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
                                                            completed
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($activity['assessment_title']); ?></small>
                                                            <br>
                                                            <small class="<?php echo $activity['percentage'] >= 70 ? 'text-success' : 'text-warning'; ?>">
                                                                Score: <?php echo round($activity['percentage'], 1); ?>%
                                                            </small>
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
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            if ($('#gradesTable tbody tr').length > 1) {
                $('#gradesTable').DataTable({
                    "pageLength": 25,
                    "order": [[ 1, "desc" ]],
                    "columnDefs": [
                        { "orderable": false, "targets": [5] } // Disable sorting on actions column
                    ],
                    "language": {
                        "emptyTable": "No student data available"
                    }
                });
            }
        });
        
        function changeCourse(courseId) {
            if (courseId) {
                window.location.href = 'gradebook.php?course_id=' + courseId;
            }
        }
        
        function refreshTable() {
            location.reload();
        }
        
        function exportGrades() {
            const courseId = document.getElementById('courseSelect').value;
            window.open('export-grades.php?course_id=' + courseId, '_blank');
        }
        
        function viewStudentDetails(studentId) {
            alert('Student details functionality will be implemented for student ID: ' + studentId);
        }
        
        function addGrade(studentId) {
            alert('Add grade functionality will be implemented for student ID: ' + studentId);
        }
    </script>
</body>
</html>
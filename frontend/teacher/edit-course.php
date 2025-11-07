<?php
/**
 * Edit Course Page
 * Forward LMS Course Editing Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require teacher authentication
$auth->requireRole('teacher');

$user = $auth->getCurrentUser();
$courseId = intval($_GET['id'] ?? 0);

if ($courseId === 0) {
    header('Location: manage-courses.php');
    exit;
}

// Get course details
$course = $db->fetch(
    "SELECT c.*, u.name as teacher_name 
     FROM courses c 
     JOIN users u ON c.teacher_id = u.id 
     WHERE c.id = ? AND c.teacher_id = ?",
    [$courseId, $user['id']]
);

if (!$course) {
    header('Location: manage-courses.php?error=course_not_found');
    exit;
}

// Get course lessons
$lessons = $db->fetchAll(
    "SELECT * FROM lessons 
     WHERE course_id = ? 
     ORDER BY order_index ASC",
    [$courseId]
);

// Get course assessments
$assessments = $db->fetchAll(
    "SELECT * FROM quizzes 
     WHERE course_id = ? 
     ORDER BY created_at DESC",
    [$courseId]
);

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_course') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $level = $_POST['level'] ?? 'beginner';
            $price = floatval($_POST['price'] ?? 0);
            $currency = $_POST['currency'] ?? 'KES';
            $duration = intval($_POST['duration'] ?? 0);
            $status = $_POST['status'] ?? 'draft';
            
            if (empty($title)) {
                throw new Exception('Course title is required');
            }
            
            $sql = "UPDATE courses SET 
                        title = ?, description = ?, category = ?, level = ?, 
                        price = ?, currency = ?, duration = ?, status = ?, updated_at = NOW()
                    WHERE id = ? AND teacher_id = ?";
            
            $params = [
                $title, $description, $category, $level,
                $price, $currency, $duration, $status,
                $courseId, $user['id']
            ];
            
            $db->execute($sql, $params);
            
            // Refresh course data
            $course = $db->fetch(
                "SELECT c.*, u.name as teacher_name 
                 FROM courses c 
                 JOIN users u ON c.teacher_id = u.id 
                 WHERE c.id = ? AND c.teacher_id = ?",
                [$courseId, $user['id']]
            );
            
            $message = 'Course updated successfully!';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'Error updating course: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_lessons':
                echo json_encode(['success' => true, 'data' => $lessons]);
                break;
            case 'get_assessments':
                echo json_encode(['success' => true, 'data' => $assessments]);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    }
    exit;
}

$pageTitle = 'Edit Course: ' . $course['title'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Forward LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
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
        .tab-content {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .lesson-item, .assessment-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        .lesson-item:hover, .assessment-item:hover {
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .drag-handle {
            cursor: grab;
            color: #6c757d;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .required::after {
            content: " *";
            color: red;
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
                        <a class="nav-link active" href="manage-courses.php">
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
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-courses.php">My Courses</a></li>
                            <li class="breadcrumb-item active">Edit Course</li>
                        </ol>
                    </nav>
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1">Edit Course</h1>
                            <p class="text-muted mb-0">Manage your course content and settings.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="course-preview.php?id=<?php echo $courseId; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i>
                                Preview
                            </a>
                            <a href="manage-courses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Courses
                            </a>
                        </div>
                    </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Course Editing Tabs -->
                    <ul class="nav nav-tabs mb-3" id="courseTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                                <i class="fas fa-info-circle me-1"></i>
                                Course Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab">
                                <i class="fas fa-book me-1"></i>
                                Lessons
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab">
                                <i class="fas fa-clipboard-list me-1"></i>
                                Assessments
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                                <i class="fas fa-users me-1"></i>
                                Students
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="courseTabContent">
                        <!-- Course Information Tab -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="p-4">
                                <form method="POST" id="courseForm">
                                    <input type="hidden" name="action" value="update_course">
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="title" class="form-label required">Course Title</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="title" 
                                                       name="title" 
                                                       value="<?php echo htmlspecialchars($course['title']); ?>"
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                                    <option value="archived" <?php echo $course['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control note-editor" 
                                                  id="description" 
                                                  name="description"><?php echo htmlspecialchars($course['description']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Category</label>
                                                <select class="form-select" id="category" name="category">
                                                    <option value="">Select Category</option>
                                                    <option value="Programming" <?php echo $course['category'] === 'Programming' ? 'selected' : ''; ?>>Programming</option>
                                                    <option value="Design" <?php echo $course['category'] === 'Design' ? 'selected' : ''; ?>>Design</option>
                                                    <option value="Business" <?php echo $course['category'] === 'Business' ? 'selected' : ''; ?>>Business</option>
                                                    <option value="Marketing" <?php echo $course['category'] === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                                    <option value="Photography" <?php echo $course['category'] === 'Photography' ? 'selected' : ''; ?>>Photography</option>
                                                    <option value="Music" <?php echo $course['category'] === 'Music' ? 'selected' : ''; ?>>Music</option>
                                                    <option value="Health" <?php echo $course['category'] === 'Health' ? 'selected' : ''; ?>>Health & Fitness</option>
                                                    <option value="Language" <?php echo $course['category'] === 'Language' ? 'selected' : ''; ?>>Language Learning</option>
                                                    <option value="Other" <?php echo $course['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="level" class="form-label">Level</label>
                                                <select class="form-select" id="level" name="level">
                                                    <option value="beginner" <?php echo $course['level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                    <option value="intermediate" <?php echo $course['level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                    <option value="advanced" <?php echo $course['level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="duration" class="form-label">Duration (hours)</label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="duration" 
                                                       name="duration" 
                                                       value="<?php echo $course['duration']; ?>"
                                                       min="0" 
                                                       step="0.5">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="currency" class="form-label">Currency</label>
                                                <select class="form-select" id="currency" name="currency">
                                                    <option value="KES" <?php echo $course['currency'] === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                                    <option value="USD" <?php echo $course['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                    <option value="EUR" <?php echo $course['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                    <option value="GBP" <?php echo $course['currency'] === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="price" class="form-label">Price</label>
                                                <div class="input-group">
                                                    <span class="input-group-text" id="currency-symbol">
                                                        <?php echo $course['currency'] === 'KES' ? 'KSh' : ($course['currency'] === 'USD' ? '$' : ($course['currency'] === 'EUR' ? '€' : '£')); ?>
                                                    </span>
                                                    <input type="number" 
                                                           class="form-control" 
                                                           id="price" 
                                                           name="price" 
                                                           value="<?php echo $course['price']; ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Lessons Tab -->
                        <div class="tab-pane fade" id="content" role="tabpanel">
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Course Lessons</h5>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLessonModal">
                                        <i class="fas fa-plus me-1"></i>
                                        Add Lesson
                                    </button>
                                </div>
                                
                                <div id="lessons-list">
                                    <?php if (empty($lessons)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No lessons yet</h5>
                                            <p class="text-muted">Add your first lesson to start building your course.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <div class="lesson-item" data-lesson-id="<?php echo $lesson['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fas fa-grip-vertical drag-handle me-2"></i>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                                            <?php if ($lesson['is_free']): ?>
                                                                <span class="badge bg-success ms-2">Free</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-muted small mb-2">
                                                            <?php echo htmlspecialchars(substr($lesson['description'], 0, 100)) . (strlen($lesson['description']) > 100 ? '...' : ''); ?>
                                                        </p>
                                                        <div class="d-flex gap-3 text-muted small">
                                                            <span><i class="fas fa-clock me-1"></i> <?php echo $lesson['duration']; ?> min</span>
                                                            <span><i class="fas fa-list-ol me-1"></i> Order: <?php echo $lesson['order_index']; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-lesson" data-lesson-id="<?php echo $lesson['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-lesson" data-lesson-id="<?php echo $lesson['id']; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assessments Tab -->
                        <div class="tab-pane fade" id="assessments" role="tabpanel">
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Course Assessments</h5>
                                    <a href="create-assessment.php?course_id=<?php echo $courseId; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>
                                        Create Assessment
                                    </a>
                                </div>
                                
                                <div id="assessments-list">
                                    <?php if (empty($assessments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No assessments yet</h5>
                                            <p class="text-muted">Create quizzes and assignments to test student knowledge.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($assessments as $assessment): ?>
                                            <div class="assessment-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($assessment['title']); ?></h6>
                                                            <span class="badge bg-<?php echo $assessment['type'] === 'quiz' ? 'info' : 'warning'; ?> ms-2">
                                                                <?php echo ucfirst($assessment['type']); ?>
                                                            </span>
                                                            <span class="badge bg-<?php echo $assessment['status'] === 'published' ? 'success' : 'secondary'; ?> ms-1">
                                                                <?php echo ucfirst($assessment['status']); ?>
                                                            </span>
                                                        </div>
                                                        <p class="text-muted small mb-2">
                                                            <?php echo htmlspecialchars(substr($assessment['description'], 0, 100)) . (strlen($assessment['description']) > 100 ? '...' : ''); ?>
                                                        </p>
                                                        <div class="d-flex gap-3 text-muted small">
                                                            <span><i class="fas fa-clock me-1"></i> <?php echo $assessment['time_limit']; ?> min</span>
                                                            <span><i class="fas fa-star me-1"></i> Max: <?php echo $assessment['max_points']; ?> points</span>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit-assessment.php?id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view-submissions.php?id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Students Tab -->
                        <div class="tab-pane fade" id="students" role="tabpanel">
                            <div class="p-4">
                                <h5 class="mb-3">Enrolled Students</h5>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Student management coming soon</h5>
                                    <p class="text-muted">View and manage enrolled students, track progress, and communicate with learners.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Lesson Modal -->
    <div class="modal fade" id="addLessonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Lesson</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addLessonForm">
                        <div class="mb-3">
                            <label for="lesson_title" class="form-label">Lesson Title</label>
                            <input type="text" class="form-control" id="lesson_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="lesson_description" class="form-label">Description</label>
                            <textarea class="form-control" id="lesson_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="lesson_duration" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="lesson_duration" min="1">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lesson_is_free">
                            <label class="form-check-label" for="lesson_is_free">
                                Make this lesson free (preview)
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveLessonBtn">Add Lesson</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize rich text editor
            $('.note-editor').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
            
            // Update currency symbol when currency changes
            $('#currency').change(function() {
                const currency = $(this).val();
                const symbols = {
                    'KES': 'KSh',
                    'USD': '$',
                    'EUR': '€',
                    'GBP': '£'
                };
                $('#currency-symbol').text(symbols[currency] || currency);
            });
            
            // Add lesson functionality
            $('#saveLessonBtn').click(function() {
                const title = $('#lesson_title').val();
                if (!title) {
                    alert('Please enter a lesson title');
                    return;
                }
                
                // Here you would normally send an AJAX request to add the lesson
                alert('Add lesson functionality will be implemented with backend API');
                
                // Close modal
                $('#addLessonModal').modal('hide');
                
                // Clear form
                $('#addLessonForm')[0].reset();
            });
            
            // Edit lesson functionality
            $('.edit-lesson').click(function() {
                const lessonId = $(this).data('lesson-id');
                alert('Edit lesson functionality will be implemented for lesson ID: ' + lessonId);
            });
            
            // Delete lesson functionality
            $('.delete-lesson').click(function() {
                const lessonId = $(this).data('lesson-id');
                if (confirm('Are you sure you want to delete this lesson?')) {
                    alert('Delete lesson functionality will be implemented for lesson ID: ' + lessonId);
                }
            });
        });
    </script>
</body>
</html>
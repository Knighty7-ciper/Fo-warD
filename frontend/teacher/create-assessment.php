<?php
/**
 * Create Assessment Page
 * Forward LMS Assessment Creation Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require teacher authentication
$auth->requireRole('teacher');

$user = $auth->getCurrentUser();

// Get courses for the teacher
$courses = $db->fetchAll(
    "SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title",
    [$user['id']]
);

// Get course_id from URL parameter if provided
$selectedCourseId = intval($_GET['course_id'] ?? 0);

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $courseId = intval($_POST['course_id'] ?? 0);
        $type = $_POST['type'] ?? 'quiz';
        $timeLimit = intval($_POST['time_limit'] ?? 0);
        $maxAttempts = intval($_POST['max_attempts'] ?? 1);
        $passingScore = floatval($_POST['passing_score'] ?? 70.00);
        
        if (empty($title)) {
            throw new Exception('Assessment title is required');
        }
        
        if ($courseId === 0) {
            throw new Exception('Please select a course');
        }
        
        $sql = "INSERT INTO quizzes (
                    course_id, teacher_id, title, description, instructions,
                    time_limit, passing_score, max_attempts, shuffle_questions,
                    show_correct_answers, show_results_immediately, type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
        
        $params = [
            $courseId,
            $user['id'],
            $title,
            $description,
            $instructions,
            $timeLimit,
            $passingScore,
            $maxAttempts,
            isset($_POST['shuffle_questions']) ? 1 : 0,
            isset($_POST['show_correct_answers']) ? 1 : 1,
            isset($_POST['show_results_immediately']) ? 1 : 1,
            $type
        ];
        
        $db->execute($sql, $params);
        $assessmentId = $db->lastInsertId();
        
        $message = 'Assessment created successfully! You can now add questions and questions.';
        $messageType = 'success';
        
        // Redirect to add questions page
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'add-questions.php?id={$assessmentId}';
                }, 2000);
              </script>";
        
    } catch (Exception $e) {
        $message = 'Error creating assessment: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$pageTitle = 'Create New Assessment';
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
        .form-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 2rem;
        }
        .form-section .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1.5rem;
        }
        .form-section .card-body {
            padding: 1.5rem;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .assessment-type-selector {
            border: 2px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .assessment-type {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .assessment-type:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .assessment-type.selected {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .preview-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
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
                        <a class="nav-link active" href="create-assessment.php">
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
                            <li class="breadcrumb-item active">Create Assessment</li>
                        </ol>
                    </nav>
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1"><?php echo $pageTitle; ?></h1>
                            <p class="text-muted mb-0">Create quizzes and assignments to test student knowledge.</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Dashboard
                        </a>
                    </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <?php if ($messageType === 'success'): ?>
                                <div class="mt-2">
                                    <small>Redirecting to add questions...</small>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Assessment Creation Form -->
                    <form method="POST" id="assessmentForm">
                        <div class="form-section">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Basic Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Assessment Type Selection -->
                                <div class="mb-4">
                                    <label class="form-label">Assessment Type</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="assessment-type border rounded p-3 text-center" data-type="quiz">
                                                <i class="fas fa-question-circle fa-2x text-primary mb-2"></i>
                                                <h6>Quiz</h6>
                                                <p class="form-text mb-0">Multiple choice, true/false, and short answer questions</p>
                                                <input type="radio" name="type" value="quiz" class="d-none" <?php echo ($_POST['type'] ?? 'quiz') === 'quiz' ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="assessment-type border rounded p-3 text-center" data-type="assignment">
                                                <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                                                <h6>Assignment</h6>
                                                <p class="form-text mb-0">Open-ended questions and file submissions</p>
                                                <input type="radio" name="type" value="assignment" class="d-none" <?php echo ($_POST['type'] ?? '') === 'assignment' ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="title" class="form-label required">Assessment Title</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="title" 
                                                   name="title" 
                                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                                   placeholder="Enter assessment title"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="course_id" class="form-label required">Course</label>
                                            <select class="form-select" id="course_id" name="course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course['id']; ?>" 
                                                            <?php echo ($selectedCourseId > 0 && $selectedCourseId == $course['id']) || 
                                                                     (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="3"
                                              placeholder="Brief description of the assessment..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Instructions</label>
                                    <textarea class="form-control note-editor" 
                                              id="instructions" 
                                              name="instructions" 
                                              placeholder="Instructions for students taking this assessment..."><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide clear instructions on how to complete the assessment.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>
                                    Settings & Rules
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="time_limit" 
                                                   name="time_limit" 
                                                   value="<?php echo htmlspecialchars($_POST['time_limit'] ?? '0'); ?>"
                                                   min="0" 
                                                   placeholder="0 = No time limit">
                                            <div class="form-text">Set to 0 for no time limit</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="max_attempts" class="form-label">Maximum Attempts</label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="max_attempts" 
                                                   name="max_attempts" 
                                                   value="<?php echo htmlspecialchars($_POST['max_attempts'] ?? '1'); ?>"
                                                   min="1" 
                                                   max="10">
                                            <div class="form-text">Number of times students can take this assessment</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="passing_score" class="form-label">Passing Score (%)</label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="passing_score" 
                                                   name="passing_score" 
                                                   value="<?php echo htmlspecialchars($_POST['passing_score'] ?? '70'); ?>"
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <div class="form-text">Minimum score to pass (0-100%)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="shuffle_questions" 
                                                       name="shuffle_questions" 
                                                       <?php echo isset($_POST['shuffle_questions']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="shuffle_questions">
                                                    Shuffle Questions
                                                </label>
                                                <div class="form-text">Randomize question order for each attempt</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="show_correct_answers" 
                                                       name="show_correct_answers" 
                                                       <?php echo !isset($_POST['show_correct_answers']) || $_POST['show_correct_answers'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="show_correct_answers">
                                                    Show Correct Answers
                                                </label>
                                                <div class="form-text">Display correct answers after submission</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="show_results_immediately" 
                                               name="show_results_immediately" 
                                               <?php echo !isset($_POST['show_results_immediately']) || $_POST['show_results_immediately'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_results_immediately">
                                            Show Results Immediately
                                        </label>
                                        <div class="form-text">Display results right after submission</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="preview-section">
                            <h6><i class="fas fa-eye me-2"></i>Preview</h6>
                            <div id="preview-content">
                                <p class="text-muted">Assessment details will appear here as you fill in the form...</p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Cancel
                            </a>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" id="saveDraftBtn">
                                    <i class="fas fa-save me-1"></i>
                                    Save Draft
                                </button>
                                <button type="submit" class="btn btn-primary" id="createBtn">
                                    <i class="fas fa-plus me-1"></i>
                                    Create & Add Questions
                                </button>
                            </div>
                        </div>
                    </form>
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
                height: 150,
                placeholder: 'Enter instructions for students...',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']]
                ]
            });
            
            // Assessment type selection
            $('.assessment-type').click(function() {
                const type = $(this).data('type');
                $('.assessment-type').removeClass('selected');
                $(this).addClass('selected');
                $('input[name="type"][value="' + type + '"]').prop('checked', true);
                updatePreview();
            });
            
            // Initialize selected type
            $('.assessment-type.selected, .assessment-type input:checked').parent().addClass('selected');
            
            // Update preview when form changes
            $('#assessmentForm input, #assessmentForm textarea, #assessmentForm select').on('input change', function() {
                updatePreview();
            });
            
            // Save draft functionality
            $('#saveDraftBtn').click(function() {
                alert('Save draft functionality will be implemented');
            });
            
            // Form validation
            $('#assessmentForm').submit(function(e) {
                const title = $('#title').val().trim();
                const courseId = $('#course_id').val();
                
                if (!title) {
                    e.preventDefault();
                    alert('Please enter an assessment title');
                    $('#title').focus();
                    return false;
                }
                
                if (!courseId) {
                    e.preventDefault();
                    alert('Please select a course');
                    $('#course_id').focus();
                    return false;
                }
                
                // Show loading state
                $('#createBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Creating...');
            });
            
            // Update preview function
            function updatePreview() {
                const title = $('#title').val() || 'Assessment Title';
                const type = $('input[name="type"]:checked').val() || 'quiz';
                const course = $('#course_id option:selected').text() || 'Select a course';
                const timeLimit = $('#time_limit').val() || 'No limit';
                const maxAttempts = $('#max_attempts').val() || '1';
                const passingScore = $('#passing_score').val() || '70';
                
                const typeIcon = type === 'quiz' ? 'fa-question-circle text-primary' : 'fa-file-alt text-success';
                const typeLabel = type === 'quiz' ? 'Quiz' : 'Assignment';
                
                const previewHtml = `
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas ${typeIcon} me-2"></i>
                                <h6 class="mb-0">${title}</h6>
                                <span class="badge bg-secondary ms-2">${typeLabel}</span>
                            </div>
                            <div class="row text-muted small">
                                <div class="col-md-6">
                                    <div><strong>Course:</strong> ${course}</div>
                                    <div><strong>Time Limit:</strong> ${timeLimit} minutes</div>
                                </div>
                                <div class="col-md-6">
                                    <div><strong>Max Attempts:</strong> ${maxAttempts}</div>
                                    <div><strong>Passing Score:</strong> ${passingScore}%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#preview-content').html(previewHtml);
            }
            
            // Initial preview update
            updatePreview();
        });
    </script>
</body>
</html>
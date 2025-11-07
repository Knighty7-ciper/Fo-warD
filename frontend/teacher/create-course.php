<?php
/**
 * Create Course Page
 * Forward LMS Course Creation Interface
 */

require_once __DIR__ . '/../config/auth.php';

// Require teacher authentication
$auth->requireRole('teacher');

$user = $auth->getCurrentUser();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $level = $_POST['level'] ?? 'beginner';
        $price = floatval($_POST['price'] ?? 0);
        $currency = $_POST['currency'] ?? 'KES';
        $duration = intval($_POST['duration'] ?? 0);
        
        if (empty($title)) {
            throw new Exception('Course title is required');
        }
        
        $sql = "INSERT INTO courses (
                    teacher_id, title, description, category, level, 
                    price, currency, duration, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
        
        $params = [
            $user['id'],
            $title,
            $description,
            $category,
            $level,
            $price,
            $currency,
            $duration
        ];
        
        $db->execute($sql, $params);
        $courseId = $db->lastInsertId();
        
        $message = 'Course created successfully! You can now add lessons and content.';
        $messageType = 'success';
        
        // Redirect to edit page after 2 seconds
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'edit-course.php?id={$courseId}';
                }, 2000);
              </script>";
        
    } catch (Exception $e) {
        $message = 'Error creating course: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$pageTitle = 'Create New Course';
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
        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
        }
        .step-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }
        .step {
            display: flex;
            align-items: center;
            margin-right: 2rem;
        }
        .step-number {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .step.active .step-number {
            background-color: #007bff;
        }
        .step.completed .step-number {
            background-color: #28a745;
        }
        .note-editor {
            min-height: 200px;
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
                        <a class="nav-link active" href="create-course.php">
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
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Create Course</li>
                        </ol>
                    </nav>
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1"><?php echo $pageTitle; ?></h1>
                            <p class="text-muted mb-0">Create a new course and start building your content.</p>
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
                                    <small>Redirecting to course editor...</small>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step active">
                            <div class="step-number">1</div>
                            <span>Basic Information</span>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <span>Content</span>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <span>Settings</span>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <span>Review</span>
                        </div>
                    </div>
                    
                    <!-- Course Creation Form -->
                    <form method="POST" id="courseForm">
                        <div class="form-section">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Basic Course Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="title" class="form-label required">Course Title</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="title" 
                                                   name="title" 
                                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                                   placeholder="Enter your course title"
                                                   required>
                                            <div class="form-text">Choose a clear, descriptive title for your course.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="category" class="form-label">Category</label>
                                            <select class="form-select" id="category" name="category">
                                                <option value="">Select Category</option>
                                                <option value="Programming" <?php echo ($_POST['category'] ?? '') === 'Programming' ? 'selected' : ''; ?>>Programming</option>
                                                <option value="Design" <?php echo ($_POST['category'] ?? '') === 'Design' ? 'selected' : ''; ?>>Design</option>
                                                <option value="Business" <?php echo ($_POST['category'] ?? '') === 'Business' ? 'selected' : ''; ?>>Business</option>
                                                <option value="Marketing" <?php echo ($_POST['category'] ?? '') === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                                <option value="Photography" <?php echo ($_POST['category'] ?? '') === 'Photography' ? 'selected' : ''; ?>>Photography</option>
                                                <option value="Music" <?php echo ($_POST['category'] ?? '') === 'Music' ? 'selected' : ''; ?>>Music</option>
                                                <option value="Health" <?php echo ($_POST['category'] ?? '') === 'Health' ? 'selected' : ''; ?>>Health & Fitness</option>
                                                <option value="Language" <?php echo ($_POST['category'] ?? '') === 'Language' ? 'selected' : ''; ?>>Language Learning</option>
                                                <option value="Other" <?php echo ($_POST['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Course Description</label>
                                    <textarea class="form-control note-editor" 
                                              id="description" 
                                              name="description" 
                                              placeholder="Describe what students will learn in this course..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide a detailed description of your course content and learning objectives.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>
                                    Course Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="level" class="form-label">Difficulty Level</label>
                                            <select class="form-select" id="level" name="level">
                                                <option value="beginner" <?php echo ($_POST['level'] ?? 'beginner') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                <option value="intermediate" <?php echo ($_POST['level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                <option value="advanced" <?php echo ($_POST['level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="duration" class="form-label">Estimated Duration (hours)</label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="duration" 
                                                   name="duration" 
                                                   value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>"
                                                   min="0" 
                                                   step="0.5"
                                                   placeholder="e.g., 10">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="currency" class="form-label">Currency</label>
                                            <select class="form-select" id="currency" name="currency">
                                                <option value="KES" <?php echo ($_POST['currency'] ?? 'KES') === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                                <option value="USD" <?php echo ($_POST['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                <option value="EUR" <?php echo ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                <option value="GBP" <?php echo ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">Course Price</label>
                                            <div class="input-group">
                                                <span class="input-group-text" id="currency-symbol"><?php echo ($_POST['currency'] ?? 'KES') === 'KES' ? 'KSh' : ($_POST['currency'] ?? 'KES') === 'USD' ? '$' : ($_POST['currency'] ?? 'KES') === 'EUR' ? '€' : '£'); ?></span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="price" 
                                                       name="price" 
                                                       value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>"
                                                       min="0" 
                                                       step="0.01"
                                                       placeholder="0.00">
                                            </div>
                                            <div class="form-text">Set to 0 for a free course.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Cancel
                            </a>
                            <div>
                                <button type="submit" class="btn btn-primary me-2" id="createBtn">
                                    <i class="fas fa-plus me-1"></i>
                                    Create Course
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
                height: 200,
                placeholder: 'Start writing your course description...',
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
            
            // Form validation
            $('#courseForm').submit(function(e) {
                const title = $('#title').val().trim();
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a course title');
                    $('#title').focus();
                    return false;
                }
                
                // Show loading state
                $('#createBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Creating...');
            });
            
            // Auto-save draft (you can implement this feature)
            let autoSaveTimer;
            $('#courseForm input, #courseForm textarea, #courseForm select').on('input change', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    // Implement auto-save functionality here
                    console.log('Auto-saving draft...');
                }, 5000);
            });
        });
    </script>
</body>
</html>
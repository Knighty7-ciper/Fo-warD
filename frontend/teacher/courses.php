<?php
require_once '../../shared/config/auth.php';
require_once '../../backend/config/db.php';
require_once '../../shared/utils/functions.php';

// Check if user is teacher
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: /frontend/403.php');
    exit();
}

$teacher_id = $_SESSION['user']['id'];

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $course_id = intval($_POST['course_id'] ?? 0);
    
    if ($course_id > 0) {
        try {
            switch ($action) {
                case 'publish':
                    $stmt = $conn->prepare("UPDATE courses SET is_published = 1, status = 'published' WHERE id = ? AND teacher_id = ?");
                    if ($stmt->execute([$course_id, $teacher_id])) {
                        $success_message = "Course published successfully!";
                        logActivity($teacher_id, 'publish_course', "Published course ID: $course_id", $course_id);
                    }
                    break;
                    
                case 'unpublish':
                    $stmt = $conn->prepare("UPDATE courses SET is_published = 0, status = 'draft' WHERE id = ? AND teacher_id = ?");
                    if ($stmt->execute([$course_id, $teacher_id])) {
                        $success_message = "Course unpublished successfully!";
                        logActivity($teacher_id, 'unpublish_course', "Unpublished course ID: $course_id", $course_id);
                    }
                    break;
                    
                case 'archive':
                    $stmt = $conn->prepare("UPDATE courses SET status = 'archived' WHERE id = ? AND teacher_id = ?");
                    if ($stmt->execute([$course_id, $teacher_id])) {
                        $success_message = "Course archived successfully!";
                        logActivity($teacher_id, 'archive_course', "Archived course ID: $course_id", $course_id);
                    }
                    break;
                    
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ? AND teacher_id = ?");
                    if ($stmt->execute([$course_id, $teacher_id])) {
                        $success_message = "Course deleted successfully!";
                        logActivity($teacher_id, 'delete_course', "Deleted course ID: $course_id", $course_id);
                    }
                    break;
            }
        } catch (Exception $e) {
            $error_message = "Failed to perform action: " . $e->getMessage();
        }
    }
}

// Get courses for this teacher
$courses = [];
try {
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(DISTINCT l.id) as lesson_count,
               COUNT(DISTINCT e.student_id) as enrollment_count,
               AVG(r.rating) as avg_rating,
               COUNT(DISTINCT r.id) as review_count
        FROM courses c
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'active'
        LEFT JOIN reviews r ON c.id = r.course_id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to load courses: " . $e->getMessage();
}

// Get course statistics
$stats = [
    'total' => count($courses),
    'published' => 0,
    'draft' => 0,
    'archived' => 0,
    'total_enrollments' => 0,
    'total_revenue' => 0
];

foreach ($courses as $course) {
    $stats['total_enrollments'] += $course['enrollment_count'];
    $stats['total_revenue'] += $course['enrollment_count'] * $course['price'];
    
    if ($course['is_published']) {
        $stats['published']++;
    } else {
        $stats['draft']++;
    }
    
    if ($course['status'] === 'archived') {
        $stats['archived']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/main.css">
    <link rel="stylesheet" href="/frontend/assets/css/teacher-dashboard.css">
    <link rel="stylesheet" href="/frontend/assets/css/courses-list.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../shared/templates/header.php'; ?>
    <?php include 'includes/teacher-nav.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="page-title">
                    <h1><i class="fas fa-book"></i> My Courses</h1>
                    <p>Manage your course portfolio and track performance</p>
                </div>
                <div class="header-actions">
                    <a href="create-course.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Course
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Courses</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['published'] ?></h3>
                    <p>Published</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['draft'] ?></h3>
                    <p>Drafts</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total_enrollments'] ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Courses List -->
        <div class="content-section">
            <div class="section-header">
                <h2>Course Management</h2>
                <div class="section-filters">
                    <button class="filter-btn active" data-filter="all">All Courses</button>
                    <button class="filter-btn" data-filter="published">Published</button>
                    <button class="filter-btn" data-filter="draft">Drafts</button>
                    <button class="filter-btn" data-filter="archived">Archived</button>
                </div>
            </div>

            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>No courses yet</h3>
                    <p>Start building your teaching portfolio by creating your first course</p>
                    <a href="create-course.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Your First Course
                    </a>
                </div>
            <?php else: ?>
                <div class="courses-grid" id="coursesGrid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card" data-status="<?= $course['is_published'] ? 'published' : 'draft' ?>">
                            <!-- Course Image/Thumbnail -->
                            <div class="course-thumbnail">
                                <?php if (!empty($course['thumbnail'])): ?>
                                    <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-thumbnail">
                                        <i class="fas fa-book"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="course-status">
                                    <?php if ($course['is_published']): ?>
                                        <span class="status-badge published">Published</span>
                                    <?php else: ?>
                                        <span class="status-badge draft">Draft</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Course Content -->
                            <div class="course-content">
                                <div class="course-header">
                                    <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                                    <p class="course-category"><?= htmlspecialchars($course['category']) ?></p>
                                </div>

                                <p class="course-description"><?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...</p>

                                <!-- Course Stats -->
                                <div class="course-stats">
                                    <div class="stat">
                                        <i class="fas fa-play-circle"></i>
                                        <span><?= $course['lesson_count'] ?> lessons</span>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-users"></i>
                                        <span><?= $course['enrollment_count'] ?> students</span>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-star"></i>
                                        <span><?= $course['avg_rating'] ? number_format($course['avg_rating'], 1) : 'No ratings' ?></span>
                                    </div>
                                </div>

                                <!-- Course Meta -->
                                <div class="course-meta">
                                    <div class="price">$<?= number_format($course['price'], 2) ?></div>
                                    <div class="created-date"><?= date('M j, Y', strtotime($course['created_at'])) ?></div>
                                </div>
                            </div>

                            <!-- Course Actions -->
                            <div class="course-actions">
                                <div class="action-buttons">
                                    <a href="edit-course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-secondary" title="Edit Course">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="/frontend/courses/view-course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-info" title="View Public Page" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="manage-lessons.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-success" title="Manage Lessons">
                                        <i class="fas fa-book"></i>
                                    </a>
                                    
                                    <?php if ($course['enrollment_count'] > 0): ?>
                                        <a href="gradebook.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-warning" title="View Enrollments">
                                            <i class="fas fa-users"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Course Controls -->
                                <div class="course-controls">
                                    <?php if (!$course['is_published']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                            <input type="hidden" name="action" value="publish">
                                            <button type="submit" class="btn btn-sm btn-success" title="Publish Course">
                                                <i class="fas fa-check"></i> Publish
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                            <input type="hidden" name="action" value="unpublish">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Unpublish Course">
                                                <i class="fas fa-pause"></i> Unpublish
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($course['status'] !== 'archived'): ?>
                                        <form method="POST" style="display: inline;" class="archive-form">
                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Archive Course">
                                                <i class="fas fa-archive"></i> Archive
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="edit-course.php?id=<?= $course['id'] ?>">Edit Details</a></li>
                                            <li><a class="dropdown-item" href="manage-lessons.php?course_id=<?= $course['id'] ?>">Manage Content</a></li>
                                            <li><a class="dropdown-item" href="gradebook.php?course_id=<?= $course['id'] ?>">View Students</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="delete-form" style="margin: 0;">
                                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="dropdown-item text-danger delete-course">
                                                        <i class="fas fa-trash"></i> Delete Course
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this course? This action cannot be undone.</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> All lessons, enrollments, and student progress will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete Course</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/frontend/assets/js/courses-management.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const filterBtns = document.querySelectorAll('.filter-btn');
            const courseCards = document.querySelectorAll('.course-card');
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    
                    // Update active filter button
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter courses
                    courseCards.forEach(card => {
                        const status = card.dataset.status;
                        if (filter === 'all' || status === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Delete confirmation
            const deleteForms = document.querySelectorAll('.delete-form');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            let formToSubmit = null;
            
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    formToSubmit = this;
                    deleteModal.show();
                });
            });
            
            confirmDeleteBtn.addEventListener('click', function() {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
            });
            
            // Archive confirmation
            const archiveForms = document.querySelectorAll('.archive-form');
            archiveForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to archive this course? Students can still access archived courses but new enrollments are disabled.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
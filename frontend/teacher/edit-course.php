<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$course_id = $_GET['id'] ?? 0;
$db = getDBConnection();

// Verify ownership
$sql = "SELECT * FROM courses WHERE id = :id AND teacher_id = :teacher_id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $course_id, ':teacher_id' => $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Edit Course';
include '../../shared/templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Course</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <form id="editCourseForm" class="course-form">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        
        <div class="form-section">
            <h2>Basic Information</h2>
            
            <div class="form-group">
                <label for="title">Course Title *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($course['title']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Short Description *</label>
                <textarea id="description" name="description" rows="3" required><?= htmlspecialchars($course['description']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="full_description">Full Description</label>
                <textarea id="full_description" name="full_description" rows="8"><?= htmlspecialchars($course['full_description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="programming" <?= $course['category'] == 'programming' ? 'selected' : '' ?>>Programming</option>
                        <option value="design" <?= $course['category'] == 'design' ? 'selected' : '' ?>>Design</option>
                        <option value="business" <?= $course['category'] == 'business' ? 'selected' : '' ?>>Business</option>
                        <option value="marketing" <?= $course['category'] == 'marketing' ? 'selected' : '' ?>>Marketing</option>
                        <option value="science" <?= $course['category'] == 'science' ? 'selected' : '' ?>>Science</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="level">Difficulty Level</label>
                    <select id="level" name="level">
                        <option value="beginner" <?= $course['level'] == 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= $course['level'] == 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= $course['level'] == 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="duration">Duration (weeks)</label>
                    <input type="number" id="duration" name="duration" value="<?= $course['duration'] ?? 8 ?>" min="1" max="52">
                </div>
                
                <div class="form-group">
                    <label for="price">Price (KSh)</label>
                    <input type="number" id="price" name="price" value="<?= $course['price'] ?? 0 ?>" min="0" step="100">
                    <small>Set to 0 for free course</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="thumbnail">Course Thumbnail URL</label>
                <input type="url" id="thumbnail" name="thumbnail" value="<?= htmlspecialchars($course['thumbnail'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-section">
            <h2>Course Settings</h2>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="draft" <?= $course['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $course['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= $course['status'] == 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="allow_enrollment" <?= $course['allow_enrollment'] ? 'checked' : '' ?>>
                    Allow new enrollments
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="certificate_enabled" <?= $course['certificate_enabled'] ? 'checked' : '' ?>>
                    Issue certificates upon completion
                </label>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="manage-lessons.php?course_id=<?= $course_id ?>" class="btn btn-secondary">Manage Lessons</a>
            <button type="button" class="btn btn-danger" onclick="deleteCourse()">Delete Course</button>
        </div>
    </form>
</div>

<style>
.course-form {
    max-width: 900px;
    margin: 0 auto;
}

.form-section {
    background: white;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section h2 {
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 0.85rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.getElementById('editCourseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../../backend/teacher/update-course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Course updated successfully!');
            window.location.reload();
        } else {
            alert('Error updating course: ' + result.error);
        }
    });
});

function deleteCourse() {
    if (!confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('course_id', <?= $course_id ?>);
    
    fetch('../../backend/teacher/delete-course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Course deleted successfully!');
            window.location.href = 'dashboard.php';
        } else {
            alert('Error deleting course: ' + result.error);
        }
    });
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

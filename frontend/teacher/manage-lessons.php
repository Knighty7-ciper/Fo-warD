<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$course_id = $_GET['course_id'] ?? 0;
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

// Get lessons
$sql = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_num ASC";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manage Lessons';
include '../../shared/templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Manage Lessons: <?= htmlspecialchars($course['title']) ?></h1>
        <div class="header-actions">
            <a href="edit-course.php?id=<?= $course_id ?>" class="btn btn-secondary">Back to Course</a>
            <button class="btn btn-primary" onclick="showAddLessonModal()">Add New Lesson</button>
        </div>
    </div>
    
    <div class="lessons-manager">
        <?php if (empty($lessons)): ?>
            <div class="empty-state">
                <p>No lessons yet. Add your first lesson to get started!</p>
            </div>
        <?php else: ?>
            <div class="lessons-list" id="lessonsList">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-item" data-id="<?= $lesson['id'] ?>">
                        <div class="lesson-drag-handle">⋮⋮</div>
                        <div class="lesson-info">
                            <h3><?= htmlspecialchars($lesson['title']) ?></h3>
                            <p><?= htmlspecialchars(substr($lesson['description'] ?? '', 0, 100)) ?></p>
                            <div class="lesson-meta">
                                <span>Order: <?= $lesson['order_num'] ?></span>
                                <span>Duration: <?= $lesson['duration'] ?? 30 ?> min</span>
                            </div>
                        </div>
                        <div class="lesson-actions">
                            <button class="btn btn-sm btn-secondary" onclick="editLesson(<?= $lesson['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLesson(<?= $lesson['id'] ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

 Add/Edit Lesson Modal 
<div id="lessonModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Lesson</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="lessonForm">
            <input type="hidden" id="lesson_id" name="lesson_id">
            <input type="hidden" name="course_id" value="<?= $course_id ?>">
            
            <div class="form-group">
                <label for="lesson_title">Lesson Title *</label>
                <input type="text" id="lesson_title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="lesson_description">Description</label>
                <textarea id="lesson_description" name="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="lesson_content">Lesson Content</label>
                <textarea id="lesson_content" name="content" rows="8"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="lesson_duration">Duration (minutes)</label>
                    <input type="number" id="lesson_duration" name="duration" value="30" min="1">
                </div>
                
                <div class="form-group">
                    <label for="lesson_order">Order</label>
                    <input type="number" id="lesson_order" name="order_num" value="<?= count($lessons) + 1 ?>" min="1">
                </div>
            </div>
            
            <div class="form-group">
                <label for="video_url">Video URL</label>
                <input type="url" id="video_url" name="video_url">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Lesson</button>
            </div>
        </form>
    </div>
</div>

<style>
.header-actions {
    display: flex;
    gap: 10px;
}

.lessons-manager {
    max-width: 1000px;
    margin: 0 auto;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    color: #666;
}

.lessons-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.lesson-item {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s;
}

.lesson-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.lesson-drag-handle {
    cursor: move;
    color: #999;
    font-size: 1.5rem;
}

.lesson-info {
    flex: 1;
}

.lesson-info h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.lesson-info p {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 0.9rem;
}

.lesson-meta {
    display: flex;
    gap: 20px;
    font-size: 0.85rem;
    color: #999;
}

.lesson-actions {
    display: flex;
    gap: 10px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
}

.modal-content form {
    padding: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>

<script>
function showAddLessonModal() {
    document.getElementById('modalTitle').textContent = 'Add New Lesson';
    document.getElementById('lessonForm').reset();
    document.getElementById('lesson_id').value = '';
    document.getElementById('lessonModal').classList.add('active');
}

function closeModal() {
    document.getElementById('lessonModal').classList.remove('active');
}

function editLesson(lessonId) {
    fetch(`../../backend/teacher/get-lesson.php?id=${lessonId}`)
        .then(response => response.json())
        .then(lesson => {
            document.getElementById('modalTitle').textContent = 'Edit Lesson';
            document.getElementById('lesson_id').value = lesson.id;
            document.getElementById('lesson_title').value = lesson.title;
            document.getElementById('lesson_description').value = lesson.description || '';
            document.getElementById('lesson_content').value = lesson.content || '';
            document.getElementById('lesson_duration').value = lesson.duration || 30;
            document.getElementById('lesson_order').value = lesson.order_num;
            document.getElementById('video_url').value = lesson.video_url || '';
            document.getElementById('lessonModal').classList.add('active');
        });
}

function deleteLesson(lessonId) {
    if (!confirm('Are you sure you want to delete this lesson?')) return;
    
    const formData = new FormData();
    formData.append('lesson_id', lessonId);
    
    fetch('../../backend/teacher/delete-lesson.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Error deleting lesson');
        }
    });
}

document.getElementById('lessonForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const url = formData.get('lesson_id') 
        ? '../../backend/teacher/update-lesson.php'
        : '../../backend/teacher/create-lesson.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Error saving lesson');
        }
    });
});
</script>

<?php include '../../shared/templates/footer.php'; ?>

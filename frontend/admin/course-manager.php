<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Course Management';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get all courses
$sql = "SELECT c.*, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        ORDER BY c.created_at DESC";

$stmt = $db->query($sql);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>Course Management</h1>
        <p>Approve and manage all courses</p>
    </div>
    
    <div class="filters">
        <input type="text" id="searchCourses" placeholder="Search courses..." class="search-input">
        <select id="statusFilter" class="filter-select">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending Approval</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
        </select>
    </div>
    
    <div class="courses-list">
        <?php foreach ($courses as $course): ?>
            <div class="course-admin-card" data-status="<?= $course['status'] ?>">
                <div class="course-thumbnail">
                    <?php if ($course['thumbnail']): ?>
                        <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course thumbnail">
                    <?php else: ?>
                        <div class="placeholder-thumb">
                            <?= strtoupper(substr($course['title'], 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="course-details">
                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                    <p class="course-teacher">By <?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></p>
                    <p class="course-description"><?= htmlspecialchars(substr($course['description'], 0, 150)) ?>...</p>
                    
                    <div class="course-stats">
                        <span><?= $course['enrollment_count'] ?> students</span>
                        <span><?= $course['lesson_count'] ?> lessons</span>
                        <span>KSh <?= number_format($course['price']) ?></span>
                    </div>
                </div>
                
                <div class="course-status-section">
                    <span class="status-badge status-<?= $course['status'] ?>">
                        <?= ucfirst($course['status']) ?>
                    </span>
                    
                    <div class="course-actions">
                        <?php if ($course['status'] === 'pending'): ?>
                            <button class="btn btn-success btn-sm" onclick="approveCourse(<?= $course['id'] ?>)">
                                Approve
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="rejectCourse(<?= $course['id'] ?>)">
                                Reject
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-secondary btn-sm" onclick="viewCourse(<?= $course['id'] ?>)">
                            View
                        </button>
                        
                        <button class="btn btn-warning btn-sm" onclick="archiveCourse(<?= $course['id'] ?>)">
                            Archive
                        </button>
                        
                        <button class="btn btn-danger btn-sm" onclick="deleteCourse(<?= $course['id'] ?>)">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.courses-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.course-admin-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 150px 1fr auto;
    gap: 20px;
    align-items: start;
}

.course-thumbnail {
    width: 150px;
    height: 100px;
    border-radius: 6px;
    overflow: hidden;
}

.course-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.placeholder-thumb {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: bold;
}

.course-details h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.course-teacher {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.course-description {
    color: #555;
    margin-bottom: 15px;
}

.course-stats {
    display: flex;
    gap: 20px;
    font-size: 0.9rem;
    color: #666;
}

.course-status-section {
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: flex-end;
}

.course-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.course-actions button {
    white-space: nowrap;
}

@media (max-width: 768px) {
    .course-admin-card {
        grid-template-columns: 1fr;
    }
    
    .course-status-section {
        align-items: flex-start;
    }
}
</style>

<script>
document.getElementById('searchCourses').addEventListener('input', filterCourses);
document.getElementById('statusFilter').addEventListener('change', filterCourses);

function filterCourses() {
    const search = document.getElementById('searchCourses').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    
    const cards = document.querySelectorAll('.course-admin-card');
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const cardStatus = card.dataset.status;
        
        const matchesSearch = text.includes(search);
        const matchesStatus = !status || cardStatus === status;
        
        card.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

function approveCourse(courseId) {
    fetch('../../backend/admin/approve-course.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({course_id: courseId})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Course approved!');
            location.reload();
        }
    });
}

function rejectCourse(courseId) {
    const reason = prompt('Reason for rejection:');
    if (!reason) return;
    
    fetch('../../backend/admin/reject-course.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({course_id: courseId, reason: reason})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Course rejected');
            location.reload();
        }
    });
}

function viewCourse(courseId) {
    window.location.href = '../courses/view-course.php?id=' + courseId;
}

function archiveCourse(courseId) {
    if (!confirm('Archive this course?')) return;
    
    fetch('../../backend/admin/archive-course.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({course_id: courseId})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) location.reload();
    });
}

function deleteCourse(courseId) {
    if (!confirm('Delete this course permanently?')) return;
    
    fetch('../../backend/admin/delete-course.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({course_id: courseId})
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) location.reload();
    });
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

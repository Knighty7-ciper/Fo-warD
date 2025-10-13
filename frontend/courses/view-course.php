<?php
session_start();
require_once '../../backend/config/db.php';

$course_id = $_GET['id'] ?? 0;
$db = getDBConnection();

// Get course details
$sql = "SELECT c.*, u.first_name, u.last_name, u.email,
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = :course_id";

$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: browse.php');
    exit;
}

// Check if user is enrolled
$is_enrolled = false;
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT * FROM enrollments WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id'], ':course_id' => $course_id]);
    $is_enrolled = $stmt->fetch() !== false;
}

// Get course lessons
$sql = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_num ASC";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $course['title'];
include '../../shared/templates/header.php';
?>

<div class="container">
    <div class="course-header">
        <div class="course-header-content">
            <h1><?= htmlspecialchars($course['title']) ?></h1>
            <p class="course-subtitle"><?= htmlspecialchars($course['description']) ?></p>
            <div class="course-stats">
                <span><strong><?= $student_count ?></strong> students enrolled</span>
                <span><strong><?= $lesson_count ?></strong> lessons</span>
                <span><strong><?= $course['duration'] ?? '8' ?></strong> weeks</span>
            </div>
            <p class="course-instructor">
                Taught by <strong><?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></strong>
            </p>
        </div>
        <div class="course-sidebar">
            <div class="course-card-sidebar">
                <?php if ($course['thumbnail']): ?>
                    <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course thumbnail">
                <?php endif; ?>
                <div class="sidebar-content">
                    <?php if ($course['price'] > 0): ?>
                        <div class="price">KSh <?= number_format($course['price']) ?></div>
                    <?php else: ?>
                        <div class="price free">Free Course</div>
                    <?php endif; ?>
                    
                    <?php if ($is_enrolled): ?>
                        <a href="../student/course-player.php?id=<?= $course_id ?>" class="btn btn-success btn-block">
                            Continue Learning
                        </a>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <form action="../../backend/student/enroll-course.php" method="POST">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <button type="submit" class="btn btn-primary btn-block">Enroll Now</button>
                        </form>
                    <?php else: ?>
                        <a href="../login.php?redirect=courses/view-course.php?id=<?= $course_id ?>" class="btn btn-primary btn-block">
                            Login to Enroll
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="course-content-section">
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('overview')">Overview</button>
            <button class="tab-btn" onclick="showTab('curriculum')">Curriculum</button>
            <button class="tab-btn" onclick="showTab('instructor')">Instructor</button>
        </div>
        
        <div id="overview" class="tab-content active">
            <h2>About This Course</h2>
            <div class="course-description">
                <?= nl2br(htmlspecialchars($course['full_description'] ?? $course['description'])) ?>
            </div>
            
            <h3>What You'll Learn</h3>
            <ul class="learning-objectives">
                <li>Master the fundamentals of the subject</li>
                <li>Build practical projects</li>
                <li>Gain hands-on experience</li>
                <li>Earn a certificate upon completion</li>
            </ul>
        </div>
        
        <div id="curriculum" class="tab-content">
            <h2>Course Curriculum</h2>
            <div class="lessons-list">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <div class="lesson-item">
                        <div class="lesson-number"><?= $index + 1 ?></div>
                        <div class="lesson-info">
                            <h4><?= htmlspecialchars($lesson['title']) ?></h4>
                            <p><?= htmlspecialchars($lesson['description'] ?? '') ?></p>
                        </div>
                        <div class="lesson-duration">
                            <?= $lesson['duration'] ?? '30' ?> min
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="instructor" class="tab-content">
            <h2>About the Instructor</h2>
            <div class="instructor-profile">
                <div class="instructor-avatar">
                    <?= strtoupper(substr($course['first_name'], 0, 1) . substr($course['last_name'], 0, 1)) ?>
                </div>
                <div class="instructor-details">
                    <h3><?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></h3>
                    <p><?= htmlspecialchars($course['email']) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.course-header {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
    margin-bottom: 40px;
}

.course-header-content h1 {
    font-size: 2.5rem;
    margin-bottom: 15px;
}

.course-subtitle {
    font-size: 1.2rem;
    color: #666;
    margin-bottom: 20px;
}

.course-stats {
    display: flex;
    gap: 30px;
    margin-bottom: 20px;
    padding: 15px 0;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}

.course-card-sidebar {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    position: sticky;
    top: 20px;
}

.course-card-sidebar img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.sidebar-content {
    padding: 20px;
}

.price {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
}

.price.free {
    color: #28a745;
}

.btn-block {
    width: 100%;
    display: block;
    text-align: center;
}

.tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #eee;
    margin-bottom: 30px;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1rem;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.lessons-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.lesson-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.lesson-number {
    width: 40px;
    height: 40px;
    background: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.lesson-info {
    flex: 1;
}

.lesson-info h4 {
    margin: 0 0 5px 0;
}

.lesson-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.instructor-profile {
    display: flex;
    gap: 20px;
    align-items: center;
}

.instructor-avatar {
    width: 80px;
    height: 80px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
}

@media (max-width: 768px) {
    .course-header {
        grid-template-columns: 1fr;
    }
    
    .course-card-sidebar {
        position: static;
    }
}
</style>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

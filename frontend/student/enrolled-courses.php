<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'My Courses';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get enrolled courses with progress
$sql = "SELECT c.*, u.first_name, u.last_name,
        e.enrolled_at, e.progress,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
        (SELECT COUNT(*) FROM lesson_progress lp 
         JOIN lessons l ON lp.lesson_id = l.id 
         WHERE l.course_id = c.id AND lp.user_id = :user_id AND lp.completed = 1) as completed_lessons
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE e.user_id = :user_id
        ORDER BY e.enrolled_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>My Enrolled Courses</h1>
        <a href="../courses.php" class="btn btn-primary">Browse More Courses</a>
    </div>
    
    <?php if (empty($courses)): ?>
        <div class="empty-state">
            <h2>You haven't enrolled in any courses yet</h2>
            <p>Start learning today by browsing our course catalog</p>
            <a href="../courses.php" class="btn btn-primary">Browse Courses</a>
        </div>
    <?php else: ?>
        <div class="courses-grid">
            <?php foreach ($courses as $course): 
                $progress_percent = $course['total_lessons'] > 0 
                    ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) 
                    : 0;
            ?>
                <div class="enrolled-course-card">
                    <div class="course-thumbnail">
                        <?php if ($course['thumbnail']): ?>
                            <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course thumbnail">
                        <?php else: ?>
                            <div class="placeholder-thumbnail">
                                <?= strtoupper(substr($course['title'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="progress-overlay">
                            <div class="circular-progress" style="--progress: <?= $progress_percent ?>%">
                                <span><?= $progress_percent ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="course-details">
                        <h3><?= htmlspecialchars($course['title']) ?></h3>
                        <p class="instructor">By <?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></p>
                        
                        <div class="progress-info">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progress_percent ?>%"></div>
                            </div>
                            <p class="progress-text">
                                <?= $course['completed_lessons'] ?> of <?= $course['total_lessons'] ?> lessons completed
                            </p>
                        </div>
                        
                        <div class="course-actions">
                            <a href="course-player.php?id=<?= $course['id'] ?>" class="btn btn-primary btn-block">
                                <?= $progress_percent > 0 ? 'Continue Learning' : 'Start Course' ?>
                            </a>
                            <a href="certificates.php?course_id=<?= $course['id'] ?>" class="btn btn-secondary btn-sm">
                                View Certificate
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.empty-state h2 {
    margin-bottom: 15px;
    color: #333;
}

.empty-state p {
    color: #666;
    margin-bottom: 30px;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.enrolled-course-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.enrolled-course-card:hover {
    transform: translateY(-5px);
}

.course-thumbnail {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.course-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.placeholder-thumbnail {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    font-weight: bold;
}

.progress-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
}

.circular-progress {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: conic-gradient(#28a745 var(--progress), #e9ecef var(--progress));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.circular-progress::before {
    content: '';
    position: absolute;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
}

.circular-progress span {
    position: relative;
    z-index: 1;
    font-weight: bold;
    font-size: 0.9rem;
    color: #333;
}

.course-details {
    padding: 20px;
}

.course-details h3 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 1.25rem;
}

.instructor {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.progress-info {
    margin-bottom: 20px;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: #28a745;
    transition: width 0.3s;
}

.progress-text {
    font-size: 0.85rem;
    color: #666;
    margin: 0;
}

.course-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-block {
    width: 100%;
    text-align: center;
}
</style>

<?php include '../../shared/templates/footer.php'; ?>

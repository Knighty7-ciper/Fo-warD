<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$course_id = $_GET['id'] ?? 0;
$lesson_id = $_GET['lesson'] ?? null;

$db = getDBConnection();

// Verify enrollment
$sql = "SELECT * FROM enrollments WHERE user_id = :user_id AND course_id = :course_id";
$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id'], ':course_id' => $course_id]);
if (!$stmt->fetch()) {
    header('Location: ../courses/browse.php');
    exit;
}

// Get course info
$sql = "SELECT * FROM courses WHERE id = :course_id";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all lessons
$sql = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_num ASC";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current lesson
if (!$lesson_id && !empty($lessons)) {
    $lesson_id = $lessons[0]['id'];
}

$current_lesson = null;
foreach ($lessons as $lesson) {
    if ($lesson['id'] == $lesson_id) {
        $current_lesson = $lesson;
        break;
    }
}

$page_title = $course['title'];
include '../../shared/templates/header.php';
?>

<div class="course-player">
    <div class="player-sidebar">
        <div class="course-info">
            <h3><?= htmlspecialchars($course['title']) ?></h3>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 35%"></div>
            </div>
            <p class="progress-text">35% Complete</p>
        </div>
        
        <div class="lessons-sidebar">
            <h4>Course Content</h4>
            <?php foreach ($lessons as $index => $lesson): ?>
                <a href="?id=<?= $course_id ?>&lesson=<?= $lesson['id'] ?>" 
                   class="lesson-link <?= $lesson['id'] == $lesson_id ? 'active' : '' ?>">
                    <span class="lesson-num"><?= $index + 1 ?></span>
                    <span class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></span>
                    <span class="lesson-status">✓</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="player-content">
        <?php if ($current_lesson): ?>
            <div class="lesson-header">
                <h1><?= htmlspecialchars($current_lesson['title']) ?></h1>
            </div>
            
            <div class="lesson-video">
                <?php if ($current_lesson['video_url']): ?>
                    <video controls width="100%">
                        <source src="<?= htmlspecialchars($current_lesson['video_url']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <div class="no-video">
                        <p>No video available for this lesson</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="lesson-content">
                <div class="tabs">
                    <button class="tab-btn active" onclick="showTab('description')">Description</button>
                    <button class="tab-btn" onclick="showTab('resources')">Resources</button>
                    <button class="tab-btn" onclick="showTab('notes')">My Notes</button>
                </div>
                
                <div id="description" class="tab-content active">
                    <?= nl2br(htmlspecialchars($current_lesson['content'] ?? 'No description available.')) ?>
                </div>
                
                <div id="resources" class="tab-content">
                    <h3>Downloadable Resources</h3>
                    <ul class="resources-list">
                        <li><a href="#">Lesson Slides (PDF)</a></li>
                        <li><a href="#">Source Code (ZIP)</a></li>
                        <li><a href="#">Additional Reading</a></li>
                    </ul>
                </div>
                
                <div id="notes" class="tab-content">
                    <textarea id="notesArea" placeholder="Take notes here..." rows="10"></textarea>
                    <button class="btn btn-primary" onclick="saveNotes()">Save Notes</button>
                </div>
            </div>
            
            <div class="lesson-navigation">
                <button class="btn btn-secondary" onclick="previousLesson()">← Previous Lesson</button>
                <button class="btn btn-primary" onclick="markComplete()">Mark as Complete</button>
                <button class="btn btn-secondary" onclick="nextLesson()">Next Lesson →</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.course-player {
    display: grid;
    grid-template-columns: 350px 1fr;
    height: calc(100vh - 60px);
    overflow: hidden;
}

.player-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    overflow-y: auto;
}

.course-info {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.course-info h3 {
    margin: 0 0 15px 0;
    font-size: 1.1rem;
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
    font-size: 0.9rem;
    color: #666;
    margin: 0;
}

.lessons-sidebar h4 {
    padding: 15px 20px;
    margin: 0;
    background: #e9ecef;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lesson-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #dee2e6;
    transition: background 0.2s;
}

.lesson-link:hover {
    background: #e9ecef;
}

.lesson-link.active {
    background: #007bff;
    color: white;
}

.lesson-num {
    width: 30px;
    height: 30px;
    background: #dee2e6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: bold;
}

.lesson-link.active .lesson-num {
    background: white;
    color: #007bff;
}

.lesson-title {
    flex: 1;
    font-size: 0.95rem;
}

.lesson-status {
    color: #28a745;
}

.player-content {
    overflow-y: auto;
    padding: 30px;
}

.lesson-header h1 {
    margin: 0 0 20px 0;
}

.lesson-video {
    margin-bottom: 30px;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}

.no-video {
    padding: 100px 20px;
    text-align: center;
    color: #999;
}

.lesson-content {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
}

.tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #eee;
    margin-bottom: 20px;
}

.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    color: #666;
    border-bottom: 3px solid transparent;
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

#notesArea {
    width: 100%;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    margin-bottom: 15px;
}

.lesson-navigation {
    display: flex;
    justify-content: space-between;
    gap: 15px;
}

.resources-list {
    list-style: none;
    padding: 0;
}

.resources-list li {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.resources-list a {
    color: #007bff;
    text-decoration: none;
}

@media (max-width: 768px) {
    .course-player {
        grid-template-columns: 1fr;
    }
    
    .player-sidebar {
        display: none;
    }
}
</style>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

function markComplete() {
    // Mark lesson as complete
    alert('Lesson marked as complete!');
}

function nextLesson() {
    // Navigate to next lesson
    const currentUrl = new URL(window.location.href);
    const lessonId = parseInt(currentUrl.searchParams.get('lesson'));
    // Logic to get next lesson ID
}

function previousLesson() {
    // Navigate to previous lesson
}

function saveNotes() {
    const notes = document.getElementById('notesArea').value;
    // Save notes to database
    alert('Notes saved!');
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

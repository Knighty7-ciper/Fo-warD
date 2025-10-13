<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Peer Collaboration';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get discussion forums
$sql = "SELECT f.*, c.title as course_title,
        (SELECT COUNT(*) FROM forum_posts WHERE forum_id = f.id) as post_count
        FROM forums f
        JOIN courses c ON f.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.user_id = :user_id
        ORDER BY f.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>Peer Collaboration</h1>
        <p>Connect and learn with fellow students</p>
    </div>
    
    <div class="collaboration-tabs">
        <button class="tab-btn active" onclick="showTab('forums')">Discussion Forums</button>
        <button class="tab-btn" onclick="showTab('study-groups')">Study Groups</button>
        <button class="tab-btn" onclick="showTab('messages')">Messages</button>
    </div>
    
    <div id="forums" class="tab-content active">
        <div class="forums-list">
            <?php foreach ($forums as $forum): ?>
                <div class="forum-card">
                    <div class="forum-info">
                        <h3><?= htmlspecialchars($forum['title']) ?></h3>
                        <p class="forum-course"><?= htmlspecialchars($forum['course_title']) ?></p>
                        <p class="forum-description"><?= htmlspecialchars($forum['description']) ?></p>
                    </div>
                    <div class="forum-stats">
                        <div class="stat">
                            <strong><?= $forum['post_count'] ?></strong>
                            <span>Posts</span>
                        </div>
                        <a href="forum.php?id=<?= $forum['id'] ?>" class="btn btn-primary">View Forum</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div id="study-groups" class="tab-content">
        <div class="study-groups-section">
            <h2>Join a Study Group</h2>
            <p>Coming soon - Connect with peers studying the same courses</p>
        </div>
    </div>
    
    <div id="messages" class="tab-content">
        <div class="messages-section">
            <h2>Direct Messages</h2>
            <p>Coming soon - Chat directly with other students</p>
        </div>
    </div>
</div>

<style>
.collaboration-tabs {
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

.forums-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.forum-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
}

.forum-info {
    flex: 1;
}

.forum-info h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.forum-course {
    color: #007bff;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.forum-description {
    color: #666;
    margin: 0;
}

.forum-stats {
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat {
    text-align: center;
}

.stat strong {
    display: block;
    font-size: 1.5rem;
    color: #333;
}

.stat span {
    font-size: 0.85rem;
    color: #666;
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
</script>

<?php include '../../shared/templates/footer.php'; ?>

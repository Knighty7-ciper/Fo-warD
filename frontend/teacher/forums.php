<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$user = requireAuth();

// Get user's courses
$stmt = $pdo->prepare("
    SELECT c.id, c.title 
    FROM courses c 
    WHERE c.teacher_id = ? OR c.id IN (SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active')
    ORDER BY c.title
");
$stmt->execute([$user['id'], $user['id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get forums for the first course by default
$selectedCourse = $courses[0]['id'] ?? null;
$forums = [];

if ($selectedCourse) {
    $sql = "
        SELECT 
            f.*,
            COUNT(DISTINCT t.id) as topic_count,
            COUNT(DISTINCT p.id) as post_count,
            MAX(p.created_at) as last_activity
        FROM forums f
        LEFT JOIN topics t ON f.id = t.forum_id
        LEFT JOIN posts p ON t.id = p.topic_id
        WHERE f.course_id = ?
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedCourse]);
    $forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Forums - FowarD LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-gray: #ecf0f1;
            --border-color: #ddd;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .forum-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .forum-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .forum-title {
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .course-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: var(--light-gray);
            border-radius: 8px;
        }

        .course-selector select {
            min-width: 200px;
        }

        .forum-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .forum-tabs {
            background: var(--primary-color);
            color: white;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .forum-tab {
            padding: 15px 25px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .forum-tab:hover,
        .forum-tab.active {
            background: rgba(255,255,255,0.1);
        }

        .forum-content {
            padding: 30px;
        }

        .forum-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.2s;
        }

        .forum-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .forum-item-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .forum-item-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .forum-item-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .forum-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }

        .forum-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .topic-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .topic-item:hover {
            background-color: #f8f9fa;
            border-color: var(--primary-color);
        }

        .topic-item.pinned {
            border-color: var(--warning-color);
            background: #fffbf0;
        }

        .topic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .topic-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .topic-meta {
            font-size: 12px;
            color: #666;
        }

        .topic-preview {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .topic-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #666;
        }

        .topic-stat {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .pin-badge {
            background: var(--warning-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 10px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .sidebar-card h5 {
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-primary-custom {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .modal-content {
            border-radius: 15px;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            font-size: 18px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .forum-layout {
                grid-template-columns: 1fr;
            }
            
            .course-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="forum-container">
        <!-- Header -->
        <div class="forum-header">
            <h1 class="forum-title">
                <i class="bi bi-chat-square-text"></i> Discussion Forums
            </h1>
            <p class="text-muted">Engage with your course community through discussions and collaborative learning</p>
            
            <!-- Course Selector -->
            <div class="course-selector">
                <strong><i class="bi bi-book"></i> Select Course:</strong>
                <select class="form-select" id="courseSelect" onchange="loadForums()">
                    <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>" <?= $course['id'] == $selectedCourse ? 'selected' : '' ?>>
                        <?= htmlspecialchars($course['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="forum-layout">
            <!-- Main Content -->
            <div class="main-content">
                <div class="forum-tabs">
                    <button class="forum-tab active" onclick="switchTab('forums')" id="forumsTab">
                        <i class="bi bi-collection"></i> Forums
                    </button>
                    <button class="forum-tab" onclick="switchTab('topics')" id="topicsTab">
                        <i class="bi bi-chat-dots"></i> Topics
                    </button>
                </div>

                <div class="forum-content" id="forumContent">
                    <!-- Forums Tab -->
                    <div id="forumsTabContent">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>Course Forums</h4>
                            <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
                            <button class="btn-primary-custom" onclick="showCreateForumModal()">
                                <i class="bi bi-plus"></i> Create Forum
                            </button>
                            <?php endif; ?>
                        </div>

                        <div id="forumsList">
                            <?php if (empty($forums)): ?>
                            <div class="empty-state">
                                <i class="bi bi-chat-square-text"></i>
                                <h4>No forums yet</h4>
                                <p>Be the first to create a forum for this course</p>
                                <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
                                <button class="btn-primary-custom" onclick="showCreateForumModal()">
                                    <i class="bi bi-plus"></i> Create First Forum
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="forums-grid">
                                <?php foreach ($forums as $forum): ?>
                                <div class="forum-item" onclick="selectForum(<?= $forum['id'] ?>, '<?= htmlspecialchars($forum['title']) ?>')">
                                    <div class="forum-item-header">
                                        <div>
                                            <h5 class="forum-item-title"><?= htmlspecialchars($forum['title']) ?></h5>
                                            <p class="forum-item-description"><?= htmlspecialchars($forum['description'] ?: 'No description') ?></p>
                                        </div>
                                    </div>
                                    <div class="forum-stats">
                                        <div class="forum-stat">
                                            <i class="bi bi-chat-dots"></i>
                                            <?= $forum['topic_count'] ?> topics
                                        </div>
                                        <div class="forum-stat">
                                            <i class="bi bi-chat-square"></i>
                                            <?= $forum['post_count'] ?> posts
                                        </div>
                                        <div class="forum-stat">
                                            <i class="bi bi-clock"></i>
                                            <?= $forum['last_activity'] ? date('M j', strtotime($forum['last_activity'])) : 'No activity' ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Topics Tab -->
                    <div id="topicsTabContent" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>Recent Topics</h4>
                            <button class="btn-primary-custom" onclick="showCreateTopicModal()">
                                <i class="bi bi-plus"></i> New Topic
                            </button>
                        </div>

                        <div class="search-box mb-4">
                            <input type="text" id="topicSearch" placeholder="Search topics..." onkeyup="searchTopics()">
                            <i class="bi bi-search"></i>
                        </div>

                        <div id="topicsList">
                            <div class="empty-state">
                                <i class="bi bi-chat-dots"></i>
                                <h4>No topics yet</h4>
                                <p>Start a discussion by creating the first topic</p>
                                <button class="btn-primary-custom" onclick="showCreateTopicModal()">
                                    <i class="bi bi-plus"></i> Create First Topic
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-card">
                    <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="switchTab('forums')">
                            <i class="bi bi-collection"></i> Browse Forums
                        </button>
                        <button class="btn btn-outline-primary" onclick="switchTab('topics')">
                            <i class="bi bi-chat-dots"></i> Recent Topics
                        </button>
                        <button class="btn btn-outline-primary" onclick="searchForums()">
                            <i class="bi bi-search"></i> Search Discussions
                        </button>
                    </div>
                </div>

                <div class="sidebar-card">
                    <h5><i class="bi bi-bar-chart"></i> Forum Stats</h5>
                    <div id="forumStats">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Forums:</span>
                            <strong><?= count($forums) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Active Topics:</span>
                            <strong>0</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Posts:</span>
                            <strong>0</strong>
                        </div>
                    </div>
                </div>

                <div class="sidebar-card">
                    <h5><i class="bi bi-info-circle"></i> Help</h5>
                    <p class="small text-muted">
                        Use forums to discuss course topics, ask questions, and collaborate with fellow students and teachers.
                    </p>
                    <ul class="small text-muted">
                        <li>Click on a forum to view topics</li>
                        <li>Create new topics to start discussions</li>
                        <li>Reply to posts to engage</li>
                        <li>Use search to find specific discussions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Forum Modal -->
    <div class="modal fade" id="createForumModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Forum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createForumForm">
                        <div class="mb-3">
                            <label class="form-label">Forum Title *</label>
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="Enter forum title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Describe what this forum is for"></textarea>
                        </div>
                        <input type="hidden" name="course_id" id="forumCourseId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createForum()">Create Forum</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Topic Modal -->
    <div class="modal fade" id="createTopicModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Topic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createTopicForm">
                        <div class="mb-3">
                            <label class="form-label">Select Forum *</label>
                            <select class="form-select" name="forum_id" id="topicForumSelect" required>
                                <option value="">Choose a forum...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Topic Title *</label>
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="Enter topic title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="6" required 
                                      placeholder="Write your topic content..."></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned">
                            <label class="form-check-label" for="isPinned">
                                Pin this topic to top
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createTopic()">Create Topic</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentForum = null;
        let currentTab = 'forums';

        function switchTab(tab) {
            // Update active tab
            document.querySelectorAll('.forum-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            // Show/hide content
            document.querySelectorAll('[id$="TabContent"]').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(tab + 'TabContent').style.display = 'block';
            
            currentTab = tab;
            
            // Load content based on tab
            if (tab === 'topics' && currentForum) {
                loadTopics();
            }
        }

        function loadForums() {
            const courseId = document.getElementById('courseSelect').value;
            const content = document.getElementById('forumsList');
            
            content.innerHTML = '<div class="text-center"><i class="bi bi-hourglass-split"></i> Loading forums...</div>';
            
            fetch(`api/forums.php?action=forums&course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.forums) {
                        displayForums(data.forums);
                    } else {
                        content.innerHTML = `
                            <div class="empty-state">
                                <i class="bi bi-chat-square-text"></i>
                                <h4>No forums yet</h4>
                                <p>Be the first to create a forum for this course</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading forums:', error);
                    content.innerHTML = '<div class="alert alert-danger">Error loading forums. Please try again.</div>';
                });
        }

        function displayForums(forums) {
            const content = document.getElementById('forumsList');
            
            if (forums.length === 0) {
                content.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <h4>No forums yet</h4>
                        <p>Be the first to create a forum for this course</p>
                    </div>
                `;
                return;
            }
            
            content.innerHTML = `
                <div class="forums-grid">
                    ${forums.map(forum => `
                        <div class="forum-item" onclick="selectForum(${forum.id}, '${escapeHtml(forum.title)}')">
                            <div class="forum-item-header">
                                <div>
                                    <h5 class="forum-item-title">${escapeHtml(forum.title)}</h5>
                                    <p class="forum-item-description">${escapeHtml(forum.description || 'No description')}</p>
                                </div>
                            </div>
                            <div class="forum-stats">
                                <div class="forum-stat">
                                    <i class="bi bi-chat-dots"></i>
                                    ${forum.topic_count} topics
                                </div>
                                <div class="forum-stat">
                                    <i class="bi bi-chat-square"></i>
                                    ${forum.post_count} posts
                                </div>
                                <div class="forum-stat">
                                    <i class="bi bi-clock"></i>
                                    ${forum.last_activity ? new Date(forum.last_activity).toLocaleDateString() : 'No activity'}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function selectForum(forumId, forumTitle) {
            currentForum = { id: forumId, title: forumTitle };
            loadTopics();
        }

        function loadTopics() {
            if (!currentForum) return;
            
            const content = document.getElementById('topicsList');
            content.innerHTML = '<div class="text-center"><i class="bi bi-hourglass-split"></i> Loading topics...</div>';
            
            fetch(`api/forums.php?action=topics&forum_id=${currentForum.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.topics) {
                        displayTopics(data.topics);
                    } else {
                        content.innerHTML = `
                            <div class="empty-state">
                                <i class="bi bi-chat-dots"></i>
                                <h4>No topics yet</h4>
                                <p>Be the first to create a topic in ${currentForum.title}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading topics:', error);
                    content.innerHTML = '<div class="alert alert-danger">Error loading topics. Please try again.</div>';
                });
        }

        function displayTopics(topics) {
            const content = document.getElementById('topicsList');
            
            if (topics.length === 0) {
                content.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-chat-dots"></i>
                        <h4>No topics yet</h4>
                        <p>Be the first to create a topic in ${currentForum.title}</p>
                    </div>
                `;
                return;
            }
            
            content.innerHTML = topics.map(topic => `
                <div class="topic-item ${topic.is_pinned ? 'pinned' : ''}" onclick="viewTopic(${topic.id})">
                    <div class="topic-header">
                        <div>
                            <h5 class="topic-title">${escapeHtml(topic.title)}
                                ${topic.is_pinned ? '<span class="pin-badge">PINNED</span>' : ''}
                            </h5>
                            <div class="topic-meta">
                                by ${escapeHtml(topic.author_name)} • ${new Date(topic.created_at).toLocaleString()}
                            </div>
                        </div>
                    </div>
                    <div class="topic-preview">${escapeHtml(topic.content)}</div>
                    <div class="topic-stats">
                        <div class="topic-stat">
                            <i class="bi bi-chat"></i>
                            ${topic.reply_count} replies
                        </div>
                        <div class="topic-stat">
                            <i class="bi bi-heart"></i>
                            ${topic.like_count} likes
                        </div>
                        <div class="topic-stat">
                            <i class="bi bi-clock"></i>
                            ${topic.last_reply ? 'Last reply ' + new Date(topic.last_reply).toLocaleDateString() : 'No replies'}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function showCreateForumModal() {
            document.getElementById('forumCourseId').value = document.getElementById('courseSelect').value;
            new bootstrap.Modal(document.getElementById('createForumModal')).show();
        }

        function showCreateTopicModal() {
            // Populate forum dropdown
            const courseId = document.getElementById('courseSelect').value;
            const select = document.getElementById('topicForumSelect');
            
            fetch(`api/forums.php?action=forums&course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.forums) {
                        select.innerHTML = '<option value="">Choose a forum...</option>' + 
                            data.forums.map(forum => `<option value="${forum.id}">${escapeHtml(forum.title)}</option>`).join('');
                    }
                })
                .catch(error => {
                    console.error('Error loading forums for topic:', error);
                });
            
            new bootstrap.Modal(document.getElementById('createTopicModal')).show();
        }

        function createForum() {
            const form = document.getElementById('createForumForm');
            const formData = new FormData(form);
            
            fetch('api/forums.php?action=create_forum', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createForumModal')).hide();
                    loadForums();
                    showToast('Forum created successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Error creating forum:', error);
                showToast('Error creating forum. Please try again.', 'error');
            });
        }

        function createTopic() {
            const form = document.getElementById('createTopicForm');
            const formData = new FormData(form);
            
            fetch('api/forums.php?action=create_topic', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createTopicModal')).hide();
                    if (currentTab === 'topics') {
                        loadTopics();
                    }
                    showToast('Topic created successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Error creating topic:', error);
                showToast('Error creating topic. Please try again.', 'error');
            });
        }

        function searchTopics() {
            const searchTerm = document.getElementById('topicSearch').value.toLowerCase();
            const topics = document.querySelectorAll('.topic-item');
            
            topics.forEach(topic => {
                const title = topic.querySelector('.topic-title').textContent.toLowerCase();
                const content = topic.querySelector('.topic-preview').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || content.includes(searchTerm)) {
                    topic.style.display = 'block';
                } else {
                    topic.style.display = 'none';
                }
            });
        }

        function searchForums() {
            const courseId = document.getElementById('courseSelect').value;
            const query = prompt('Enter search terms:');
            
            if (query && query.length >= 3) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}&course_id=${courseId}&type=forums`;
            } else if (query) {
                showToast('Search query must be at least 3 characters', 'warning');
            }
        }

        function viewTopic(topicId) {
            window.location.href = `topic-view.php?id=${topicId}`;
        }

        function showToast(message, type = 'info') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (currentTab === 'topics' && !currentForum) {
                // Auto-select first forum for topics tab
                const firstForum = document.querySelector('.forum-item');
                if (firstForum) {
                    const forumId = firstForum.getAttribute('onclick').match(/\d+/)[0];
                    const forumTitle = firstForum.querySelector('.forum-item-title').textContent;
                    selectForum(forumId, forumTitle);
                }
            }
        });
    </script>
</body>
</html>
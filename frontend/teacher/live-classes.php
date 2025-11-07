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

// Get recent live classes
$sql = "
    SELECT 
        lc.*,
        c.title as course_title,
        u.name as instructor_name,
        DATEDIFF(lc.start_time, NOW()) as days_until,
        TIMESTAMPDIFF(MINUTE, NOW(), lc.start_time) as minutes_until,
        (SELECT COUNT(*) FROM class_participants cp WHERE cp.class_id = lc.id AND cp.status = 'attended') as actual_attendees
    FROM live_classes lc
    JOIN courses c ON lc.course_id = c.id
    JOIN users u ON lc.instructor_id = u.id
";

$whereConditions = [];
$params = [];

if ($user['role'] === 'student') {
    $sql .= " JOIN enrollments e ON c.id = e.course_id";
    $whereConditions[] = "e.student_id = ? AND e.status = 'active'";
    $params[] = $user['id'];
} elseif ($user['role'] === 'teacher') {
    $whereConditions[] = "lc.instructor_id = ?";
    $params[] = $user['id'];
}

if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " ORDER BY lc.start_time DESC LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recentClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes - FowarD LMS</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .action-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .action-section h3 {
            color: var(--dark-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary-custom {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-success-custom {
            background: var(--secondary-color);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-success-custom:hover {
            background: #27ae60;
            color: white;
        }

        .btn-warning-custom {
            background: var(--warning-color);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-warning-custom:hover {
            background: #e67e22;
            color: white;
        }

        .classes-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-nav {
            display: flex;
            border-bottom: 2px solid var(--light-gray);
            margin-bottom: 25px;
        }

        .tab-nav button {
            background: none;
            border: none;
            padding: 15px 25px;
            font-size: 1.1rem;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .tab-nav button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .class-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.2s;
        }

        .class-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .class-card.live {
            border-left: 5px solid var(--secondary-color);
            background: linear-gradient(135deg, #f8fff8, #ffffff);
        }

        .class-card.upcoming {
            border-left: 5px solid var(--warning-color);
            background: linear-gradient(135deg, #fffbf0, #ffffff);
        }

        .class-card.completed {
            border-left: 5px solid #95a5a6;
            opacity: 0.8;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .class-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .class-course {
            color: #666;
            font-size: 0.9rem;
        }

        .class-time {
            text-align: right;
            color: #666;
            font-size: 0.9rem;
        }

        .class-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .class-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }

        .class-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #666;
        }

        .class-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .join-btn {
            background: var(--secondary-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .join-btn:hover {
            background: #27ae60;
            color: white;
            text-decoration: none;
        }

        .join-btn.disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .live-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--secondary-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .upcoming-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--warning-color);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .course-selector {
            margin-bottom: 20px;
        }

        .course-selector select {
            min-width: 250px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
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

        .schedule-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .time-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .timezone-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .class-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .class-footer {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .class-meta {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-camera-video"></i> Live Classes</h1>
            <p>Join live interactive sessions with your instructors and fellow students</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon text-primary">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-number" id="totalClasses">0</div>
                <div class="stat-label">Total Classes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-success">
                    <i class="bi bi-play-circle"></i>
                </div>
                <div class="stat-number" id="liveClasses">0</div>
                <div class="stat-label">Live Now</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-warning">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-number" id="upcomingClasses">0</div>
                <div class="stat-label">Upcoming</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-info">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-number" id="totalAttendees">0</div>
                <div class="stat-label">Total Attendees</div>
            </div>
        </div>

        <!-- Course Filter -->
        <div class="course-selector">
            <select class="form-select" id="courseFilter" onchange="filterClasses()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Action Section (for teachers) -->
        <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
        <div class="action-section">
            <h3><i class="bi bi-plus-circle"></i> Quick Actions</h3>
            <div class="d-flex gap-3 flex-wrap">
                <button class="btn-primary-custom" onclick="showScheduleModal()">
                    <i class="bi bi-calendar-plus"></i> Schedule New Class
                </button>
                <button class="btn-success-custom" onclick="viewAllClasses()">
                    <i class="bi bi-list"></i> View All Classes
                </button>
                <button class="btn-warning-custom" onclick="viewClassAnalytics()">
                    <i class="bi bi-bar-chart"></i> Analytics
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Classes Section -->
        <div class="classes-section">
            <div class="tab-nav">
                <button class="active" onclick="switchTab('upcoming')" id="upcomingTab">
                    <i class="bi bi-calendar-event"></i> Upcoming
                </button>
                <button onclick="switchTab('live')" id="liveTab">
                    <i class="bi bi-broadcast"></i> Live Now
                </button>
                <button onclick="switchTab('completed')" id="completedTab">
                    <i class="bi bi-check-circle"></i> Completed
                </button>
            </div>

            <div id="upcomingContent">
                <div id="upcomingClassesList">
                    <?php if (empty(array_filter($recentClasses, function($c) { return $c['status'] === 'scheduled' && $c['start_time'] > date('Y-m-d H:i:s'); }))): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <h4>No upcoming classes</h4>
                        <p>Schedule a new class to get started</p>
                        <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
                        <button class="btn-primary-custom" onclick="showScheduleModal()">
                            <i class="bi bi-plus"></i> Schedule First Class
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div id="upcomingClassesContainer"></div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="liveContent" style="display: none;">
                <div id="liveClassesList">
                    <div class="empty-state">
                        <i class="bi bi-broadcast"></i>
                        <h4>No live classes</h4>
                        <p>Check back later for live sessions</p>
                    </div>
                </div>
            </div>

            <div id="completedContent" style="display: none;">
                <div id="completedClassesList">
                    <?php if (empty(array_filter($recentClasses, function($c) { return $c['status'] === 'completed'; }))): ?>
                    <div class="empty-state">
                        <i class="bi bi-archive"></i>
                        <h4>No completed classes</h4>
                        <p>Your class history will appear here</p>
                    </div>
                    <?php else: ?>
                    <div id="completedClassesContainer"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Class Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Live Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course *</label>
                                <select class="form-select" name="course_id" required>
                                    <option value="">Select course...</option>
                                    <?php 
                                    $teacherCourses = array_filter($courses, function($c) use ($user) {
                                        return true; // In a real implementation, filter by teacher courses only
                                    });
                                    foreach ($teacherCourses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration" value="60" min="15" max="240" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Class Title *</label>
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="Enter class title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Describe what this class will cover"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="date" required 
                                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time *</label>
                                <input type="time" class="form-control" name="time" required value="14:00">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Participants</label>
                                <input type="number" class="form-control" name="max_participants" value="50" min="1" max="200">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_recorded" id="isRecorded" checked>
                                    <label class="form-check-label" for="isRecorded">
                                        Record this class
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="timezone-info">
                            <i class="bi bi-info-circle"></i> 
                            All times are in your local timezone: <strong><?= date_default_timezone_get() ?></strong>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="scheduleClass()">Schedule Class</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = 'upcoming';
        let allClasses = <?= json_encode($recentClasses) ?>;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadClasses();
            updateStats();
            refreshClasses();
            
            // Refresh classes every 30 seconds
            setInterval(refreshClasses, 30000);
        });

        function switchTab(tab) {
            // Update active tab
            document.querySelectorAll('.tab-nav button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            // Show/hide content
            document.querySelectorAll('[id$="Content"]').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(tab + 'Content').style.display = 'block';
            
            currentTab = tab;
            loadClasses();
        }

        function loadClasses() {
            const courseId = document.getElementById('courseFilter').value;
            const filteredClasses = filterClassesByCourse(allClasses, courseId);
            
            const upcoming = filteredClasses.filter(c => c.status === 'scheduled' && c.start_time > new Date().toISOString());
            const live = filteredClasses.filter(c => c.status === 'live');
            const completed = filteredClasses.filter(c => c.status === 'completed' || (c.status === 'scheduled' && c.start_time <= new Date().toISOString()));
            
            displayClasses('upcoming', upcoming);
            displayClasses('live', live);
            displayClasses('completed', completed);
        }

        function displayClasses(type, classes) {
            const container = document.getElementById(type + 'ClassesContainer') || document.getElementById(type + 'ClassesList');
            
            if (classes.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-${type === 'upcoming' ? 'calendar-x' : type === 'live' ? 'broadcast' : 'archive'}"></i>
                        <h4>No ${type} classes</h4>
                        <p>${type === 'upcoming' ? 'Your upcoming classes will appear here' : 
                                 type === 'live' ? 'No classes are currently live' : 
                                 'Your completed classes will appear here'}</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = classes.map(cls => createClassCard(cls, type)).join('');
        }

        function createClassCard(cls, type) {
            const startTime = new Date(cls.start_time);
            const timeString = startTime.toLocaleString();
            const canJoin = canUserJoin(cls);
            const joinButton = canJoin ? 
                `<a href="#" class="join-btn" onclick="joinClass(${cls.id})">
                    <i class="bi bi-camera-video"></i> Join Now
                 </a>` :
                `<a href="#" class="join-btn disabled">
                    <i class="bi bi-clock"></i> Not Available
                 </a>`;
            
            const statusIndicator = type === 'live' ? 
                '<div class="live-indicator">• LIVE</div>' :
                type === 'upcoming' ? 
                `<div class="upcoming-badge">
                    <i class="bi bi-clock"></i>
                    ${getTimeUntil(cls.start_time)}
                 </div>` : '';
            
            const actionButtons = `<?= $user['role'] === 'teacher' || $user['role'] === 'admin' ? `
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="editClass(${cls.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteClass(${cls.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            ` : '' ?>`;
            
            return `
                <div class="class-card ${type}">
                    <div class="class-header">
                        <div>
                            <h4 class="class-title">${escapeHtml(cls.title)}</h4>
                            <p class="class-course">${escapeHtml(cls.course_title)} • ${escapeHtml(cls.instructor_name)}</p>
                        </div>
                        <div class="class-time">
                            <div class="time-display">${timeString}</div>
                            <div class="timezone-info">${startTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                            ${statusIndicator}
                        </div>
                    </div>
                    
                    ${cls.description ? `<div class="class-description">${escapeHtml(cls.description)}</div>` : ''}
                    
                    <div class="class-footer">
                        <div class="class-meta">
                            <div class="class-meta-item">
                                <i class="bi bi-clock"></i>
                                <span>${cls.duration || 60} min</span>
                            </div>
                            <div class="class-meta-item">
                                <i class="bi bi-people"></i>
                                <span>${cls.actual_attendees || 0} attended</span>
                            </div>
                            <div class="class-meta-item">
                                <i class="bi bi-record"></i>
                                <span>${cls.is_recorded ? 'Recorded' : 'Live Only'}</span>
                            </div>
                        </div>
                        <div>
                            ${joinButton}
                        </div>
                    </div>
                </div>
            `;
        }

        function canUserJoin(cls) {
            const now = new Date();
            const startTime = new Date(cls.start_time);
            const joinTime = new Date(startTime.getTime() - 15 * 60 * 1000); // 15 minutes before
            
            return now >= joinTime && (cls.status === 'live' || now <= startTime);
        }

        function getTimeUntil(dateString) {
            const now = new Date();
            const target = new Date(dateString);
            const diff = target - now;
            
            if (diff < 0) return 'Started';
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            if (days > 0) return `${days}d ${hours}h`;
            if (hours > 0) return `${hours}h ${minutes}m`;
            return `${minutes}m`;
        }

        function updateStats() {
            const totalClasses = allClasses.length;
            const liveClasses = allClasses.filter(c => c.status === 'live').length;
            const upcomingClasses = allClasses.filter(c => c.status === 'scheduled' && new Date(c.start_time) > new Date()).length;
            const totalAttendees = allClasses.reduce((sum, c) => sum + (c.actual_attendees || 0), 0);
            
            document.getElementById('totalClasses').textContent = totalClasses;
            document.getElementById('liveClasses').textContent = liveClasses;
            document.getElementById('upcomingClasses').textContent = upcomingClasses;
            document.getElementById('totalAttendees').textContent = totalAttendees;
        }

        function filterClasses() {
            loadClasses();
        }

        function filterClassesByCourse(classes, courseId) {
            if (!courseId) return classes;
            return classes.filter(cls => cls.course_id == courseId);
        }

        function refreshClasses() {
            // In a real implementation, this would make an API call to refresh class data
            // For now, we'll just update the stats
            updateStats();
        }

        function showScheduleModal() {
            new bootstrap.Modal(document.getElementById('scheduleModal')).show();
        }

        function scheduleClass() {
            const form = document.getElementById('scheduleForm');
            const formData = new FormData(form);
            
            // Combine date and time
            const date = formData.get('date');
            const time = formData.get('time');
            const startTime = `${date}T${time}:00`;
            formData.set('start_time', startTime);
            
            fetch('api/live-class.php?action=schedule', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                    showToast('Class scheduled successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                console.error('Error scheduling class:', error);
                showToast('Error scheduling class. Please try again.', 'error');
            });
        }

        function joinClass(classId) {
            fetch(`api/live-class.php?action=join&class_id=${classId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // In a real implementation, this would redirect to the video conference
                        window.open(data.class_info.meeting_url || '#', '_blank');
                        showToast('Joining class...', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error joining class:', error);
                    showToast('Error joining class. Please try again.', 'error');
                });
        }

        function editClass(classId) {
            showToast('Edit functionality coming soon!', 'info');
        }

        function deleteClass(classId) {
            if (confirm('Are you sure you want to cancel this class?')) {
                fetch(`api/live-class.php?action=cancel_class&id=${classId}`, { method: 'DELETE' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Class cancelled successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        }
                    })
                    .catch(error => {
                        console.error('Error cancelling class:', error);
                        showToast('Error cancelling class. Please try again.', 'error');
                    });
            }
        }

        function viewAllClasses() {
            window.location.href = 'classes-list.php';
        }

        function viewClassAnalytics() {
            window.location.href = 'analytics.php?type=live-classes';
        }

        function showToast(message, type = 'info') {
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
    </script>
</body>
</html>
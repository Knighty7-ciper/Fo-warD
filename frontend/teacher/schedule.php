<?php
session_start();
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Schedule Live Classes';
include '../../shared/templates/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Schedule Live Classes</h1>
        <p>Create and manage your live class sessions</p>
    </div>
    
    <div class="schedule-container">
         Create New Class Form 
        <div class="card">
            <div class="card-header">
                <h2>Schedule New Live Class</h2>
            </div>
            <div class="card-body">
                <form id="scheduleForm" class="form">
                    <div class="form-group">
                        <label for="course_id">Select Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php
                            $db = getDBConnection();
                            $sql = "SELECT id, title FROM courses WHERE teacher_id = :teacher_id";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([':teacher_id' => $_SESSION['user_id']]);
                            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($courses as $course) {
                                echo "<option value='{$course['id']}'>{$course['title']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Class Title</label>
                        <input type="text" id="title" name="title" required placeholder="e.g., Introduction to PHP">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_time">Start Date & Time</label>
                            <input type="datetime-local" id="start_time" name="start_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (minutes)</label>
                            <input type="number" id="duration" name="duration" min="15" max="240" value="60" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Schedule Class</button>
                </form>
            </div>
        </div>
        
         Scheduled Classes List 
        <div class="card">
            <div class="card-header">
                <h2>Your Scheduled Classes</h2>
            </div>
            <div class="card-body">
                <div id="classList" class="class-list">
                    <p class="loading">Loading classes...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.schedule-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.card-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.card-body {
    padding: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.class-list {
    display: grid;
    gap: 15px;
}

.class-item {
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.class-info h3 {
    margin: 0 0 5px 0;
    color: #333;
}

.class-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.class-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-scheduled {
    background: #fff3cd;
    color: #856404;
}

.status-live {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #d1ecf1;
    color: #0c5460;
}
</style>

<script>
// Load scheduled classes
function loadClasses() {
    fetch('../../backend/teacher/schedule-handler.php')
        .then(response => response.json())
        .then(classes => {
            const classList = document.getElementById('classList');
            
            if (classes.length === 0) {
                classList.innerHTML = '<p>No classes scheduled yet.</p>';
                return;
            }
            
            classList.innerHTML = classes.map(cls => `
                <div class="class-item">
                    <div class="class-info">
                        <h3>${cls.title}</h3>
                        <p>Start: ${new Date(cls.start_time).toLocaleString()}</p>
                        <p>Duration: ${cls.duration} minutes</p>
                        <span class="status-badge status-${cls.status}">${cls.status.toUpperCase()}</span>
                    </div>
                    <div class="class-actions">
                        ${cls.status === 'scheduled' ? `
                            <button class="btn btn-success" onclick="startClass(${cls.id})">Start Class</button>
                        ` : ''}
                        ${cls.status === 'live' ? `
                            <button class="btn btn-danger" onclick="endClass(${cls.id})">End Class</button>
                            <a href="/frontend/live-class/host.php?room=${cls.room_id}" class="btn btn-primary">Join</a>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        });
}

// Schedule new class
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create');
    
    fetch('../../backend/teacher/schedule-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Class scheduled successfully!');
            this.reset();
            loadClasses();
        } else {
            alert('Error scheduling class');
        }
    });
});

// Start class
function startClass(classId) {
    const formData = new FormData();
    formData.append('action', 'start');
    formData.append('class_id', classId);
    
    fetch('../../backend/teacher/schedule-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadClasses();
        }
    });
}

// End class
function endClass(classId) {
    if (!confirm('Are you sure you want to end this class?')) return;
    
    const formData = new FormData();
    formData.append('action', 'end');
    formData.append('class_id', classId);
    
    fetch('../../backend/teacher/schedule-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadClasses();
        }
    });
}

// Load classes on page load
loadClasses();
</script>

<?php include '../../shared/templates/footer.php'; ?>

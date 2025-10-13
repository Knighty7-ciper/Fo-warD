<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Admin Dashboard';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get statistics
$stats = [];

// Total users
$sql = "SELECT COUNT(*) as count FROM users";
$stmt = $db->query($sql);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total courses
$sql = "SELECT COUNT(*) as count FROM courses";
$stmt = $db->query($sql);
$stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total enrollments
$sql = "SELECT COUNT(*) as count FROM enrollments";
$stmt = $db->query($sql);
$stats['total_enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending approvals
$sql = "SELECT COUNT(*) as count FROM courses WHERE status = 'pending'";
$stmt = $db->query($sql);
$stats['pending_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<div class="container">
    <div class="page-header">
        <h1>Admin Dashboard</h1>
        <p>Manage your learning platform</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #007bff;">
                <span>👥</span>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_users']) ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #28a745;">
                <span>📚</span>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_courses']) ?></h3>
                <p>Total Courses</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #17a2b8;">
                <span>✓</span>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_enrollments']) ?></h3>
                <p>Total Enrollments</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffc107;">
                <span>⏳</span>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['pending_courses']) ?></h3>
                <p>Pending Approvals</p>
            </div>
        </div>
    </div>
    
    <div class="admin-sections">
        <div class="admin-card">
            <h2>User Management</h2>
            <p>Manage users, roles, and permissions</p>
            <a href="user-manager.php" class="btn btn-primary">Manage Users</a>
        </div>
        
        <div class="admin-card">
            <h2>Course Management</h2>
            <p>Approve, edit, and manage courses</p>
            <a href="course-manager.php" class="btn btn-primary">Manage Courses</a>
        </div>
        
        <div class="admin-card">
            <h2>Certificate Issuer</h2>
            <p>Issue and manage certificates</p>
            <a href="certificate-issuer.php" class="btn btn-primary">Manage Certificates</a>
        </div>
        
        <div class="admin-card">
            <h2>Audit Logs</h2>
            <p>View system activity and logs</p>
            <a href="audit-logs.php" class="btn btn-primary">View Logs</a>
        </div>
        
        <div class="admin-card">
            <h2>System Settings</h2>
            <p>Configure platform settings</p>
            <a href="settings.php" class="btn btn-primary">Settings</a>
        </div>
        
        <div class="admin-card">
            <h2>Reports</h2>
            <p>Generate and view reports</p>
            <a href="reports.php" class="btn btn-primary">View Reports</a>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    color: #333;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #666;
}

.admin-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.admin-card {
    background: white;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-card h2 {
    margin: 0 0 10px 0;
    color: #333;
}

.admin-card p {
    color: #666;
    margin-bottom: 20px;
}
</style>

<?php include '../../shared/templates/footer.php'; ?>

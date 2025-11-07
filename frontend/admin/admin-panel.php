<?php
require_once '../../config/database.php';
require_once '../../config/auth.php';

$user = requireAuth();

// Verify admin role
if ($user['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied. Admin privileges required.']));
}

// Get initial dashboard data
try {
    // System stats
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments,
            (SELECT COUNT(*) FROM live_classes) as total_classes,
            (SELECT COUNT(*) FROM files) as total_files,
            (SELECT SUM(file_size) FROM files) as total_storage_used,
            (SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_last_24h,
            (SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_last_week
    ");
    $systemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity
    $stmt = $pdo->query("
        SELECT 
            'user' as type,
            u.name as name,
            u.email as email,
            u.created_at as timestamp,
            'New user registered' as description
        FROM users u 
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'course' as type,
            c.title as name,
            u.email as email,
            c.created_at as timestamp,
            'New course created' as description
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        ORDER BY timestamp DESC
        LIMIT 10
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $systemStats = [];
    $recentActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - FowarD LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--dark-color), #34495e);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sidebar-header p {
            margin: 5px 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 0 20px;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 0;
        }

        .content-header {
            background: white;
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .content-header h1 {
            margin: 0;
            color: var(--dark-color);
            font-size: 2.2rem;
            font-weight: 300;
        }

        .content-header p {
            margin: 10px 0 0 0;
            color: #666;
            font-size: 1.1rem;
        }

        .content-body {
            padding: 30px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary-color); }
        .stat-icon.success { background: var(--secondary-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.danger { background: var(--danger-color); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stat-change.positive { color: var(--secondary-color); }
        .stat-change.negative { color: var(--danger-color); }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-primary-custom {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-success-custom {
            background: var(--secondary-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-success-custom:hover {
            background: #27ae60;
            color: white;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-color);
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #f8d7da; color: #721c24; }
        .status-inactive { background: #fff3cd; color: #856404; }

        .user-role {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-admin { background: var(--primary-color); color: white; }
        .role-teacher { background: var(--secondary-color); color: white; }
        .role-student { background: #6c757d; color: white; }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1rem;
            color: white;
        }

        .activity-icon.user { background: var(--primary-color); }
        .activity-icon.course { background: var(--secondary-color); }
        .activity-icon.file { background: var(--warning-color); }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .activity-description {
            color: #666;
            font-size: 0.9rem;
        }

        .activity-time {
            color: #999;
            font-size: 0.85rem;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
            outline: none;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .search-box i {
            position: absolute;
            right: 20px;
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

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="bi bi-shield-check"></i> Admin Panel</h3>
                <p>System Administration</p>
            </div>
            
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard" onclick="showSection('dashboard')">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#users" onclick="showSection('users')">
                            <i class="bi bi-people"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#courses" onclick="showSection('courses')">
                            <i class="bi bi-book"></i>
                            Course Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#content" onclick="showSection('content')">
                            <i class="bi bi-file-earmark-text"></i>
                            Content Audit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#analytics" onclick="showSection('analytics')">
                            <i class="bi bi-bar-chart"></i>
                            Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#system" onclick="showSection('system')">
                            <i class="bi bi-gear"></i>
                            System Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#support" onclick="showSection('support')">
                            <i class="bi bi-headset"></i>
                            Support
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section">
                <div class="content-header">
                    <h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>
                    <p>Welcome to the Forward LMS administration panel</p>
                </div>

                <div class="content-body">
                    <!-- System Statistics -->
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($systemStats['total_users'] ?? 0) ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i> Active platform
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <i class="bi bi-book"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($systemStats['total_courses'] ?? 0) ?></div>
                            <div class="stat-label">Total Courses</div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i> Growing content
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <i class="bi bi-person-check"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($systemStats['total_enrollments'] ?? 0) ?></div>
                            <div class="stat-label">Enrollments</div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i> High engagement
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon danger">
                                    <i class="bi bi-camera-video"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($systemStats['total_classes'] ?? 0) ?></div>
                            <div class="stat-label">Live Classes</div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i> Interactive sessions
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="content-section">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="bi bi-graph-up"></i> Platform Growth
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="growthChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="content-section">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="bi bi-activity"></i> Recent Activity
                                    </h3>
                                </div>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $activity['type'] ?>">
                                            <i class="bi bi-<?= $activity['type'] === 'user' ? 'person' : 'book' ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?= htmlspecialchars($activity['name']) ?></div>
                                            <div class="activity-description"><?= htmlspecialchars($activity['description']) ?></div>
                                            <div class="activity-time"><?= date('M j, Y', strtotime($activity['timestamp'])) ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Management Section -->
            <div id="users-section" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1><i class="bi bi-people"></i> User Management</h1>
                    <p>Manage platform users, roles, and permissions</p>
                </div>

                <div class="content-body">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-list-ul"></i> All Users
                        </h3>
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="showCreateUserModal()">
                                <i class="bi bi-person-plus"></i> Add User
                            </button>
                            <button class="btn btn-outline-primary" onclick="exportUsers()">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="search-box">
                        <input type="text" id="userSearch" placeholder="Search users by name or email..." onkeyup="filterUsers()">
                        <i class="bi bi-search"></i>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Users will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div id="analytics-section" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1><i class="bi bi-bar-chart"></i> Platform Analytics</h1>
                    <p>Comprehensive insights and usage statistics</p>
                </div>

                <div class="content-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="content-section">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="bi bi-pie-chart"></i> User Distribution
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="userDistChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="content-section">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="bi bi-line-chart"></i> Engagement Trends
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="engagementChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select role...</option>
                                    <option value="student">Student</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createUser()">Create User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Global variables
        let currentSection = 'dashboard';
        let usersData = [];
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadUsers();
            loadSystemStats();
        });

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('[id$="-section"]').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').style.display = 'block';
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[href="#${sectionName}"]`).classList.add('active');
            
            currentSection = sectionName;
            
            // Load section-specific data
            switch(sectionName) {
                case 'users':
                    loadUsers();
                    break;
                case 'analytics':
                    loadAnalytics();
                    break;
            }
        }

        function initializeCharts() {
            // Growth Chart
            const growthCtx = document.getElementById('growthChart').getContext('2d');
            new Chart(growthCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Users',
                        data: [120, 190, 300, 500, 800, 1000],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Courses',
                        data: [20, 35, 45, 60, 75, 90],
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });

            // User Distribution Chart
            const userDistCtx = document.getElementById('userDistChart').getContext('2d');
            new Chart(userDistCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Students', 'Teachers', 'Admins'],
                    datasets: [{
                        data: [750, 45, 5],
                        backgroundColor: ['#3498db', '#2ecc71', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Engagement Chart
            const engagementCtx = document.getElementById('engagementChart').getContext('2d');
            new Chart(engagementCtx, {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Daily Active Users',
                        data: [65, 78, 90, 81, 56, 55, 40],
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function loadUsers() {
            fetch('../../api/admin.php?action=users')
                .then(response => response.json())
                .then(data => {
                    if (data.users) {
                        usersData = data.users;
                        displayUsers(data.users);
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                });
        }

        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                ${user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="fw-bold">${user.name}</div>
                            </div>
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td>
                        <span class="user-role role-${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                    </td>
                    <td>
                        <span class="status-badge status-${user.status}">${user.status}</span>
                    </td>
                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                    <td>${user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewUser(${user.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editUser(${user.id})" title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="suspendUser(${user.id})" title="Suspend User">
                                <i class="bi bi-shield-x"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function filterUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const filteredUsers = usersData.filter(user => 
                user.name.toLowerCase().includes(searchTerm) || 
                user.email.toLowerCase().includes(searchTerm)
            );
            displayUsers(filteredUsers);
        }

        function showCreateUserModal() {
            new bootstrap.Modal(document.getElementById('createUserModal')).show();
        }

        function createUser() {
            const form = document.getElementById('createUserForm');
            const formData = new FormData(form);
            
            fetch('../../api/admin.php?action=create_user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
                    form.reset();
                    showToast('User created successfully!', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Error creating user', 'error');
                }
            })
            .catch(error => {
                console.error('Error creating user:', error);
                showToast('Error creating user', 'error');
            });
        }

        function viewUser(userId) {
            fetch(`../../api/admin.php?action=user_details&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.user_details) {
                        console.log('User details:', data.user_details);
                        showToast('User details loaded', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error loading user details:', error);
                    showToast('Error loading user details', 'error');
                });
        }

        function editUser(userId) {
            showToast('Edit user feature coming soon!', 'info');
        }

        function suspendUser(userId) {
            const reason = prompt('Enter reason for suspension:');
            if (reason) {
                fetch('../../api/admin.php?action=suspend_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, reason: reason })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('User suspended successfully', 'success');
                        loadUsers();
                    } else {
                        showToast(data.error || 'Error suspending user', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error suspending user:', error);
                    showToast('Error suspending user', 'error');
                });
            }
        }

        function loadSystemStats() {
            fetch('../../api/admin.php?action=system_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.system_stats) {
                        console.log('System stats:', data.system_stats);
                    }
                })
                .catch(error => {
                    console.error('Error loading system stats:', error);
                });
        }

        function loadAnalytics() {
            fetch('../../api/admin.php?action=platform_overview')
                .then(response => response.json())
                .then(data => {
                    console.log('Analytics data:', data);
                })
                .catch(error => {
                    console.error('Error loading analytics:', error);
                });
        }

        function exportUsers() {
            showToast('Export feature coming soon!', 'info');
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
    </script>
</body>
</html>
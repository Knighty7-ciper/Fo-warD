<?php
require_once '../../backend/includes/auth.php';
$user = requireAuth();

if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
    header('Location: /frontend/index.php');
    exit;
}

$pageTitle = 'Reports & Analytics';
include '../../shared/templates/header.php';
?>

<link rel="stylesheet" href="../assets/css/reports.css">

<div class="reports-container">
    <div class="reports-header">
        <h1>Reports & Analytics</h1>
        <div class="header-actions">
            <select id="periodFilter" onchange="updatePeriod()">
                <option value="7days">Last 7 Days</option>
                <option value="30days" selected>Last 30 Days</option>
                <option value="90days">Last 90 Days</option>
                <option value="1year">Last Year</option>
            </select>
            <button class="btn btn-secondary" onclick="exportReport()">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>
    </div>

    <div class="reports-tabs">
        <button class="tab-btn active" data-tab="overview" onclick="switchTab('overview')">Overview</button>
        <button class="tab-btn" data-tab="enrollment" onclick="switchTab('enrollment')">Enrollments</button>
        <button class="tab-btn" data-tab="courses" onclick="switchTab('courses')">Course Performance</button>
        <button class="tab-btn" data-tab="students" onclick="switchTab('students')">Student Progress</button>
        <?php if ($user['role'] === 'admin'): ?>
        <button class="tab-btn" data-tab="revenue" onclick="switchTab('revenue')">Revenue</button>
        <?php endif; ?>
        <button class="tab-btn" data-tab="activity" onclick="switchTab('activity')">Activity</button>
    </div>

     Overview Tab 
    <div id="overview-tab" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalUsers">0</h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #f0fdf4; color: #10b981;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalCourses">0</h3>
                    <p>Total Courses</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalEnrollments">0</h3>
                    <p>Total Enrollments</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fce7f3; color: #ec4899;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3 id="activeStudents">0</h3>
                    <p>Active Students</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ede9fe; color: #8b5cf6;">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-info">
                    <h3 id="completedCourses">0</h3>
                    <p>Completed Courses</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #3b82f6;">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-info">
                    <h3 id="avgCompletionRate">0%</h3>
                    <p>Avg Completion Rate</p>
                </div>
            </div>

            <?php if ($user['role'] === 'admin'): ?>
            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalRevenue">$0</h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3 id="newUsersMonth">0</h3>
                    <p>New Users This Month</p>
                </div>
            </div>
        </div>
    </div>

     Enrollment Tab 
    <div id="enrollment-tab" class="tab-content">
        <div class="chart-container">
            <h3>Enrollment Trend</h3>
            <canvas id="enrollmentChart"></canvas>
        </div>

        <div class="data-grid">
            <div class="data-card">
                <h3>Top Courses by Enrollment</h3>
                <div id="topCoursesList"></div>
            </div>

            <div class="data-card">
                <h3>Enrollment by Status</h3>
                <canvas id="enrollmentStatusChart"></canvas>
            </div>
        </div>
    </div>

     Course Performance Tab 
    <div id="courses-tab" class="tab-content">
        <div class="table-container">
            <h3>Course Performance Overview</h3>
            <table id="coursePerformanceTable">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Instructor</th>
                        <th>Students</th>
                        <th>Avg Progress</th>
                        <th>Completed</th>
                        <th>Completion Rate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

     Student Progress Tab 
    <div id="students-tab" class="tab-content">
        <div class="table-container">
            <h3>Student Progress Overview</h3>
            <table id="studentProgressTable">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Enrolled Courses</th>
                        <th>Avg Progress</th>
                        <th>Completed</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

     Revenue Tab 
    <?php if ($user['role'] === 'admin'): ?>
    <div id="revenue-tab" class="tab-content">
        <div class="stats-row">
            <div class="stat-card-lg">
                <h4>Total Revenue</h4>
                <h2 id="revenueTotalAmount">$0</h2>
            </div>
            <div class="stat-card-lg">
                <h4>Avg Transaction</h4>
                <h2 id="revenueAvgTransaction">$0</h2>
            </div>
            <div class="stat-card-lg">
                <h4>Total Transactions</h4>
                <h2 id="revenueTotalTransactions">0</h2>
            </div>
        </div>

        <div class="chart-container">
            <h3>Revenue Trend</h3>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="data-grid">
            <div class="data-card">
                <h3>Top Revenue Courses</h3>
                <div id="topRevenueCourses"></div>
            </div>

            <div class="data-card">
                <h3>Revenue by Payment Method</h3>
                <canvas id="revenueMethodChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

     Activity Tab 
    <div id="activity-tab" class="tab-content">
        <div class="chart-container">
            <h3>Activity Trend</h3>
            <canvas id="activityChart"></canvas>
        </div>

        <div class="data-grid">
            <div class="data-card">
                <h3>Activity by Type</h3>
                <canvas id="activityTypeChart"></canvas>
            </div>

            <div class="data-card">
                <h3>Most Active Users</h3>
                <div id="activeUsersList"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../assets/js/reports.js"></script>

<?php include '../../shared/templates/footer.php'; ?>

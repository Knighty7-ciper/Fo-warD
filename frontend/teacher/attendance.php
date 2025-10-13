<?php
require_once '../../backend/config/auth.php';
requireLogin();
requireRole(['teacher', 'admin']);

$course_id = $_GET['course_id'] ?? null;
if (!$course_id) {
    header('Location: courses.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - FowarD LMS</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/attendance.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="attendance-header">
            <h1>Attendance Management</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="showSettings()">Settings</button>
                <button class="btn btn-secondary" onclick="exportReport()">Export Report</button>
                <button class="btn btn-primary" onclick="showCreateSession()">Create Session</button>
            </div>
        </div>

        <div class="attendance-tabs">
            <button class="tab-btn active" data-tab="sessions">Sessions</button>
            <button class="tab-btn" data-tab="statistics">Statistics</button>
            <button class="tab-btn" data-tab="report">Full Report</button>
        </div>

        <div id="sessions-tab" class="tab-content active">
            <div class="sessions-list" id="sessionsList">
                <div class="loading">Loading sessions...</div>
            </div>
        </div>

        <div id="statistics-tab" class="tab-content">
            <div class="statistics-grid" id="statisticsGrid">
                <div class="loading">Loading statistics...</div>
            </div>
        </div>

        <div id="report-tab" class="tab-content">
            <div class="report-container" id="reportContainer">
                <div class="loading">Loading report...</div>
            </div>
        </div>
    </div>

    <!-- Create Session Modal -->
    <div id="createSessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Attendance Session</h2>
                <button class="close-btn" onclick="closeModal('createSessionModal')">&times;</button>
            </div>
            <form id="createSessionForm">
                <div class="form-group">
                    <label for="sessionDate">Date *</label>
                    <input type="date" id="sessionDate" required>
                </div>
                <div class="form-group">
                    <label for="sessionTime">Time *</label>
                    <input type="time" id="sessionTime" required>
                </div>
                <div class="form-group">
                    <label for="duration">Duration (minutes)</label>
                    <input type="number" id="duration" value="60" min="15" step="15">
                </div>
                <div class="form-group">
                    <label for="sessionType">Session Type</label>
                    <select id="sessionType">
                        <option value="lecture">Lecture</option>
                        <option value="lab">Lab</option>
                        <option value="tutorial">Tutorial</option>
                        <option value="exam">Exam</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" placeholder="e.g., Room 101">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createSessionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Session</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark Attendance Modal -->
    <div id="markAttendanceModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Mark Attendance</h2>
                <button class="close-btn" onclick="closeModal('markAttendanceModal')">&times;</button>
            </div>
            <div class="attendance-marking">
                <div class="marking-header">
                    <div class="session-info" id="sessionInfo"></div>
                    <div class="bulk-actions">
                        <button class="btn btn-sm" onclick="markAllPresent()">Mark All Present</button>
                        <button class="btn btn-sm" onclick="markAllAbsent()">Mark All Absent</button>
                    </div>
                </div>
                <div class="students-list" id="studentsList"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('markAttendanceModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAttendance()">Save Attendance</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Attendance Settings</h2>
                <button class="close-btn" onclick="closeModal('settingsModal')">&times;</button>
            </div>
            <form id="settingsForm">
                <div class="form-group">
                    <label for="requiredPercentage">Required Attendance Percentage</label>
                    <input type="number" id="requiredPercentage" min="0" max="100" step="0.01" value="75">
                    <small>Minimum attendance percentage required for course completion</small>
                </div>
                <div class="form-group">
                    <label for="lateThreshold">Late Threshold (minutes)</label>
                    <input type="number" id="lateThreshold" min="1" value="15">
                    <small>Minutes after session start to mark as late instead of absent</small>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="allowSelfCheckin">
                        Allow Student Self Check-in
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="geofenceEnabled">
                        Enable Location-based Check-in
                    </label>
                </div>
                <div id="geofenceSettings" style="display: none;">
                    <div class="form-group">
                        <label for="geofenceLatitude">Latitude</label>
                        <input type="number" id="geofenceLatitude" step="0.000001">
                    </div>
                    <div class="form-group">
                        <label for="geofenceLongitude">Longitude</label>
                        <input type="number" id="geofenceLongitude" step="0.000001">
                    </div>
                    <div class="form-group">
                        <label for="geofenceRadius">Radius (meters)</label>
                        <input type="number" id="geofenceRadius" value="100" min="10">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('settingsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/attendance.js"></script>
</body>
</html>

<?php
require_once '../../backend/config/auth.php';
requireLogin();

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
    <title>My Attendance - FowarD LMS</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/student-attendance.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="attendance-header">
            <h1>My Attendance</h1>
        </div>

        <div class="attendance-summary" id="attendanceSummary">
            <div class="loading">Loading attendance summary...</div>
        </div>

        <div class="attendance-records">
            <h2>Attendance History</h2>
            <div class="records-list" id="recordsList">
                <div class="loading">Loading records...</div>
            </div>
        </div>

        <div id="selfCheckinSection" style="display: none;">
            <button class="btn btn-primary btn-large" onclick="selfCheckin()">Check In Now</button>
        </div>
    </div>

    <script>
        const courseId = <?php echo json_encode($course_id); ?>;
    </script>
    <script src="../assets/js/student-attendance.js"></script>
</body>
</html>

<?php
require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();

$course_id = $_POST['course_id'];
$teacher_id = $_SESSION['user_id'];

// Verify ownership
$sql = "SELECT * FROM courses WHERE id = :id AND teacher_id = :teacher_id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $course_id, ':teacher_id' => $teacher_id]);

if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Course not found or unauthorized']);
    exit;
}

// Delete related data first
$sql = "DELETE FROM lessons WHERE course_id = :course_id";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);

$sql = "DELETE FROM enrollments WHERE course_id = :course_id";
$stmt = $db->prepare($sql);
$stmt->execute([':course_id' => $course_id]);

// Delete course
$sql = "DELETE FROM courses WHERE id = :id";
$stmt = $db->prepare($sql);
$result = $stmt->execute([':id' => $course_id]);

echo json_encode(['success' => $result]);
?>

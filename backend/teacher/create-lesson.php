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

// Verify course ownership
$sql = "SELECT * FROM courses WHERE id = :id AND teacher_id = :teacher_id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $course_id, ':teacher_id' => $teacher_id]);

if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Course not found or unauthorized']);
    exit;
}

// Create lesson
$sql = "INSERT INTO lessons (course_id, title, description, content, duration, order_num, video_url, created_at)
        VALUES (:course_id, :title, :description, :content, :duration, :order_num, :video_url, NOW())";

$stmt = $db->prepare($sql);
$result = $stmt->execute([
    ':course_id' => $course_id,
    ':title' => $_POST['title'],
    ':description' => $_POST['description'] ?? '',
    ':content' => $_POST['content'] ?? '',
    ':duration' => $_POST['duration'] ?? 30,
    ':order_num' => $_POST['order_num'] ?? 1,
    ':video_url' => $_POST['video_url'] ?? ''
]);

echo json_encode([
    'success' => $result,
    'lesson_id' => $db->lastInsertId()
]);
?>

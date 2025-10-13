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

$lesson_id = $_GET['id'];
$teacher_id = $_SESSION['user_id'];

// Get lesson with ownership verification
$sql = "SELECT l.* FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = :lesson_id AND c.teacher_id = :teacher_id";
$stmt = $db->prepare($sql);
$stmt->execute([':lesson_id' => $lesson_id, ':teacher_id' => $teacher_id]);

$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lesson) {
    echo json_encode($lesson);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Lesson not found']);
}
?>

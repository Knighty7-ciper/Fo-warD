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

$lesson_id = $_POST['lesson_id'];
$teacher_id = $_SESSION['user_id'];

// Verify ownership through course
$sql = "SELECT l.* FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = :lesson_id AND c.teacher_id = :teacher_id";
$stmt = $db->prepare($sql);
$stmt->execute([':lesson_id' => $lesson_id, ':teacher_id' => $teacher_id]);

if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Lesson not found or unauthorized']);
    exit;
}

// Update lesson
$sql = "UPDATE lessons SET 
        title = :title,
        description = :description,
        content = :content,
        duration = :duration,
        order_num = :order_num,
        video_url = :video_url,
        updated_at = NOW()
        WHERE id = :id";

$stmt = $db->prepare($sql);
$result = $stmt->execute([
    ':title' => $_POST['title'],
    ':description' => $_POST['description'] ?? '',
    ':content' => $_POST['content'] ?? '',
    ':duration' => $_POST['duration'] ?? 30,
    ':order_num' => $_POST['order_num'] ?? 1,
    ':video_url' => $_POST['video_url'] ?? '',
    ':id' => $lesson_id
]);

echo json_encode(['success' => $result]);
?>

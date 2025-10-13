<?php
require_once '../config/db.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$user = authenticate();
if (!$user || $user['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$course_id = $_GET['course_id'] ?? null;

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing course_id']);
    exit;
}

// Verify teacher owns the course
$stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this course']);
    exit;
}

// Get lessons
$stmt = $pdo->prepare("SELECT id, title, order_index FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

echo json_encode(['lessons' => $lessons]);
?>

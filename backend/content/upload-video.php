<?php
require_once '../config/db.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = authenticate();
if (!$user || $user['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$lesson_id = $_POST['lesson_id'] ?? null;
$course_id = $_POST['course_id'] ?? null;

if (!$lesson_id || !$course_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
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

// Handle file upload
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No video file uploaded']);
    exit;
}

$file = $_FILES['video'];
$allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
$max_size = 500 * 1024 * 1024; // 500MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only MP4, WebM, and OGG are allowed']);
    exit;
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 500MB']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/videos/' . $course_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload file']);
    exit;
}

// Update lesson with video path
$video_url = '/uploads/videos/' . $course_id . '/' . $filename;
$stmt = $pdo->prepare("UPDATE lessons SET video_url = ?, duration = ? WHERE id = ? AND course_id = ?");
$duration = $_POST['duration'] ?? 0;
$stmt->execute([$video_url, $duration, $lesson_id, $course_id]);

echo json_encode([
    'success' => true,
    'video_url' => $video_url,
    'message' => 'Video uploaded successfully'
]);
?>

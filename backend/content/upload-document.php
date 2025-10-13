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
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No document file uploaded']);
    exit;
}

$file = $_FILES['document'];
$allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
$max_size = 50 * 1024 * 1024; // 50MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only PDF, DOC, DOCX, PPT, PPTX are allowed']);
    exit;
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 50MB']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/documents/' . $course_id . '/';
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

// Save document to database
$document_url = '/uploads/documents/' . $course_id . '/' . $filename;
$original_name = $file['name'];
$file_size = $file['size'];

$stmt = $pdo->prepare("INSERT INTO lesson_documents (lesson_id, filename, original_name, file_url, file_size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute([$lesson_id, $filename, $original_name, $document_url, $file_size]);

echo json_encode([
    'success' => true,
    'document_url' => $document_url,
    'document_id' => $pdo->lastInsertId(),
    'message' => 'Document uploaded successfully'
]);
?>

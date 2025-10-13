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

// Update course
$sql = "UPDATE courses SET 
        title = :title,
        description = :description,
        full_description = :full_description,
        category = :category,
        level = :level,
        duration = :duration,
        price = :price,
        thumbnail = :thumbnail,
        status = :status,
        allow_enrollment = :allow_enrollment,
        certificate_enabled = :certificate_enabled,
        updated_at = NOW()
        WHERE id = :id AND teacher_id = :teacher_id";

$stmt = $db->prepare($sql);
$result = $stmt->execute([
    ':title' => $_POST['title'],
    ':description' => $_POST['description'],
    ':full_description' => $_POST['full_description'] ?? '',
    ':category' => $_POST['category'] ?? 'programming',
    ':level' => $_POST['level'] ?? 'beginner',
    ':duration' => $_POST['duration'] ?? 8,
    ':price' => $_POST['price'] ?? 0,
    ':thumbnail' => $_POST['thumbnail'] ?? '',
    ':status' => $_POST['status'] ?? 'draft',
    ':allow_enrollment' => isset($_POST['allow_enrollment']) ? 1 : 0,
    ':certificate_enabled' => isset($_POST['certificate_enabled']) ? 1 : 0,
    ':id' => $course_id,
    ':teacher_id' => $teacher_id
]);

echo json_encode(['success' => $result]);
?>

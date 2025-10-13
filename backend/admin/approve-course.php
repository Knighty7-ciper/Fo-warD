<?php
require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$course_id = $data['course_id'];

$db = getDBConnection();

$sql = "UPDATE courses SET status = 'published' WHERE id = :id";
$stmt = $db->prepare($sql);
$result = $stmt->execute([':id' => $course_id]);

// Log action
$sql = "INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, created_at)
        VALUES (:user_id, 'approve', 'course', :course_id, 'Course approved', NOW())";
$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id'], ':course_id' => $course_id]);

echo json_encode(['success' => $result]);
?>

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

$input = json_decode(file_get_contents('php://input'), true);
$submission_id = $input['submission_id'] ?? null;
$grade = $input['grade'] ?? null;
$feedback = $input['feedback'] ?? '';

if (!$submission_id || $grade === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Verify teacher owns the assignment
$stmt = $pdo->prepare("
    SELECT c.teacher_id
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$submission_id]);
$result = $stmt->fetch();

if (!$result || $result['teacher_id'] != $user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this assignment']);
    exit;
}

// Update grade
$stmt = $pdo->prepare("
    UPDATE assignment_submissions 
    SET grade = ?, feedback = ?, graded_at = NOW(), graded_by = ? 
    WHERE id = ?
");
$stmt->execute([$grade, $feedback, $user['id'], $submission_id]);

echo json_encode([
    'success' => true,
    'message' => 'Grade saved successfully'
]);
?>

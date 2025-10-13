<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? null;

if ($course_id) {
    // Get progress for specific course
    $sql = "SELECT 
            (SELECT COUNT(*) FROM lessons WHERE course_id = :course_id) as total_lessons,
            (SELECT COUNT(*) FROM lesson_progress lp 
             JOIN lessons l ON lp.lesson_id = l.id 
             WHERE l.course_id = :course_id AND lp.user_id = :user_id AND lp.completed = 1) as completed_lessons";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':course_id' => $course_id, ':user_id' => $user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $progress_percent = $progress['total_lessons'] > 0 
        ? round(($progress['completed_lessons'] / $progress['total_lessons']) * 100) 
        : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'course_id' => $course_id,
            'total_lessons' => $progress['total_lessons'],
            'completed_lessons' => $progress['completed_lessons'],
            'progress_percent' => $progress_percent
        ]
    ]);
} else {
    // Get overall progress
    $sql = "SELECT 
            COUNT(DISTINCT e.course_id) as enrolled_courses,
            (SELECT COUNT(*) FROM certificates WHERE user_id = :user_id) as certificates_earned,
            (SELECT points_balance FROM users WHERE id = :user_id) as total_points,
            (SELECT COUNT(*) FROM lesson_progress WHERE user_id = :user_id AND completed = 1) as lessons_completed
            FROM enrollments e
            WHERE e.user_id = :user_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}
?>

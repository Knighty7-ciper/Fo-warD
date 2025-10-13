<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'teacher') {
    getTeacherAnalytics($db, $user_id);
} elseif ($role === 'admin') {
    getAdminAnalytics($db);
} else {
    getStudentAnalytics($db, $user_id);
}

function getTeacherAnalytics($db, $teacher_id) {
    // Total courses
    $sql = "SELECT COUNT(*) as total_courses FROM courses WHERE teacher_id = :teacher_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':teacher_id' => $teacher_id]);
    $courses = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total students
    $sql = "SELECT COUNT(DISTINCT e.user_id) as total_students
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE c.teacher_id = :teacher_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':teacher_id' => $teacher_id]);
    $students = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revenue
    $sql = "SELECT SUM(c.price) as total_revenue
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE c.teacher_id = :teacher_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':teacher_id' => $teacher_id]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Course performance
    $sql = "SELECT c.title, COUNT(e.user_id) as enrollment_count
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            WHERE c.teacher_id = :teacher_id
            GROUP BY c.id
            ORDER BY enrollment_count DESC
            LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([':teacher_id' => $teacher_id]);
    $top_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_courses' => $courses['total_courses'],
            'total_students' => $students['total_students'],
            'total_revenue' => $revenue['total_revenue'] ?? 0,
            'top_courses' => $top_courses
        ]
    ]);
}

function getAdminAnalytics($db) {
    // Platform statistics
    $sql = "SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments,
            (SELECT COUNT(*) FROM certificates) as total_certificates";
    $stmt = $db->query($sql);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revenue
    $sql = "SELECT SUM(c.price) as total_revenue
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id";
    $stmt = $db->query($sql);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Growth trends (last 30 days)
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    $stmt = $db->query($sql);
    $user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_users' => $stats['total_users'],
            'total_courses' => $stats['total_courses'],
            'total_enrollments' => $stats['total_enrollments'],
            'total_certificates' => $stats['total_certificates'],
            'total_revenue' => $revenue['total_revenue'] ?? 0,
            'user_growth' => $user_growth
        ]
    ]);
}

function getStudentAnalytics($db, $user_id) {
    // Learning statistics
    $sql = "SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE user_id = :user_id) as enrolled_courses,
            (SELECT COUNT(*) FROM certificates WHERE user_id = :user_id) as certificates_earned,
            (SELECT points_balance FROM users WHERE id = :user_id) as total_points,
            (SELECT COUNT(*) FROM lesson_progress WHERE user_id = :user_id AND completed = 1) as lessons_completed";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Learning streak
    $sql = "SELECT COUNT(DISTINCT DATE(completed_at)) as streak_days
            FROM lesson_progress
            WHERE user_id = :user_id 
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $streak = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'enrolled_courses' => $stats['enrolled_courses'],
            'certificates_earned' => $stats['certificates_earned'],
            'total_points' => $stats['total_points'],
            'lessons_completed' => $stats['lessons_completed'],
            'learning_streak' => $streak['streak_days']
        ]
    ]);
}
?>

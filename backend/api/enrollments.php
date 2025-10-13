<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/db.php';
require_once '../config/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        getEnrollments($db);
        break;
    
    case 'POST':
        enrollStudent($db);
        break;
    
    case 'DELETE':
        unenrollStudent($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getEnrollments($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $course_id = $_GET['course_id'] ?? null;
    
    if ($course_id) {
        // Get enrollments for a specific course
        $sql = "SELECT e.*, u.first_name, u.last_name, u.email
                FROM enrollments e
                JOIN users u ON e.user_id = u.id
                WHERE e.course_id = :course_id
                ORDER BY e.enrolled_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':course_id' => $course_id]);
    } else {
        // Get user's enrollments
        $sql = "SELECT e.*, c.title, c.description, c.thumbnail,
                (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.user_id = :user_id
                ORDER BY e.enrolled_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
    }
    
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $enrollments
    ]);
}

function enrollStudent($db) {
    session_start();
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $course_id = $data['course_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if already enrolled
    $sql = "SELECT * FROM enrollments WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':course_id' => $course_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Already enrolled']);
        return;
    }
    
    // Enroll student
    $sql = "INSERT INTO enrollments (user_id, course_id, enrolled_at, progress)
            VALUES (:user_id, :course_id, NOW(), 0)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':course_id' => $course_id
    ]);
    
    if ($result) {
        // Award points
        $sql = "UPDATE users SET points_balance = points_balance + 10 WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Enrolled successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Enrollment failed']);
    }
}

function unenrollStudent($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $course_id = $data['course_id'];
    $user_id = $_SESSION['user_id'];
    
    $sql = "DELETE FROM enrollments WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':course_id' => $course_id
    ]);
    
    echo json_encode(['success' => $result]);
}
?>

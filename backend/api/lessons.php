<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
        if (isset($_GET['id'])) {
            getLesson($db, $_GET['id']);
        } else {
            getLessons($db);
        }
        break;
    
    case 'POST':
        markLessonComplete($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getLessons($db) {
    $course_id = $_GET['course_id'] ?? null;
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'course_id required']);
        return;
    }
    
    $sql = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_num ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':course_id' => $course_id]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $lessons
    ]);
}

function getLesson($db, $id) {
    $sql = "SELECT * FROM lessons WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lesson) {
        echo json_encode([
            'success' => true,
            'data' => $lesson
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
    }
}

function markLessonComplete($db) {
    session_start();
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $lesson_id = $data['lesson_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if already completed
    $sql = "SELECT * FROM lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':lesson_id' => $lesson_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['message' => 'Already completed']);
        return;
    }
    
    // Mark as complete
    $sql = "INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
            VALUES (:user_id, :lesson_id, 1, NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':lesson_id' => $lesson_id
    ]);
    
    if ($result) {
        // Award points
        $sql = "UPDATE users SET points_balance = points_balance + 10 WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        
        // Record reward
        $sql = "INSERT INTO rewards (user_id, points, description, earned_at)
                VALUES (:user_id, 10, 'Completed lesson', NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        
        echo json_encode([
            'success' => true,
            'points_earned' => 10
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark complete']);
    }
}
?>

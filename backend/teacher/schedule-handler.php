<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/live-class.php';

session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$db = getDBConnection();
$liveClass = new LiveClassHandler($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $liveClass->createLiveClass(
                $_POST['course_id'],
                $_SESSION['user_id'],
                $_POST['title'],
                $_POST['start_time'],
                $_POST['duration']
            );
            echo json_encode($result);
            break;
            
        case 'start':
            $result = $liveClass->startLiveClass($_POST['class_id'], $_SESSION['user_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'end':
            $result = $liveClass->endLiveClass($_POST['class_id'], $_SESSION['user_id']);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    // GET request - fetch scheduled classes
    $sql = "SELECT * FROM live_classes 
            WHERE teacher_id = :teacher_id 
            ORDER BY start_time DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':teacher_id' => $_SESSION['user_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($classes);
}
?>

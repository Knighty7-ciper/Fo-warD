<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    $db = getDBConnection();
    
    switch ($method) {
        case 'GET':
            getPreferences($db, $user_id);
            break;
        case 'PUT':
            updatePreferences($db, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getPreferences($db, $user_id) {
    $stmt = $db->prepare("SELECT * FROM email_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prefs) {
        // Create default preferences
        $query = "INSERT INTO email_preferences (user_id) VALUES (?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $stmt = $db->prepare("SELECT * FROM email_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['preferences' => $prefs]);
}

function updatePreferences($db, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $query = "INSERT INTO email_preferences 
              (user_id, email_enabled, digest_frequency, notify_new_message, 
               notify_assignment_due, notify_quiz_available, notify_grade_posted,
               notify_course_update, notify_forum_reply, notify_announcement,
               notify_certificate, marketing_emails)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              email_enabled = VALUES(email_enabled),
              digest_frequency = VALUES(digest_frequency),
              notify_new_message = VALUES(notify_new_message),
              notify_assignment_due = VALUES(notify_assignment_due),
              notify_quiz_available = VALUES(notify_quiz_available),
              notify_grade_posted = VALUES(notify_grade_posted),
              notify_course_update = VALUES(notify_course_update),
              notify_forum_reply = VALUES(notify_forum_reply),
              notify_announcement = VALUES(notify_announcement),
              notify_certificate = VALUES(notify_certificate),
              marketing_emails = VALUES(marketing_emails),
              updated_at = NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $user_id,
        $data['email_enabled'] ?? true,
        $data['digest_frequency'] ?? 'none',
        $data['notify_new_message'] ?? true,
        $data['notify_assignment_due'] ?? true,
        $data['notify_quiz_available'] ?? true,
        $data['notify_grade_posted'] ?? true,
        $data['notify_course_update'] ?? true,
        $data['notify_forum_reply'] ?? true,
        $data['notify_announcement'] ?? true,
        $data['notify_certificate'] ?? true,
        $data['marketing_emails'] ?? false
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Email preferences updated successfully'
    ]);
}

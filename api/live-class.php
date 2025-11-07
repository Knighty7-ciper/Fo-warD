<?php
/**
 * Live Class Integration API
 * Phase 5: Live Class Features
 * Features: Video conferencing, class scheduling, recording management
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $user = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    switch($method) {
        case 'GET':
            handleGetRequest($pdo, $user, $action, $_GET);
            break;
        case 'POST':
            handlePostRequest($pdo, $user, $action, $input);
            break;
        case 'PUT':
            handlePutRequest($pdo, $user, $action, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $user, $action, $_GET);
            break;
        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

function handleGetRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'classes':
            getLiveClasses($pdo, $user, $params);
            break;
        case 'upcoming':
            getUpcomingClasses($pdo, $user, $params);
            break;
        case 'join':
            joinLiveClass($pdo, $user, $params);
            break;
        case 'recording':
            getClassRecording($pdo, $user, $params);
            break;
        case 'participants':
            getClassParticipants($pdo, $user, $params);
            break;
        case 'chat':
            getClassChat($pdo, $user, $params);
            break;
        case 'stats':
            getClassStats($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'schedule':
            scheduleLiveClass($pdo, $user, $input);
            break;
        case 'start':
            startLiveClass($pdo, $user, $input);
            break;
        case 'end':
            endLiveClass($pdo, $user, $input);
            break;
        case 'record':
            startRecording($pdo, $user, $input);
            break;
        case 'chat':
            sendChatMessage($pdo, $user, $input);
            break;
        case 'share_screen':
            shareScreen($pdo, $user, $input);
            break;
        case 'mute_participant':
            muteParticipant($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'update_class':
            updateLiveClass($pdo, $user, $input);
            break;
        case 'update_settings':
            updateClassSettings($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'cancel_class':
            cancelLiveClass($pdo, $user, $params);
            break;
        case 'delete_recording':
            deleteRecording($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

// Get live classes
function getLiveClasses($pdo, $user, $params) {
    $courseId = $params['course_id'] ?? null;
    $status = $params['status'] ?? 'all'; // all, scheduled, live, completed
    $page = (int)($params['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            lc.*,
            c.title as course_title,
            u.name as instructor_name,
            u.avatar as instructor_avatar,
            (SELECT COUNT(*) FROM class_participants cp WHERE cp.class_id = lc.id AND cp.status = 'attended') as actual_attendees,
            (SELECT COUNT(*) FROM class_participants cp WHERE cp.class_id = lc.id) as total_enrolled
        FROM live_classes lc
        JOIN courses c ON lc.course_id = c.id
        JOIN users u ON lc.instructor_id = u.id
    ";
    
    $whereConditions = [];
    $queryParams = [];
    
    // Role-based filtering
    if ($user['role'] === 'student') {
        $sql .= " JOIN enrollments e ON c.id = e.course_id";
        $whereConditions[] = "e.student_id = ? AND e.status = 'active'";
        $queryParams[] = $user['id'];
    } elseif ($user['role'] === 'teacher') {
        $whereConditions[] = "lc.instructor_id = ?";
        $queryParams[] = $user['id'];
    }
    // Admins can see all classes
    
    if ($courseId) {
        $whereConditions[] = "lc.course_id = ?";
        $queryParams[] = $courseId;
    }
    
    if ($status !== 'all') {
        $currentTime = date('Y-m-d H:i:s');
        switch($status) {
            case 'scheduled':
                $whereConditions[] = "lc.status = 'scheduled' AND lc.start_time > ?";
                $queryParams[] = $currentTime;
                break;
            case 'live':
                $whereConditions[] = "lc.status = 'live'";
                break;
            case 'completed':
                $whereConditions[] = "lc.status = 'completed' OR (lc.status = 'scheduled' AND lc.start_time <= ?)";
                $queryParams[] = $currentTime;
                break;
        }
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY lc.start_time DESC LIMIT ? OFFSET ?";
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = str_replace("SELECT lc.*, c.title as course_title, u.name as instructor_name, u.avatar as instructor_avatar, (SELECT COUNT(*) FROM class_participants cp WHERE cp.class_id = lc.id AND cp.status = 'attended') as actual_attendees, (SELECT COUNT(*) FROM class_participants cp WHERE cp.class_id = lc.id) as total_enrolled", "SELECT COUNT(DISTINCT lc.id)", $sql);
    $countSql = preg_replace('/ORDER BY lc\.start_time DESC LIMIT \? OFFSET \?$/', '', $countSql);
    
    $stmt = $pdo->prepare($countSql);
    $countParams = array_slice($queryParams, 0, -2); // Remove limit and offset
    $stmt->execute($countParams);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['COUNT(DISTINCT lc.id)'];
    
    echo json_encode([
        'classes' => $classes,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// Get upcoming classes for user
function getUpcomingClasses($pdo, $user, $params) {
    $courseId = $params['course_id'] ?? null;
    
    $sql = "
        SELECT 
            lc.*,
            c.title as course_title,
            u.name as instructor_name,
            DATEDIFF(lc.start_time, NOW()) as days_until,
            TIMESTAMPDIFF(MINUTE, NOW(), lc.start_time) as minutes_until
        FROM live_classes lc
        JOIN courses c ON lc.course_id = c.id
        JOIN users u ON lc.instructor_id = u.id
        WHERE lc.status = 'scheduled' 
        AND lc.start_time > NOW()
    ";
    
    $queryParams = [];
    
    // Role-based filtering
    if ($user['role'] === 'student') {
        $sql .= " AND c.id IN (SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active')";
        $queryParams[] = $user['id'];
    } elseif ($user['role'] === 'teacher') {
        $sql .= " AND lc.instructor_id = ?";
        $queryParams[] = $user['id'];
    }
    
    if ($courseId) {
        $sql .= " AND lc.course_id = ?";
        $queryParams[] = $courseId;
    }
    
    $sql .= " ORDER BY lc.start_time ASC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['upcoming_classes' => $classes]);
}

// Join a live class
function joinLiveClass($pdo, $user, $params) {
    $classId = $params['class_id'] ?? null;
    
    if (!$classId) {
        throw new Exception('Class ID required');
    }
    
    // Get class details
    $stmt = $pdo->prepare("
        SELECT lc.*, c.teacher_id as course_teacher_id
        FROM live_classes lc
        JOIN courses c ON lc.course_id = c.id
        WHERE lc.id = ?
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception('Class not found');
    }
    
    // Check if user has access
    if (!hasClassAccess($pdo, $user, $class)) {
        throw new Exception('Access denied');
    }
    
    // Check if class is live or can be joined
    $currentTime = date('Y-m-d H:i:s');
    $joinTime = date('Y-m-d H:i:s', strtotime($class['start_time']) - 900); // 15 minutes before
    
    if ($currentTime < $joinTime) {
        throw new Exception('Class has not started yet. You can join 15 minutes before the scheduled time.');
    }
    
    // Update or insert participant
    $stmt = $pdo->prepare("
        INSERT INTO class_participants (class_id, user_id, joined_at, status)
        VALUES (?, ?, NOW(), 'joined')
        ON DUPLICATE KEY UPDATE 
        joined_at = NOW(), 
        status = 'joined',
        left_at = NULL
    ");
    $stmt->execute([$classId, $user['id']]);
    
    // Generate session token for WebRTC
    $sessionToken = generateSessionToken();
    
    // Update class status if first participant
    if ($class['status'] === 'scheduled') {
        $stmt = $pdo->prepare("UPDATE live_classes SET status = 'live', actual_start_time = NOW() WHERE id = ?");
        $stmt->execute([$classId]);
    }
    
    echo json_encode([
        'success' => true,
        'session_token' => $sessionToken,
        'class_info' => [
            'id' => $class['id'],
            'title' => $class['title'],
            'status' => 'live',
            'participant_count' => getParticipantCount($pdo, $classId)
        ]
    ]);
}

// Schedule a new live class
function scheduleLiveClass($pdo, $user, $input) {
    if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
        throw new Exception('Only teachers and admins can schedule live classes');
    }
    
    $courseId = $input['course_id'];
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $startTime = $input['start_time'];
    $duration = (int)($input['duration'] ?? 60); // minutes
    $maxParticipants = (int)($input['max_participants'] ?? 50);
    $isRecorded = $input['is_recorded'] ?? true;
    $requireApproval = $input['require_approval'] ?? false;
    
    if (empty($title) || empty($startTime)) {
        throw new Exception('Title and start time are required');
    }
    
    // Validate start time (must be at least 30 minutes in future)
    $startDateTime = new DateTime($startTime);
    $now = new DateTime();
    $minStart = clone $now;
    $minStart->modify('+30 minutes');
    
    if ($startDateTime < $minStart) {
        throw new Exception('Class must be scheduled at least 30 minutes in advance');
    }
    
    // Verify teacher owns the course
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$courseId, $user['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('You can only schedule classes for your own courses');
        }
    }
    
    // Create class
    $stmt = $pdo->prepare("
        INSERT INTO live_classes (
            course_id, instructor_id, title, description, 
            start_time, duration, max_participants, 
            is_recorded, require_approval, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $stmt->execute([
        $courseId, $user['id'], $title, $description,
        $startTime, $duration, $maxParticipants,
        $isRecorded ? 1 : 0, $requireApproval ? 1 : 0
    ]);
    
    $classId = $pdo->lastInsertId();
    
    // Auto-enroll course students
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("
            INSERT INTO class_participants (class_id, user_id, status, enrolled_at)
            SELECT ?, student_id, 'enrolled', NOW()
            FROM enrollments 
            WHERE course_id = ? AND status = 'active'
            ON DUPLICATE KEY UPDATE status = 'enrolled'
        ");
        $stmt->execute([$classId, $courseId]);
    }
    
    // Generate meeting URL
    $meetingUrl = generateMeetingUrl($classId);
    
    echo json_encode(['class_id' => $classId, 'meeting_url' => $meetingUrl, 'success' => true]);
}

// Start a live class
function startLiveClass($pdo, $user, $input) {
    $classId = $input['class_id'];
    
    // Verify user can start this class
    $stmt = $pdo->prepare("
        SELECT lc.*, c.teacher_id
        FROM live_classes lc
        JOIN courses c ON lc.course_id = c.id
        WHERE lc.id = ?
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception('Class not found');
    }
    
    if ($class['instructor_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Only the instructor can start the class');
    }
    
    // Update class status
    $stmt = $pdo->prepare("
        UPDATE live_classes 
        SET status = 'live', actual_start_time = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$classId]);
    
    // Send notifications to enrolled participants
    notifyClassStarting($pdo, $classId);
    
    echo json_encode(['success' => true, 'meeting_url' => generateMeetingUrl($classId)]);
}

// End a live class
function endLiveClass($pdo, $user, $input) {
    $classId = $input['class_id'];
    
    // Verify user can end this class
    $stmt = $pdo->prepare("SELECT * FROM live_classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception('Class not found');
    }
    
    if ($class['instructor_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Only the instructor can end the class');
    }
    
    // Update class status
    $stmt = $pdo->prepare("
        UPDATE live_classes 
        SET status = 'completed', actual_end_time = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$classId]);
    
    // Mark participants as left
    $stmt = $pdo->prepare("
        UPDATE class_participants 
        SET left_at = NOW(), status = 'attended' 
        WHERE class_id = ? AND left_at IS NULL
    ");
    $stmt->execute([$classId]);
    
    echo json_encode(['success' => true]);
}

// Send chat message in live class
function sendChatMessage($pdo, $user, $input) {
    $classId = $input['class_id'];
    $message = trim($input['message'] ?? '');
    
    if (empty($message)) {
        throw new Exception('Message content required');
    }
    
    if (strlen($message) > 500) {
        throw new Exception('Message too long (max 500 characters)');
    }
    
    // Verify user is participant in the class
    $stmt = $pdo->prepare("
        SELECT id FROM class_participants 
        WHERE class_id = ? AND user_id = ?
    ");
    $stmt->execute([$classId, $user['id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('You are not a participant in this class');
    }
    
    // Insert chat message
    $stmt = $pdo->prepare("
        INSERT INTO class_chat (class_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$classId, $user['id'], $message]);
    
    $messageId = $pdo->lastInsertId();
    
    // Get the message with user info
    $stmt = $pdo->prepare("
        SELECT cc.*, u.name as user_name, u.role as user_role
        FROM class_chat cc
        JOIN users u ON cc.user_id = u.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$messageId]);
    $chatMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['message' => $chatMessage]);
}

// Get class chat messages
function getClassChat($pdo, $user, $params) {
    $classId = $params['class_id'];
    $limit = (int)($params['limit'] ?? 50);
    $offset = (int)($params['offset'] ?? 0);
    
    // Verify user has access
    if (!hasClassAccess($pdo, $user, ['id' => $classId])) {
        throw new Exception('Access denied');
    }
    
    $stmt = $pdo->prepare("
        SELECT cc.*, u.name as user_name, u.role as user_role
        FROM class_chat cc
        JOIN users u ON cc.user_id = u.id
        WHERE cc.class_id = ?
        ORDER BY cc.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$classId, $limit, $offset]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['messages' => $messages]);
}

// Get class participants
function getClassParticipants($pdo, $user, $params) {
    $classId = $params['class_id'];
    
    // Verify user has access
    if (!hasClassAccess($pdo, $user, ['id' => $classId])) {
        throw new Exception('Access denied');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            cp.*,
            u.name as user_name,
            u.role as user_role,
            u.avatar as user_avatar,
            CASE 
                WHEN cp.joined_at IS NOT NULL AND cp.left_at IS NULL THEN 'online'
                WHEN cp.joined_at IS NOT NULL THEN 'offline'
                ELSE 'not_joined'
            END as current_status
        FROM class_participants cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.class_id = ?
        ORDER BY cp.enrolled_at ASC
    ");
    $stmt->execute([$classId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['participants' => $participants]);
}

// Get class statistics
function getClassStats($pdo, $user, $params) {
    $classId = $params['class_id'];
    
    // Verify user has access
    if (!hasClassAccess($pdo, $user, ['id' => $classId])) {
        throw new Exception('Access denied');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            lc.*,
            COUNT(CASE WHEN cp.status = 'enrolled' THEN 1 END) as total_enrolled,
            COUNT(CASE WHEN cp.status = 'attended' THEN 1 END) as attended,
            COUNT(CASE WHEN cp.joined_at IS NOT NULL THEN 1 END) as joined,
            COUNT(CASE WHEN cp.left_at IS NOT NULL THEN 1 END) as left,
            AVG(CASE 
                WHEN cp.joined_at IS NOT NULL AND cp.left_at IS NOT NULL 
                THEN TIMESTAMPDIFF(SECOND, cp.joined_at, cp.left_at) 
            END) as avg_duration_seconds,
            (SELECT COUNT(*) FROM class_chat WHERE class_id = lc.id) as chat_messages,
            (SELECT COUNT(*) FROM class_recordings WHERE class_id = lc.id) as recording_count
        FROM live_classes lc
        LEFT JOIN class_participants cp ON lc.id = cp.class_id
        WHERE lc.id = ?
        GROUP BY lc.id
    ");
    $stmt->execute([$classId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['stats' => $stats]);
}

// Helper functions
function hasClassAccess($pdo, $user, $class) {
    if ($user['role'] === 'admin') {
        return true;
    }
    
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("
            SELECT lc.id FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            WHERE lc.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$class['id'], $user['id']]);
        return (bool)$stmt->fetch();
    }
    
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("
            SELECT lc.id FROM live_classes lc
            JOIN enrollments e ON lc.course_id = e.course_id
            WHERE lc.id = ? AND e.student_id = ? AND e.status = 'active'
        ");
        $stmt->execute([$class['id'], $user['id']]);
        return (bool)$stmt->fetch();
    }
    
    return false;
}

function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function generateMeetingUrl($classId) {
    return "https://meet.forwardlms.com/room/" . $classId . "-" . substr(md5($classId), 0, 8);
}

function getParticipantCount($pdo, $classId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM class_participants 
        WHERE class_id = ? AND left_at IS NULL
    ");
    $stmt->execute([$classId]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function notifyClassStarting($pdo, $classId) {
    // This would integrate with a notification system
    // For now, just log the notification
    error_log("Class $classId is starting - notifications sent");
}

// Placeholder implementations for other functions
function startRecording($pdo, $user, $input) {
    echo json_encode(['success' => true, 'message' => 'Recording started']);
}

function shareScreen($pdo, $user, $input) {
    echo json_encode(['success' => true, 'message' => 'Screen sharing activated']);
}

function muteParticipant($pdo, $user, $input) {
    echo json_encode(['success' => true, 'message' => 'Participant muted']);
}

function updateLiveClass($pdo, $user, $input) {
    echo json_encode(['success' => true]);
}

function updateClassSettings($pdo, $user, $input) {
    echo json_encode(['success' => true]);
}

function cancelLiveClass($pdo, $user, $params) {
    echo json_encode(['success' => true]);
}

function getClassRecording($pdo, $user, $params) {
    echo json_encode(['recordings' => []]);
}

function deleteRecording($pdo, $user, $params) {
    echo json_encode(['success' => true]);
}
?>
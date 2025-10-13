<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    $db = getDBConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($db, $user_id, $user_role);
            break;
        case 'POST':
            handlePost($db, $user_id, $user_role);
            break;
        case 'PUT':
            handlePut($db, $user_id, $user_role);
            break;
        case 'DELETE':
            handleDelete($db, $user_id, $user_role);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($db, $user_id, $user_role) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'sessions':
            getSessions($db, $user_id, $user_role);
            break;
        case 'session':
            getSession($db, $user_id, $user_role);
            break;
        case 'records':
            getRecords($db, $user_id, $user_role);
            break;
        case 'statistics':
            getStatistics($db, $user_id, $user_role);
            break;
        case 'settings':
            getSettings($db, $user_id, $user_role);
            break;
        case 'report':
            getReport($db, $user_id, $user_role);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getSessions($db, $user_id, $user_role) {
    $course_id = $_GET['course_id'] ?? null;
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    // Check access
    if ($user_role === 'student') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
    }
    
    $query = "SELECT s.*, u.name as created_by_name,
              (SELECT COUNT(*) FROM attendance_records WHERE session_id = s.id AND status = 'present') as present_count,
              (SELECT COUNT(*) FROM attendance_records WHERE session_id = s.id) as total_marked
              FROM attendance_sessions s
              JOIN users u ON s.created_by = u.id
              WHERE s.course_id = ?
              ORDER BY s.session_date DESC, s.session_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$course_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['sessions' => $sessions]);
}

function getSession($db, $user_id, $user_role) {
    $session_id = $_GET['session_id'] ?? null;
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    $query = "SELECT s.*, u.name as created_by_name, c.title as course_title
              FROM attendance_sessions s
              JOIN users u ON s.created_by = u.id
              JOIN courses c ON s.course_id = c.id
              WHERE s.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        return;
    }
    
    // Get attendance records
    $query = "SELECT r.*, u.name as student_name, u.email as student_email
              FROM attendance_records r
              JOIN users u ON r.user_id = u.id
              WHERE r.session_id = ?
              ORDER BY u.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'session' => $session,
        'records' => $records
    ]);
}

function getRecords($db, $user_id, $user_role) {
    $course_id = $_GET['course_id'] ?? null;
    $student_id = $_GET['student_id'] ?? ($user_role === 'student' ? $user_id : null);
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    $query = "SELECT r.*, s.session_date, s.session_time, s.session_type, s.location
              FROM attendance_records r
              JOIN attendance_sessions s ON r.session_id = s.id
              WHERE s.course_id = ?";
    
    $params = [$course_id];
    
    if ($student_id) {
        $query .= " AND r.user_id = ?";
        $params[] = $student_id;
    }
    
    $query .= " ORDER BY s.session_date DESC, s.session_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['records' => $records]);
}

function getStatistics($db, $user_id, $user_role) {
    $course_id = $_GET['course_id'] ?? null;
    $student_id = $_GET['student_id'] ?? ($user_role === 'student' ? $user_id : null);
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    if ($student_id) {
        // Individual student statistics
        $query = "SELECT 
                  COUNT(*) as total_sessions,
                  SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) as present,
                  SUM(CASE WHEN r.status = 'absent' THEN 1 ELSE 0 END) as absent,
                  SUM(CASE WHEN r.status = 'late' THEN 1 ELSE 0 END) as late,
                  SUM(CASE WHEN r.status = 'excused' THEN 1 ELSE 0 END) as excused,
                  ROUND((SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
                  FROM attendance_sessions s
                  LEFT JOIN attendance_records r ON s.id = r.session_id AND r.user_id = ?
                  WHERE s.course_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$student_id, $course_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['statistics' => $stats]);
    } else {
        // Course-wide statistics
        $query = "SELECT 
                  COUNT(DISTINCT s.id) as total_sessions,
                  COUNT(DISTINCT e.user_id) as total_students,
                  AVG(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance_rate
                  FROM attendance_sessions s
                  CROSS JOIN enrollments e
                  LEFT JOIN attendance_records r ON s.id = r.session_id AND e.user_id = r.user_id
                  WHERE s.course_id = ? AND e.course_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id, $course_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['statistics' => $stats]);
    }
}

function getSettings($db, $user_id, $user_role) {
    $course_id = $_GET['course_id'] ?? null;
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM attendance_settings WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        // Return default settings
        $settings = [
            'course_id' => $course_id,
            'required_percentage' => 75.00,
            'late_threshold_minutes' => 15,
            'allow_self_checkin' => false,
            'geofence_enabled' => false
        ];
    }
    
    echo json_encode(['settings' => $settings]);
}

function getReport($db, $user_id, $user_role) {
    $course_id = $_GET['course_id'] ?? null;
    
    if (!$course_id || $user_role === 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Get all students with their attendance statistics
    $query = "SELECT u.id, u.name, u.email,
              COUNT(DISTINCT s.id) as total_sessions,
              SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) as present,
              SUM(CASE WHEN r.status = 'absent' THEN 1 ELSE 0 END) as absent,
              SUM(CASE WHEN r.status = 'late' THEN 1 ELSE 0 END) as late,
              SUM(CASE WHEN r.status = 'excused' THEN 1 ELSE 0 END) as excused,
              ROUND((SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) / COUNT(DISTINCT s.id)) * 100, 2) as attendance_percentage
              FROM enrollments e
              JOIN users u ON e.user_id = u.id
              CROSS JOIN attendance_sessions s
              LEFT JOIN attendance_records r ON s.id = r.session_id AND u.id = r.user_id
              WHERE e.course_id = ? AND s.course_id = ?
              GROUP BY u.id, u.name, u.email
              ORDER BY u.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$course_id, $course_id]);
    $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['report' => $report]);
}

function handlePost($db, $user_id, $user_role) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create_session';
    
    if ($user_role === 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    switch ($action) {
        case 'create_session':
            createSession($db, $user_id, $data);
            break;
        case 'mark_attendance':
            markAttendance($db, $user_id, $data);
            break;
        case 'bulk_mark':
            bulkMarkAttendance($db, $user_id, $data);
            break;
        case 'self_checkin':
            selfCheckin($db, $user_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function createSession($db, $user_id, $data) {
    $required = ['course_id', 'session_date', 'session_time'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $query = "INSERT INTO attendance_sessions 
              (course_id, session_date, session_time, duration_minutes, session_type, location, notes, created_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['course_id'],
        $data['session_date'],
        $data['session_time'],
        $data['duration_minutes'] ?? 60,
        $data['session_type'] ?? 'lecture',
        $data['location'] ?? null,
        $data['notes'] ?? null,
        $user_id
    ]);
    
    $session_id = $db->lastInsertId();
    
    // Auto-create attendance records for all enrolled students
    $query = "INSERT INTO attendance_records (session_id, user_id, status, marked_by)
              SELECT ?, user_id, 'absent', ?
              FROM enrollments
              WHERE course_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id, $user_id, $data['course_id']]);
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'message' => 'Attendance session created successfully'
    ]);
}

function markAttendance($db, $user_id, $data) {
    $required = ['session_id', 'student_id', 'status'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $query = "UPDATE attendance_records 
              SET status = ?, notes = ?, check_in_time = ?, marked_by = ?, updated_at = NOW()
              WHERE session_id = ? AND user_id = ?";
    
    $check_in_time = $data['status'] === 'present' || $data['status'] === 'late' ? date('Y-m-d H:i:s') : null;
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['status'],
        $data['notes'] ?? null,
        $check_in_time,
        $user_id,
        $data['session_id'],
        $data['student_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance marked successfully'
    ]);
}

function bulkMarkAttendance($db, $user_id, $data) {
    if (!isset($data['session_id']) || !isset($data['records'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        $query = "UPDATE attendance_records 
                  SET status = ?, check_in_time = ?, marked_by = ?, updated_at = NOW()
                  WHERE session_id = ? AND user_id = ?";
        
        $stmt = $db->prepare($query);
        
        foreach ($data['records'] as $record) {
            $check_in_time = $record['status'] === 'present' || $record['status'] === 'late' ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                $record['status'],
                $check_in_time,
                $user_id,
                $data['session_id'],
                $record['student_id']
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk attendance marked successfully'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function selfCheckin($db, $user_id, $data) {
    $session_id = $data['session_id'] ?? null;
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    // Check if self check-in is allowed
    $query = "SELECT s.course_id, st.allow_self_checkin, st.geofence_enabled,
              st.geofence_latitude, st.geofence_longitude, st.geofence_radius_meters
              FROM attendance_sessions s
              LEFT JOIN attendance_settings st ON s.course_id = st.course_id
              WHERE s.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || !$settings['allow_self_checkin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Self check-in not allowed for this course']);
        return;
    }
    
    // Check geofence if enabled
    if ($settings['geofence_enabled']) {
        $user_lat = $data['latitude'] ?? null;
        $user_lng = $data['longitude'] ?? null;
        
        if (!$user_lat || !$user_lng) {
            http_response_code(400);
            echo json_encode(['error' => 'Location required for check-in']);
            return;
        }
        
        $distance = calculateDistance(
            $user_lat, $user_lng,
            $settings['geofence_latitude'], $settings['geofence_longitude']
        );
        
        if ($distance > $settings['geofence_radius_meters']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not within the allowed location for check-in']);
            return;
        }
    }
    
    // Mark attendance
    $query = "UPDATE attendance_records 
              SET status = 'present', check_in_time = NOW(), marked_by = ?, updated_at = NOW()
              WHERE session_id = ? AND user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $session_id, $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Checked in successfully'
    ]);
}

function handlePut($db, $user_id, $user_role) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'update_session';
    
    if ($user_role === 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    switch ($action) {
        case 'update_session':
            updateSession($db, $user_id, $data);
            break;
        case 'update_settings':
            updateSettings($db, $user_id, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function updateSession($db, $user_id, $data) {
    $session_id = $data['session_id'] ?? null;
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    $query = "UPDATE attendance_sessions 
              SET session_date = ?, session_time = ?, duration_minutes = ?, 
                  session_type = ?, location = ?, notes = ?, updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['session_date'],
        $data['session_time'],
        $data['duration_minutes'],
        $data['session_type'],
        $data['location'] ?? null,
        $data['notes'] ?? null,
        $session_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session updated successfully'
    ]);
}

function updateSettings($db, $user_id, $data) {
    $course_id = $data['course_id'] ?? null;
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID required']);
        return;
    }
    
    $query = "INSERT INTO attendance_settings 
              (course_id, required_percentage, late_threshold_minutes, allow_self_checkin, 
               geofence_enabled, geofence_latitude, geofence_longitude, geofence_radius_meters)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              required_percentage = VALUES(required_percentage),
              late_threshold_minutes = VALUES(late_threshold_minutes),
              allow_self_checkin = VALUES(allow_self_checkin),
              geofence_enabled = VALUES(geofence_enabled),
              geofence_latitude = VALUES(geofence_latitude),
              geofence_longitude = VALUES(geofence_longitude),
              geofence_radius_meters = VALUES(geofence_radius_meters),
              updated_at = NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $course_id,
        $data['required_percentage'] ?? 75.00,
        $data['late_threshold_minutes'] ?? 15,
        $data['allow_self_checkin'] ?? false,
        $data['geofence_enabled'] ?? false,
        $data['geofence_latitude'] ?? null,
        $data['geofence_longitude'] ?? null,
        $data['geofence_radius_meters'] ?? 100
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully'
    ]);
}

function handleDelete($db, $user_id, $user_role) {
    if ($user_role === 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $session_id = $_GET['session_id'] ?? null;
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM attendance_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session deleted successfully'
    ]);
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

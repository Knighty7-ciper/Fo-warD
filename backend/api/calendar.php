<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch($_GET['action']) {
                    case 'list':
                        getEvents($conn, $user);
                        break;
                    case 'upcoming':
                        getUpcomingEvents($conn, $user);
                        break;
                    case 'export':
                        exportCalendar($conn, $user);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else if (isset($_GET['id'])) {
                getEvent($conn, $_GET['id'], $user);
            } else {
                getEvents($conn, $user);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            createEvent($conn, $data, $user);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateEvent($conn, $data, $user);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            deleteEvent($conn, $data['id'], $user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getEvents($conn, $user) {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $type = $_GET['type'] ?? null;
    
    $sql = "SELECT e.*, 
            u.name as creator_name,
            c.title as course_title,
            ea.status as attendance_status
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN courses c ON e.course_id = c.id
            LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
            WHERE (e.start_datetime BETWEEN ? AND ? OR e.end_datetime BETWEEN ? AND ?)
            AND (e.created_by = ? OR ea.user_id = ? OR e.course_id IN (
                SELECT course_id FROM enrollments WHERE user_id = ?
            ))";
    
    $params = [$user['id'], $start, $end, $start, $end, $user['id'], $user['id'], $user['id']];
    
    if ($type) {
        $sql .= " AND e.event_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY e.start_datetime ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add assignment and quiz deadlines
    if (!$type || $type === 'assignment' || $type === 'quiz') {
        $deadlines = getDeadlines($conn, $user, $start, $end);
        $events = array_merge($events, $deadlines);
    }
    
    echo json_encode(['success' => true, 'events' => $events]);
}

function getDeadlines($conn, $user, $start, $end) {
    $deadlines = [];
    
    // Get assignment deadlines
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date as start_datetime, a.due_date as end_datetime,
               'assignment' as event_type, c.title as course_title, c.id as course_id,
               '#ef4444' as color, TRUE as is_deadline
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.user_id = ? AND a.due_date BETWEEN ? AND ?
        AND a.status = 'published'
    ");
    $stmt->execute([$user['id'], $start, $end]);
    $deadlines = array_merge($deadlines, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Get quiz deadlines
    $stmt = $conn->prepare("
        SELECT q.id, q.title, q.end_time as start_datetime, q.end_time as end_datetime,
               'quiz' as event_type, c.title as course_title, c.id as course_id,
               '#f59e0b' as color, TRUE as is_deadline
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.user_id = ? AND q.end_time BETWEEN ? AND ?
        AND q.status = 'published'
    ");
    $stmt->execute([$user['id'], $start, $end]);
    $deadlines = array_merge($deadlines, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    return $deadlines;
}

function getUpcomingEvents($conn, $user) {
    $limit = $_GET['limit'] ?? 10;
    
    $stmt = $conn->prepare("
        SELECT e.*, u.name as creator_name, c.title as course_title
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN event_attendees ea ON e.id = ea.event_id
        WHERE e.start_datetime >= NOW()
        AND (e.created_by = ? OR ea.user_id = ? OR e.course_id IN (
            SELECT course_id FROM enrollments WHERE user_id = ?
        ))
        ORDER BY e.start_datetime ASC
        LIMIT ?
    ");
    
    $stmt->execute([$user['id'], $user['id'], $user['id'], (int)$limit]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'events' => $events]);
}

function getEvent($conn, $id, $user) {
    $stmt = $conn->prepare("
        SELECT e.*, u.name as creator_name, c.title as course_title,
               GROUP_CONCAT(DISTINCT CONCAT(att.id, ':', att.name, ':', ea.status) SEPARATOR '|') as attendees
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN event_attendees ea ON e.id = ea.event_id
        LEFT JOIN users att ON ea.user_id = att.id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }
    
    // Parse attendees
    if ($event['attendees']) {
        $attendeesList = [];
        foreach (explode('|', $event['attendees']) as $att) {
            list($id, $name, $status) = explode(':', $att);
            $attendeesList[] = ['id' => $id, 'name' => $name, 'status' => $status];
        }
        $event['attendees'] = $attendeesList;
    } else {
        $event['attendees'] = [];
    }
    
    echo json_encode(['success' => true, 'event' => $event]);
}

function createEvent($conn, $data, $user) {
    $stmt = $conn->prepare("
        INSERT INTO events (title, description, event_type, start_datetime, end_datetime, 
                          location, course_id, created_by, is_all_day, color)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['event_type'] ?? 'other',
        $data['start_datetime'],
        $data['end_datetime'],
        $data['location'] ?? null,
        $data['course_id'] ?? null,
        $user['id'],
        $data['is_all_day'] ?? false,
        $data['color'] ?? '#3b82f6'
    ]);
    
    $eventId = $conn->lastInsertId();
    
    // Add attendees if provided
    if (!empty($data['attendees'])) {
        $stmt = $conn->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (?, ?)");
        foreach ($data['attendees'] as $attendeeId) {
            $stmt->execute([$eventId, $attendeeId]);
        }
    }
    
    echo json_encode(['success' => true, 'id' => $eventId, 'message' => 'Event created successfully']);
}

function updateEvent($conn, $data, $user) {
    // Check ownership
    $stmt = $conn->prepare("SELECT created_by FROM events WHERE id = ?");
    $stmt->execute([$data['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event || ($event['created_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE events 
        SET title = ?, description = ?, event_type = ?, start_datetime = ?, 
            end_datetime = ?, location = ?, course_id = ?, is_all_day = ?, color = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['event_type'] ?? 'other',
        $data['start_datetime'],
        $data['end_datetime'],
        $data['location'] ?? null,
        $data['course_id'] ?? null,
        $data['is_all_day'] ?? false,
        $data['color'] ?? '#3b82f6',
        $data['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
}

function deleteEvent($conn, $id, $user) {
    $stmt = $conn->prepare("SELECT created_by FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event || ($event['created_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
}

function exportCalendar($conn, $user) {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t', strtotime('+1 year'));
    
    $stmt = $conn->prepare("
        SELECT e.* FROM events e
        LEFT JOIN event_attendees ea ON e.id = ea.event_id
        WHERE (e.start_datetime BETWEEN ? AND ?)
        AND (e.created_by = ? OR ea.user_id = ?)
        ORDER BY e.start_datetime ASC
    ");
    
    $stmt->execute([$start, $end, $user['id'], $user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="calendar.ics"');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//FowarD LMS//Calendar//EN\r\n";
    
    foreach ($events as $event) {
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $event['id'] . "@foward-lms.com\r\n";
        echo "DTSTAMP:" . date('Ymd\THis\Z', strtotime($event['created_at'])) . "\r\n";
        echo "DTSTART:" . date('Ymd\THis\Z', strtotime($event['start_datetime'])) . "\r\n";
        echo "DTEND:" . date('Ymd\THis\Z', strtotime($event['end_datetime'])) . "\r\n";
        echo "SUMMARY:" . $event['title'] . "\r\n";
        if ($event['description']) {
            echo "DESCRIPTION:" . str_replace("\n", "\\n", $event['description']) . "\r\n";
        }
        if ($event['location']) {
            echo "LOCATION:" . $event['location'] . "\r\n";
        }
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    exit;
}

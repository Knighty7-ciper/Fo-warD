<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/db.php';
require_once '../config/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getCourse($db, $_GET['id']);
        } else {
            getCourses($db);
        }
        break;
    
    case 'POST':
        createCourse($db);
        break;
    
    case 'PUT':
        updateCourse($db);
        break;
    
    case 'DELETE':
        deleteCourse($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getCourses($db) {
    $status = $_GET['status'] ?? 'published';
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "SELECT c.*, u.first_name, u.last_name,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.status = :status";
    
    $params = [':status' => $status];
    
    if ($category) {
        $sql .= " AND c.category = :category";
        $params[':category'] = $category;
    }
    
    if ($search) {
        $sql .= " AND (c.title LIKE :search OR c.description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $courses,
        'count' => count($courses)
    ]);
}

function getCourse($db, $id) {
    $sql = "SELECT c.*, u.first_name, u.last_name, u.email,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($course) {
        // Get lessons
        $sql = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_num ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':course_id' => $id]);
        $course['lessons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $course
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
    }
}

function createCourse($db) {
    session_start();
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "INSERT INTO courses (teacher_id, title, description, category, level, price, status, created_at)
            VALUES (:teacher_id, :title, :description, :category, :level, :price, 'draft', NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':teacher_id' => $_SESSION['user_id'],
        ':title' => $data['title'],
        ':description' => $data['description'],
        ':category' => $data['category'] ?? 'programming',
        ':level' => $data['level'] ?? 'beginner',
        ':price' => $data['price'] ?? 0
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'course_id' => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create course']);
    }
}

function updateCourse($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $course_id = $data['id'];
    
    // Verify ownership or admin
    $sql = "SELECT * FROM courses WHERE id = :id AND (teacher_id = :user_id OR :is_admin = 1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $course_id,
        ':user_id' => $_SESSION['user_id'],
        ':is_admin' => $_SESSION['role'] === 'admin' ? 1 : 0
    ]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $sql = "UPDATE courses SET 
            title = :title,
            description = :description,
            category = :category,
            level = :level,
            price = :price,
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':title' => $data['title'],
        ':description' => $data['description'],
        ':category' => $data['category'],
        ':level' => $data['level'],
        ':price' => $data['price'],
        ':id' => $course_id
    ]);
    
    echo json_encode(['success' => $result]);
}

function deleteCourse($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $course_id = $data['id'];
    
    // Verify ownership or admin
    $sql = "SELECT * FROM courses WHERE id = :id AND (teacher_id = :user_id OR :is_admin = 1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $course_id,
        ':user_id' => $_SESSION['user_id'],
        ':is_admin' => $_SESSION['role'] === 'admin' ? 1 : 0
    ]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $sql = "DELETE FROM courses WHERE id = :id";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([':id' => $course_id]);
    
    echo json_encode(['success' => $result]);
}
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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
        getCertificates($db);
        break;
    
    case 'POST':
        issueCertificate($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getCertificates($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT cert.*, c.title as course_title
            FROM certificates cert
            JOIN courses c ON cert.course_id = c.id
            WHERE cert.user_id = :user_id
            ORDER BY cert.issued_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $certificates
    ]);
}

function issueCertificate($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $course_id = $data['course_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if course is completed
    $sql = "SELECT 
            (SELECT COUNT(*) FROM lessons WHERE course_id = :course_id) as total_lessons,
            (SELECT COUNT(*) FROM lesson_progress lp 
             JOIN lessons l ON lp.lesson_id = l.id 
             WHERE l.course_id = :course_id AND lp.user_id = :user_id AND lp.completed = 1) as completed_lessons";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':course_id' => $course_id, ':user_id' => $user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($progress['completed_lessons'] < $progress['total_lessons']) {
        echo json_encode(['error' => 'Course not completed']);
        return;
    }
    
    // Check if certificate already exists
    $sql = "SELECT * FROM certificates WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':course_id' => $course_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Certificate already issued']);
        return;
    }
    
    // Generate certificate number
    $cert_number = 'CERT-' . strtoupper(uniqid());
    $blockchain_hash = hash('sha256', $cert_number . $user_id . $course_id . time());
    
    // Issue certificate
    $sql = "INSERT INTO certificates (user_id, course_id, certificate_number, blockchain_hash, issued_at)
            VALUES (:user_id, :course_id, :cert_number, :blockchain_hash, NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':course_id' => $course_id,
        ':cert_number' => $cert_number,
        ':blockchain_hash' => $blockchain_hash
    ]);
    
    if ($result) {
        // Award points
        $sql = "UPDATE users SET points_balance = points_balance + 50 WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        
        echo json_encode([
            'success' => true,
            'certificate_id' => $db->lastInsertId(),
            'certificate_number' => $cert_number,
            'blockchain_hash' => $blockchain_hash
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to issue certificate']);
    }
}
?>

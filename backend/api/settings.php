<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            getSettings($conn);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateSettings($conn, $data);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getSettings($conn) {
    $stmt = $conn->query("SELECT * FROM site_settings ORDER BY category, setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'settings' => $settings]);
}

function updateSettings($conn, $data) {
    foreach ($data as $key => $value) {
        $stmt = $conn->prepare("
            UPDATE site_settings 
            SET setting_value = ?
            WHERE setting_key = ?
        ");
        $stmt->execute([$value, $key]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
}

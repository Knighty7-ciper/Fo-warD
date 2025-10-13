<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            createFolder($conn, $data, $user);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateFolder($conn, $data, $user);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            deleteFolder($conn, $data['id'], $user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function createFolder($conn, $data, $user) {
    $stmt = $conn->prepare("
        INSERT INTO folders (name, parent_id, course_id, created_by, is_public)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['parent_id'] ?? null,
        $data['course_id'] ?? null,
        $user['id'],
        $data['is_public'] ?? false
    ]);
    
    $folderId = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'id' => $folderId, 'message' => 'Folder created successfully']);
}

function updateFolder($conn, $data, $user) {
    $stmt = $conn->prepare("SELECT created_by FROM folders WHERE id = ?");
    $stmt->execute([$data['id']]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder || ($folder['created_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE folders SET name = ?, is_public = ? WHERE id = ?");
    $stmt->execute([$data['name'], $data['is_public'] ?? false, $data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Folder updated successfully']);
}

function deleteFolder($conn, $id, $user) {
    $stmt = $conn->prepare("SELECT created_by FROM folders WHERE id = ?");
    $stmt->execute([$id]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder || ($folder['created_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Folder deleted successfully']);
}

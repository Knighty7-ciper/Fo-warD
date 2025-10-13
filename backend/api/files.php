<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// File upload directory
$uploadDir = '../../uploads/files/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch($_GET['action']) {
                    case 'list':
                        listFiles($conn, $user);
                        break;
                    case 'download':
                        downloadFile($conn, $_GET['id'], $user);
                        break;
                    case 'search':
                        searchFiles($conn, $_GET['q'], $user);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else if (isset($_GET['id'])) {
                getFile($conn, $_GET['id'], $user);
            } else {
                listFiles($conn, $user);
            }
            break;
            
        case 'POST':
            if (isset($_FILES['file'])) {
                uploadFile($conn, $_FILES['file'], $_POST, $user, $uploadDir);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded']);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateFile($conn, $data, $user);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            deleteFile($conn, $data['id'], $user, $uploadDir);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listFiles($conn, $user) {
    $folderId = $_GET['folder_id'] ?? null;
    $courseId = $_GET['course_id'] ?? null;
    
    $sql = "SELECT f.*, u.name as uploader_name, c.title as course_title,
            fo.name as folder_name
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            LEFT JOIN courses c ON f.course_id = c.id
            LEFT JOIN folders fo ON f.folder_id = fo.id
            WHERE (f.uploaded_by = ? OR f.is_public = TRUE OR f.course_id IN (
                SELECT course_id FROM enrollments WHERE user_id = ?
            ))";
    
    $params = [$user['id'], $user['id']];
    
    if ($folderId) {
        $sql .= " AND f.folder_id = ?";
        $params[] = $folderId;
    } else if ($folderId === '0') {
        $sql .= " AND f.folder_id IS NULL";
    }
    
    if ($courseId) {
        $sql .= " AND f.course_id = ?";
        $params[] = $courseId;
    }
    
    $sql .= " ORDER BY f.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get folders
    $folderSql = "SELECT * FROM folders WHERE created_by = ? OR is_public = TRUE";
    $folderParams = [$user['id']];
    
    if ($folderId) {
        $folderSql .= " AND parent_id = ?";
        $folderParams[] = $folderId;
    } else {
        $folderSql .= " AND parent_id IS NULL";
    }
    
    $stmt = $conn->prepare($folderSql);
    $stmt->execute($folderParams);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'files' => $files, 'folders' => $folders]);
}

function getFile($conn, $id, $user) {
    $stmt = $conn->prepare("
        SELECT f.*, u.name as uploader_name, c.title as course_title
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        WHERE f.id = ?
    ");
    
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    // Check permissions
    if ($file['uploaded_by'] != $user['id'] && !$file['is_public']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    echo json_encode(['success' => true, 'file' => $file]);
}

function uploadFile($conn, $file, $data, $user, $uploadDir) {
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload failed']);
        return;
    }
    
    // Check file size (max 50MB)
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size is 50MB']);
        return;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        return;
    }
    
    // Insert into database
    $stmt = $conn->prepare("
        INSERT INTO files (filename, original_filename, file_path, file_size, 
                         file_type, mime_type, uploaded_by, course_id, folder_id, 
                         is_public, description, tags)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $filename,
        $file['name'],
        $filePath,
        $file['size'],
        $extension,
        $file['type'],
        $user['id'],
        $data['course_id'] ?? null,
        $data['folder_id'] ?? null,
        $data['is_public'] ?? false,
        $data['description'] ?? null,
        $data['tags'] ?? null
    ]);
    
    $fileId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'id' => $fileId,
        'filename' => $filename,
        'message' => 'File uploaded successfully'
    ]);
}

function updateFile($conn, $data, $user) {
    // Check ownership
    $stmt = $conn->prepare("SELECT uploaded_by FROM files WHERE id = ?");
    $stmt->execute([$data['id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file || ($file['uploaded_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE files 
        SET original_filename = ?, description = ?, tags = ?, 
            is_public = ?, folder_id = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['original_filename'],
        $data['description'] ?? null,
        $data['tags'] ?? null,
        $data['is_public'] ?? false,
        $data['folder_id'] ?? null,
        $data['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'File updated successfully']);
}

function deleteFile($conn, $id, $user, $uploadDir) {
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file || ($file['uploaded_by'] != $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    // Delete physical file
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
}

function downloadFile($conn, $id, $user) {
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    // Check permissions
    if ($file['uploaded_by'] != $user['id'] && !$file['is_public']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Increment download count
    $stmt = $conn->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$id]);
    
    // Serve file
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $file['file_size']);
    readfile($file['file_path']);
    exit;
}

function searchFiles($conn, $query, $user) {
    $stmt = $conn->prepare("
        SELECT f.*, u.name as uploader_name
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE (f.uploaded_by = ? OR f.is_public = TRUE)
        AND (f.original_filename LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)
        ORDER BY f.created_at DESC
        LIMIT 50
    ");
    
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$user['id'], $searchTerm, $searchTerm, $searchTerm]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'files' => $files]);
}

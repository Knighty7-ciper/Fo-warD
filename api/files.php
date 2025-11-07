<?php
/**
 * Enhanced File Management API
 * Phase 5: Advanced Content Management System
 * Features: Secure file upload, validation, storage optimization, progress tracking
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

// File upload configuration
const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
const ALLOWED_VIDEO_TYPES = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
const ALLOWED_DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'rtf'];
const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
const ALLOWED_AUDIO_TYPES = ['mp3', 'wav', 'ogg', 'm4a'];
const UPLOAD_BASE_DIR = '../uploads/';

try {
    $user = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    switch($method) {
        case 'GET':
            handleGetRequest($pdo, $user);
            break;
        case 'POST':
            handlePostRequest($pdo, $user);
            break;
        case 'PUT':
            handlePutRequest($pdo, $user, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $user, $input);
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle GET requests - list files, search, download
 */
function handleGetRequest($pdo, $user) {
    $action = $_GET['action'] ?? 'list';
    
    switch($action) {
        case 'list':
            listFiles($pdo, $user);
            break;
        case 'search':
            searchFiles($pdo, $user, $_GET['q'] ?? '');
            break;
        case 'download':
            downloadFile($pdo, $user, $_GET['id']);
            break;
        case 'get':
            getFileDetails($pdo, $user, $_GET['id']);
            break;
        case 'stats':
            getFileStats($pdo, $user);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests - file upload
 */
function handlePostRequest($pdo, $user) {
    if (isset($_FILES['file'])) {
        uploadFile($pdo, $user);
    } else {
        throw new Exception('No file uploaded', 400);
    }
}

/**
 * Handle PUT requests - file updates
 */
function handlePutRequest($pdo, $user, $input) {
    if (!isset($input['id'])) {
        throw new Exception('File ID required', 400);
    }
    updateFile($pdo, $user, $input);
}

/**
 * Handle DELETE requests - file deletion
 */
function handleDeleteRequest($pdo, $user, $input) {
    if (!isset($input['id'])) {
        throw new Exception('File ID required', 400);
    }
    deleteFile($pdo, $user, $input['id']);
}

/**
 * List files with filtering and pagination
 */
function listFiles($pdo, $user) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(50, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $folderId = $_GET['folder_id'] ?? null;
    $courseId = $_GET['course_id'] ?? null;
    $type = $_GET['type'] ?? null;
    
    // Base query with access control
    $sql = "SELECT f.*, u.name as uploader_name, c.title as course_title, 
                   fo.name as folder_name,
                   CASE 
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_VIDEO_TYPES) . "') THEN 'video'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_DOCUMENT_TYPES) . "') THEN 'document'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_IMAGE_TYPES) . "') THEN 'image'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_AUDIO_TYPES) . "') THEN 'audio'
                       ELSE 'other'
                   END as file_category
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            LEFT JOIN courses c ON f.course_id = c.id
            LEFT JOIN folders fo ON f.folder_id = fo.id
            WHERE (f.uploaded_by = ? OR f.is_public = TRUE 
                   OR f.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
    
    $params = [$user['id'], $user['id']];
    
    // Apply filters
    if ($folderId) {
        $sql .= " AND f.folder_id = ?";
        $params[] = $folderId;
    } else if (isset($_GET['folder_id']) && $_GET['folder_id'] === '0') {
        $sql .= " AND f.folder_id IS NULL";
    }
    
    if ($courseId) {
        $sql .= " AND f.course_id = ?";
        $params[] = $courseId;
    }
    
    if ($type) {
        switch($type) {
            case 'video':
                $sql .= " AND f.file_type IN ('" . implode("','", ALLOWED_VIDEO_TYPES) . "')";
                break;
            case 'document':
                $sql .= " AND f.file_type IN ('" . implode("','", ALLOWED_DOCUMENT_TYPES) . "')";
                break;
            case 'image':
                $sql .= " AND f.file_type IN ('" . implode("','", ALLOWED_IMAGE_TYPES) . "')";
                break;
            case 'audio':
                $sql .= " AND f.file_type IN ('" . implode("','", ALLOWED_AUDIO_TYPES) . "')";
                break;
        }
    }
    
    // Get total count
    $countSql = str_replace("SELECT f.*, u.name as uploader_name, c.title as course_title, 
                   fo.name as folder_name,
                   CASE 
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_VIDEO_TYPES) . "') THEN 'video'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_DOCUMENT_TYPES) . "') THEN 'document'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_IMAGE_TYPES) . "') THEN 'image'
                       WHEN f.file_type IN ('" . implode("','", ALLOWED_AUDIO_TYPES) . "') THEN 'audio'
                       ELSE 'other'
                   END as file_category", "SELECT COUNT(*)", $sql);
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Add ordering and pagination
    $sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get folders for current context
    $folderSql = "SELECT * FROM folders WHERE created_by = ? OR is_public = TRUE";
    $folderParams = [$user['id']];
    
    if ($folderId) {
        $folderSql .= " AND parent_id = ?";
        $folderParams[] = $folderId;
    } else {
        $folderSql .= " AND parent_id IS NULL";
    }
    
    if ($courseId) {
        $folderSql .= " AND (course_id = ? OR course_id IS NULL)";
        $folderParams[] = $courseId;
    }
    
    $folderSql .= " ORDER BY name ASC";
    
    $stmt = $pdo->prepare($folderSql);
    $stmt->execute($folderParams);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format file sizes
    foreach ($files as &$file) {
        $file['formatted_size'] = formatFileSize($file['file_size']);
        $file['upload_date'] = date('Y-m-d H:i:s', strtotime($file['created_at']));
        $file['can_edit'] = $file['uploaded_by'] == $user['id'] || $user['role'] === 'admin';
        $file['can_delete'] = $file['can_edit'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'files' => $files,
            'folders' => $folders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]
    ]);
}

/**
 * Upload file with validation and optimization
 */
function uploadFile($pdo, $user) {
    $file = $_FILES['file'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (max size exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (max size exceeds MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File upload incomplete',
            UPLOAD_ERR_NO_FILE => 'No file selected',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload directory missing',
            UPLOAD_ERR_CANT_WRITE => 'File write failed',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension'
        ];
        
        $error = $errorMessages[$file['error']] ?? 'Upload failed';
        throw new Exception($error, 400);
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File too large. Maximum size: ' . formatFileSize(MAX_FILE_SIZE), 400);
    }
    
    // Get file information
    $originalFilename = $file['name'];
    $fileInfo = pathinfo($originalFilename);
    $extension = strtolower($fileInfo['extension'] ?? '');
    $filename = $fileInfo['filename'] ?? 'unnamed';
    
    // Validate file type
    $allAllowedTypes = array_merge(
        ALLOWED_VIDEO_TYPES, 
        ALLOWED_DOCUMENT_TYPES, 
        ALLOWED_IMAGE_TYPES, 
        ALLOWED_AUDIO_TYPES
    );
    
    if (!in_array($extension, $allAllowedTypes)) {
        throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allAllowedTypes), 400);
    }
    
    // Generate secure filename
    $secureFilename = uniqid() . '_' . time() . '.' . $extension;
    
    // Determine category and create subdirectory
    $category = getFileCategory($extension);
    $uploadDir = UPLOAD_BASE_DIR . $category . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory', 500);
        }
    }
    
    $filePath = $uploadDir . $secureFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save file', 500);
    }
    
    // Process file based on type
    try {
        if (in_array($extension, ALLOWED_IMAGE_TYPES)) {
            optimizeImage($filePath, $extension);
        }
    } catch (Exception $e) {
        // Continue even if optimization fails
        error_log("File optimization failed: " . $e->getMessage());
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO files (filename, original_filename, file_path, file_size, 
                         file_type, mime_type, uploaded_by, course_id, folder_id, 
                         is_public, description, tags)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $mimeType = mime_content_type($filePath);
    $courseId = $_POST['course_id'] ?? null;
    $folderId = $_POST['folder_id'] ?? null;
    $isPublic = filter_var($_POST['is_public'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $description = $_POST['description'] ?? null;
    $tags = $_POST['tags'] ?? null;
    
    $stmt->execute([
        $secureFilename,
        $originalFilename,
        $filePath,
        filesize($filePath),
        $extension,
        $mimeType,
        $user['id'],
        $courseId,
        $folderId,
        $isPublic,
        $description,
        $tags
    ]);
    
    $fileId = $pdo->lastInsertId();
    
    // Get the saved file details
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as uploader_name, c.title as course_title
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fileId]);
    $savedFile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $savedFile['formatted_size'] = formatFileSize($savedFile['file_size']);
    $savedFile['file_category'] = $category;
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => $savedFile
    ]);
}

/**
 * Search files by name, description, or tags
 */
function searchFiles($pdo, $user, $query) {
    if (empty(trim($query))) {
        throw new Exception('Search query required', 400);
    }
    
    $sql = "
        SELECT f.*, u.name as uploader_name, c.title as course_title,
               MATCH(f.original_filename, f.description, f.tags) AGAINST(? IN BOOLEAN MODE) as relevance
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        WHERE (f.uploaded_by = ? OR f.is_public = TRUE 
               OR f.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))
        AND (f.original_filename LIKE ? 
             OR f.description LIKE ? 
             OR f.tags LIKE ?
             OR MATCH(f.original_filename, f.description, f.tags) AGAINST(? IN BOOLEAN MODE))
        ORDER BY relevance DESC, f.created_at DESC
        LIMIT 50
    ";
    
    $searchTerm = '%' . $query . '%';
    $fulltextTerm = '*' . str_replace(' ', '* *', trim($query)) . '*';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fulltextTerm, $user['id'], $user['id'], $searchTerm, $searchTerm, $searchTerm, $fulltextTerm]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results
    foreach ($files as &$file) {
        $file['formatted_size'] = formatFileSize($file['file_size']);
        $file['file_category'] = getFileCategory($file['file_type']);
    }
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($files),
        'data' => $files
    ]);
}

/**
 * Download file with permission check
 */
function downloadFile($pdo, $user, $fileId) {
    if (!$fileId) {
        throw new Exception('File ID required', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found', 404);
    }
    
    // Check permissions
    if ($file['uploaded_by'] != $user['id'] && !$file['is_public']) {
        // Check if user is enrolled in the course
        if ($file['course_id']) {
            $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$user['id'], $file['course_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Access denied', 403);
            }
        } else {
            throw new Exception('Access denied', 403);
        }
    }
    
    // Check if file exists
    if (!file_exists($file['file_path'])) {
        throw new Exception('File not found on server', 404);
    }
    
    // Increment download count
    $stmt = $pdo->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$fileId]);
    
    // Serve file
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . addslashes($file['original_filename']) . '"');
    header('Content-Length: ' . $file['file_size']);
    header('Cache-Control: private, must-revalidate');
    header('Pragma: public');
    
    // For large files, use readfile instead of file_get_contents
    $fp = fopen($file['file_path'], 'rb');
    if ($fp) {
        fpassthru($fp);
        fclose($fp);
    } else {
        readfile($file['file_path']);
    }
    exit;
}

/**
 * Get detailed file information
 */
function getFileDetails($pdo, $user, $fileId) {
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as uploader_name, c.title as course_title, fo.name as folder_name
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        LEFT JOIN folders fo ON f.folder_id = fo.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found', 404);
    }
    
    // Check access
    if ($file['uploaded_by'] != $user['id'] && !$file['is_public'] && $user['role'] !== 'admin') {
        if ($file['course_id']) {
            $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$user['id'], $file['course_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Access denied', 403);
            }
        } else {
            throw new Exception('Access denied', 403);
        }
    }
    
    $file['formatted_size'] = formatFileSize($file['file_size']);
    $file['file_category'] = getFileCategory($file['file_type']);
    $file['can_edit'] = $file['uploaded_by'] == $user['id'] || $user['role'] === 'admin';
    $file['can_delete'] = $file['can_edit'];
    
    echo json_encode(['success' => true, 'data' => $file]);
}

/**
 * Update file metadata
 */
function updateFile($pdo, $user, $data) {
    $fileId = $data['id'];
    
    // Check ownership
    $stmt = $pdo->prepare("SELECT uploaded_by FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found', 404);
    }
    
    if ($file['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("
        UPDATE files 
        SET original_filename = ?, description = ?, tags = ?, 
            is_public = ?, folder_id = ?, course_id = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['original_filename'] ?? $file['original_filename'],
        $data['description'] ?? null,
        $data['tags'] ?? null,
        filter_var($data['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN),
        $data['folder_id'] ?? null,
        $data['course_id'] ?? null,
        $fileId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'File updated successfully']);
}

/**
 * Delete file
 */
function deleteFile($pdo, $user, $fileId) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found', 404);
    }
    
    if ($file['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Unauthorized', 403);
    }
    
    // Delete physical file
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
}

/**
 * Get file statistics
 */
function getFileStats($pdo, $user) {
    $sql = "
        SELECT 
            COUNT(*) as total_files,
            SUM(file_size) as total_size,
            file_type,
            COUNT(*) as count_by_type
        FROM files 
        WHERE uploaded_by = ?
        GROUP BY file_type
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalFiles = 0;
    $totalSize = 0;
    $typeStats = [];
    
    foreach ($stats as $stat) {
        $totalFiles += $stat['count_by_type'];
        $totalSize += $stat['total_size'];
        $typeStats[$stat['file_type']] = [
            'count' => $stat['count_by_type'],
            'size' => $stat['total_size'],
            'formatted_size' => formatFileSize($stat['total_size'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'formatted_total_size' => formatFileSize($totalSize),
            'by_type' => $typeStats
        ]
    ]);
}

/**
 * Helper Functions
 */

function getFileCategory($extension) {
    if (in_array($extension, ALLOWED_VIDEO_TYPES)) return 'videos';
    if (in_array($extension, ALLOWED_DOCUMENT_TYPES)) return 'documents';
    if (in_array($extension, ALLOWED_IMAGE_TYPES)) return 'images';
    if (in_array($extension, ALLOWED_AUDIO_TYPES)) return 'audio';
    return 'other';
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function optimizeImage($filePath, $extension) {
    // Only optimize JPEGs and PNGs
    if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
        return;
    }
    
    $maxWidth = 1920;
    $maxHeight = 1080;
    $quality = 85;
    
    switch($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($filePath);
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                
                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                    $newWidth = (int)($width * $ratio);
                    $newHeight = (int)($height * $ratio);
                    
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagejpeg($resized, $filePath, $quality);
                    imagedestroy($resized);
                }
                imagedestroy($image);
            }
            break;
            
        case 'png':
            $image = imagecreatefrompng($filePath);
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                
                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                    $newWidth = (int)($width * $ratio);
                    $newHeight = (int)($height * $ratio);
                    
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                    
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagepng($resized, $filePath);
                    imagedestroy($resized);
                }
                imagedestroy($image);
            }
            break;
    }
}
?>
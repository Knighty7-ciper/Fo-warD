<?php
/**
 * Folder Management API
 * Phase 5: Advanced Content Management System
 * Features: Create, organize, and manage file folders
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
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    switch($method) {
        case 'GET':
            handleGetRequest($pdo, $user);
            break;
        case 'POST':
            handlePostRequest($pdo, $user, $input);
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
 * Handle GET requests - list folders, get folder details
 */
function handleGetRequest($pdo, $user) {
    $action = $_GET['action'] ?? 'list';
    
    switch($action) {
        case 'list':
            listFolders($pdo, $user);
            break;
        case 'get':
            getFolderDetails($pdo, $user, $_GET['id']);
            break;
        case 'breadcrumb':
            getFolderBreadcrumb($pdo, $user, $_GET['id']);
            break;
        case 'tree':
            getFolderTree($pdo, $user);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests - create folder
 */
function handlePostRequest($pdo, $user, $input) {
    if (!isset($input['action'])) {
        createFolder($pdo, $user, $input);
    } else {
        switch($input['action']) {
            case 'move_files':
                moveFilesToFolder($pdo, $user, $input);
                break;
            default:
                throw new Exception('Invalid action', 400);
        }
    }
}

/**
 * Handle PUT requests - update folder
 */
function handlePutRequest($pdo, $user, $input) {
    if (!isset($input['id'])) {
        throw new Exception('Folder ID required', 400);
    }
    updateFolder($pdo, $user, $input);
}

/**
 * Handle DELETE requests - delete folder
 */
function handleDeleteRequest($pdo, $user, $input) {
    if (!isset($input['id'])) {
        throw new Exception('Folder ID required', 400);
    }
    deleteFolder($pdo, $user, $input['id']);
}

/**
 * List folders with filtering
 */
function listFolders($pdo, $user) {
    $parentId = $_GET['parent_id'] ?? null;
    $courseId = $_GET['course_id'] ?? null;
    
    $sql = "SELECT f.*, u.name as creator_name, c.title as course_title,
                   (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count,
                   (SELECT COUNT(*) FROM folders WHERE parent_id = f.id) as subfolder_count
            FROM folders f
            LEFT JOIN users u ON f.created_by = u.id
            LEFT JOIN courses c ON f.course_id = c.id
            WHERE (f.created_by = ? OR f.is_public = TRUE)";
    
    $params = [$user['id']];
    
    if ($parentId) {
        $sql .= " AND f.parent_id = ?";
        $params[] = $parentId;
    } else if (isset($_GET['parent_id']) && $_GET['parent_id'] === '0') {
        $sql .= " AND f.parent_id IS NULL";
    }
    
    if ($courseId) {
        $sql .= " AND f.course_id = ?";
        $params[] = $courseId;
    }
    
    $sql .= " ORDER BY f.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add computed fields
    foreach ($folders as &$folder) {
        $folder['total_items'] = $folder['file_count'] + $folder['subfolder_count'];
        $folder['has_subfolders'] = $folder['subfolder_count'] > 0;
        $folder['can_edit'] = $folder['created_by'] == $user['id'] || $user['role'] === 'admin';
        $folder['can_delete'] = $folder['can_edit'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $folders
    ]);
}

/**
 * Get detailed folder information
 */
function getFolderDetails($pdo, $user, $folderId) {
    if (!$folderId) {
        throw new Exception('Folder ID required', 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as creator_name, c.title as course_title,
               (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count,
               (SELECT COUNT(*) FROM folders WHERE parent_id = f.id) as subfolder_count
        FROM folders f
        LEFT JOIN users u ON f.created_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception('Folder not found', 404);
    }
    
    // Check permissions
    if ($folder['created_by'] != $user['id'] && !$folder['is_public'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied', 403);
    }
    
    $folder['total_items'] = $folder['file_count'] + $folder['subfolder_count'];
    $folder['has_subfolders'] = $folder['subfolder_count'] > 0;
    $folder['can_edit'] = $folder['created_by'] == $user['id'] || $user['role'] === 'admin';
    $folder['can_delete'] = $folder['can_edit'];
    
    // Get parent folder info if exists
    if ($folder['parent_id']) {
        $stmt = $pdo->prepare("SELECT id, name FROM folders WHERE id = ?");
        $stmt->execute([$folder['parent_id']]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $folder['parent'] = $parent;
    }
    
    echo json_encode(['success' => true, 'data' => $folder]);
}

/**
 * Get folder breadcrumb path
 */
function getFolderBreadcrumb($pdo, $user, $folderId) {
    if (!$folderId) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    $breadcrumb = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND (created_by = ? OR is_public = TRUE OR ? = 'admin')");
        $stmt->execute([$currentId, $user['id'], $user['role']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$folder) {
            break;
        }
        
        array_unshift($breadcrumb, [
            'id' => $folder['id'],
            'name' => $folder['name']
        ]);
        
        $currentId = $folder['parent_id'];
    }
    
    echo json_encode(['success' => true, 'data' => $breadcrumb]);
}

/**
 * Get complete folder tree structure
 */
function getFolderTree($pdo, $user) {
    $sql = "SELECT id, name, parent_id, course_id, is_public, created_by,
                   (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count
            FROM folders f
            WHERE (f.created_by = ? OR f.is_public = TRUE OR ? = 'admin')
            ORDER BY f.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['role']]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build tree structure
    $folderTree = buildFolderTree($folders);
    
    echo json_encode([
        'success' => true,
        'data' => $folderTree
    ]);
}

/**
 * Create new folder
 */
function createFolder($pdo, $user, $data) {
    if (!isset($data['name']) || empty(trim($data['name']))) {
        throw new Exception('Folder name is required', 400);
    }
    
    $name = trim($data['name']);
    $parentId = $data['parent_id'] ?? null;
    $courseId = $data['course_id'] ?? null;
    $isPublic = filter_var($data['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    // Validate parent folder if provided
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND (created_by = ? OR is_public = TRUE)");
        $stmt->execute([$parentId, $user['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Parent folder not found or access denied', 404);
        }
    }
    
    // Check for duplicate names in same parent
    $stmt = $pdo->prepare("
        SELECT id FROM folders 
        WHERE name = ? AND parent_id IS ? AND (created_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$name, $parentId, $user['id'], $user['role']]);
    if ($stmt->fetch()) {
        throw new Exception('A folder with this name already exists in this location', 400);
    }
    
    // Create folder
    $stmt = $pdo->prepare("
        INSERT INTO folders (name, parent_id, course_id, created_by, is_public)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$name, $parentId, $courseId, $user['id'], $isPublic]);
    $folderId = $pdo->lastInsertId();
    
    // Get the created folder details
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as creator_name, c.title as course_title
        FROM folders f
        LEFT JOIN users u ON f.created_by = u.id
        LEFT JOIN courses c ON f.course_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Folder created successfully',
        'data' => $folder
    ]);
}

/**
 * Update folder
 */
function updateFolder($pdo, $user, $data) {
    $folderId = $data['id'];
    
    // Check ownership
    $stmt = $pdo->prepare("SELECT created_by, parent_id FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception('Folder not found', 404);
    }
    
    if ($folder['created_by'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Unauthorized', 403);
    }
    
    // Validate new parent if provided
    if (isset($data['parent_id']) && $data['parent_id'] != $folder['parent_id']) {
        $newParentId = $data['parent_id'];
        
        if ($newParentId) {
            // Prevent circular reference
            if (isDescendant($pdo, $newParentId, $folderId)) {
                throw new Exception('Cannot move folder into its own descendant', 400);
            }
            
            // Check access to new parent
            $stmt = $pdo->prepare("SELECT created_by FROM folders WHERE id = ?");
            $stmt->execute([$newParentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$parent || ($parent['created_by'] != $user['id'] && $user['role'] !== 'admin')) {
                throw new Exception('Access denied to new parent folder', 403);
            }
        }
        
        $data['parent_id'] = $newParentId;
    }
    
    // Check for duplicate name in new location
    if (isset($data['name'])) {
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE name = ? AND parent_id IS ? AND id != ?
        ");
        $stmt->execute([$data['name'], $data['parent_id'] ?? null, $folderId]);
        if ($stmt->fetch()) {
            throw new Exception('A folder with this name already exists in this location', 400);
        }
    }
    
    // Update folder
    $stmt = $pdo->prepare("
        UPDATE folders 
        SET name = COALESCE(?, name),
            parent_id = COALESCE(?, parent_id),
            course_id = COALESCE(?, course_id),
            is_public = COALESCE(?, is_public)
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'] ?? null,
        $data['parent_id'] ?? null,
        $data['course_id'] ?? null,
        isset($data['is_public']) ? (filter_var($data['is_public'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : null,
        $folderId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Folder updated successfully']);
}

/**
 * Delete folder
 */
function deleteFolder($pdo, $user, $folderId) {
    // Check ownership and content
    $stmt = $pdo->prepare("
        SELECT f.*, 
               (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count,
               (SELECT COUNT(*) FROM folders WHERE parent_id = f.id) as subfolder_count
        FROM folders f 
        WHERE f.id = ?
    ");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception('Folder not found', 404);
    }
    
    if ($folder['created_by'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Unauthorized', 403);
    }
    
    if ($folder['file_count'] > 0 || $folder['subfolder_count'] > 0) {
        throw new Exception('Cannot delete folder that contains files or subfolders', 400);
    }
    
    // Delete folder
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
    
    echo json_encode(['success' => true, 'message' => 'Folder deleted successfully']);
}

/**
 * Move files to folder
 */
function moveFilesToFolder($pdo, $user, $data) {
    if (!isset($data['file_ids']) || !is_array($data['file_ids'])) {
        throw new Exception('File IDs are required', 400);
    }
    
    $fileIds = $data['file_ids'];
    $targetFolderId = $data['folder_id'] ?? null;
    
    // Validate target folder if provided
    if ($targetFolderId) {
        $stmt = $pdo->prepare("SELECT created_by FROM folders WHERE id = ?");
        $stmt->execute([$targetFolderId]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$folder) {
            throw new Exception('Target folder not found', 404);
        }
        
        if ($folder['created_by'] != $user['id'] && $user['role'] !== 'admin') {
            throw new Exception('Access denied to target folder', 403);
        }
    }
    
    // Move files
    $placeholders = str_repeat('?,', count($fileIds) - 1) . '?';
    $sql = "UPDATE files SET folder_id = ? WHERE id IN ($placeholders) AND uploaded_by = ?";
    $params = array_merge([$targetFolderId], $fileIds, [$user['id']]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movedCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Moved {$movedCount} file(s) successfully"
    ]);
}

/**
 * Helper Functions
 */

function buildFolderTree($folders, $parentId = null) {
    $tree = [];
    
    foreach ($folders as $folder) {
        if ($folder['parent_id'] == $parentId) {
            $children = buildFolderTree($folders, $folder['id']);
            $folder['children'] = $children;
            $tree[] = $folder;
        }
    }
    
    return $tree;
}

function isDescendant($pdo, $parentId, $childId) {
    $stmt = $pdo->prepare("SELECT parent_id FROM folders WHERE id = ?");
    $stmt->execute([$childId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder || !$folder['parent_id']) {
        return false;
    }
    
    if ($folder['parent_id'] == $parentId) {
        return true;
    }
    
    return isDescendant($pdo, $parentId, $folder['parent_id']);
}
?>
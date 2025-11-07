<?php
/**
 * File Optimization and Utilities API
 * Phase 5: Advanced Content Management System
 * Features: File compression, image optimization, storage management, and analytics
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
 * Handle GET requests - analytics, optimization status, storage info
 */
function handleGetRequest($pdo, $user) {
    $action = $_GET['action'] ?? 'analytics';
    
    switch($action) {
        case 'analytics':
            getFileAnalytics($pdo, $user);
            break;
        case 'storage':
            getStorageInfo($pdo, $user);
            break;
        case 'optimization':
            getOptimizationStatus($pdo, $user);
            break;
        case 'cleanup':
            getCleanupStatus($pdo, $user);
            break;
        case 'duplicates':
            findDuplicateFiles($pdo, $user);
            break;
        case 'large_files':
            getLargeFiles($pdo, $user);
            break;
        case 'access_stats':
            getAccessStatistics($pdo, $user);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests - optimization tasks
 */
function handlePostRequest($pdo, $user, $input) {
    if (!isset($input['action'])) {
        throw new Exception('Action required', 400);
    }
    
    switch($input['action']) {
        case 'optimize':
            optimizeFiles($pdo, $user, $input);
            break;
        case 'cleanup':
            cleanupFiles($pdo, $user, $input);
            break;
        case 'generate_thumbnails':
            generateThumbnails($pdo, $user, $input);
            break;
        case 'compress':
            compressFiles($pdo, $user, $input);
            break;
        case 'backup':
            createFileBackup($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - configuration updates
 */
function handlePutRequest($pdo, $user, $input) {
    switch($input['action'] ?? 'quota') {
        case 'quota':
            updateStorageQuota($pdo, $user, $input);
            break;
        case 'settings':
            updateOptimizationSettings($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle DELETE requests - cleanup operations
 */
function handleDeleteRequest($pdo, $user, $input) {
    switch($input['action'] ?? 'temp') {
        case 'temp':
            cleanupTempFiles($pdo, $user, $input);
            break;
        case 'old_versions':
            cleanupOldVersions($pdo, $user, $input);
            break;
        case 'duplicates':
            removeDuplicateFiles($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Get comprehensive file analytics
 */
function getFileAnalytics($pdo, $user) {
    // Get file statistics by type
    $stmt = $pdo->query("SELECT * FROM file_statistics ORDER BY total_size DESC");
    $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get storage usage by user
    $stmt = $pdo->prepare("SELECT * FROM user_storage_usage WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userStorage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            fal.*,
            f.original_filename,
            f.file_size,
            u.name as user_name
        FROM file_access_log fal
        JOIN files f ON fal.file_id = f.id
        JOIN users u ON fal.user_id = u.id
        WHERE fal.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY fal.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get most accessed files
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            COUNT(fal.id) as access_count,
            MAX(fal.created_at) as last_accessed_log
        FROM files f
        LEFT JOIN file_access_log fal ON f.id = fal.file_id
        WHERE f.uploaded_by = ? OR f.is_public = TRUE
        GROUP BY f.id
        ORDER BY access_count DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $popularFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get folder statistics
    $stmt = $pdo->prepare("
        SELECT 
            fo.*,
            COUNT(f.id) as file_count,
            COALESCE(SUM(f.file_size), 0) as total_size
        FROM folders fo
        LEFT JOIN files f ON fo.id = f.folder_id
        WHERE fo.created_by = ?
        GROUP BY fo.id
        ORDER BY total_size DESC
    ");
    $stmt->execute([$user['id']]);
    $folderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate growth metrics
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as files_added,
            SUM(file_size) as size_added
        FROM files
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND uploaded_by = ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user['id']]);
    $growthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'type_statistics' => $typeStats,
            'user_storage' => $userStorage,
            'recent_activity' => $recentActivity,
            'popular_files' => $popularFiles,
            'folder_statistics' => $folderStats,
            'growth_data' => $growthData
        ]
    ]);
}

/**
 * Get storage information and quotas
 */
function getStorageInfo($pdo, $user) {
    // Get or create storage quota
    $stmt = $pdo->prepare("SELECT * FROM storage_quotas WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $quota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quota) {
        // Create default quota
        $stmt = $pdo->prepare("INSERT INTO storage_quotas (user_id) VALUES (?)");
        $stmt->execute([$user['id']]);
        
        $stmt = $pdo->prepare("SELECT * FROM storage_quotas WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $quota = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get current usage
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as file_count,
            SUM(file_size) as total_size,
            AVG(file_size) as avg_file_size,
            MAX(file_size) as largest_file,
            MIN(file_size) as smallest_file
        FROM files 
        WHERE uploaded_by = ?
    ");
    $stmt->execute([$user['id']]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top file types by size
    $stmt = $pdo->prepare("
        SELECT 
            file_type,
            COUNT(*) as count,
            SUM(file_size) as total_size,
            ROUND(AVG(file_size), 2) as avg_size
        FROM files
        WHERE uploaded_by = ?
        GROUP BY file_type
        ORDER BY total_size DESC
    ");
    $stmt->execute([$user['id']]);
    $typeBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentages
    $usagePercentage = $quota['quota_bytes'] > 0 ? 
        round(($quota['used_bytes'] / $quota['quota_bytes']) * 100, 2) : 0;
    
    $quota['usage_percentage'] = $usagePercentage;
    $quota['available_bytes'] = $quota['quota_bytes'] - $quota['used_bytes'];
    $quota['formatted_quota'] = formatBytes($quota['quota_bytes']);
    $quota['formatted_used'] = formatBytes($quota['used_bytes']);
    $quota['formatted_available'] = formatBytes($quota['available_bytes']);
    
    $usage['formatted_total'] = formatBytes($usage['total_size'] ?? 0);
    $usage['formatted_avg'] = formatBytes($usage['avg_file_size'] ?? 0);
    $usage['formatted_largest'] = formatBytes($usage['largest_file'] ?? 0);
    $usage['formatted_smallest'] = formatBytes($usage['smallest_file'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'quota' => $quota,
            'usage' => $usage,
            'type_breakdown' => $typeBreakdown
        ]
    ]);
}

/**
 * Optimize files (images, compression, etc.)
 */
function optimizeFiles($pdo, $user, $input) {
    $fileIds = $input['file_ids'] ?? [];
    $options = $input['options'] ?? [];
    
    if (empty($fileIds)) {
        throw new Exception('File IDs required', 400);
    }
    
    $optimized = [];
    $errors = [];
    
    foreach ($fileIds as $fileId) {
        try {
            $file = getFileForOptimization($pdo, $user, $fileId);
            if (!$file) {
                $errors[] = "File {$fileId} not found or access denied";
                continue;
            }
            
            $originalSize = $file['file_size'];
            $optimizationResult = optimizeSingleFile($file, $options);
            
            if ($optimizationResult['optimized']) {
                $newSize = filesize($file['file_path']);
                $savings = $originalSize - $newSize;
                $savingsPercent = round(($savings / $originalSize) * 100, 2);
                
                // Update database
                $stmt = $pdo->prepare("UPDATE files SET file_size = ? WHERE id = ?");
                $stmt->execute([$newSize, $fileId]);
                
                $optimized[] = [
                    'id' => $fileId,
                    'original_size' => $originalSize,
                    'new_size' => $newSize,
                    'savings' => $savings,
                    'savings_percent' => $savingsPercent,
                    'filename' => $file['original_filename']
                ];
                
                // Log optimization
                logFileAccess($pdo, $fileId, $user['id'], 'optimize', 'File optimized');
            }
        } catch (Exception $e) {
            $errors[] = "File {$fileId}: " . $e->getMessage();
        }
    }
    
    $totalSavings = array_sum(array_column($optimized, 'savings'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Optimization completed',
        'data' => [
            'optimized' => $optimized,
            'errors' => $errors,
            'total_files' => count($fileIds),
            'successful' => count($optimized),
            'failed' => count($errors),
            'total_savings' => $totalSavings,
            'formatted_savings' => formatBytes($totalSavings)
        ]
    ]);
}

/**
 * Generate thumbnails for images
 */
function generateThumbnails($pdo, $user, $input) {
    $fileIds = $input['file_ids'] ?? [];
    $size = $input['size'] ?? '150x150';
    
    if (empty($fileIds)) {
        throw new Exception('File IDs required', 400);
    }
    
    $generated = [];
    $errors = [];
    
    foreach ($fileIds as $fileId) {
        try {
            $file = getFileForOptimization($pdo, $user, $fileId);
            if (!$file) {
                $errors[] = "File {$fileId} not found";
                continue;
            }
            
            if (!in_array(strtolower($file['file_type']), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $errors[] = "File {$fileId} is not an image";
                continue;
            }
            
            $thumbnailPath = generateImageThumbnail($file['file_path'], $size);
            
            if ($thumbnailPath) {
                // Update database with thumbnail path
                $stmt = $pdo->prepare("UPDATE files SET thumbnail_path = ? WHERE id = ?");
                $stmt->execute([$thumbnailPath, $fileId]);
                
                $generated[] = [
                    'id' => $fileId,
                    'thumbnail_path' => $thumbnailPath,
                    'size' => $size,
                    'filename' => $file['original_filename']
                ];
            } else {
                $errors[] = "Failed to generate thumbnail for file {$fileId}";
            }
        } catch (Exception $e) {
            $errors[] = "File {$fileId}: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Thumbnail generation completed',
        'data' => [
            'generated' => $generated,
            'errors' => $errors,
            'total_files' => count($fileIds),
            'successful' => count($generated),
            'failed' => count($errors)
        ]
    ]);
}

/**
 * Find duplicate files
 */
function findDuplicateFiles($pdo, $user) {
    $threshold = intval($_GET['threshold'] ?? 1024 * 1024); // 1MB default
    
    $stmt = $pdo->prepare("
        SELECT 
            f1.id,
            f1.original_filename,
            f1.file_size,
            f1.file_type,
            f1.file_path,
            f1.uploaded_by,
            u.name as uploader_name,
            f1.created_at,
            GROUP_CONCAT(f2.id) as duplicate_ids,
            COUNT(*) as duplicate_count
        FROM files f1
        JOIN files f2 ON f1.file_size = f2.file_size AND f1.file_type = f2.file_type
        JOIN users u ON f1.uploaded_by = u.id
        WHERE f1.file_size >= ?
        AND (f1.uploaded_by = ? OR f1.is_public = TRUE)
        GROUP BY f1.id, f1.original_filename, f1.file_size, f1.file_type, f1.file_path, f1.uploaded_by, u.name, f1.created_at
        HAVING duplicate_count > 1
        ORDER BY f1.file_size DESC, duplicate_count DESC
    ");
    $stmt->execute([$threshold, $user['id']]);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'duplicates' => $duplicates,
            'threshold' => $threshold,
            'total_groups' => count($duplicates)
        ]
    ]);
}

/**
 * Helper Functions
 */

function getFileForOptimization($pdo, $user, $fileId) {
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as uploader_name
        FROM files f
        JOIN users u ON f.uploaded_by = u.id
        WHERE f.id = ? AND (f.uploaded_by = ? OR f.is_public = TRUE OR ? = 'admin')
    ");
    $stmt->execute([$fileId, $user['id'], $user['role']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function optimizeSingleFile($file, $options) {
    $originalPath = $file['file_path'];
    $fileType = strtolower($file['file_type']);
    
    switch ($fileType) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp':
            return optimizeImage($originalPath, $options);
        case 'pdf':
            return optimizePDF($originalPath, $options);
        default:
            return ['optimized' => false, 'reason' => 'File type not supported for optimization'];
    }
}

function optimizeImage($path, $options) {
    if (!file_exists($path)) {
        return ['optimized' => false, 'reason' => 'File not found'];
    }
    
    $maxWidth = $options['max_width'] ?? 1920;
    $maxHeight = $options['max_height'] ?? 1080;
    $quality = $options['quality'] ?? 85;
    $progressive = $options['progressive'] ?? true;
    
    $imageInfo = getimagesize($path);
    if (!$imageInfo) {
        return ['optimized' => false, 'reason' => 'Invalid image file'];
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Check if resizing is needed
    if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
        return ['optimized' => false, 'reason' => 'Image already optimized size'];
    }
    
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($path);
            if (!$source) return ['optimized' => false, 'reason' => 'Failed to process JPEG'];
            
            $newWidth = min($originalWidth, $maxWidth);
            $newHeight = min($originalHeight, $maxHeight);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            if ($progressive) {
                imageinterlace($resized, true);
            }
            
            $result = imagejpeg($resized, $path, $quality);
            imagedestroy($source);
            imagedestroy($resized);
            
            return ['optimized' => $result, 'new_dimensions' => "{$newWidth}x{$newHeight}"];
            
        case 'png':
            $source = imagecreatefrompng($path);
            if (!$source) return ['optimized' => false, 'reason' => 'Failed to process PNG'];
            
            $newWidth = min($originalWidth, $maxWidth);
            $newHeight = min($originalHeight, $maxHeight);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            $result = imagepng($resized, $path, 9); // PNG compression level 9
            imagedestroy($source);
            imagedestroy($resized);
            
            return ['optimized' => $result, 'new_dimensions' => "{$newWidth}x{$newHeight}"];
    }
    
    return ['optimized' => false, 'reason' => 'Unsupported image format'];
}

function optimizePDF($path, $options) {
    // PDF optimization would require external tools like Ghostscript
    // This is a placeholder implementation
    return ['optimized' => false, 'reason' => 'PDF optimization not yet implemented'];
}

function generateImageThumbnail($path, $size) {
    $dimensions = explode('x', $size);
    if (count($dimensions) !== 2) {
        return false;
    }
    
    $thumbWidth = intval($dimensions[0]);
    $thumbHeight = intval($dimensions[1]);
    
    $imageInfo = getimagesize($path);
    if (!$imageInfo) {
        return false;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // Calculate thumbnail dimensions maintaining aspect ratio
    $ratio = min($thumbWidth / $originalWidth, $thumbHeight / $originalHeight);
    $newWidth = intval($originalWidth * $ratio);
    $newHeight = intval($originalHeight * $ratio);
    
    $thumbPath = dirname($path) . '/thumbs/' . pathinfo($path, PATHINFO_FILENAME) . "_{$size}." . $extension;
    
    // Create thumbs directory if it doesn't exist
    $thumbDir = dirname($thumbPath);
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($path);
            if (!$source) return false;
            
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            $result = imagejpeg($thumbnail, $thumbPath, 80);
            imagedestroy($source);
            imagedestroy($thumbnail);
            return $result ? $thumbPath : false;
            
        case 'png':
            $source = imagecreatefrompng($path);
            if (!$source) return false;
            
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
            
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            $result = imagepng($thumbnail, $thumbPath);
            imagedestroy($source);
            imagedestroy($thumbnail);
            return $result ? $thumbPath : false;
    }
    
    return false;
}

function logFileAccess($pdo, $fileId, $userId, $action, $description = '') {
    $stmt = $pdo->prepare("
        INSERT INTO file_access_log (file_id, user_id, action, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $fileId,
        $userId,
        $action,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
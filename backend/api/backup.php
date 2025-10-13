<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    $db = getDBConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($db, $user_id);
            break;
        case 'POST':
            handlePost($db, $user_id);
            break;
        case 'DELETE':
            handleDelete($db, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($db, $user_id) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getBackups($db);
            break;
        case 'settings':
            getSettings($db);
            break;
        case 'download':
            downloadBackup($db, $_GET['id'] ?? null);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getBackups($db) {
    $query = "SELECT b.*, u.name as created_by_name
              FROM backups b
              LEFT JOIN users u ON b.created_by = u.id
              ORDER BY b.created_at DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format file sizes
    foreach ($backups as &$backup) {
        $backup['file_size_formatted'] = formatBytes($backup['file_size']);
    }
    
    echo json_encode(['backups' => $backups]);
}

function getSettings($db) {
    $stmt = $db->prepare("SELECT * FROM backup_settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        $settings = [
            'auto_backup_enabled' => true,
            'backup_frequency' => 'daily',
            'backup_time' => '02:00:00',
            'retention_days' => 30,
            'max_backups' => 10,
            'include_uploads' => false
        ];
    }
    
    echo json_encode(['settings' => $settings]);
}

function downloadBackup($db, $backup_id) {
    if (!$backup_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ? AND status = 'completed'");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        return;
    }
    
    $filepath = $backup['filepath'];
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found on disk']);
        return;
    }
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

function handlePost($db, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            createBackup($db, $user_id, $data);
            break;
        case 'restore':
            restoreBackup($db, $user_id, $data);
            break;
        case 'update_settings':
            updateSettings($db, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function createBackup($db, $user_id, $data) {
    $backup_type = $data['backup_type'] ?? 'manual';
    $include_uploads = $data['include_uploads'] ?? false;
    
    // Create backup directory if it doesn't exist
    $backup_dir = __DIR__ . '/../../database/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "foward_lms_backup_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;
    
    // Create backup record
    $query = "INSERT INTO backups (filename, filepath, file_size, backup_type, status, created_by)
              VALUES (?, ?, 0, ?, 'in_progress', ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$filename, $filepath, $backup_type, $user_id]);
    $backup_id = $db->lastInsertId();
    
    try {
        // Get database credentials
        $config = require __DIR__ . '/../config/database.php';
        
        // Get all tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        // Generate SQL dump
        $sql_dump = generateSQLDump($db, $tables);
        
        // Write to file
        file_put_contents($filepath, $sql_dump);
        $file_size = filesize($filepath);
        
        // Update backup record
        $query = "UPDATE backups 
                  SET status = 'completed', file_size = ?, tables_included = ?, completed_at = NOW()
                  WHERE id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$file_size, implode(',', $tables), $backup_id]);
        
        // Update last backup time
        $db->query("UPDATE backup_settings SET last_backup_at = NOW()");
        
        // Clean old backups
        cleanOldBackups($db, $backup_dir);
        
        echo json_encode([
            'success' => true,
            'backup_id' => $backup_id,
            'filename' => $filename,
            'file_size' => formatBytes($file_size),
            'message' => 'Backup created successfully'
        ]);
        
    } catch (Exception $e) {
        // Update backup record with error
        $query = "UPDATE backups SET status = 'failed', error_message = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$e->getMessage(), $backup_id]);
        
        throw $e;
    }
}

function generateSQLDump($db, $tables) {
    $sql_dump = "-- FowarD LMS Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $result = $db->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $sql_dump .= "-- Table: $table\n";
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $db->query("SELECT * FROM `$table`");
        $num_rows = $result->rowCount();
        
        if ($num_rows > 0) {
            $sql_dump .= "-- Data for table: $table\n";
            
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                
                $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            
            $sql_dump .= "\n";
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    return $sql_dump;
}

function restoreBackup($db, $user_id, $data) {
    $backup_id = $data['backup_id'] ?? null;
    
    if (!$backup_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ? AND status = 'completed'");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        return;
    }
    
    $filepath = $backup['filepath'];
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }
    
    try {
        // Read SQL file
        $sql = file_get_contents($filepath);
        
        // Execute SQL statements
        $db->exec($sql);
        
        echo json_encode([
            'success' => true,
            'message' => 'Database restored successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to restore backup: ' . $e->getMessage());
    }
}

function updateSettings($db, $data) {
    $query = "UPDATE backup_settings 
              SET auto_backup_enabled = ?, backup_frequency = ?, backup_time = ?,
                  retention_days = ?, max_backups = ?, include_uploads = ?,
                  notification_email = ?, updated_at = NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['auto_backup_enabled'] ?? true,
        $data['backup_frequency'] ?? 'daily',
        $data['backup_time'] ?? '02:00:00',
        $data['retention_days'] ?? 30,
        $data['max_backups'] ?? 10,
        $data['include_uploads'] ?? false,
        $data['notification_email'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup settings updated successfully'
    ]);
}

function handleDelete($db, $user_id) {
    $backup_id = $_GET['id'] ?? null;
    
    if (!$backup_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ?");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        return;
    }
    
    // Delete file
    if (file_exists($backup['filepath'])) {
        unlink($backup['filepath']);
    }
    
    // Delete record
    $stmt = $db->prepare("DELETE FROM backups WHERE id = ?");
    $stmt->execute([$backup_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup deleted successfully'
    ]);
}

function cleanOldBackups($db, $backup_dir) {
    // Get settings
    $stmt = $db->query("SELECT retention_days, max_backups FROM backup_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        return;
    }
    
    // Delete backups older than retention period
    $retention_date = date('Y-m-d H:i:s', strtotime("-{$settings['retention_days']} days"));
    
    $query = "SELECT * FROM backups WHERE created_at < ? AND status = 'completed'";
    $stmt = $db->prepare($query);
    $stmt->execute([$retention_date]);
    $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($old_backups as $backup) {
        if (file_exists($backup['filepath'])) {
            unlink($backup['filepath']);
        }
        $db->prepare("DELETE FROM backups WHERE id = ?")->execute([$backup['id']]);
    }
    
    // Keep only max_backups most recent
    $query = "SELECT * FROM backups WHERE status = 'completed' ORDER BY created_at DESC";
    $stmt = $db->query($query);
    $all_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($all_backups) > $settings['max_backups']) {
        $to_delete = array_slice($all_backups, $settings['max_backups']);
        
        foreach ($to_delete as $backup) {
            if (file_exists($backup['filepath'])) {
                unlink($backup['filepath']);
            }
            $db->prepare("DELETE FROM backups WHERE id = ?")->execute([$backup['id']]);
        }
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

<?php
/**
 * Automatic Backup Script
 * Run this via cron job based on backup frequency settings
 * Example crontab: 0 2 * * * php /path/to/backend/cron/auto-backup.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    
    // Get backup settings
    $stmt = $db->query("SELECT * FROM backup_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || !$settings['auto_backup_enabled']) {
        echo "Automatic backups are disabled\n";
        exit;
    }
    
    // Check if backup is due
    $last_backup = $settings['last_backup_at'];
    $frequency = $settings['backup_frequency'];
    
    $should_backup = false;
    
    if (!$last_backup) {
        $should_backup = true;
    } else {
        $last_backup_time = strtotime($last_backup);
        $now = time();
        
        switch ($frequency) {
            case 'daily':
                $should_backup = ($now - $last_backup_time) >= 86400; // 24 hours
                break;
            case 'weekly':
                $should_backup = ($now - $last_backup_time) >= 604800; // 7 days
                break;
            case 'monthly':
                $should_backup = ($now - $last_backup_time) >= 2592000; // 30 days
                break;
        }
    }
    
    if (!$should_backup) {
        echo "Backup not due yet\n";
        exit;
    }
    
    // Trigger backup via API
    $backup_data = [
        'action' => 'create',
        'backup_type' => 'automatic',
        'include_uploads' => $settings['include_uploads']
    ];
    
    // Create backup directory if it doesn't exist
    $backup_dir = __DIR__ . '/../../database/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "foward_lms_auto_backup_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;
    
    // Create backup record
    $query = "INSERT INTO backups (filename, filepath, file_size, backup_type, status)
              VALUES (?, ?, 0, 'automatic', 'in_progress')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$filename, $filepath]);
    $backup_id = $db->lastInsertId();
    
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
    
    echo "Automatic backup completed successfully: $filename (" . formatBytes($file_size) . ")\n";
    
    // Send notification email if configured
    if ($settings['notification_email']) {
        $subject = "FowarD LMS - Automatic Backup Completed";
        $message = "Automatic backup completed successfully.\n\n";
        $message .= "Filename: $filename\n";
        $message .= "Size: " . formatBytes($file_size) . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        mail($settings['notification_email'], $subject, $message);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Auto-backup error: " . $e->getMessage());
    
    if (isset($backup_id)) {
        $query = "UPDATE backups SET status = 'failed', error_message = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$e->getMessage(), $backup_id]);
    }
}

function generateSQLDump($db, $tables) {
    $sql_dump = "-- FowarD LMS Automatic Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $row[1] . ";\n\n";
        
        $result = $db->query("SELECT * FROM `$table`");
        
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
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    return $sql_dump;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

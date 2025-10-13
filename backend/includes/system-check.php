<?php
/**
 * System Health Check Utility
 * Checks system requirements and configuration
 */

class SystemCheck {
    private static $requirements = [
        'php_version' => '7.4.0',
        'extensions' => ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl']
    ];
    
    public static function checkAll() {
        $results = [
            'php_version' => self::checkPHPVersion(),
            'extensions' => self::checkExtensions(),
            'directories' => self::checkDirectories(),
            'database' => self::checkDatabase(),
            'overall' => true
        ];
        
        foreach ($results as $key => $value) {
            if ($key !== 'overall' && is_array($value)) {
                foreach ($value as $item) {
                    if (!$item['status']) {
                        $results['overall'] = false;
                        break 2;
                    }
                }
            } elseif ($key !== 'overall' && !$value['status']) {
                $results['overall'] = false;
                break;
            }
        }
        
        return $results;
    }
    
    public static function checkPHPVersion() {
        $current = PHP_VERSION;
        $required = self::$requirements['php_version'];
        $status = version_compare($current, $required, '>=');
        
        return [
            'status' => $status,
            'current' => $current,
            'required' => $required,
            'message' => $status ? "PHP version is compatible" : "PHP version is too old"
        ];
    }
    
    public static function checkExtensions() {
        $results = [];
        foreach (self::$requirements['extensions'] as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => $ext,
                'status' => $loaded,
                'message' => $loaded ? "Extension loaded" : "Extension not found"
            ];
        }
        return $results;
    }
    
    public static function checkDirectories() {
        $dirs = [
            'config' => __DIR__ . '/../../backend/config',
            'uploads' => __DIR__ . '/../../uploads',
            'cache' => __DIR__ . '/../../cache',
            'logs' => __DIR__ . '/../../logs'
        ];
        
        $results = [];
        foreach ($dirs as $name => $path) {
            if (!file_exists($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_writable($path);
            $results[] = [
                'name' => $name,
                'path' => $path,
                'status' => $writable,
                'message' => $writable ? "Directory is writable" : "Directory is not writable"
            ];
        }
        return $results;
    }
    
    public static function checkDatabase() {
        try {
            require_once __DIR__ . '/../config/db.php';
            global $pdo;
            
            if ($pdo) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                return [
                    'status' => true,
                    'message' => "Database connection successful"
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => "Database connection failed: " . $e->getMessage()
            ];
        }
        
        return [
            'status' => false,
            'message' => "Database not configured"
        ];
    }
    
    public static function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }
}
?>

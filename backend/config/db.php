<?php
/**
 * Forward LMS Database Configuration
 * Optimized for InfinityFree MySQL hosting
 * 
 * IMPORTANT: Update the database credentials below with your InfinityFree values:
 * - Host: Your InfinityFree MySQL hostname
 * - Database: Your database name (if0_XXXXXXX)
 * - Username: Your database username (if0_XXXXXXX)  
 * - Password: Your database password
 */

// Database configuration - UPDATE THESE WITH YOUR INFINITYFREE DETAILS
$host = 'sql107.infinityfree.com';
$dbname = 'if0_40203219_Okaris';
$username = 'if0_40203219';
$password = 'AoJxsw0Lq8wykF';

// Connection options optimized for shared hosting
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_TIMEOUT => 30,  // Connection timeout for shared hosting
    PDO::ATTR_PERSISTENT => false  // Disable persistent connections on shared hosting
];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        $options
    );
    
    // Set MySQL session variables for better performance
    $pdo->exec("SET SESSION sql_mode=''");
    $pdo->exec("SET SESSION time_zone='+00:00'");
    
} catch(PDOException $e) {
    // Log error for debugging (in production, log to file)
    $error_message = date('Y-m-d H:i:s') . " - Database connection failed: " . $e->getMessage() . "\n";
    error_log($error_message, 3, __DIR__ . '/../logs/database_errors.log');
    
    // Show user-friendly error message
    die("Unable to connect to database. Please check your configuration and try again. If the problem persists, contact support.");
}

// Database connection test function
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch(PDOException $e) {
        return false;
    }
}

// Helper function for database queries with error logging
function db_query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        // Log the failed query for debugging
        $error_message = date('Y-m-d H:i:s') . " - Query failed: " . $e->getMessage() . " | SQL: " . $sql . "\n";
        error_log($error_message, 3, __DIR__ . '/../logs/query_errors.log');
        throw new Exception("Database query failed. Please try again or contact support if the issue persists.");
    }
}

// Helper function to get the last insert ID
function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

// Helper function to begin a transaction
function beginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

// Helper function to commit a transaction
function commitTransaction() {
    global $pdo;
    return $pdo->commit();
}

// Helper function to rollback a transaction
function rollbackTransaction() {
    global $pdo;
    return $pdo->rollback();
}

// Test database connection on include (for debugging)
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    if (!testDatabaseConnection()) {
        error_log("Database connection test failed!");
    }
}

/**
 * Database wrapper class for backward compatibility
 * Provides static methods and instance management
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function select($sql, $params = []) {
        $stmt = db_query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = db_query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: [];
    }
    
    public function execute($sql, $params = []) {
        return db_query($sql, $params);
    }
    
    public function lastInsertId() {
        return getLastInsertId();
    }
    
    public function setUserContext($userId) {
        // Placeholder for user context - can be used for RLS in future
        $_SESSION['db_user_context'] = $userId;
    }
    
    public function beginTransaction() {
        return beginTransaction();
    }
    
    public function commit() {
        return commitTransaction();
    }
    
    public function rollback() {
        return rollbackTransaction();
    }
}

/**
 * Global function for backward compatibility
 * Returns PDO instance
 */
function getDBConnection() {
    global $pdo;
    return $pdo;
}
?>

<?php
/**
 * Forward LMS Database Configuration - Infinity Free
 * YOUR ACTUAL CREDENTIALS - Replace this in your htdocs/config/database.php
 */

// Your Infinity Free Database Credentials
$host = 'sql107.infinityfree.com';     // Your actual host
$dbname = 'if0_40203219_Okaris';       // Your actual database name
$username = 'if0_40203219';            // Your actual username
$password = 'AoJxsw0Lq8wykF';          // Your actual password

// Create global PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions for your existing code
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Database class wrapper for your existing code
class Database {
    private static $pdo;
    
    public static function connect() {
        global $pdo;
        return $pdo;
    }
    
    public static function query($sql, $params = []) {
        try {
            global $pdo;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }
    
    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }
    
    public static function execute($sql, $params = []) {
        return self::query($sql, $params)->rowCount();
    }
    
    public static function lastInsertId() {
        global $pdo;
        return $pdo->lastInsertId();
    }
}

// Create global database instance (for your existing code)
try {
    $db = new Database();
} catch (Exception $e) {
    // Handle gracefully for existing code
}
?>
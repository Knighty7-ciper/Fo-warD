<?php
/**
 * Authentication System
 * Forward LMS User Authentication and Authorization
 */

class Auth {
    private $db;
    private $jwtSecret;
    private $sessionName = 'forward_lms_user';
    
    public function __construct($database) {
        $this->db = $database;
        $this->jwtSecret = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * User Registration
     */
    public function register($name, $email, $password, $role = 'student') {
        // Validate input
        if (empty($name) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        
        // Check if email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE email = ?", 
            [$email]
        );
        
        if ($existing) {
            throw new Exception("Email already registered");
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $sql = "INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
        $params = [$name, $email, $hashedPassword, $role];
        
        $this->db->execute($sql, $params);
        $userId = $this->db->lastInsertId();
        
        return $this->login($email, $password);
    }
    
    /**
     * User Login
     */
    public function login($email, $password) {
        // Get user by email
        $user = $this->db->fetch(
            "SELECT id, name, email, password, role, status FROM users WHERE email = ?", 
            [$email]
        );
        
        if (!$user) {
            throw new Exception("Invalid email or password");
        }
        
        if ($user['status'] !== 'active') {
            throw new Exception("Account is not active. Please contact administrator.");
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Invalid email or password");
        }
        
        // Update last login
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?", 
            [$user['id']]
        );
        
        // Set session
        $_SESSION[$this->sessionName] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        
        // Generate JWT token
        $token = $this->generateJWT($user);
        
        return [
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token,
            'session_id' => session_id()
        ];
    }
    
    /**
     * User Logout
     */
    public function logout() {
        unset($_SESSION[$this->sessionName]);
        session_destroy();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION[$this->sessionName]);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return $_SESSION[$this->sessionName];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole($roles) {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], $roles);
    }
    
    /**
     * Require authentication
     */
    public function requireAuth($redirect = '/frontend/login.php') {
        if (!$this->isLoggedIn()) {
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            } else {
                header("Location: $redirect");
                exit;
            }
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirect = '/frontend/login.php') {
        $this->requireAuth($redirect);
        
        if (!$this->hasRole($role)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                exit;
            } else {
                header("HTTP/1.0 403 Forbidden");
                exit;
            }
        }
    }
    
    /**
     * Generate JWT Token
     */
    private function generateJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60) // 7 days
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->jwtSecret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify JWT Token
     */
    public function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$header, $payload, $signature] = $parts;
        
        // Verify signature
        $validSignature = hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true);
        $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        
        if (!hash_equals($signature, $validSignature)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Get token from request
     */
    public function getTokenFromRequest() {
        $headers = getallheaders();
        
        // Check Authorization header
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        // Check query parameter
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        // Check POST parameter
        if (isset($_POST['token'])) {
            return $_POST['token'];
        }
        
        return null;
    }
    
    /**
     * Authenticate using JWT token
     */
    public function authenticateWithJWT() {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        $payload = $this->verifyJWT($token);
        if (!$payload) {
            return null;
        }
        
        // Get user from database
        $user = $this->db->fetch(
            "SELECT id, name, email, role, status FROM users WHERE id = ?", 
            [$payload['user_id']]
        );
        
        if (!$user || $user['status'] !== 'active') {
            return null;
        }
        
        return $user;
    }
}

// Initialize auth instance
require_once __DIR__ . '/database.php';
$auth = new Auth($db);
?>
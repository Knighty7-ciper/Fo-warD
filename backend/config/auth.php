<?php

class Auth {
    private static $session_lifetime = 3600;
    private static $session_name = 'FORWARD_SESSION';

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0);

            session_name(self::$session_name);
            session_start();

            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
                $_SESSION['created_at'] = time();
            }

            if (isset($_SESSION['created_at']) &&
                (time() - $_SESSION['created_at'] > self::$session_lifetime)) {
                self::logout();
            }
        }
    }

    public static function login($user) {
        self::init();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';
        $_SESSION['created_at'] = time();

        $db = Database::getInstance();
        $db->setUserContext($user['id']);

        self::logAudit($user['id'], 'login', 'user', $user['id']);

        return true;
    }

    public static function logout() {
        self::init();

        if (self::isAuthenticated()) {
            self::logAudit($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id']);
        }

        $_SESSION = [];

        if (isset($_COOKIE[self::$session_name])) {
            setcookie(self::$session_name, '', time() - 3600, '/');
        }

        session_destroy();

        return true;
    }

    public static function isAuthenticated() {
        self::init();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: /frontend/login.php');
            exit();
        }
    }

    public static function requireRole($allowed_roles) {
        self::requireAuth();

        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }

        if (!in_array($_SESSION['role'], $allowed_roles)) {
            header('HTTP/1.1 403 Forbidden');
            header('Location: /frontend/403.php');
            exit();
        }
    }

    public static function getUser() {
        self::init();

        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'avatar_url' => $_SESSION['avatar_url'] ?? ''
        ];
    }

    public static function getUserId() {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getUserRole() {
        self::init();
        return $_SESSION['role'] ?? null;
    }

    public static function isAdmin() {
        return self::getUserRole() === 'admin';
    }

    public static function isTeacher() {
        return self::getUserRole() === 'teacher';
    }

    public static function isStudent() {
        return self::getUserRole() === 'student';
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public static function logAudit($user_id, $action, $entity_type, $entity_id, $details = []) {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            $sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
                    VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)";

            $db->query($sql, [
                ':user_id' => $user_id,
                ':action' => $action,
                ':entity_type' => $entity_type,
                ':entity_id' => $entity_id,
                ':details' => json_encode($details),
                ':ip_address' => $ip
            ]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }

    public static function generateCSRFToken() {
        self::init();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        self::init();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

function authenticate() {
    Auth::init();
    
    if (!Auth::isAuthenticated()) {
        return null;
    }
    
    $user_id = Auth::getUserId();
    if (!$user_id) {
        return null;
    }
    
    // Get full user data from database
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, role, avatar, bio, points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return $user;
}

// Helper function to require authentication
function require_auth($allowed_roles = null) {
    $user = authenticate();
    
    if (!$user) {
        header('Location: /frontend/auth/login.php');
        exit;
    }
    
    if ($allowed_roles !== null) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!in_array($user['role'], $allowed_roles)) {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    return $user;
}

?>

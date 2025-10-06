<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../shared/utils/sanitize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

$email = Sanitize::email($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    error_response('Email and password are required');
}

try {
    $db = Database::getInstance();

    $sql = "SELECT * FROM users WHERE email = :email AND status = 'active'";
    $user = $db->selectOne($sql, [':email' => $email]);

    if (!$user) {
        error_response('Invalid credentials');
    }

    if (!Auth::verifyPassword($password, $user['password_hash'])) {
        error_response('Invalid credentials');
    }

    Auth::login($user);

    $redirect_url = match($user['role']) {
        'admin' => '/frontend/admin/dashboard.php',
        'teacher' => '/frontend/teacher/dashboard.php',
        'student' => '/frontend/student/dashboard.php',
        default => '/frontend/index.php'
    };

    success_response(['redirect_url' => $redirect_url], 'Login successful');

} catch (Exception $e) {
    log_message("Login error: " . $e->getMessage(), 'ERROR');
    error_response('Login failed', 500);
}
?>

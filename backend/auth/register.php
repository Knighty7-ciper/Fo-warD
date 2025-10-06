<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../shared/utils/sanitize.php';
require_once __DIR__ . '/../../shared/utils/captcha.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

$email = Sanitize::email($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$first_name = Sanitize::string($_POST['first_name'] ?? '');
$last_name = Sanitize::string($_POST['last_name'] ?? '');
$role = Sanitize::string($_POST['role'] ?? 'student');
$captcha = $_POST['captcha'] ?? '';

if (!$email) {
    error_response('Valid email is required');
}

if (empty($password) || strlen($password) < 8) {
    error_response('Password must be at least 8 characters long');
}

if ($password !== $confirm_password) {
    error_response('Passwords do not match');
}

if (empty($first_name) || empty($last_name)) {
    error_response('First name and last name are required');
}

if (!in_array($role, ['student', 'teacher'])) {
    error_response('Invalid role selected');
}

if (!Captcha::verify($captcha)) {
    error_response('Invalid CAPTCHA');
}

try {
    $db = Database::getInstance();

    $check_sql = "SELECT id FROM users WHERE email = :email";
    $existing = $db->selectOne($check_sql, [':email' => $email]);

    if ($existing) {
        error_response('Email already registered');
    }

    $password_hash = Auth::hashPassword($password);

    $insert_sql = "INSERT INTO users (email, password_hash, first_name, last_name, role, status)
                   VALUES (:email, :password_hash, :first_name, :last_name, :role, 'active')
                   RETURNING id";

    $result = $db->query($insert_sql, [
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':role' => $role
    ]);

    $new_user = $result->fetch();

    if (!$new_user) {
        error_response('Registration failed');
    }

    $user_data = [
        'id' => $new_user['id'],
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role,
        'avatar_url' => ''
    ];

    Auth::login($user_data);

    $redirect_url = $role === 'teacher'
        ? '/frontend/teacher/dashboard.php'
        : '/frontend/student/dashboard.php';

    success_response(['redirect_url' => $redirect_url], 'Registration successful');

} catch (Exception $e) {
    log_message("Registration error: " . $e->getMessage(), 'ERROR');
    error_response('Registration failed', 500);
}
?>

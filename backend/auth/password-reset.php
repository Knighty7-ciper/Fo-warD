<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../shared/utils/sanitize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

$action = $_POST['action'] ?? '';

if ($action === 'request') {
    $email = Sanitize::email($_POST['email'] ?? '');

    if (!$email) {
        error_response('Valid email is required');
    }

    try {
        $db = Database::getInstance();

        $sql = "SELECT id, email, first_name FROM users WHERE email = :email AND status = 'active'";
        $user = $db->selectOne($sql, [':email' => $email]);

        if (!$user) {
            success_response([], 'If the email exists, a reset link has been sent');
        }

        $token = Auth::generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $update_sql = "UPDATE users
                       SET reset_token = :token, reset_token_expires = :expires_at
                       WHERE id = :user_id";

        $db->update($update_sql, [
            ':token' => hash('sha256', $token),
            ':expires_at' => $expires_at,
            ':user_id' => $user['id']
        ]);

        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/frontend/reset-password.php?token={$token}";

        $message = "
            <h2>Password Reset Request</h2>
            <p>Hello {$user['first_name']},</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='{$reset_link}'>{$reset_link}</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";

        send_email($user['email'], 'Password Reset Request', $message);

        success_response([], 'If the email exists, a reset link has been sent');

    } catch (Exception $e) {
        log_message("Password reset request error: " . $e->getMessage(), 'ERROR');
        error_response('Password reset request failed', 500);
    }

} elseif ($action === 'reset') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        error_response('Invalid reset token');
    }

    if (empty($password) || strlen($password) < 8) {
        error_response('Password must be at least 8 characters long');
    }

    if ($password !== $confirm_password) {
        error_response('Passwords do not match');
    }

    try {
        $db = Database::getInstance();

        $token_hash = hash('sha256', $token);

        $sql = "SELECT id FROM users
                WHERE reset_token = :token
                AND reset_token_expires > NOW()
                AND status = 'active'";

        $user = $db->selectOne($sql, [':token' => $token_hash]);

        if (!$user) {
            error_response('Invalid or expired reset token');
        }

        $password_hash = Auth::hashPassword($password);

        $update_sql = "UPDATE users
                       SET password_hash = :password_hash,
                           reset_token = NULL,
                           reset_token_expires = NULL
                       WHERE id = :user_id";

        $db->update($update_sql, [
            ':password_hash' => $password_hash,
            ':user_id' => $user['id']
        ]);

        success_response([], 'Password reset successful');

    } catch (Exception $e) {
        log_message("Password reset error: " . $e->getMessage(), 'ERROR');
        error_response('Password reset failed', 500);
    }

} else {
    error_response('Invalid action');
}
?>

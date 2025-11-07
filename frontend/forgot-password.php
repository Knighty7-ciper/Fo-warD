<?php
$page_title = 'Forgot Password - Reset Your Account';
$body_class = 'auth-page';

require_once __DIR__ . '/../shared/templates/header.php';

// Handle password reset request
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save reset token to database
                $updateStmt = $db->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $updateStmt->execute([$token, $expires, $user['id']]);
                
                // In a real application, you would send an email here
                // For now, we'll show the token for testing purposes
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/frontend/reset-password.php?token=" . $token;
                $success = true;
                $message = "Password reset instructions have been sent to your email address. For testing purposes, your reset link is: <strong>" . $reset_link . "</strong>";
            } else {
                // Don't reveal if email exists - for security
                $success = true;
                $message = "If an account with that email exists, password reset instructions have been sent.";
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Reset Password</h1>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
            
            <div class="auth-actions">
                <a href="/frontend/login.php" class="btn btn-primary">Back to Login</a>
                <a href="/" class="btn btn-secondary">Go to Homepage</a>
            </div>
        <?php else: ?>
            <form method="POST" class="auth-form" id="forgot-password-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Send Reset Link
                </button>
            </form>

            <div class="auth-links">
                <p>Remember your password? <a href="/frontend/login.php">Sign in</a></p>
                <p>Don't have an account? <a href="/frontend/register.php">Create one</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.auth-container {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.auth-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 3rem 2rem;
    width: 100%;
    max-width: 400px;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header h1 {
    font-size: 1.75rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.auth-header p {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary-color, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    text-align: center;
    display: inline-block;
}

.btn-primary {
    background-color: var(--primary-color, #2563eb);
    color: white;
    width: 100%;
}

.btn-primary:hover {
    background-color: var(--primary-hover, #1d4ed8);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
}

.btn-full {
    width: 100%;
}

.auth-links {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.auth-links p {
    margin: 0.5rem 0;
    color: #6b7280;
    font-size: 0.9rem;
}

.auth-links a {
    color: var(--primary-color, #2563eb);
    text-decoration: none;
    font-weight: 500;
}

.auth-links a:hover {
    text-decoration: underline;
}

.auth-actions {
    text-align: center;
    margin-top: 1.5rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

@media (max-width: 480px) {
    .auth-card {
        padding: 2rem 1.5rem;
        margin: 1rem;
    }
    
    .auth-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .auth-actions .btn {
        width: 200px;
    }
}
</style>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>
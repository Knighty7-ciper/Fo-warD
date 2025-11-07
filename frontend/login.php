<?php
session_start();

require_once __DIR__ . '/../backend/config/auth.php';

if (Auth::isAuthenticated()) {
    $role = Auth::getUserRole();
    $redirect = match($role) {
        'admin' => '/frontend/admin/dashboard.php',
        'teacher' => '/frontend/teacher/dashboard.php',
        'student' => '/frontend/student/dashboard.php',
        default => '/frontend/index.php'
    };
    header("Location: {$redirect}");
    exit();
}

$page_title = 'Login - Forward LMS';
$body_class = 'login-page';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-wrapper">
                <h1>Welcome Back</h1>
                <p>Sign in to access your account</p>

                <form id="login-form" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required class="form-control">
                    </div>

                    <div class="form-group form-flex">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me"> Remember me
                        </label>
                        <a href="/frontend/forgot-password.php" class="link-small">Forgot password?</a>
                    </div>

                    <div id="error-message" class="error-message" style="display: none;"></div>

                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                </form>

                <p class="auth-footer">
                    Don't have an account?
                    <a href="/frontend/register.php">Sign up</a>
                </p>
            </div>

            <div class="auth-image">
                <img src="/frontend/assets/images/login-illustration.svg" alt="Login">
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const errorDiv = document.getElementById('error-message');

    try {
        const response = await fetch('/backend/auth/login.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.data.redirect_url;
        } else {
            errorDiv.textContent = data.error || 'Login failed';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.style.display = 'block';
    }
});
</script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>

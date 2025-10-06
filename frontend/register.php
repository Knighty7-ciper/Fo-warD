<?php
session_start();

require_once __DIR__ . '/../backend/config/auth.php';
require_once __DIR__ . '/../shared/utils/captcha.php';

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

$page_title = 'Register - Forward LMS';
$body_class = 'register-page';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-wrapper">
                <h1>Create Account</h1>
                <p>Join Forward LMS and start learning today</p>

                <form id="register-form" class="auth-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="role">I want to join as</label>
                        <select id="role" name="role" class="form-control">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required class="form-control" minlength="8">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="captcha">Enter CAPTCHA</label>
                        <div class="captcha-wrapper">
                            <?php echo Captcha::renderHTML(); ?>
                            <input type="text" id="captcha" name="captcha" required class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" required>
                            I agree to the <a href="/frontend/terms.php" target="_blank">Terms & Conditions</a>
                        </label>
                    </div>

                    <div id="error-message" class="error-message" style="display: none;"></div>

                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                </form>

                <p class="auth-footer">
                    Already have an account?
                    <a href="/frontend/login.php">Sign in</a>
                </p>
            </div>

            <div class="auth-image">
                <img src="/frontend/assets/images/register-illustration.svg" alt="Register">
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('register-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const errorDiv = document.getElementById('error-message');
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (password !== confirmPassword) {
        errorDiv.textContent = 'Passwords do not match';
        errorDiv.style.display = 'block';
        return;
    }

    try {
        const response = await fetch('/backend/auth/register.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.data.redirect_url;
        } else {
            errorDiv.textContent = data.error || 'Registration failed';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.style.display = 'block';
    }
});

function refreshCaptcha() {
    document.getElementById('captcha-image').src = '/backend/api/captcha/image.php?' + Date.now();
}
</script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>

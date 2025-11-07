<?php
$page_title = '403 Forbidden - Access Denied';
$body_class = 'error-page';

// Include header
require_once __DIR__ . '/../shared/templates/header.php';
?>

<div class="error-container">
    <div class="error-content">
        <div class="error-code">403</div>
        <h1 class="error-title">Access Forbidden</h1>
        <p class="error-message">
            Sorry, you don't have permission to access this page or resource.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">Go to Homepage</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
        
        <?php if (Auth::isAuthenticated()): ?>
            <div class="user-info">
                <p>You are logged in as: <strong><?php echo htmlspecialchars(Auth::getUser()['first_name'] . ' ' . Auth::getUser()['last_name']); ?></strong></p>
                <p>Your role: <strong><?php echo htmlspecialchars(Auth::getUserRole()); ?></strong></p>
            </div>
        <?php else: ?>
            <div class="login-prompt">
                <p>Need access? Please <a href="/frontend/login.php">log in</a> or <a href="/frontend/register.php">create an account</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.error-container {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
}

.error-content {
    max-width: 500px;
}

.error-code {
    font-size: 8rem;
    font-weight: bold;
    color: var(--primary-color, #2563eb);
    margin-bottom: 1rem;
    line-height: 1;
}

.error-title {
    font-size: 2.5rem;
    color: #1f2937;
    margin-bottom: 1rem;
}

.error-message {
    font-size: 1.1rem;
    color: #6b7280;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background-color: var(--primary-color, #2563eb);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-hover, #1d4ed8);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
    transform: translateY(-1px);
}

.user-info, .login-prompt {
    padding: 1rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    margin-top: 1rem;
}

.user-info p, .login-prompt p {
    margin: 0.5rem 0;
    color: #4b5563;
}

.login-prompt a {
    color: var(--primary-color, #2563eb);
    text-decoration: none;
    font-weight: 500;
}

.login-prompt a:hover {
    text-decoration: underline;
}

@media (max-width: 640px) {
    .error-code {
        font-size: 6rem;
    }
    
    .error-title {
        font-size: 2rem;
    }
    
    .error-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 200px;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../shared/templates/footer.php';
?>
<?php
$page_title = 'Settings - Forward LMS';
$body_class = 'settings-page';

require_once __DIR__ . '/../shared/templates/header.php';

// Include database and auth
require_once __DIR__ . '/../../backend/config/auth.php';
require_once __DIR__ . '/../../backend/config/db.php';

// Check if user is logged in
if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$current_user = Auth::getUser();
$db = getDBConnection();

$message = '';
$error = '';
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    try {
        switch ($action) {
            case 'update_profile':
                $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
                $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
                $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
                $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
                $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
                
                if (!$email) {
                    $error = 'Please provide a valid email address.';
                } else {
                    // Check if email is already taken by another user
                    $email_check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                    $email_check_stmt = $db->prepare($email_check_sql);
                    $email_check_stmt->execute([$email, $current_user['id']]);
                    
                    if ($email_check_stmt->fetch()) {
                        $error = 'This email address is already in use by another account.';
                    } else {
                        $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, location = ? WHERE id = ?";
                        $update_stmt = $db->prepare($update_sql);
                        $update_stmt->execute([$first_name, $last_name, $email, $phone, $bio, $location, $current_user['id']]);
                        
                        $success = true;
                        $message = 'Profile updated successfully!';
                        
                        // Refresh user data
                        $current_user = Auth::getUser();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New password and confirmation do not match.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } else {
                    // Verify current password
                    $password_sql = "SELECT password FROM users WHERE id = ?";
                    $password_stmt = $db->prepare($password_sql);
                    $password_stmt->execute([$current_user['id']]);
                    $user_password = $password_stmt->fetch(PDO::FETCH_ASSOC)['password'];
                    
                    if (!password_verify($current_password, $user_password)) {
                        $error = 'Current password is incorrect.';
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_password_sql = "UPDATE users SET password = ? WHERE id = ?";
                        $update_password_stmt = $db->prepare($update_password_sql);
                        $update_password_stmt->execute([$hashed_password, $current_user['id']]);
                        
                        $success = true;
                        $message = 'Password changed successfully!';
                    }
                }
                break;
                
            case 'update_preferences':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
                $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
                $theme = filter_input(INPUT_POST, 'theme', FILTER_SANITIZE_STRING);
                $language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
                
                $preferences_sql = "UPDATE users SET 
                    email_notifications = ?, 
                    push_notifications = ?, 
                    marketing_emails = ?,
                    theme = ?,
                    language = ?
                    WHERE id = ?";
                $preferences_stmt = $db->prepare($preferences_sql);
                $preferences_stmt->execute([$email_notifications, $push_notifications, $marketing_emails, $theme, $language, $current_user['id']]);
                
                $success = true;
                $message = 'Preferences updated successfully!';
                break;
                
            case 'upload_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['avatar']['type'];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $error = 'Only JPEG, PNG, and GIF images are allowed.';
                    } elseif ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                        $error = 'Image size must be less than 5MB.';
                    } else {
                        $upload_dir = __DIR__ . '/../assets/images/avatars/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                        $filename = $current_user['id'] . '_' . time() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                            $avatar_url = '/frontend/assets/images/avatars/' . $filename;
                            
                            $update_avatar_sql = "UPDATE users SET avatar_url = ? WHERE id = ?";
                            $update_avatar_stmt = $db->prepare($update_avatar_sql);
                            $update_avatar_stmt->execute([$avatar_url, $current_user['id']]);
                            
                            $success = true;
                            $message = 'Avatar updated successfully!';
                            
                            // Refresh user data
                            $current_user = Auth::getUser();
                        } else {
                            $error = 'Failed to upload image. Please try again.';
                        }
                    }
                } else {
                    $error = 'Please select an image to upload.';
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Settings update error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Get user preferences
try {
    $prefs_sql = "SELECT email_notifications, push_notifications, marketing_emails, theme, language, timezone 
                  FROM users WHERE id = ?";
    $prefs_stmt = $db->prepare($prefs_sql);
    $prefs_stmt->execute([$current_user['id']]);
    $preferences = $prefs_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Preferences fetch error: " . $e->getMessage());
    $preferences = [
        'email_notifications' => 1,
        'push_notifications' => 1,
        'marketing_emails' => 0,
        'theme' => 'light',
        'language' => 'en',
        'timezone' => 'UTC'
    ];
}
?>

<div class="settings-container">
    <!-- Page Header -->
    <div class="settings-header">
        <h1>Account Settings</h1>
        <p>Manage your account preferences and profile information</p>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="settings-content">
        <!-- Settings Navigation -->
        <div class="settings-nav">
            <nav class="nav-list">
                <a href="#profile" class="nav-item active" data-section="profile">
                    <span class="nav-icon">👤</span>
                    Profile Information
                </a>
                <a href="#security" class="nav-item" data-section="security">
                    <span class="nav-icon">🔒</span>
                    Security & Password
                </a>
                <a href="#preferences" class="nav-item" data-section="preferences">
                    <span class="nav-icon">⚙️</span>
                    Notifications & Preferences
                </a>
                <a href="#account" class="nav-item" data-section="account">
                    <span class="nav-icon">🏠</span>
                    Account Management
                </a>
            </nav>
        </div>

        <!-- Settings Sections -->
        <div class="settings-sections">
            <!-- Profile Information Section -->
            <div id="profile" class="settings-section active">
                <h2>Profile Information</h2>
                
                <form method="POST" enctype="multipart/form-data" class="settings-form">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="form-group avatar-group">
                        <label>Profile Picture</label>
                        <div class="avatar-upload">
                            <div class="avatar-preview">
                                <?php if (!empty($current_user['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($current_user['avatar_url']); ?>" 
                                         alt="Profile Picture" class="current-avatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?php echo strtoupper(substr($current_user['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-controls">
                                <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display: none;">
                                <label for="avatar-input" class="btn btn-secondary">Choose Photo</label>
                                <button type="submit" class="btn btn-primary">Upload</button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" name="first_name" id="first_name" 
                                   value="<?php echo htmlspecialchars($current_user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" name="last_name" id="last_name" 
                                   value="<?php echo htmlspecialchars($current_user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" 
                               value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" name="location" id="location" 
                                   value="<?php echo htmlspecialchars($current_user['location'] ?? ''); ?>" 
                                   placeholder="City, Country">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea name="bio" id="bio" rows="4" 
                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($current_user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <!-- Security & Password Section -->
            <div id="security" class="settings-section">
                <h2>Security & Password</h2>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" 
                                   minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   minlength="8" required>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Include both letters and numbers</li>
                            <li>Use a mix of uppercase and lowercase letters</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>

            <!-- Preferences Section -->
            <div id="preferences" class="settings-section">
                <h2>Notifications & Preferences</h2>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="form-section">
                        <h3>Email Notifications</h3>
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="email_notifications" value="1" 
                                       <?php echo ($preferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="checkbox-custom"></span>
                                Enable email notifications
                            </label>
                            <p class="checkbox-description">Receive email notifications for important updates</p>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="marketing_emails" value="1" 
                                       <?php echo ($preferences['marketing_emails'] ?? 0) ? 'checked' : ''; ?>>
                                <span class="checkbox-custom"></span>
                                Marketing and promotional emails
                            </label>
                            <p class="checkbox-description">Receive information about new courses and special offers</p>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Display Preferences</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="theme">Theme</label>
                                <select name="theme" id="theme">
                                    <option value="light" <?php echo ($preferences['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo ($preferences['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    <option value="auto" <?php echo ($preferences['theme'] ?? 'light') === 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="language">Language</label>
                                <select name="language" id="language">
                                    <option value="en" <?php echo ($preferences['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo ($preferences['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                    <option value="fr" <?php echo ($preferences['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>French</option>
                                    <option value="de" <?php echo ($preferences['language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>German</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </form>
            </div>

            <!-- Account Management Section -->
            <div id="account" class="settings-section">
                <h2>Account Management</h2>
                
                <div class="account-info">
                    <div class="info-item">
                        <strong>Account Type:</strong>
                        <span><?php echo ucfirst($current_user['role']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Member Since:</strong>
                        <span><?php echo date('F j, Y', strtotime($current_user['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Last Login:</strong>
                        <span><?php echo date('F j, Y g:i A', strtotime($current_user['last_login'] ?? $current_user['created_at'])); ?></span>
                    </div>
                </div>
                
                <div class="account-actions">
                    <h3>Danger Zone</h3>
                    <div class="danger-actions">
                        <div class="action-item">
                            <div class="action-info">
                                <h4>Deactivate Account</h4>
                                <p>Temporarily disable your account. You can reactivate it later.</p>
                            </div>
                            <button class="btn btn-warning" onclick="confirmDeactivation()">Deactivate</button>
                        </div>
                        
                        <div class="action-item">
                            <div class="action-info">
                                <h4>Delete Account</h4>
                                <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                            </div>
                            <button class="btn btn-danger" onclick="confirmDeletion()">Delete Account</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modals -->
<div id="deactivate-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Deactivation</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to deactivate your account? You can reactivate it later by contacting support.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deactivate-modal')">Cancel</button>
                <button class="btn btn-warning" onclick="deactivateAccount()">Deactivate</button>
            </div>
        </div>
    </div>
</div>

<div id="delete-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Deletion</h3>
        </div>
        <div class="modal-body">
            <p><strong>Warning:</strong> This action cannot be undone. All your data, courses, and certificates will be permanently deleted.</p>
            <p>Type <strong>DELETE</strong> to confirm:</p>
            <input type="text" id="delete-confirm" class="form-input" placeholder="Type DELETE here">
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('delete-modal')">Cancel</button>
                <button class="btn btn-danger" id="delete-btn" disabled onclick="deleteAccount()">Delete Account</button>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.settings-header {
    text-align: center;
    margin-bottom: 3rem;
}

.settings-header h1 {
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.settings-header p {
    font-size: 1.1rem;
    color: #64748b;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Settings Content */
.settings-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 3rem;
}

/* Settings Navigation */
.settings-nav {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

.nav-list {
    list-style: none;
    padding: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: #374151;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s;
}

.nav-item:last-child {
    border-bottom: none;
}

.nav-item:hover,
.nav-item.active {
    background: #f0f9ff;
    color: #3b82f6;
    border-right: 4px solid #3b82f6;
}

.nav-icon {
    font-size: 1.1rem;
}

/* Settings Sections */
.settings-section {
    display: none;
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.settings-section.active {
    display: block;
}

.settings-section h2 {
    font-size: 1.75rem;
    color: #1e293b;
    margin-bottom: 2rem;
    font-weight: 600;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.settings-section h3 {
    font-size: 1.25rem;
    color: #374151;
    margin-bottom: 1rem;
    font-weight: 600;
}

.form-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 8px;
}

/* Forms */
.settings-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

/* Avatar Upload */
.avatar-group {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 2rem;
    margin-bottom: 2rem;
}

.avatar-upload {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.avatar-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.current-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 600;
}

.avatar-controls {
    display: flex;
    gap: 0.75rem;
}

/* Checkboxes */
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
    color: #374151;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    position: relative;
    transition: all 0.2s;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: 600;
}

.checkbox-description {
    color: #64748b;
    font-size: 0.9rem;
    margin-left: 2.75rem;
    margin-top: -0.25rem;
}

/* Password Requirements */
.password-requirements {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.password-requirements h4 {
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.password-requirements ul {
    margin: 0;
    padding-left: 1.25rem;
    color: #64748b;
    font-size: 0.85rem;
}

/* Account Info */
.account-info {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item strong {
    color: #374151;
}

.info-item span {
    color: #64748b;
}

/* Danger Zone */
.danger-actions {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 1.5rem;
}

.action-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #fecaca;
}

.action-item:last-child {
    border-bottom: none;
}

.action-info h4 {
    color: #991b1b;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.action-info p {
    color: #7f1d1d;
    font-size: 0.9rem;
    margin: 0;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-align: center;
    display: inline-block;
    font-size: 0.9rem;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
    background-color: #e2e8f0;
}

.btn-warning {
    background-color: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background-color: #d97706;
}

.btn-danger {
    background-color: #ef4444;
    color: white;
}

.btn-danger:hover {
    background-color: #dc2626;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body p {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

#delete-confirm {
    margin: 1rem 0;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    width: 100%;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .settings-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .settings-nav {
        position: static;
    }
    
    .nav-list {
        display: flex;
        overflow-x: auto;
    }
    
    .nav-item {
        border-bottom: none;
        border-right: 1px solid #f1f5f9;
        white-space: nowrap;
    }
    
    .nav-item:last-child {
        border-right: none;
    }
}

@media (max-width: 768px) {
    .settings-container {
        padding: 1rem;
    }
    
    .settings-header h1 {
        font-size: 2rem;
    }
    
    .settings-section {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .avatar-upload {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .action-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .modal-content {
        width: 95%;
    }
}

@media (max-width: 480px) {
    .nav-list {
        flex-direction: column;
    }
    
    .nav-item {
        border-right: none;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .modal-actions .btn {
        width: 100%;
    }
}
</style>

<script>
// Navigation
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.settings-section');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('data-section');
            
            // Update active nav item
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Show target section
            sections.forEach(section => {
                section.classList.remove('active');
                if (section.id === targetId) {
                    section.classList.add('active');
                }
            });
        });
    });
    
    // Avatar upload preview
    const avatarInput = document.getElementById('avatar-input');
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.avatar-preview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="current-avatar">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Password confirmation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword) {
        function validatePasswords() {
            if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    // Delete confirmation
    const deleteConfirm = document.getElementById('delete-confirm');
    const deleteBtn = document.getElementById('delete-btn');
    
    if (deleteConfirm && deleteBtn) {
        deleteConfirm.addEventListener('input', function() {
            deleteBtn.disabled = this.value !== 'DELETE';
        });
    }
});

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function confirmDeactivation() {
    openModal('deactivate-modal');
}

function confirmDeletion() {
    openModal('delete-modal');
}

function deactivateAccount() {
    if (confirm('Are you sure you want to deactivate your account?')) {
        // In a real application, you would make an AJAX call to deactivate the account
        alert('Account deactivation feature would be implemented here.');
        closeModal('deactivate-modal');
    }
}

function deleteAccount() {
    if (confirm('Are you absolutely sure? This action cannot be undone.')) {
        // In a real application, you would make an AJAX call to delete the account
        alert('Account deletion feature would be implemented here.');
        closeModal('delete-modal');
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});
</script>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>
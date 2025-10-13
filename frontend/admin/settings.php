<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'System Settings';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get current settings
$sql = "SELECT * FROM system_settings";
$stmt = $db->query($sql);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="container">
    <div class="page-header">
        <h1>System Settings</h1>
        <p>Configure platform settings</p>
    </div>
    
    <div class="settings-container">
        <form id="settingsForm">
            <div class="settings-section">
                <h2>General Settings</h2>
                
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'Forward LMS') ?>">
                </div>
                
                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <textarea id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="settings-section">
                <h2>Registration Settings</h2>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_registration" <?= ($settings['allow_registration'] ?? '1') == '1' ? 'checked' : '' ?>>
                        Allow new user registration
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_verification" <?= ($settings['email_verification'] ?? '0') == '1' ? 'checked' : '' ?>>
                        Require email verification
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="default_role">Default User Role</label>
                    <select id="default_role" name="default_role">
                        <option value="student" <?= ($settings['default_role'] ?? 'student') == 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= ($settings['default_role'] ?? 'student') == 'teacher' ? 'selected' : '' ?>>Teacher</option>
                    </select>
                </div>
            </div>
            
            <div class="settings-section">
                <h2>Course Settings</h2>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="course_approval_required" <?= ($settings['course_approval_required'] ?? '1') == '1' ? 'checked' : '' ?>>
                        Require admin approval for new courses
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="max_file_size">Max Upload File Size (MB)</label>
                    <input type="number" id="max_file_size" name="max_file_size" value="<?= htmlspecialchars($settings['max_file_size'] ?? '50') ?>" min="1" max="500">
                </div>
            </div>
            
            <div class="settings-section">
                <h2>Payment Settings</h2>
                
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency">
                        <option value="KES" <?= ($settings['currency'] ?? 'KES') == 'KES' ? 'selected' : '' ?>>KES (Kenyan Shilling)</option>
                        <option value="USD" <?= ($settings['currency'] ?? 'KES') == 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="enable_mpesa" <?= ($settings['enable_mpesa'] ?? '1') == '1' ? 'checked' : '' ?>>
                        Enable M-Pesa payments
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="button" class="btn btn-secondary" onclick="location.reload()">Reset</button>
            </div>
        </form>
    </div>
</div>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.settings-section {
    background: white;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings-section h2 {
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group input[type="checkbox"] {
    margin-right: 8px;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}
</style>

<script>
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../../backend/admin/update-settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Settings saved successfully!');
        } else {
            alert('Error saving settings');
        }
    });
});
</script>

<?php include '../../shared/templates/footer.php'; ?>

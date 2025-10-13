<?php
$page_title = 'Submit Assignment';
require_once __DIR__ . '/../../shared/templates/header.php';

if (!Auth::isAuthenticated() || Auth::getUserRole() !== 'student') {
    header('Location: /frontend/login.php');
    exit;
}

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    header('Location: /frontend/student/dashboard.php');
    exit;
}
?>

<link rel="stylesheet" href="/frontend/assets/css/assignments.css">

<div class="assignment-submit-container">
    <div class="assignment-header" id="assignment-header">
        <div class="loading">Loading assignment...</div>
    </div>
    
    <div class="submission-form-container">
        <form id="submission-form" enctype="multipart/form-data">
            <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignment_id); ?>">
            
            <div class="form-section">
                <h3>Your Submission</h3>
                
                <div class="form-group">
                    <label>Text Submission (optional)</label>
                    <textarea name="submission_text" rows="10" placeholder="Enter your submission text here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Upload Files</label>
                    <div class="file-upload-area" id="file-upload-area">
                        <input type="file" name="files[]" id="file-input" multiple style="display: none;">
                        <div class="upload-prompt" onclick="document.getElementById('file-input').click()">
                            <div class="upload-icon">📁</div>
                            <p>Click to upload files or drag and drop</p>
                            <small>Allowed file types will be shown above</small>
                        </div>
                    </div>
                    <div id="file-list" class="file-list"></div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Assignment</button>
            </div>
        </form>
    </div>
</div>

<script src="/frontend/assets/js/assignment-submit.js"></script>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

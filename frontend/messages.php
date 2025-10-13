<?php
$page_title = 'Messages';
require_once __DIR__ . '/../shared/templates/header.php';

if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php');
    exit;
}

$user_id = Auth::getUserId();
$folder = $_GET['folder'] ?? 'inbox';
$message_id = $_GET['id'] ?? null;
?>

<link rel="stylesheet" href="/frontend/assets/css/messages.css">

<div class="messages-container">
    <div class="messages-sidebar">
        <button class="btn btn-primary btn-compose" onclick="showComposeModal()">
            <span class="icon">✉</span> Compose
        </button>
        
        <nav class="messages-nav">
            <a href="?folder=inbox" class="nav-item <?php echo $folder === 'inbox' ? 'active' : ''; ?>">
                <span class="icon">📥</span>
                <span>Inbox</span>
                <span class="badge" id="inbox-count">0</span>
            </a>
            <a href="?folder=sent" class="nav-item <?php echo $folder === 'sent' ? 'active' : ''; ?>">
                <span class="icon">📤</span>
                <span>Sent</span>
            </a>
            <a href="?folder=archive" class="nav-item <?php echo $folder === 'archive' ? 'active' : ''; ?>">
                <span class="icon">📁</span>
                <span>Archive</span>
            </a>
            <a href="?folder=trash" class="nav-item <?php echo $folder === 'trash' ? 'active' : ''; ?>">
                <span class="icon">🗑</span>
                <span>Trash</span>
            </a>
        </nav>
    </div>
    
    <div class="messages-list" id="messages-list">
        <div class="messages-header">
            <h2><?php echo ucfirst($folder); ?></h2>
            <div class="messages-actions">
                <button class="btn-icon" onclick="refreshMessages()" title="Refresh">
                    <span>🔄</span>
                </button>
            </div>
        </div>
        
        <div class="messages-items" id="messages-items">
            <div class="loading">Loading messages...</div>
        </div>
    </div>
    
    <div class="message-view" id="message-view">
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>No message selected</h3>
            <p>Select a message from the list to view its contents</p>
        </div>
    </div>
</div>

 Compose Modal 
<div class="modal" id="compose-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>New Message</h3>
            <button class="btn-close" onclick="closeComposeModal()">×</button>
        </div>
        <form id="compose-form" onsubmit="sendMessage(event)">
            <div class="form-group">
                <label>To:</label>
                <select name="recipients" id="recipients-select" multiple required>
                     Will be populated via AJAX 
                </select>
                <small>Hold Ctrl/Cmd to select multiple recipients</small>
            </div>
            <div class="form-group">
                <label>Subject:</label>
                <input type="text" name="subject" required>
            </div>
            <div class="form-group">
                <label>Message:</label>
                <textarea name="body" rows="10" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeComposeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </div>
        </form>
    </div>
</div>

<script src="/frontend/assets/js/messages.js"></script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>

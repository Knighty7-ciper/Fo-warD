<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$user = requireAuth();

// Get user's conversations for sidebar
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        CASE 
            WHEN c.participant_1_id = ? THEN u2.name 
            ELSE u1.name 
        END as other_participant_name,
        CASE 
            WHEN c.participant_1_id = ? THEN u2.role 
            ELSE u1.role 
        END as other_participant_role,
        (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages m2 
         WHERE m2.conversation_id = c.id 
         AND m2.sender_id != ? 
         AND m2.is_read = 0) as unread_count
    FROM conversations c
    JOIN users u1 ON c.participant_1_id = u1.id
    JOIN users u2 ON c.participant_2_id = u2.id
    WHERE (c.participant_1_id = ? OR c.participant_2_id = ?)
    ORDER BY c.updated_at DESC
    LIMIT 20
");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contacts for new message
$role = $user['role'];
$allowedRoles = [];
if ($role === 'admin') {
    $allowedRoles = ['admin', 'teacher', 'student'];
} elseif ($role === 'teacher') {
    $allowedRoles = ['admin', 'teacher', 'student'];
} elseif ($role === 'student') {
    $allowedRoles = ['admin', 'teacher'];
}

if (!empty($allowedRoles)) {
    $placeholders = str_repeat('?,', count($allowedRoles) - 1) . '?';
    $sql = "SELECT id, name, role, email, avatar FROM users WHERE role IN ($placeholders) AND id != ? ORDER BY name LIMIT 50";
    $params = array_merge($allowedRoles, [$user['id']]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $contacts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging - FowarD LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-gray: #ecf0f1;
            --border-color: #ddd;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .messaging-container {
            height: 100vh;
            display: flex;
            background: white;
        }

        .sidebar {
            width: 350px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: white;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-color);
            color: white;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }

        .search-box {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .conversation-item:hover {
            background-color: var(--light-gray);
        }

        .conversation-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        .conversation-item.unread {
            background-color: #e3f2fd;
        }

        .conversation-item.unread:hover {
            background-color: #bbdefb;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 16px;
        }

        .conversation-time {
            font-size: 12px;
            opacity: 0.7;
        }

        .conversation-preview {
            font-size: 14px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item.active .conversation-preview {
            color: rgba(255, 255, 255, 0.8);
        }

        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }

        .chat-user-name {
            font-weight: 600;
            font-size: 16px;
        }

        .chat-user-role {
            font-size: 12px;
            color: #666;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .message.sent .message-avatar {
            order: 2;
            margin-left: 10px;
            margin-right: 0;
            background: var(--secondary-color);
        }

        .message-content {
            max-width: 70%;
        }

        .message-bubble {
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            position: relative;
        }

        .message.sent .message-bubble {
            background: var(--primary-color);
            color: white;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .message-input-area {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: white;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }

        .send-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .send-btn:hover {
            background: #2980b9;
        }

        .new-message-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: transform 0.2s;
            z-index: 1000;
        }

        .new-message-btn:hover {
            transform: scale(1.1);
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #666;
            text-align: center;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }

        .modal-content {
            border-radius: 15px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-color);
            color: white;
        }

        .contact-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
        }

        .contact-item:hover {
            background-color: var(--light-gray);
        }

        .contact-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }

        .contact-info {
            flex: 1;
        }

        .contact-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .contact-role {
            font-size: 12px;
            color: #666;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: absolute;
                z-index: 1000;
                height: 100%;
            }
            
            .sidebar.hidden {
                display: none;
            }
            
            .chat-area {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="messaging-container">
        <!-- Sidebar with conversations -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="bi bi-chat-dots"></i> Messages</h3>
            </div>
            
            <div class="search-box">
                <input type="text" id="conversationSearch" placeholder="Search conversations...">
            </div>
            
            <div class="conversations-list" id="conversationsList">
                <?php foreach ($conversations as $conversation): ?>
                <div class="conversation-item <?= $conversation['unread_count'] > 0 ? 'unread' : '' ?>" 
                     data-conversation-id="<?= $conversation['id'] ?>"
                     onclick="selectConversation(<?= $conversation['id'] ?>)">
                    <div class="conversation-header">
                        <div class="conversation-name"><?= htmlspecialchars($conversation['other_participant_name']) ?></div>
                        <div class="conversation-time"><?= date('M j', strtotime($conversation['last_message_time'])) ?></div>
                    </div>
                    <div class="conversation-preview">
                        <?= htmlspecialchars($conversation['last_message'] ?: 'No messages yet') ?>
                    </div>
                    <?php if ($conversation['unread_count'] > 0): ?>
                    <div class="unread-badge"><?= $conversation['unread_count'] ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main chat area -->
        <div class="chat-area" id="chatArea">
            <div class="chat-header">
                <div class="chat-user-info" id="chatUserInfo">
                    <div class="chat-avatar" id="chatUserAvatar">?</div>
                    <div>
                        <div class="chat-user-name" id="chatUserName">Select a conversation</div>
                        <div class="chat-user-role" id="chatUserRole">Choose someone to start chatting</div>
                    </div>
                </div>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="empty-state">
                    <i class="bi bi-chat-dots"></i>
                    <h4>No conversation selected</h4>
                    <p>Choose a conversation from the sidebar or start a new one</p>
                </div>
            </div>

            <div class="message-input-area">
                <div class="input-group">
                    <input type="text" class="message-input" id="messageInput" 
                           placeholder="Type your message..." 
                           onkeypress="handleKeyPress(event)"
                           disabled>
                    <button class="send-btn" onclick="sendMessage()" id="sendBtn" disabled>
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select recipient:</label>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($contacts as $contact): ?>
                            <div class="contact-item" onclick="selectContact(<?= $contact['id'] ?>, '<?= htmlspecialchars($contact['name']) ?>', '<?= $contact['role'] ?>')">
                                <div class="contact-avatar"><?= strtoupper(substr($contact['name'], 0, 1)) ?></div>
                                <div class="contact-info">
                                    <div class="contact-name"><?= htmlspecialchars($contact['name']) ?></div>
                                    <div class="contact-role"><?= ucfirst($contact['role']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Button -->
    <button class="new-message-btn" onclick="showNewMessageModal()">
        <i class="bi bi-plus"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentConversationId = null;
        let currentRecipient = null;
        let messagePolling = null;

        // Initialize messaging system
        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
            checkUnreadCount();
            
            // Start polling for new messages every 5 seconds
            messagePolling = setInterval(loadMessages, 5000);
        });

        function selectConversation(conversationId) {
            currentConversationId = conversationId;
            
            // Update active conversation
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
            
            // Enable message input
            document.getElementById('messageInput').disabled = false;
            document.getElementById('sendBtn').disabled = false;
            
            // Load messages
            loadMessages();
        }

        function loadMessages() {
            if (!currentConversationId) return;
            
            fetch(`api/messaging.php?action=messages&conversation_id=${currentConversationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages) {
                        displayMessages(data.messages);
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                });
        }

        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            
            if (messages.length === 0) {
                chatMessages.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-chat-dots"></i>
                        <h4>No messages yet</h4>
                        <p>Start the conversation by sending a message below</p>
                    </div>
                `;
                return;
            }
            
            chatMessages.innerHTML = messages.map(message => {
                const isSent = message.sender_id === <?= $user['id'] ?>;
                const time = new Date(message.created_at).toLocaleString();
                
                return `
                    <div class="message ${isSent ? 'sent' : ''}">
                        ${!isSent ? `<div class="message-avatar">${message.sender_name.charAt(0).toUpperCase()}</div>` : ''}
                        <div class="message-content">
                            <div class="message-bubble">${escapeHtml(message.content)}</div>
                            <div class="message-time">${time}</div>
                        </div>
                        ${isSent ? `<div class="message-avatar">${message.sender_name.charAt(0).toUpperCase()}</div>` : ''}
                    </div>
                `;
            }).join('');
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const content = input.value.trim();
            
            if (!content) return;
            
            const formData = new FormData();
            formData.append('content', content);
            
            if (currentConversationId) {
                formData.append('conversation_id', currentConversationId);
            } else if (currentRecipient) {
                formData.append('recipient_id', currentRecipient.id);
            }
            
            // Disable input while sending
            input.disabled = true;
            document.getElementById('sendBtn').disabled = true;
            
            fetch('api/messaging.php?action=send', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    if (data.conversation_id && !currentConversationId) {
                        currentConversationId = data.conversation_id;
                        loadConversations();
                    }
                    loadMessages();
                    checkUnreadCount();
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
            })
            .finally(() => {
                input.disabled = false;
                document.getElementById('sendBtn').disabled = false;
                input.focus();
            });
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        function loadConversations() {
            fetch('api/messaging.php?action=conversations')
                .then(response => response.json())
                .then(data => {
                    if (data.conversations) {
                        updateConversationsList(data.conversations);
                    }
                })
                .catch(error => {
                    console.error('Error loading conversations:', error);
                });
        }

        function updateConversationsList(conversations) {
            const list = document.getElementById('conversationsList');
            list.innerHTML = conversations.map(conv => {
                const otherName = conv.other_participant_name;
                const lastMessage = conv.last_message || 'No messages yet';
                const time = new Date(conv.last_message_time).toLocaleDateString();
                
                return `
                    <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''}" 
                         data-conversation-id="${conv.id}"
                         onclick="selectConversation(${conv.id})">
                        <div class="conversation-header">
                            <div class="conversation-name">${escapeHtml(otherName)}</div>
                            <div class="conversation-time">${time}</div>
                        </div>
                        <div class="conversation-preview">${escapeHtml(lastMessage)}</div>
                        ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
                    </div>
                `;
            }).join('');
        }

        function showNewMessageModal() {
            new bootstrap.Modal(document.getElementById('newMessageModal')).show();
        }

        function selectContact(id, name, role) {
            currentRecipient = { id: id, name: name, role: role };
            
            // Update chat header
            document.getElementById('chatUserName').textContent = name;
            document.getElementById('chatUserRole').textContent = role.charAt(0).toUpperCase() + role.slice(1);
            document.getElementById('chatUserAvatar').textContent = name.charAt(0).toUpperCase();
            
            // Enable message input
            document.getElementById('messageInput').disabled = false;
            document.getElementById('sendBtn').disabled = false;
            
            // Clear current conversation
            currentConversationId = null;
            
            // Clear messages display
            document.getElementById('chatMessages').innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-chat-dots"></i>
                    <h4>New conversation with ${name}</h4>
                    <p>Start typing to send your first message</p>
                </div>
            `;
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('newMessageModal')).hide();
        }

        function checkUnreadCount() {
            fetch('api/messaging.php?action=unread')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        // Update document title or show notification
                        document.title = `(${data.unread_count}) Messages - FowarD LMS`;
                    } else {
                        document.title = 'Messages - FowarD LMS';
                    }
                })
                .catch(error => {
                    console.error('Error checking unread count:', error);
                });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Search functionality
        document.getElementById('conversationSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const conversations = document.querySelectorAll('.conversation-item');
            
            conversations.forEach(conv => {
                const name = conv.querySelector('.conversation-name').textContent.toLowerCase();
                const preview = conv.querySelector('.conversation-preview').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    conv.style.display = 'block';
                } else {
                    conv.style.display = 'none';
                }
            });
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (messagePolling) {
                clearInterval(messagePolling);
            }
        });
    </script>
</body>
</html>
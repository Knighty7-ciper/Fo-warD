<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Peer Collaboration';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get discussion forums
$sql = "SELECT f.*, c.title as course_title,
        (SELECT COUNT(*) FROM forum_posts WHERE forum_id = f.id) as post_count
        FROM forums f
        JOIN courses c ON f.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.user_id = :user_id
        ORDER BY f.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>Peer Collaboration</h1>
        <p>Connect and learn with fellow students</p>
    </div>
    
    <div class="collaboration-tabs">
        <button class="tab-btn active" onclick="showTab('forums')">Discussion Forums</button>
        <button class="tab-btn" onclick="showTab('study-groups')">Study Groups</button>
        <button class="tab-btn" onclick="showTab('messages')">Messages</button>
    </div>
    
    <div id="forums" class="tab-content active">
        <div class="forums-list">
            <?php foreach ($forums as $forum): ?>
                <div class="forum-card">
                    <div class="forum-info">
                        <h3><?= htmlspecialchars($forum['title']) ?></h3>
                        <p class="forum-course"><?= htmlspecialchars($forum['course_title']) ?></p>
                        <p class="forum-description"><?= htmlspecialchars($forum['description']) ?></p>
                    </div>
                    <div class="forum-stats">
                        <div class="stat">
                            <strong><?= $forum['post_count'] ?></strong>
                            <span>Posts</span>
                        </div>
                        <a href="forum.php?id=<?= $forum['id'] ?>" class="btn btn-primary">View Forum</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div id="study-groups" class="tab-content">
        <div class="study-groups-section">
            <div class="section-header">
                <h2>Study Groups</h2>
                <button class="btn btn-primary" onclick="createStudyGroup()">
                    <i class="fas fa-plus"></i> Create Study Group
                </button>
            </div>
            
            <div class="study-groups-grid" id="studyGroupsGrid">
                <!-- Study groups will be loaded here -->
            </div>
        </div>
    </div>
    
    <div id="messages" class="tab-content">
        <div class="messages-section">
            <div class="section-header">
                <h2>Direct Messages</h2>
                <div class="message-actions">
                    <button class="btn btn-primary" onclick="startNewMessage()">
                        <i class="fas fa-plus"></i> New Message
                    </button>
                </div>
            </div>
            
            <div class="messages-container">
                <div class="conversations-list" id="conversationsList">
                    <!-- Conversations will be loaded here -->
                </div>
                
                <div class="chat-area" id="chatArea" style="display: none;">
                    <div class="chat-header">
                        <div class="chat-participant" id="chatParticipant">
                            <!-- Active conversation participant -->
                        </div>
                        <button class="close-chat" onclick="closeChat()">&times;</button>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <!-- Messages will be displayed here -->
                    </div>
                    <div class="chat-input">
                        <input type="text" id="messageInput" placeholder="Type a message..." maxlength="500">
                        <button class="send-btn" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.collaboration-tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #eee;
    margin-bottom: 30px;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1rem;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.forums-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.forum-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
}

.forum-info {
    flex: 1;
}

.forum-info h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.forum-course {
    color: #007bff;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.forum-description {
    color: #666;
    margin: 0;
}

.forum-stats {
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat {
    text-align: center;
}

.stat strong {
    display: block;
    font-size: 1.5rem;
    color: #333;
}

.stat span {
    font-size: 0.85rem;
    color: #666;
}
</style>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
    
    // Load content for active tab
    if (tabName === 'study-groups') {
        loadStudyGroups();
    } else if (tabName === 'messages') {
        loadConversations();
    }
}

// Study Groups Functions
function loadStudyGroups() {
    const container = document.getElementById('studyGroupsGrid');
    
    // Mock study groups data (replace with actual API call)
    const studyGroups = [
        {
            id: 1,
            name: 'JavaScript Fundamentals',
            course: 'Web Development 101',
            description: 'Study group for JavaScript basics and ES6 features',
            members: 12,
            maxMembers: 20,
            isJoined: true,
            lastActivity: '2 hours ago'
        },
        {
            id: 2,
            name: 'Database Design',
            course: 'Database Systems',
            description: 'Collaborative learning for database design principles',
            members: 8,
            maxMembers: 15,
            isJoined: false,
            lastActivity: '1 day ago'
        },
        {
            id: 3,
            name: 'UI/UX Design',
            course: 'User Experience Design',
            description: 'Portfolio review and design critique sessions',
            members: 15,
            maxMembers: 25,
            isJoined: true,
            lastActivity: '30 minutes ago'
        }
    ];
    
    let html = '';
    studyGroups.forEach(group => {
        html += `
            <div class="study-group-card">
                <div class="group-header">
                    <h3>${group.name}</h3>
                    <span class="course-tag">${group.course}</span>
                </div>
                <p class="group-description">${group.description}</p>
                <div class="group-stats">
                    <div class="stat">
                        <strong>${group.members}</strong>
                        <span>Members</span>
                    </div>
                    <div class="stat">
                        <strong>${group.maxMembers}</strong>
                        <span>Max</span>
                    </div>
                    <div class="stat">
                        <strong>${group.lastActivity}</strong>
                        <span>Active</span>
                    </div>
                </div>
                <div class="group-actions">
                    ${group.isJoined ? 
                        '<button class="btn btn-secondary" onclick="leaveGroup(' + group.id + ')">Leave Group</button>' :
                        '<button class="btn btn-primary" onclick="joinGroup(' + group.id + ')">Join Group</button>'
                    }
                    <button class="btn btn-outline" onclick="viewGroup(' + group.id + ')">View Details</button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html || '<p class="no-groups">No study groups available. Create one to get started!</p>';
}

function createStudyGroup() {
    const course = prompt('Enter course name for the study group:');
    if (course) {
        const name = prompt('Enter study group name:');
        const description = prompt('Enter group description:');
        
        if (name && description) {
            alert('Study group "' + name + '" created successfully!');
            loadStudyGroups(); // Reload to show new group
        }
    }
}

function joinGroup(groupId) {
    if (confirm('Join this study group?')) {
        alert('Successfully joined the study group!');
        loadStudyGroups();
    }
}

function leaveGroup(groupId) {
    if (confirm('Are you sure you want to leave this study group?')) {
        alert('Left the study group.');
        loadStudyGroups();
    }
}

function viewGroup(groupId) {
    window.location.href = '/frontend/student/study-group.php?id=' + groupId;
}

// Messaging Functions
let currentConversation = null;

function loadConversations() {
    const container = document.getElementById('conversationsList');
    
    // Mock conversations data (replace with actual API call)
    const conversations = [
        {
            id: 1,
            participant: {
                name: 'Sarah Johnson',
                avatar: '/frontend/assets/images/default-avatar.png',
                course: 'Web Development 101'
            },
            lastMessage: 'Thanks for sharing the notes!',
            lastMessageTime: '2 min ago',
            unreadCount: 0
        },
        {
            id: 2,
            participant: {
                name: 'Mike Chen',
                avatar: '/frontend/assets/images/default-avatar.png',
                course: 'Database Systems'
            },
            lastMessage: 'Are you attending the study session tomorrow?',
            lastMessageTime: '1 hour ago',
            unreadCount: 2
        },
        {
            id: 3,
            participant: {
                name: 'Emily Rodriguez',
                avatar: '/frontend/assets/images/default-avatar.png',
                course: 'UI/UX Design'
            },
            lastMessage: 'Great portfolio! I loved your color choices.',
            lastMessageTime: '3 hours ago',
            unreadCount: 1
        }
    ];
    
    let html = '';
    conversations.forEach(conv => {
        html += `
            <div class="conversation-item ${conv.unreadCount > 0 ? 'unread' : ''}" onclick="openConversation(${conv.id})">
                <img src="${conv.participant.avatar}" alt="${conv.participant.name}" class="participant-avatar">
                <div class="conversation-info">
                    <div class="conversation-header">
                        <span class="participant-name">${conv.participant.name}</span>
                        <span class="message-time">${conv.lastMessageTime}</span>
                    </div>
                    <div class="conversation-course">${conv.participant.course}</div>
                    <div class="last-message">${conv.lastMessage}</div>
                </div>
                ${conv.unreadCount > 0 ? `<div class="unread-badge">${conv.unreadCount}</div>` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html || '<p class="no-conversations">No conversations yet. Start a new message!</p>';
}

function startNewMessage() {
    const participantName = prompt('Enter the name of the student you want to message:');
    if (participantName) {
        // Create new conversation (mock implementation)
        alert('Starting conversation with ' + participantName + '...');
        loadConversations();
    }
}

function openConversation(conversationId) {
    // Mock conversation data
    const conversations = {
        1: {
            participant: { name: 'Sarah Johnson', avatar: '/frontend/assets/images/default-avatar.png' },
            messages: [
                { id: 1, sender: 'Sarah Johnson', text: 'Hi! I saw you posted about the JavaScript project.', time: '10:30 AM' },
                { id: 2, sender: 'You', text: 'Yes! Are you working on the same assignment?', time: '10:32 AM' },
                { id: 3, sender: 'Sarah Johnson', text: 'Yes! I could use some help with the DOM manipulation part.', time: '10:35 AM' },
                { id: 4, sender: 'You', text: 'Of course! I just finished that section. I can share my notes.', time: '10:36 AM' },
                { id: 5, sender: 'Sarah Johnson', text: 'Thanks for sharing the notes!', time: '10:40 AM' }
            ]
        }
    };
    
    const conversation = conversations[conversationId];
    if (conversation) {
        currentConversation = conversationId;
        
        // Update chat area
        document.getElementById('conversationsList').style.display = 'none';
        document.getElementById('chatArea').style.display = 'block';
        
        // Set participant info
        document.getElementById('chatParticipant').innerHTML = `
            <img src="${conversation.participant.avatar}" alt="${conversation.participant.name}">
            <div>
                <div class="participant-name">${conversation.participant.name}</div>
                <div class="participant-status">Online</div>
            </div>
        `;
        
        // Load messages
        loadMessages(conversation.messages);
    }
}

function loadMessages(messages) {
    const container = document.getElementById('chatMessages');
    
    let html = '';
    messages.forEach(message => {
        const isOwn = message.sender === 'You';
        html += `
            <div class="message ${isOwn ? 'own' : 'other'}">
                <div class="message-content">
                    ${!isOwn ? `<div class="message-sender">${message.sender}</div>` : ''}
                    <div class="message-text">${message.text}</div>
                    <div class="message-time">${message.time}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (message) {
        // Add message to chat (mock implementation)
        const messageHtml = `
            <div class="message own">
                <div class="message-content">
                    <div class="message-text">${message}</div>
                    <div class="message-time">${new Date().toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            </div>
        `;
        
        document.getElementById('chatMessages').innerHTML += messageHtml;
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
        input.value = '';
        
        // Simulate reply after 2 seconds
        setTimeout(() => {
            const replyHtml = `
                <div class="message other">
                    <div class="message-content">
                        <div class="message-sender">Sarah Johnson</div>
                        <div class="message-text">Thanks for the message! I'll get back to you soon.</div>
                        <div class="message-time">${new Date().toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' })}</div>
                    </div>
                </div>
            `;
            document.getElementById('chatMessages').innerHTML += replyHtml;
            document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
        }, 2000);
    }
}

function closeChat() {
    document.getElementById('conversationsList').style.display = 'block';
    document.getElementById('chatArea').style.display = 'none';
    currentConversation = null;
}

// Auto-refresh conversations every 30 seconds
setInterval(() => {
    if (document.getElementById('conversationsList').style.display !== 'none') {
        loadConversations();
    }
}, 30000);

// Load initial content
document.addEventListener('DOMContentLoaded', function() {
    loadStudyGroups();
});
</script>

<?php include '../../shared/templates/footer.php'; ?>

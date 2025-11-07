<?php
require_once '../../shared/config/auth.php';
require_once '../../backend/config/db.php';
require_once '../../shared/utils/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /frontend/login.php?redirect=live-class/host.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

// Get live class session from URL parameter
$room_id = $_GET['room_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

if (empty($room_id) && empty($session_id)) {
    header('Location: /frontend/teacher/schedule.php');
    exit();
}

// Get class session details
$session = null;
if (!empty($session_id)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM class_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Session not found
    }
}

// Generate room ID if not provided
if (empty($room_id) && $session) {
    $room_id = 'room_' . $session['id'];
}

// If no session found, create a temporary one
if (!$session) {
    $session = [
        'id' => 0,
        'title' => 'Live Class Session',
        'description' => 'Interactive live class session',
        'start_time' => date('Y-m-d H:i:s'),
        'duration' => 60,
        'teacher_id' => $user_id,
        'max_participants' => 50,
        'status' => 'active'
    ];
}

// Check permissions
$is_teacher = $user_role === 'teacher' && $session['teacher_id'] == $user_id;
$is_admin = $user_role === 'admin';

if (!$is_teacher && !$is_admin) {
    header('Location: /frontend/403.php');
    exit();
}

// Get participants list
$participants = [];
try {
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email, u.avatar
        FROM live_participants p
        JOIN users u ON p.user_id = u.id
        WHERE p.room_id = ? AND p.status = 'joined'
        ORDER BY p.joined_at DESC
    ");
    $stmt->execute([$room_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Participants table might not exist
}

// Get chat messages
$chat_messages = [];
try {
    $stmt = $conn->prepare("
        SELECT cm.*, u.first_name, u.last_name, u.avatar
        FROM live_chat_messages cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.room_id = ?
        ORDER BY cm.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$room_id]);
    $chat_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $chat_messages = array_reverse($chat_messages); // Show oldest first
} catch (Exception $e) {
    // Chat table might not exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Class Host - <?= htmlspecialchars($session['title']) ?></title>
    <link rel="stylesheet" href="/frontend/assets/css/main.css">
    <link rel="stylesheet" href="/frontend/assets/css/live-class.css">
    <link rel="stylesheet" href="/frontend/assets/css/host-panel.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="live-class-body">
    <div class="live-class-container">
        <!-- Header Bar -->
        <div class="live-class-header">
            <div class="session-info">
                <h1><i class="fas fa-video"></i> <?= htmlspecialchars($session['title']) ?></h1>
                <div class="session-details">
                    <span class="room-id">Room: <?= htmlspecialchars($room_id) ?></span>
                    <span class="participants-count" id="participantsCount"><?= count($participants) ?> participants</span>
                    <span class="session-time" id="sessionTime">00:00:00</span>
                </div>
            </div>
            <div class="header-controls">
                <button class="control-btn" id="toggleVideo" title="Toggle Video">
                    <i class="fas fa-video"></i>
                </button>
                <button class="control-btn" id="toggleAudio" title="Toggle Audio">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="control-btn" id="toggleScreen" title="Share Screen">
                    <i class="fas fa-desktop"></i>
                </button>
                <button class="control-btn" id="toggleChat" title="Toggle Chat">
                    <i class="fas fa-comments"></i>
                </button>
                <button class="control-btn" id="toggleParticipants" title="Show Participants">
                    <i class="fas fa-users"></i>
                </button>
                <button class="control-btn danger" id="endSession" title="End Session">
                    <i class="fas fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="live-class-main">
            <!-- Video Area -->
            <div class="video-section">
                <div class="video-container">
                    <video id="localVideo" autoplay muted></video>
                    <div class="video-overlay">
                        <div class="participant-info">
                            <img src="<?= htmlspecialchars($user['avatar'] ?? '/frontend/assets/images/default-avatar.png') ?>" alt="You" class="participant-avatar">
                            <span class="participant-name">You (Host)</span>
                        </div>
                    </div>
                </div>

                <!-- Remote Participants Grid -->
                <div class="remote-videos" id="remoteVideos">
                    <!-- Remote participant videos will be added here dynamically -->
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <!-- Participants Panel -->
                <div class="panel participants-panel" id="participantsPanel">
                    <div class="panel-header">
                        <h3><i class="fas fa-users"></i> Participants</h3>
                        <button class="panel-close" id="closeParticipants">&times;</button>
                    </div>
                    <div class="panel-content">
                        <div class="participants-list" id="participantsList">
                            <!-- Current participants -->
                            <div class="participant-item host">
                                <img src="<?= htmlspecialchars($user['avatar'] ?? '/frontend/assets/images/default-avatar.png') ?>" alt="Host" class="participant-avatar">
                                <div class="participant-details">
                                    <span class="participant-name">You (Host)</span>
                                    <span class="participant-role">Instructor</span>
                                </div>
                                <div class="participant-status">
                                    <i class="fas fa-microphone"></i>
                                    <i class="fas fa-video"></i>
                                </div>
                            </div>
                            
                            <!-- Other participants will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="panel chat-panel" id="chatPanel">
                    <div class="panel-header">
                        <h3><i class="fas fa-comments"></i> Chat</h3>
                        <button class="panel-close" id="closeChat">&times;</button>
                    </div>
                    <div class="panel-content">
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($chat_messages as $message): ?>
                                <div class="chat-message">
                                    <img src="<?= htmlspecialchars($message['avatar'] ?? '/frontend/assets/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($message['first_name']) ?>" class="message-avatar">
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="message-author"><?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?></span>
                                            <span class="message-time"><?= date('H:i', strtotime($message['created_at'])) ?></span>
                                        </div>
                                        <div class="message-text"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chat-input">
                            <input type="text" id="chatInput" placeholder="Type a message..." maxlength="500">
                            <button id="sendMessage" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Presenter Tools -->
        <div class="presenter-tools" id="presenterTools">
            <div class="tools-header">
                <h4><i class="fas fa-chalkboard-teacher"></i> Presenter Tools</h4>
            </div>
            <div class="tools-content">
                <div class="tool-section">
                    <h5>Screen Sharing</h5>
                    <button class="tool-btn" id="startScreenShare">
                        <i class="fas fa-desktop"></i> Start Screen Share
                    </button>
                    <button class="tool-btn" id="stopScreenShare" style="display: none;">
                        <i class="fas fa-stop"></i> Stop Sharing
                    </button>
                </div>

                <div class="tool-section">
                    <h5>Whiteboard</h5>
                    <button class="tool-btn" id="openWhiteboard">
                        <i class="fas fa-paint-brush"></i> Open Whiteboard
                    </button>
                </div>

                <div class="tool-section">
                    <h5>File Sharing</h5>
                    <button class="tool-btn" id="shareFiles">
                        <i class="fas fa-file"></i> Share Files
                    </button>
                    <input type="file" id="fileInput" multiple style="display: none;">
                </div>

                <div class="tool-section">
                    <h5>Session Controls</h5>
                    <button class="tool-btn" id="lockRoom">
                        <i class="fas fa-lock"></i> Lock Room
                    </button>
                    <button class="tool-btn" id="muteAll">
                        <i class="fas fa-microphone-slash"></i> Mute All
                    </button>
                    <button class="tool-btn" id="endForAll">
                        <i class="fas fa-user-slash"></i> Remove All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Whiteboard Modal -->
    <div class="modal fade" id="whiteboardModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Interactive Whiteboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="whiteboard-container">
                        <div class="whiteboard-toolbar">
                            <div class="tool-group">
                                <button class="whiteboard-tool active" data-tool="pen">
                                    <i class="fas fa-pen"></i> Pen
                                </button>
                                <button class="whiteboard-tool" data-tool="eraser">
                                    <i class="fas fa-eraser"></i> Eraser
                                </button>
                                <button class="whiteboard-tool" data-tool="line">
                                    <i class="fas fa-minus"></i> Line
                                </button>
                                <button class="whiteboard-tool" data-tool="rectangle">
                                    <i class="fas fa-square"></i> Rectangle
                                </button>
                                <button class="whiteboard-tool" data-tool="circle">
                                    <i class="fas fa-circle"></i> Circle
                                </button>
                            </div>
                            <div class="tool-group">
                                <input type="color" id="colorPicker" value="#000000">
                                <input type="range" id="sizePicker" min="1" max="20" value="2">
                            </div>
                            <div class="tool-group">
                                <button class="whiteboard-tool" id="clearBoard">
                                    <i class="fas fa-trash"></i> Clear
                                </button>
                                <button class="whiteboard-tool" id="saveBoard">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </div>
                        </div>
                        <canvas id="whiteboardCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- End Session Confirmation -->
    <div class="modal fade" id="endSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">End Live Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to end this live session?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> All participants will be disconnected and the session will end immediately.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmEndSession">End Session</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/frontend/assets/js/live-class-host.js"></script>
    <script>
        // Initialize live class session
        const liveClass = new LiveClassHost({
            roomId: '<?= htmlspecialchars($room_id) ?>',
            userId: <?= $user_id ?>,
            userRole: '<?= $user_role ?>',
            sessionId: <?= $session['id'] ?>,
            isHost: true
        });

        // Session timer
        let sessionStartTime = Date.now();
        const sessionTimeElement = document.getElementById('sessionTime');

        function updateSessionTime() {
            const elapsed = Math.floor((Date.now() - sessionStartTime) / 1000);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            
            sessionTimeElement.textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        setInterval(updateSessionTime, 1000);
        updateSessionTime();

        // Auto-save session data
        setInterval(() => {
            liveClass.saveSessionData();
        }, 30000); // Save every 30 seconds
    </script>
</body>
</html>
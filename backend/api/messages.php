<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = Auth::getUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single message
                $message_id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                           mr.is_read, mr.read_at, mr.is_starred, mr.folder
                    FROM messages m
                    JOIN users u ON m.sender_id = u.id
                    JOIN message_recipients mr ON m.id = mr.message_id
                    WHERE m.id = ? AND (mr.recipient_id = ? OR m.sender_id = ?)
                    AND mr.is_deleted = FALSE
                ");
                $stmt->execute([$message_id, $user_id, $user_id]);
                $message = $stmt->fetch();
                
                if ($message) {
                    // Mark as read
                    $pdo->prepare("UPDATE message_recipients SET is_read = TRUE, read_at = NOW() WHERE message_id = ? AND recipient_id = ?")
                        ->execute([$message_id, $user_id]);
                    
                    // Get recipients
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.name, u.avatar
                        FROM message_recipients mr
                        JOIN users u ON mr.recipient_id = u.id
                        WHERE mr.message_id = ?
                    ");
                    $stmt->execute([$message_id]);
                    $message['recipients'] = $stmt->fetchAll();
                    
                    // Get replies
                    $stmt = $pdo->prepare("
                        SELECT m.*, u.name as sender_name, u.avatar as sender_avatar
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.parent_id = ?
                        ORDER BY m.created_at ASC
                    ");
                    $stmt->execute([$message_id]);
                    $message['replies'] = $stmt->fetchAll();
                    
                    echo json_encode($message);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Message not found']);
                }
            } elseif (isset($_GET['folder'])) {
                // Get messages by folder
                $folder = $_GET['folder'];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                if ($folder === 'sent') {
                    $stmt = $pdo->prepare("
                        SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                               (SELECT COUNT(*) FROM message_recipients WHERE message_id = m.id) as recipient_count
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.sender_id = ? AND m.parent_id IS NULL
                        ORDER BY m.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->execute([$user_id, $limit, $offset]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                               mr.is_read, mr.is_starred, mr.folder
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        JOIN message_recipients mr ON m.id = mr.message_id
                        WHERE mr.recipient_id = ? AND mr.folder = ? AND mr.is_deleted = FALSE
                        AND m.parent_id IS NULL
                        ORDER BY m.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->execute([$user_id, $folder, $limit, $offset]);
                }
                
                $messages = $stmt->fetchAll();
                echo json_encode($messages);
            } elseif (isset($_GET['unread_count'])) {
                // Get unread message count
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM message_recipients
                    WHERE recipient_id = ? AND is_read = FALSE AND is_deleted = FALSE
                ");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch();
                echo json_encode(['count' => $result['count']]);
            } else {
                // Get inbox by default
                $stmt = $pdo->prepare("
                    SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                           mr.is_read, mr.is_starred
                    FROM messages m
                    JOIN users u ON m.sender_id = u.id
                    JOIN message_recipients mr ON m.id = mr.message_id
                    WHERE mr.recipient_id = ? AND mr.folder = 'inbox' AND mr.is_deleted = FALSE
                    AND m.parent_id IS NULL
                    ORDER BY m.created_at DESC
                    LIMIT 20
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['reply_to'])) {
                // Reply to message
                $parent_id = (int)$data['reply_to'];
                $body = $data['body'] ?? '';
                
                // Get original message
                $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
                $stmt->execute([$parent_id]);
                $parent = $stmt->fetch();
                
                if (!$parent) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Parent message not found']);
                    exit;
                }
                
                // Create reply
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, subject, body, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, 'Re: ' . $parent['subject'], $body, $parent_id]);
                $message_id = $pdo->lastInsertId();
                
                // Add recipient (original sender)
                $stmt = $pdo->prepare("INSERT INTO message_recipients (message_id, recipient_id) VALUES (?, ?)");
                $stmt->execute([$message_id, $parent['sender_id']]);
                
                // Create notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'message', ?, ?, ?)");
                $stmt->execute([
                    $parent['sender_id'],
                    'New Reply',
                    'You have a new reply to your message',
                    '/frontend/messages.php?id=' . $parent_id
                ]);
                
                echo json_encode(['success' => true, 'message_id' => $message_id]);
            } else {
                // Send new message
                $subject = $data['subject'] ?? '';
                $body = $data['body'] ?? '';
                $recipients = $data['recipients'] ?? [];
                
                if (empty($subject) || empty($body) || empty($recipients)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    exit;
                }
                
                // Create message
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, subject, body) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $subject, $body]);
                $message_id = $pdo->lastInsertId();
                
                // Add recipients
                $stmt = $pdo->prepare("INSERT INTO message_recipients (message_id, recipient_id) VALUES (?, ?)");
                foreach ($recipients as $recipient_id) {
                    $stmt->execute([$message_id, $recipient_id]);
                    
                    // Create notification
                    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'message', ?, ?, ?)");
                    $notif_stmt->execute([
                        $recipient_id,
                        'New Message',
                        'You have a new message: ' . $subject,
                        '/frontend/messages.php?id=' . $message_id
                    ]);
                }
                
                echo json_encode(['success' => true, 'message_id' => $message_id]);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $message_id = (int)$data['id'];
            
            if (isset($data['is_read'])) {
                $stmt = $pdo->prepare("UPDATE message_recipients SET is_read = ?, read_at = NOW() WHERE message_id = ? AND recipient_id = ?");
                $stmt->execute([$data['is_read'], $message_id, $user_id]);
            }
            
            if (isset($data['is_starred'])) {
                $stmt = $pdo->prepare("UPDATE message_recipients SET is_starred = ? WHERE message_id = ? AND recipient_id = ?");
                $stmt->execute([$data['is_starred'], $message_id, $user_id]);
            }
            
            if (isset($data['folder'])) {
                $stmt = $pdo->prepare("UPDATE message_recipients SET folder = ? WHERE message_id = ? AND recipient_id = ?");
                $stmt->execute([$data['folder'], $message_id, $user_id]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            $message_id = (int)$_GET['id'];
            $stmt = $pdo->prepare("UPDATE message_recipients SET is_deleted = TRUE WHERE message_id = ? AND recipient_id = ?");
            $stmt->execute([$message_id, $user_id]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

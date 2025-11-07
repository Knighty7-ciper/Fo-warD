<?php
/**
 * Messaging System API
 * Phase 5: Communication Tools
 * Features: Direct messaging, message threads, notifications
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $user = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    switch($method) {
        case 'GET':
            handleGetRequest($pdo, $user, $action);
            break;
        case 'POST':
            handlePostRequest($pdo, $user, $action, $input);
            break;
        case 'PUT':
            handlePutRequest($pdo, $user, $action, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $user, $action, $_GET);
            break;
        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

function handleGetRequest($pdo, $user, $action) {
    switch($action) {
        case 'conversations':
            getConversations($pdo, $user);
            break;
        case 'messages':
            getMessages($pdo, $user, $_GET);
            break;
        case 'unread':
            getUnreadMessages($pdo, $user);
            break;
        case 'contacts':
            getContacts($pdo, $user);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'send':
            sendMessage($pdo, $user, $input);
            break;
        case 'create_conversation':
            createConversation($pdo, $user, $input);
            break;
        case 'mark_read':
            markAsRead($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'update_message':
            updateMessage($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'delete_message':
            deleteMessage($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

// Get user conversations
function getConversations($pdo, $user) {
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
            m.content as last_message,
            m.created_at as last_message_time,
            (SELECT COUNT(*) FROM messages m2 
             WHERE m2.conversation_id = c.id 
             AND m2.sender_id != ? 
             AND m2.is_read = 0) as unread_count
        FROM conversations c
        JOIN users u1 ON c.participant_1_id = u1.id
        JOIN users u2 ON c.participant_2_id = u2.id
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE (c.participant_1_id = ? OR c.participant_2_id = ?)
        AND m.id = (
            SELECT MAX(m3.id) FROM messages m3 WHERE m3.conversation_id = c.id
        )
        ORDER BY c.updated_at DESC
    ");
    
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['conversations' => $conversations]);
}

// Get messages in a conversation
function getMessages($pdo, $user, $params) {
    $conversationId = $params['conversation_id'] ?? null;
    
    if (!$conversationId) {
        throw new Exception('Conversation ID required');
    }
    
    // Verify user is participant
    $stmt = $pdo->prepare("
        SELECT * FROM conversations 
        WHERE id = ? AND (participant_1_id = ? OR participant_2_id = ?)
    ");
    $stmt->execute([$conversationId, $user['id'], $user['id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Access denied');
    }
    
    // Get messages
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.name as sender_name,
            u.role as sender_role,
            u.avatar as sender_avatar
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark messages as read
    markMessagesAsRead($pdo, $conversationId, $user['id']);
    
    echo json_encode(['messages' => $messages]);
}

// Get unread message count
function getUnreadMessages($pdo, $user) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE (c.participant_1_id = ? OR c.participant_2_id = ?)
        AND m.sender_id != ?
        AND m.is_read = 0
    ");
    
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['unread_count' => (int)$result['unread_count']]);
}

// Get contacts (users user can message)
function getContacts($pdo, $user) {
    $role = $user['role'];
    $allowedRoles = [];
    
    if ($role === 'admin') {
        $allowedRoles = ['admin', 'teacher', 'student'];
    } elseif ($role === 'teacher') {
        $allowedRoles = ['admin', 'teacher', 'student'];
    } elseif ($role === 'student') {
        $allowedRoles = ['admin', 'teacher'];
    }
    
    $placeholders = str_repeat('?,', count($allowedRoles) - 1) . '?';
    $sql = "SELECT id, name, role, email, avatar FROM users WHERE role IN ($placeholders) AND id != ? ORDER BY name";
    
    $params = array_merge($allowedRoles, [$user['id']]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['contacts' => $contacts]);
}

// Send a new message
function sendMessage($pdo, $user, $input) {
    $conversationId = $input['conversation_id'] ?? null;
    $recipientId = $input['recipient_id'] ?? null;
    $content = trim($input['content'] ?? '');
    
    if (empty($content)) {
        throw new Exception('Message content required');
    }
    
    if (strlen($content) > 2000) {
        throw new Exception('Message too long (max 2000 characters)');
    }
    
    // Create conversation if it doesn't exist
    if (!$conversationId) {
        $conversationId = createConversation($pdo, $user, ['recipient_id' => $recipientId]);
    }
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, content, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([$conversationId, $user['id'], $content]);
    $messageId = $pdo->lastInsertId();
    
    // Update conversation timestamp
    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    // Get the created message
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['message' => $message, 'conversation_id' => $conversationId]);
}

// Create a new conversation
function createConversation($pdo, $user, $input) {
    $recipientId = $input['recipient_id'];
    
    if ($recipientId == $user['id']) {
        throw new Exception('Cannot create conversation with yourself');
    }
    
    // Check if conversation already exists
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE (participant_1_id = ? AND participant_2_id = ?) 
        OR (participant_1_id = ? AND participant_2_id = ?)
    ");
    $stmt->execute([$user['id'], $recipientId, $recipientId, $user['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        return $existing['id'];
    }
    
    // Create new conversation
    $stmt = $pdo->prepare("
        INSERT INTO conversations (participant_1_id, participant_2_id, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    $stmt->execute([$user['id'], $recipientId]);
    
    return $pdo->lastInsertId();
}

// Mark conversation as read
function markAsRead($pdo, $user, $input) {
    $conversationId = $input['conversation_id'] ?? null;
    
    if (!$conversationId) {
        throw new Exception('Conversation ID required');
    }
    
    markMessagesAsRead($pdo, $conversationId, $user['id']);
    
    echo json_encode(['success' => true]);
}

// Helper function to mark messages as read
function markMessagesAsRead($pdo, $conversationId, $userId) {
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
    ");
    $stmt->execute([$conversationId, $userId]);
}

// Update message (for editing)
function updateMessage($pdo, $user, $input) {
    $messageId = $input['message_id'] ?? null;
    $content = trim($input['content'] ?? '');
    
    if (!$messageId) {
        throw new Exception('Message ID required');
    }
    
    if (empty($content)) {
        throw new Exception('Message content required');
    }
    
    // Verify user owns the message
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$messageId, $user['id']]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        throw new Exception('Message not found or access denied');
    }
    
    // Only allow editing within 5 minutes
    $timeDiff = time() - strtotime($message['created_at']);
    if ($timeDiff > 300) { // 5 minutes
        throw new Exception('Message too old to edit');
    }
    
    // Update message
    $stmt = $pdo->prepare("UPDATE messages SET content = ?, is_edited = 1, edited_at = NOW() WHERE id = ?");
    $stmt->execute([$content, $messageId]);
    
    echo json_encode(['success' => true]);
}

// Delete message
function deleteMessage($pdo, $user, $params) {
    $messageId = $params['id'] ?? null;
    
    if (!$messageId) {
        throw new Exception('Message ID required');
    }
    
    // Verify user owns the message
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$messageId, $user['id']]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        throw new Exception('Message not found or access denied');
    }
    
    // Soft delete - just mark as deleted
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$messageId]);
    
    echo json_encode(['success' => true]);
}
?>
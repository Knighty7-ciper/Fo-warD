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
            if (isset($_GET['unread_count'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch();
                echo json_encode(['count' => $result['count']]);
            } else {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $stmt = $pdo->prepare("
                    SELECT * FROM notifications
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$user_id, $limit]);
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['mark_all_read'])) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } elseif (isset($data['id'])) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$data['id'], $user_id]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$_GET['id'], $user_id]);
            } elseif (isset($_GET['clear_all'])) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

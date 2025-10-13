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
            // Get user's bookmarks
            $type = $_GET['type'] ?? 'all';
            
            $sql = "SELECT * FROM bookmarks WHERE user_id = ?";
            $params = [$user_id];
            
            if ($type !== 'all') {
                $sql .= " AND bookmarkable_type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $bookmarks = $stmt->fetchAll();
            
            // Fetch details for each bookmark
            foreach ($bookmarks as &$bookmark) {
                $table = '';
                $fields = 'id, title';
                
                switch ($bookmark['bookmarkable_type']) {
                    case 'course':
                        $table = 'courses';
                        $fields = 'id, title, description, thumbnail';
                        break;
                    case 'lesson':
                        $table = 'lessons';
                        $fields = 'id, title, description, course_id';
                        break;
                    case 'assignment':
                        $table = 'assignments';
                        $fields = 'id, title, description, due_date';
                        break;
                    case 'quiz':
                        $table = 'quizzes';
                        $fields = 'id, title, description';
                        break;
                    case 'forum_topic':
                        $table = 'forum_topics';
                        $fields = 'id, title, content as description';
                        break;
                }
                
                if ($table) {
                    $stmt = $pdo->prepare("SELECT $fields FROM $table WHERE id = ?");
                    $stmt->execute([$bookmark['bookmarkable_id']]);
                    $bookmark['details'] = $stmt->fetch();
                }
            }
            
            echo json_encode($bookmarks);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                INSERT INTO bookmarks (user_id, bookmarkable_type, bookmarkable_id, notes)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE notes = VALUES(notes)
            ");
            $stmt->execute([
                $user_id,
                $data['type'],
                $data['id'],
                $data['notes'] ?? ''
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            $type = $_GET['type'];
            $id = (int)$_GET['id'];
            
            $stmt = $pdo->prepare("
                DELETE FROM bookmarks 
                WHERE user_id = ? AND bookmarkable_type = ? AND bookmarkable_id = ?
            ");
            $stmt->execute([$user_id, $type, $id]);
            
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

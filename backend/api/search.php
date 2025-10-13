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
    if ($method === 'GET') {
        $query = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? 'all';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        
        if (empty($query)) {
            echo json_encode(['error' => 'Query parameter required']);
            exit;
        }
        
        $search_term = '%' . $query . '%';
        $results = [];
        
        // Search courses
        if ($type === 'all' || $type === 'courses') {
            $stmt = $pdo->prepare("
                SELECT 'course' as type, id, title, description, thumbnail as image,
                       teacher_id, created_at
                FROM courses
                WHERE (title LIKE ? OR description LIKE ?) AND status = 'published'
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $limit]);
            $results['courses'] = $stmt->fetchAll();
        }
        
        // Search lessons
        if ($type === 'all' || $type === 'lessons') {
            $stmt = $pdo->prepare("
                SELECT 'lesson' as type, l.id, l.title, l.description, l.course_id,
                       c.title as course_title, l.created_at
                FROM lessons l
                JOIN courses c ON l.course_id = c.id
                WHERE (l.title LIKE ? OR l.description LIKE ? OR l.content LIKE ?)
                AND c.status = 'published'
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $search_term, $limit]);
            $results['lessons'] = $stmt->fetchAll();
        }
        
        // Search assignments
        if ($type === 'all' || $type === 'assignments') {
            $stmt = $pdo->prepare("
                SELECT 'assignment' as type, a.id, a.title, a.description,
                       a.course_id, c.title as course_title, a.due_date, a.created_at
                FROM assignments a
                JOIN courses c ON a.course_id = c.id
                WHERE (a.title LIKE ? OR a.description LIKE ?) AND a.status = 'published'
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $limit]);
            $results['assignments'] = $stmt->fetchAll();
        }
        
        // Search quizzes
        if ($type === 'all' || $type === 'quizzes') {
            $stmt = $pdo->prepare("
                SELECT 'quiz' as type, q.id, q.title, q.description,
                       q.course_id, c.title as course_title, q.created_at
                FROM quizzes q
                JOIN courses c ON q.course_id = c.id
                WHERE (q.title LIKE ? OR q.description LIKE ?) AND q.status = 'published'
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $limit]);
            $results['quizzes'] = $stmt->fetchAll();
        }
        
        // Search forum topics
        if ($type === 'all' || $type === 'forum') {
            $stmt = $pdo->prepare("
                SELECT 'forum_topic' as type, t.id, t.title, t.content as description,
                       t.category_id, c.name as category_name, t.created_at
                FROM forum_topics t
                JOIN forum_categories c ON t.category_id = c.id
                WHERE (t.title LIKE ? OR t.content LIKE ?)
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $limit]);
            $results['forum'] = $stmt->fetchAll();
        }
        
        // Search users (if admin or teacher)
        if (Auth::getUserRole() !== 'student' && ($type === 'all' || $type === 'users')) {
            $stmt = $pdo->prepare("
                SELECT 'user' as type, id, name, email, role, avatar, created_at
                FROM users
                WHERE (name LIKE ? OR email LIKE ?) AND status = 'active'
                LIMIT ?
            ");
            $stmt->execute([$search_term, $search_term, $limit]);
            $results['users'] = $stmt->fetchAll();
        }
        
        // Calculate total results
        $total_results = 0;
        foreach ($results as $category) {
            $total_results += count($category);
        }
        
        // Save search history
        $stmt = $pdo->prepare("
            INSERT INTO search_history (user_id, query, result_count)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $query, $total_results]);
        
        // Update popular searches
        $stmt = $pdo->prepare("
            INSERT INTO popular_searches (query, search_count)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE search_count = search_count + 1
        ");
        $stmt->execute([$query]);
        
        echo json_encode([
            'query' => $query,
            'total_results' => $total_results,
            'results' => $results
        ]);
        
    } elseif ($method === 'GET' && isset($_GET['suggestions'])) {
        // Get search suggestions
        $query = $_GET['q'] ?? '';
        $search_term = $query . '%';
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT query, search_count
            FROM popular_searches
            WHERE query LIKE ?
            ORDER BY search_count DESC
            LIMIT 10
        ");
        $stmt->execute([$search_term]);
        
        echo json_encode($stmt->fetchAll());
        
    } elseif ($method === 'GET' && isset($_GET['recent'])) {
        // Get recent searches
        $stmt = $pdo->prepare("
            SELECT DISTINCT query, created_at
            FROM search_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        
        echo json_encode($stmt->fetchAll());
        
    } elseif ($method === 'GET' && isset($_GET['popular'])) {
        // Get popular searches
        $stmt = $pdo->query("
            SELECT query, search_count
            FROM popular_searches
            ORDER BY search_count DESC
            LIMIT 10
        ");
        
        echo json_encode($stmt->fetchAll());
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

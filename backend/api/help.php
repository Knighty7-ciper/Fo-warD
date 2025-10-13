<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch($_GET['action']) {
                    case 'search':
                        searchArticles($conn, $_GET['q'] ?? '');
                        break;
                    case 'categories':
                        getCategories($conn);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else if (isset($_GET['slug'])) {
                getArticleBySlug($conn, $_GET['slug']);
            } else if (isset($_GET['id'])) {
                getArticle($conn, $_GET['id']);
            } else {
                listArticles($conn);
            }
            break;
            
        case 'POST':
            $user = requireAuth();
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['feedback'])) {
                submitFeedback($conn, $data, $user);
            } else {
                createArticle($conn, $data, $user);
            }
            break;
            
        case 'PUT':
            $user = requireAuth();
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            updateArticle($conn, $data, $user);
            break;
            
        case 'DELETE':
            $user = requireAuth();
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            deleteArticle($conn, $data['id']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listArticles($conn) {
    $category = $_GET['category'] ?? null;
    
    $sql = "SELECT * FROM help_articles WHERE status = 'published'";
    $params = [];
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY views DESC, created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'articles' => $articles]);
}

function getArticle($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM help_articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        http_response_code(404);
        echo json_encode(['error' => 'Article not found']);
        return;
    }
    
    // Increment views
    $stmt = $conn->prepare("UPDATE help_articles SET views = views + 1 WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'article' => $article]);
}

function getArticleBySlug($conn, $slug) {
    $stmt = $conn->prepare("SELECT * FROM help_articles WHERE slug = ? AND status = 'published'");
    $stmt->execute([$slug]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        http_response_code(404);
        echo json_encode(['error' => 'Article not found']);
        return;
    }
    
    // Increment views
    $stmt = $conn->prepare("UPDATE help_articles SET views = views + 1 WHERE id = ?");
    $stmt->execute([$article['id']]);
    
    echo json_encode(['success' => true, 'article' => $article]);
}

function searchArticles($conn, $query) {
    $stmt = $conn->prepare("
        SELECT * FROM help_articles
        WHERE status = 'published'
        AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)
        ORDER BY views DESC
        LIMIT 20
    ");
    
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'articles' => $articles]);
}

function getCategories($conn) {
    $stmt = $conn->query("
        SELECT category, COUNT(*) as count
        FROM help_articles
        WHERE status = 'published'
        GROUP BY category
        ORDER BY count DESC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function createArticle($conn, $data, $user) {
    $slug = generateSlug($data['title']);
    
    $stmt = $conn->prepare("
        INSERT INTO help_articles (title, slug, content, category, tags, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['title'],
        $slug,
        $data['content'],
        $data['category'] ?? 'General',
        $data['tags'] ?? '',
        $data['status'] ?? 'published',
        $user['id']
    ]);
    
    $articleId = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'id' => $articleId, 'message' => 'Article created successfully']);
}

function updateArticle($conn, $data, $user) {
    $stmt = $conn->prepare("
        UPDATE help_articles 
        SET title = ?, content = ?, category = ?, tags = ?, status = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['title'],
        $data['content'],
        $data['category'] ?? 'General',
        $data['tags'] ?? '',
        $data['status'] ?? 'published',
        $data['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Article updated successfully']);
}

function deleteArticle($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM help_articles WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Article deleted successfully']);
}

function submitFeedback($conn, $data, $user) {
    $stmt = $conn->prepare("
        INSERT INTO help_feedback (article_id, user_id, is_helpful, comment)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['article_id'],
        $user['id'] ?? null,
        $data['is_helpful'],
        $data['comment'] ?? null
    ]);
    
    // Update article counts
    $field = $data['is_helpful'] ? 'helpful_count' : 'not_helpful_count';
    $stmt = $conn->prepare("UPDATE help_articles SET $field = $field + 1 WHERE id = ?");
    $stmt->execute([$data['article_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Thank you for your feedback']);
}

function generateSlug($title) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    return $slug;
}

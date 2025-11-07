<?php
/**
 * Discussion Forums API
 * Phase 5: Communication Tools
 * Features: Course forums, topic discussions, threaded replies
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
            handleGetRequest($pdo, $user, $action, $_GET);
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

function handleGetRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'forums':
            getForums($pdo, $user, $params);
            break;
        case 'topics':
            getTopics($pdo, $user, $params);
            break;
        case 'posts':
            getPosts($pdo, $user, $params);
            break;
        case 'posts_tree':
            getPostsTree($pdo, $user, $params);
            break;
        case 'search':
            searchForums($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'create_forum':
            createForum($pdo, $user, $input);
            break;
        case 'create_topic':
            createTopic($pdo, $user, $input);
            break;
        case 'create_post':
            createPost($pdo, $user, $input);
            break;
        case 'like_post':
            likePost($pdo, $user, $input);
            break;
        case 'attach_file':
            attachFile($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'update_forum':
            updateForum($pdo, $user, $input);
            break;
        case 'update_topic':
            updateTopic($pdo, $user, $input);
            break;
        case 'update_post':
            updatePost($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'delete_forum':
            deleteForum($pdo, $user, $params);
            break;
        case 'delete_topic':
            deleteTopic($pdo, $user, $params);
            break;
        case 'delete_post':
            deletePost($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

// Get forums (course-based)
function getForums($pdo, $user, $params) {
    $courseId = $params['course_id'] ?? null;
    
    $sql = "
        SELECT 
            f.*,
            c.title as course_title,
            COUNT(DISTINCT t.id) as topic_count,
            COUNT(DISTINCT p.id) as post_count,
            MAX(p.created_at) as last_activity
        FROM forums f
        JOIN courses c ON f.course_id = c.id
        LEFT JOIN topics t ON f.id = t.forum_id
        LEFT JOIN posts p ON t.id = p.topic_id
    ";
    
    $whereConditions = [];
    $params = [];
    
    if ($courseId) {
        $whereConditions[] = "f.course_id = ?";
        $params[] = $courseId;
    }
    
    // Role-based filtering
    if ($user['role'] === 'student') {
        $whereConditions[] = "c.id IN (SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active')";
        $params[] = $user['id'];
    } elseif ($user['role'] === 'teacher') {
        $whereConditions[] = "c.teacher_id = ?";
        $params[] = $user['id'];
    }
    // Admins can see all forums
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " GROUP BY f.id ORDER BY f.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['forums' => $forums]);
}

// Get topics in a forum
function getTopics($pdo, $user, $params) {
    $forumId = $params['forum_id'] ?? null;
    $page = (int)($params['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    if (!$forumId) {
        throw new Exception('Forum ID required');
    }
    
    // Verify access to forum
    $stmt = $pdo->prepare("
        SELECT f.*, c.teacher_id 
        FROM forums f 
        JOIN courses c ON f.course_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$forumId]);
    $forum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$forum) {
        throw new Exception('Forum not found');
    }
    
    // Check access permissions
    if (!hasForumAccess($pdo, $user, $forum)) {
        throw new Exception('Access denied');
    }
    
    // Get topics
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.name as author_name,
            u.role as author_role,
            u.avatar as author_avatar,
            COUNT(DISTINCT p.id) as reply_count,
            MAX(p.created_at) as last_reply,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = t.id) as like_count
        FROM topics t
        JOIN users u ON t.author_id = u.id
        LEFT JOIN posts p ON t.id = p.topic_id
        WHERE t.forum_id = ?
        GROUP BY t.id
        ORDER BY 
            CASE WHEN t.is_pinned THEN 1 ELSE 0 END DESC,
            t.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$forumId, $limit, $offset]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM topics WHERE forum_id = ?");
    $stmt->execute([$forumId]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'topics' => $topics,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// Get posts in a topic (flat list)
function getPosts($pdo, $user, $params) {
    $topicId = $params['topic_id'] ?? null;
    $page = (int)($params['page'] ?? 1);
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    if (!$topicId) {
        throw new Exception('Topic ID required');
    }
    
    // Verify access to topic
    $stmt = $pdo->prepare("
        SELECT t.*, f.course_id, c.teacher_id
        FROM topics t
        JOIN forums f ON t.forum_id = f.id
        JOIN courses c ON f.course_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        throw new Exception('Topic not found');
    }
    
    // Check access permissions
    if (!hasTopicAccess($pdo, $user, $topic)) {
        throw new Exception('Access denied');
    }
    
    // Get posts
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name as author_name,
            u.role as author_role,
            u.avatar as author_avatar,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.author_id = u.id
        WHERE p.topic_id = ? AND p.parent_id IS NULL
        ORDER BY p.created_at ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user['id'], $topicId, $limit, $offset]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM posts WHERE topic_id = ? AND parent_id IS NULL");
    $stmt->execute([$topicId]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'posts' => $posts,
        'topic' => $topic,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// Get posts in a topic (threaded structure)
function getPostsTree($pdo, $user, $params) {
    $topicId = $params['topic_id'] ?? null;
    
    if (!$topicId) {
        throw new Exception('Topic ID required');
    }
    
    // Get all posts for the topic
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name as author_name,
            u.role as author_role,
            u.avatar as author_avatar,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as like_count
        FROM posts p
        JOIN users u ON p.author_id = u.id
        WHERE p.topic_id = ?
        ORDER BY p.created_at ASC
    ");
    
    $stmt->execute([$topicId]);
    $allPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build tree structure
    $posts = [];
    $children = [];
    
    foreach ($allPosts as $post) {
        if ($post['parent_id'] === null) {
            $post['replies'] = [];
            $posts[$post['id']] = $post;
        } else {
            $children[$post['parent_id']][] = $post;
        }
    }
    
    // Add replies to parent posts
    foreach ($children as $parentId => $replies) {
        if (isset($posts[$parentId])) {
            $posts[$parentId]['replies'] = $replies;
        }
    }
    
    $posts = array_values($posts);
    
    echo json_encode(['posts' => $posts]);
}

// Search forums
function searchForums($pdo, $user, $params) {
    $query = $params['q'] ?? '';
    $courseId = $params['course_id'] ?? null;
    
    if (strlen($query) < 3) {
        throw new Exception('Search query must be at least 3 characters');
    }
    
    $sql = "
        SELECT DISTINCT
            t.id as topic_id,
            t.title as topic_title,
            t.content as topic_content,
            f.id as forum_id,
            f.title as forum_title,
            c.title as course_title,
            u.name as author_name
        FROM topics t
        JOIN forums f ON t.forum_id = f.id
        JOIN courses c ON f.course_id = c.id
        JOIN users u ON t.author_id = u.id
        WHERE (t.title LIKE ? OR t.content LIKE ?)
    ";
    
    $searchTerm = "%$query%";
    $queryParams = [$searchTerm, $searchTerm];
    
    if ($courseId) {
        $sql .= " AND f.course_id = ?";
        $queryParams[] = $courseId;
    }
    
    // Role-based filtering
    if ($user['role'] === 'student') {
        $sql .= " AND c.id IN (SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active')";
        $queryParams[] = $user['id'];
    } elseif ($user['role'] === 'teacher') {
        $sql .= " AND c.teacher_id = ?";
        $queryParams[] = $user['id'];
    }
    
    $sql .= " ORDER BY t.updated_at DESC LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['results' => $results]);
}

// Create new forum
function createForum($pdo, $user, $input) {
    if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
        throw new Exception('Only teachers and admins can create forums');
    }
    
    $courseId = $input['course_id'];
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    
    if (empty($title)) {
        throw new Exception('Forum title required');
    }
    
    // Verify teacher owns the course
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND (teacher_id = ? OR ? = 'admin')");
    $stmt->execute([$courseId, $user['id'], $user['role']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Access denied or course not found');
    }
    
    // Create forum
    $stmt = $pdo->prepare("
        INSERT INTO forums (course_id, title, description, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$courseId, $title, $description, $user['id']]);
    $forumId = $pdo->lastInsertId();
    
    echo json_encode(['forum_id' => $forumId, 'success' => true]);
}

// Create new topic
function createTopic($pdo, $user, $input) {
    $forumId = $input['forum_id'];
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $isPinned = $input['is_pinned'] ?? false;
    
    if (empty($title) || empty($content)) {
        throw new Exception('Title and content required');
    }
    
    // Verify access to forum
    $stmt = $pdo->prepare("
        SELECT f.*, c.teacher_id 
        FROM forums f 
        JOIN courses c ON f.course_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$forumId]);
    $forum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$forum) {
        throw new Exception('Forum not found');
    }
    
    if (!hasForumAccess($pdo, $user, $forum)) {
        throw new Exception('Access denied');
    }
    
    // Create topic
    $stmt = $pdo->prepare("
        INSERT INTO topics (forum_id, author_id, title, content, is_pinned, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$forumId, $user['id'], $title, $content, $isPinned ? 1 : 0]);
    $topicId = $pdo->lastInsertId();
    
    echo json_encode(['topic_id' => $topicId, 'success' => true]);
}

// Create new post
function createPost($pdo, $user, $input) {
    $topicId = $input['topic_id'];
    $content = trim($input['content'] ?? '');
    $parentId = $input['parent_id'] ?? null; // For replies
    
    if (empty($content)) {
        throw new Exception('Post content required');
    }
    
    if (strlen($content) > 5000) {
        throw new Exception('Post too long (max 5000 characters)');
    }
    
    // Verify access to topic
    $stmt = $pdo->prepare("
        SELECT t.*, f.course_id, c.teacher_id
        FROM topics t
        JOIN forums f ON t.forum_id = f.id
        JOIN courses c ON f.course_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        throw new Exception('Topic not found');
    }
    
    if (!hasTopicAccess($pdo, $user, $topic)) {
        throw new Exception('Access denied');
    }
    
    // Verify parent post belongs to same topic if replying
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND topic_id = ?");
        $stmt->execute([$parentId, $topicId]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid parent post');
        }
    }
    
    // Create post
    $stmt = $pdo->prepare("
        INSERT INTO posts (topic_id, author_id, content, parent_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$topicId, $user['id'], $content, $parentId]);
    $postId = $pdo->lastInsertId();
    
    // Update topic updated_at
    $stmt = $pdo->prepare("UPDATE topics SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$topicId]);
    
    echo json_encode(['post_id' => $postId, 'success' => true]);
}

// Like/unlike a post
function likePost($pdo, $user, $input) {
    $postId = $input['post_id'];
    
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $user['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $action = 'unliked';
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$postId, $user['id']]);
        $action = 'liked';
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode(['action' => $action, 'like_count' => (int)$likeCount]);
}

// Helper function to check forum access
function hasForumAccess($pdo, $user, $forum) {
    if ($user['role'] === 'admin') {
        return true;
    }
    
    if ($user['role'] === 'teacher' && $forum['teacher_id'] == $user['id']) {
        return true;
    }
    
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("
            SELECT id FROM enrollments 
            WHERE student_id = ? AND course_id = ? AND status = 'active'
        ");
        $stmt->execute([$user['id'], $forum['course_id']]);
        return (bool)$stmt->fetch();
    }
    
    return false;
}

// Helper function to check topic access
function hasTopicAccess($pdo, $user, $topic) {
    if ($user['role'] === 'admin') {
        return true;
    }
    
    if ($user['role'] === 'teacher' && $topic['teacher_id'] == $user['id']) {
        return true;
    }
    
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("
            SELECT id FROM enrollments 
            WHERE student_id = ? AND course_id = ? AND status = 'active'
        ");
        $stmt->execute([$user['id'], $topic['course_id']]);
        return (bool)$stmt->fetch();
    }
    
    return false;
}

// Update forum
function updateForum($pdo, $user, $input) {
    $forumId = $input['forum_id'];
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    
    if (empty($title)) {
        throw new Exception('Forum title required');
    }
    
    // Verify user can update forum
    $stmt = $pdo->prepare("
        SELECT f.*, c.teacher_id 
        FROM forums f 
        JOIN courses c ON f.course_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$forumId]);
    $forum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$forum) {
        throw new Exception('Forum not found');
    }
    
    if ($user['role'] !== 'admin' && $forum['teacher_id'] != $user['id']) {
        throw new Exception('Access denied');
    }
    
    // Update forum
    $stmt = $pdo->prepare("UPDATE forums SET title = ?, description = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $description, $forumId]);
    
    echo json_encode(['success' => true]);
}

// Update topic
function updateTopic($pdo, $user, $input) {
    $topicId = $input['topic_id'];
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    
    if (empty($title) || empty($content)) {
        throw new Exception('Title and content required');
    }
    
    // Verify user owns the topic
    $stmt = $pdo->prepare("SELECT author_id FROM topics WHERE id = ?");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        throw new Exception('Topic not found');
    }
    
    if ($topic['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Update topic
    $stmt = $pdo->prepare("UPDATE topics SET title = ?, content = ?, is_edited = 1, edited_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $content, $topicId]);
    
    echo json_encode(['success' => true]);
}

// Update post
function updatePost($pdo, $user, $input) {
    $postId = $input['post_id'];
    $content = trim($input['content'] ?? '');
    
    if (empty($content)) {
        throw new Exception('Post content required');
    }
    
    // Verify user owns the post
    $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    if ($post['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Update post
    $stmt = $pdo->prepare("UPDATE posts SET content = ?, is_edited = 1, edited_at = NOW() WHERE id = ?");
    $stmt->execute([$content, $postId]);
    
    echo json_encode(['success' => true]);
}

// Delete forum
function deleteForum($pdo, $user, $params) {
    $forumId = $params['id'];
    
    // Verify user can delete forum
    $stmt = $pdo->prepare("
        SELECT f.*, c.teacher_id 
        FROM forums f 
        JOIN courses c ON f.course_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$forumId]);
    $forum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$forum) {
        throw new Exception('Forum not found');
    }
    
    if ($user['role'] !== 'admin' && $forum['teacher_id'] != $user['id']) {
        throw new Exception('Access denied');
    }
    
    // Soft delete forum
    $stmt = $pdo->prepare("UPDATE forums SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$forumId]);
    
    echo json_encode(['success' => true]);
}

// Delete topic
function deleteTopic($pdo, $user, $params) {
    $topicId = $params['id'];
    
    // Verify user can delete topic
    $stmt = $pdo->prepare("SELECT author_id FROM topics WHERE id = ?");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        throw new Exception('Topic not found');
    }
    
    if ($topic['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Soft delete topic
    $stmt = $pdo->prepare("UPDATE topics SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$topicId]);
    
    echo json_encode(['success' => true]);
}

// Delete post
function deletePost($pdo, $user, $params) {
    $postId = $params['id'];
    
    // Verify user can delete post
    $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    if ($post['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Soft delete post
    $stmt = $pdo->prepare("UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$postId]);
    
    echo json_encode(['success' => true]);
}

// Attach file to post
function attachFile($pdo, $user, $input) {
    $postId = $input['post_id'];
    $fileId = $input['file_id'];
    
    // Verify user owns the post
    $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    if ($post['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Verify file belongs to user
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$fileId, $user['id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found or access denied');
    }
    
    // Attach file to post
    $stmt = $pdo->prepare("UPDATE posts SET attached_file_id = ? WHERE id = ?");
    $stmt->execute([$fileId, $postId]);
    
    echo json_encode(['success' => true]);
}
?>
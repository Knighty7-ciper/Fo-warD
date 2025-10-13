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
$user_role = Auth::getUserRole();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['categories'])) {
                // Get all categories with topic counts
                $stmt = $pdo->query("
                    SELECT c.*, 
                           (SELECT COUNT(*) FROM forum_topics WHERE category_id = c.id) as topic_count,
                           (SELECT COUNT(*) FROM forum_posts p 
                            JOIN forum_topics t ON p.topic_id = t.id 
                            WHERE t.category_id = c.id) as post_count
                    FROM forum_categories c
                    WHERE c.is_active = TRUE
                    ORDER BY c.order_index
                ");
                echo json_encode($stmt->fetchAll());
                
            } elseif (isset($_GET['category_id'])) {
                // Get topics by category
                $category_id = (int)$_GET['category_id'];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as author_name, u.avatar as author_avatar,
                           lr.name as last_reply_name,
                           (SELECT COUNT(*) FROM forum_votes WHERE votable_type = 'topic' AND votable_id = t.id AND vote_type = 'up') as upvotes
                    FROM forum_topics t
                    JOIN users u ON t.author_id = u.id
                    LEFT JOIN users lr ON t.last_reply_by = lr.id
                    WHERE t.category_id = ?
                    ORDER BY t.is_pinned DESC, t.last_reply_at DESC, t.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$category_id, $limit, $offset]);
                echo json_encode($stmt->fetchAll());
                
            } elseif (isset($_GET['topic_id'])) {
                // Get single topic with posts
                $topic_id = (int)$_GET['topic_id'];
                
                // Increment view count
                $pdo->prepare("UPDATE forum_topics SET view_count = view_count + 1 WHERE id = ?")
                    ->execute([$topic_id]);
                
                // Get topic
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as author_name, u.avatar as author_avatar,
                           c.name as category_name,
                           (SELECT COUNT(*) FROM forum_votes WHERE votable_type = 'topic' AND votable_id = t.id AND vote_type = 'up') as upvotes,
                           (SELECT vote_type FROM forum_votes WHERE votable_type = 'topic' AND votable_id = t.id AND user_id = ?) as user_vote
                    FROM forum_topics t
                    JOIN users u ON t.author_id = u.id
                    JOIN forum_categories c ON t.category_id = c.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$user_id, $topic_id]);
                $topic = $stmt->fetch();
                
                if ($topic) {
                    // Get posts
                    $stmt = $pdo->prepare("
                        SELECT p.*, u.name as author_name, u.avatar as author_avatar,
                               (SELECT COUNT(*) FROM forum_votes WHERE votable_type = 'post' AND votable_id = p.id AND vote_type = 'up') as upvotes,
                               (SELECT vote_type FROM forum_votes WHERE votable_type = 'post' AND votable_id = p.id AND user_id = ?) as user_vote
                        FROM forum_posts p
                        JOIN users u ON p.author_id = u.id
                        WHERE p.topic_id = ?
                        ORDER BY p.is_solution DESC, p.created_at ASC
                    ");
                    $stmt->execute([$user_id, $topic_id]);
                    $topic['posts'] = $stmt->fetchAll();
                    
                    // Check if user is subscribed
                    $stmt = $pdo->prepare("SELECT id FROM forum_subscriptions WHERE user_id = ? AND topic_id = ?");
                    $stmt->execute([$user_id, $topic_id]);
                    $topic['is_subscribed'] = $stmt->fetch() ? true : false;
                    
                    echo json_encode($topic);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Topic not found']);
                }
                
            } elseif (isset($_GET['search'])) {
                // Search topics
                $search = '%' . $_GET['search'] . '%';
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as author_name, c.name as category_name,
                           (SELECT COUNT(*) FROM forum_votes WHERE votable_type = 'topic' AND votable_id = t.id AND vote_type = 'up') as upvotes
                    FROM forum_topics t
                    JOIN users u ON t.author_id = u.id
                    JOIN forum_categories c ON t.category_id = c.id
                    WHERE t.title LIKE ? OR t.content LIKE ?
                    ORDER BY t.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$search, $search]);
                echo json_encode($stmt->fetchAll());
                
            } elseif (isset($_GET['user_topics'])) {
                // Get user's topics
                $stmt = $pdo->prepare("
                    SELECT t.*, c.name as category_name,
                           (SELECT COUNT(*) FROM forum_posts WHERE topic_id = t.id) as reply_count
                    FROM forum_topics t
                    JOIN forum_categories c ON t.category_id = c.id
                    WHERE t.author_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['create_topic'])) {
                // Create new topic
                $stmt = $pdo->prepare("
                    INSERT INTO forum_topics (category_id, course_id, author_id, title, content)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['category_id'],
                    $data['course_id'] ?? null,
                    $user_id,
                    $data['title'],
                    $data['content']
                ]);
                
                $topic_id = $pdo->lastInsertId();
                
                // Auto-subscribe author to topic
                $pdo->prepare("INSERT INTO forum_subscriptions (user_id, topic_id) VALUES (?, ?)")
                    ->execute([$user_id, $topic_id]);
                
                echo json_encode(['success' => true, 'topic_id' => $topic_id]);
                
            } elseif (isset($data['create_post'])) {
                // Create reply/post
                $topic_id = (int)$data['topic_id'];
                
                // Check if topic is locked
                $stmt = $pdo->prepare("SELECT is_locked FROM forum_topics WHERE id = ?");
                $stmt->execute([$topic_id]);
                $topic = $stmt->fetch();
                
                if ($topic['is_locked'] && $user_role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Topic is locked']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO forum_posts (topic_id, author_id, content, parent_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $topic_id,
                    $user_id,
                    $data['content'],
                    $data['parent_id'] ?? null
                ]);
                
                $post_id = $pdo->lastInsertId();
                
                // Update topic reply count and last reply
                $pdo->prepare("
                    UPDATE forum_topics 
                    SET reply_count = reply_count + 1, last_reply_at = NOW(), last_reply_by = ?
                    WHERE id = ?
                ")->execute([$user_id, $topic_id]);
                
                // Notify subscribed users
                $stmt = $pdo->prepare("
                    SELECT user_id FROM forum_subscriptions 
                    WHERE topic_id = ? AND user_id != ?
                ");
                $stmt->execute([$topic_id, $user_id]);
                $subscribers = $stmt->fetchAll();
                
                foreach ($subscribers as $sub) {
                    $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, link)
                        VALUES (?, 'forum', ?, ?, ?)
                    ")->execute([
                        $sub['user_id'],
                        'New Forum Reply',
                        'Someone replied to a topic you are following',
                        '/frontend/forum/topic.php?id=' . $topic_id
                    ]);
                }
                
                echo json_encode(['success' => true, 'post_id' => $post_id]);
                
            } elseif (isset($data['vote'])) {
                // Vote on topic or post
                $votable_type = $data['votable_type'];
                $votable_id = (int)$data['votable_id'];
                $vote_type = $data['vote_type'];
                
                // Check if already voted
                $stmt = $pdo->prepare("
                    SELECT id FROM forum_votes 
                    WHERE user_id = ? AND votable_type = ? AND votable_id = ?
                ");
                $stmt->execute([$user_id, $votable_type, $votable_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update vote
                    $pdo->prepare("
                        UPDATE forum_votes 
                        SET vote_type = ?
                        WHERE id = ?
                    ")->execute([$vote_type, $existing['id']]);
                } else {
                    // Insert new vote
                    $pdo->prepare("
                        INSERT INTO forum_votes (user_id, votable_type, votable_id, vote_type)
                        VALUES (?, ?, ?, ?)
                    ")->execute([$user_id, $votable_type, $votable_id, $vote_type]);
                }
                
                echo json_encode(['success' => true]);
                
            } elseif (isset($data['subscribe'])) {
                // Subscribe to topic
                $topic_id = (int)$data['topic_id'];
                
                $pdo->prepare("
                    INSERT IGNORE INTO forum_subscriptions (user_id, topic_id)
                    VALUES (?, ?)
                ")->execute([$user_id, $topic_id]);
                
                echo json_encode(['success' => true]);
                
            } elseif (isset($data['unsubscribe'])) {
                // Unsubscribe from topic
                $topic_id = (int)$data['topic_id'];
                
                $pdo->prepare("
                    DELETE FROM forum_subscriptions 
                    WHERE user_id = ? AND topic_id = ?
                ")->execute([$user_id, $topic_id]);
                
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['mark_solution'])) {
                // Mark post as solution
                $post_id = (int)$data['post_id'];
                
                // Get topic and check if user is author
                $stmt = $pdo->prepare("
                    SELECT t.author_id FROM forum_topics t
                    JOIN forum_posts p ON t.id = p.topic_id
                    WHERE p.id = ?
                ");
                $stmt->execute([$post_id]);
                $topic = $stmt->fetch();
                
                if ($topic['author_id'] == $user_id || $user_role === 'admin') {
                    $pdo->prepare("UPDATE forum_posts SET is_solution = TRUE WHERE id = ?")
                        ->execute([$post_id]);
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only topic author can mark solution']);
                }
                
            } elseif (isset($data['pin_topic'])) {
                // Pin/unpin topic (admin only)
                if ($user_role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Admin only']);
                    exit;
                }
                
                $topic_id = (int)$data['topic_id'];
                $is_pinned = $data['is_pinned'];
                
                $pdo->prepare("UPDATE forum_topics SET is_pinned = ? WHERE id = ?")
                    ->execute([$is_pinned, $topic_id]);
                
                echo json_encode(['success' => true]);
                
            } elseif (isset($data['lock_topic'])) {
                // Lock/unlock topic (admin only)
                if ($user_role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Admin only']);
                    exit;
                }
                
                $topic_id = (int)$data['topic_id'];
                $is_locked = $data['is_locked'];
                
                $pdo->prepare("UPDATE forum_topics SET is_locked = ? WHERE id = ?")
                    ->execute([$is_locked, $topic_id]);
                
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'DELETE':
            if (isset($_GET['topic_id'])) {
                // Delete topic
                $topic_id = (int)$_GET['topic_id'];
                
                // Check if user is author or admin
                $stmt = $pdo->prepare("SELECT author_id FROM forum_topics WHERE id = ?");
                $stmt->execute([$topic_id]);
                $topic = $stmt->fetch();
                
                if ($topic['author_id'] == $user_id || $user_role === 'admin') {
                    $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$topic_id]);
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized']);
                }
                
            } elseif (isset($_GET['post_id'])) {
                // Delete post
                $post_id = (int)$_GET['post_id'];
                
                // Check if user is author or admin
                $stmt = $pdo->prepare("SELECT author_id, topic_id FROM forum_posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $post = $stmt->fetch();
                
                if ($post['author_id'] == $user_id || $user_role === 'admin') {
                    $pdo->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$post_id]);
                    
                    // Update topic reply count
                    $pdo->prepare("UPDATE forum_topics SET reply_count = reply_count - 1 WHERE id = ?")
                        ->execute([$post['topic_id']]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized']);
                }
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

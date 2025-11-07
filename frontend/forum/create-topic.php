<?php
require_once '../../shared/config/auth.php';
require_once '../../backend/config/db.php';
require_once '../../shared/utils/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /frontend/login.php?redirect=forum/create-topic.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $is_closed = isset($_POST['is_closed']) ? 1 : 0;
    
    // Validation
    if (empty($title)) $errors[] = "Topic title is required";
    if (empty($content)) $errors[] = "Topic content is required";
    if (empty($category)) $errors[] = "Category is required";
    if (strlen($title) < 5) $errors[] = "Title must be at least 5 characters long";
    if (strlen($title) > 200) $errors[] = "Title must be less than 200 characters";
    if (strlen($content) < 10) $errors[] = "Content must be at least 10 characters long";
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Create topic
            $stmt = $conn->prepare("INSERT INTO forum_topics (title, content, category, user_id, is_pinned, is_closed, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            if ($stmt->execute([$title, $content, $category, $user_id, $is_pinned, $is_closed])) {
                $topic_id = $conn->lastInsertId();
                
                // Process tags
                if (!empty($tags)) {
                    $tagArray = array_map('trim', explode(',', $tags));
                    $tagStmt = $conn->prepare("INSERT IGNORE INTO forum_tags (name, created_at) VALUES (?, NOW())");
                    $linkStmt = $conn->prepare("INSERT INTO forum_topic_tags (topic_id, tag_id) VALUES (?, ?)");
                    
                    foreach ($tagArray as $tag) {
                        if (strlen($tag) > 0) {
                            $tagStmt->execute([$tag]);
                            $tag_id = $conn->lastInsertId();
                            
                            if ($tag_id) {
                                $linkStmt->execute([$topic_id, $tag_id]);
                            }
                        }
                    }
                }
                
                // Log activity
                logActivity($user_id, 'create_forum_topic', "Created forum topic: $title", $topic_id);
                
                $conn->commit();
                $success = true;
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Failed to create topic: " . $e->getMessage();
        }
    }
}

// Get available categories
$categories = [];
try {
    $stmt = $conn->query("SELECT DISTINCT category FROM forum_topics WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Categories will be empty if query fails
}

// Get popular tags
$popularTags = [];
try {
    $stmt = $conn->query("
        SELECT t.name, COUNT(tt.topic_id) as usage_count 
        FROM forum_tags t 
        LEFT JOIN forum_topic_tags tt ON t.id = tt.tag_id 
        GROUP BY t.id, t.name 
        ORDER BY usage_count DESC 
        LIMIT 20
    ");
    $popularTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tags will be empty if query fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Topic - FowarD LMS Forum</title>
    <link rel="stylesheet" href="/frontend/assets/css/main.css">
    <link rel="stylesheet" href="/frontend/assets/css/forum.css">
    <link rel="stylesheet" href="/frontend/assets/css/create-topic.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../shared/templates/header.php'; ?>

    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/frontend/forum/index.php">Forum Home</a>
            <span class="separator">/</span>
            <span class="current">Create New Topic</span>
        </nav>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="page-title">
                    <h1><i class="fas fa-plus-circle"></i> Create New Topic</h1>
                    <p>Start a new discussion in the community forum</p>
                </div>
                <div class="header-actions">
                    <a href="/frontend/forum/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Forum
                    </a>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Topic created successfully!</strong>
                    <p>Your topic has been posted and is now visible to other users.</p>
                </div>
            </div>
            <div class="action-buttons">
                <a href="/frontend/forum/index.php" class="btn btn-primary">
                    <i class="fas fa-comments"></i> View Forum
                </a>
                <a href="/frontend/forum/topic.php?id=<?= $topic_id ?>" class="btn btn-success">
                    <i class="fas fa-eye"></i> View Your Topic
                </a>
            </div>
        <?php else: ?>
            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Please fix the following errors:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="topic-form" id="topicForm">
                    <div class="form-section basic-info">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                        
                        <div class="form-group">
                            <label for="title">Topic Title *</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required placeholder="Enter a clear, descriptive title for your topic" maxlength="200">
                            <div class="input-hint">
                                <span class="char-counter" id="titleCounter">0/200</span>
                                <small>Choose a title that clearly describes your topic</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="General Discussion" <?= (($_POST['category'] ?? '') === 'General Discussion') ? 'selected' : '' ?>>General Discussion</option>
                                <option value="Course Help" <?= (($_POST['category'] ?? '') === 'Course Help') ? 'selected' : '' ?>>Course Help</option>
                                <option value="Technical Support" <?= (($_POST['category'] ?? '') === 'Technical Support') ? 'selected' : '' ?>>Technical Support</option>
                                <option value="Study Groups" <?= (($_POST['category'] ?? '') === 'Study Groups') ? 'selected' : '' ?>>Study Groups</option>
                                <option value="Career & Jobs" <?= (($_POST['category'] ?? '') === 'Career & Jobs') ? 'selected' : '' ?>>Career & Jobs</option>
                                <option value="Feature Requests" <?= (($_POST['category'] ?? '') === 'Feature Requests') ? 'selected' : '' ?>>Feature Requests</option>
                                <option value="Feedback" <?= (($_POST['category'] ?? '') === 'Feedback') ? 'selected' : '' ?>>Feedback</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-section content-section">
                        <h3><i class="fas fa-edit"></i> Topic Content</h3>
                        
                        <div class="form-group">
                            <label for="content">Topic Content *</label>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" data-command="bold" title="Bold">
                                    <i class="fas fa-bold"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="italic" title="Italic">
                                    <i class="fas fa-italic"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="underline" title="Underline">
                                    <i class="fas fa-underline"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List">
                                    <i class="fas fa-list-ul"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List">
                                    <i class="fas fa-list-ol"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="createLink" title="Insert Link">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button type="button" class="toolbar-btn" data-command="formatBlock" data-value="blockquote" title="Quote">
                                    <i class="fas fa-quote-right"></i>
                                </button>
                            </div>
                            <textarea id="content" name="content" required placeholder="Share your thoughts, questions, or start a discussion. Be specific and provide enough detail to help others understand your topic."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                            <div class="input-hint">
                                <span class="char-counter" id="contentCounter">0 characters</span>
                                <small>Be detailed and provide context to encourage meaningful responses</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section tags-section">
                        <h3><i class="fas fa-tags"></i> Tags & Organization</h3>
                        
                        <div class="form-group">
                            <label for="tags">Tags</label>
                            <div class="tag-input-container">
                                <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>" placeholder="Enter tags separated by commas">
                                <div class="tag-suggestions" id="tagSuggestions">
                                    <div class="suggestions-title">Popular tags:</div>
                                    <div class="suggestions-list" id="popularTags">
                                        <?php foreach ($popularTags as $tag): ?>
                                            <span class="tag-suggestion" data-tag="<?= htmlspecialchars($tag['name']) ?>">
                                                <?= htmlspecialchars($tag['name']) ?>
                                                <small>(<?= $tag['usage_count'] ?>)</small>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <small>Add relevant tags to help others find your topic. Examples: beginner, javascript, database, homework</small>
                        </div>
                    </div>

                    <div class="form-section options-section">
                        <h3><i class="fas fa-cog"></i> Topic Options</h3>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_pinned" value="1" <?= isset($_POST['is_pinned']) ? 'checked' : '' ?>>
                                <span class="checkmark"></span>
                                Pin this topic
                            </label>
                            <small>Only moderators can pin topics. This option will be reviewed.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_closed" value="1" <?= isset($_POST['is_closed']) ? 'checked' : '' ?>>
                                <span class="checkmark"></span>
                                Close topic for replies
                            </label>
                            <small>If checked, users will not be able to reply to this topic</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-paper-plane"></i> Create Topic
                        </button>
                        <a href="/frontend/forum/index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Topic Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="preview-content" id="previewContent">
                        <!-- Preview will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/frontend/assets/js/create-topic.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('topicForm');
            const titleInput = document.getElementById('title');
            const contentTextarea = document.getElementById('content');
            const titleCounter = document.getElementById('titleCounter');
            const contentCounter = document.getElementById('contentCounter');
            const tagInput = document.getElementById('tags');
            const tagSuggestions = document.querySelectorAll('.tag-suggestion');
            
            // Character counters
            function updateCounters() {
                titleCounter.textContent = `${titleInput.value.length}/200`;
                contentCounter.textContent = `${contentTextarea.value.length} characters`;
            }
            
            titleInput.addEventListener('input', updateCounters);
            contentTextarea.addEventListener('input', updateCounters);
            updateCounters();
            
            // Tag suggestions
            tagSuggestions.forEach(tag => {
                tag.addEventListener('click', function() {
                    const tagName = this.dataset.tag;
                    const currentTags = tagInput.value;
                    const tagList = currentTags ? currentTags.split(',').map(t => t.trim()) : [];
                    
                    if (!tagList.includes(tagName)) {
                        tagList.push(tagName);
                        tagInput.value = tagList.join(', ');
                    }
                });
            });
            
            // Editor toolbar
            const toolbarBtns = document.querySelectorAll('.toolbar-btn');
            toolbarBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const command = this.dataset.command;
                    const value = this.dataset.value || null;
                    
                    document.execCommand(command, false, value);
                    contentTextarea.focus();
                });
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const content = contentTextarea.value.trim();
                const category = document.getElementById('category').value;
                
                const errors = [];
                
                if (title.length < 5) {
                    errors.push('Title must be at least 5 characters long');
                }
                
                if (content.length < 10) {
                    errors.push('Content must be at least 10 characters long');
                }
                
                if (!category) {
                    errors.push('Please select a category');
                }
                
                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n' + errors.join('\n'));
                }
            });
        });
    </script>
</body>
</html>
<?php
$page_title = 'Browse Courses - Forward LMS';
$body_class = 'courses-page';

require_once __DIR__ . '/../shared/templates/header.php';

// Include database connection
require_once __DIR__ . '/../../backend/config/db.php';

$db = getDBConnection();

// Handle search and filters
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
$level = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_STRING);
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?? 'newest';

// Build query
$where_conditions = ["c.status = 'published'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ? OR c.short_description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category)) {
    $where_conditions[] = "c.category = ?";
    $params[] = $category;
}

if (!empty($level)) {
    $where_conditions[] = "c.difficulty_level = ?";
    $params[] = $level;
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
switch ($sort) {
    case 'oldest':
        $order_clause = "c.created_at ASC";
        break;
    case 'title':
        $order_clause = "c.title ASC";
        break;
    case 'popular':
        $order_clause = "enrollment_count DESC, c.created_at DESC";
        break;
    case 'rating':
        $order_clause = "c.rating DESC, c.created_at DESC";
        break;
    case 'newest':
    default:
        $order_clause = "c.created_at DESC";
        break;
}

try {
    $sql = "SELECT 
                c.*,
                u.first_name as instructor_first_name,
                u.last_name as instructor_last_name,
                COUNT(DISTINCT e.id) as enrollment_count,
                COUNT(DISTINCT l.id) as lesson_count,
                AVG(s.rating) as average_rating,
                COUNT(DISTINCT s.id) as review_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.id
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN course_lessons l ON c.id = l.course_id
            LEFT JOIN student_reviews s ON c.id = s.course_id
            WHERE $where_clause
            GROUP BY c.id
            ORDER BY $order_clause";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $cat_sql = "SELECT DISTINCT category, COUNT(*) as count 
                FROM courses 
                WHERE status = 'published' 
                GROUP BY category 
                ORDER BY category";
    $cat_stmt = $db->query($cat_sql);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Course listing error: " . $e->getMessage());
    $courses = [];
    $categories = [];
}
?>

<div class="courses-container">
    <!-- Search and Filter Section -->
    <div class="courses-header">
        <div class="header-content">
            <h1>Browse Courses</h1>
            <p>Search and filter available courses</p>
        </div>
        
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search courses..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="search-input"
                >
                <button type="submit" class="search-btn">Search</button>
            </div>
            
            <div class="filter-controls">
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="level" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Levels</option>
                    <option value="beginner" <?php echo ($level === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                    <option value="intermediate" <?php echo ($level === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                    <option value="advanced" <?php echo ($level === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                </select>
                
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                    <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                    <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Highest Rated</option>
                    <option value="title" <?php echo ($sort === 'title') ? 'selected' : ''; ?>>A-Z</option>
                    <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div class="courses-results">
        <div class="results-header">
            <h2>Course Results</h2>
            <span class="results-count"><?php echo count($courses); ?> course(s) found</span>
        </div>

        <?php if (empty($courses)): ?>
            <div class="no-results">
                <div class="no-results-icon">📚</div>
                <h3>No courses found</h3>
                <p>Try adjusting your search criteria or browse all courses.</p>
                <a href="/frontend/courses.php" class="btn btn-primary">Browse All Courses</a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-image">
                            <?php if (!empty($course['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="course-image-placeholder" style="display: none;">
                                    📖
                                </div>
                            <?php else: ?>
                                <div class="course-image-placeholder">
                                    📖
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-content">
                            <div class="course-header">
                                <span class="course-category"><?php echo htmlspecialchars($course['category']); ?></span>
                                <span class="course-level level-<?php echo htmlspecialchars($course['difficulty_level']); ?>">
                                    <?php echo ucfirst($course['difficulty_level']); ?>
                                </span>
                            </div>
                            
                            <h3 class="course-title">
                                <a href="/frontend/courses/<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </a>
                            </h3>
                            
                            <p class="course-description">
                                <?php echo htmlspecialchars($course['short_description'] ?? substr($course['description'], 0, 120) . '...'); ?>
                            </p>
                            
                            <div class="course-instructor">
                                <div class="instructor-avatar">
                                    <?php echo strtoupper(substr($course['instructor_first_name'], 0, 1)); ?>
                                </div>
                                <span class="instructor-name">
                                    <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                                </span>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat">
                                    <span class="stat-icon">👥</span>
                                    <span class="stat-text"><?php echo number_format($course['enrollment_count']); ?> students</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-icon">📚</span>
                                    <span class="stat-text"><?php echo $course['lesson_count']; ?> lessons</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-icon">⭐</span>
                                    <span class="stat-text">
                                        <?php echo number_format($course['average_rating'] ?? 0, 1); ?>
                                        (<?php echo $course['review_count']; ?>)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="course-footer">
                                <div class="course-price">
                                    <?php if ($course['price'] > 0): ?>
                                        <span class="price">$<?php echo number_format($course['price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="price free">Free</span>
                                    <?php endif; ?>
                                </div>
                                <a href="/frontend/course-enroll.php?course_id=<?php echo $course['id']; ?>" 
                                   class="btn btn-primary">Enroll Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.courses-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.courses-header {
    text-align: center;
    margin-bottom: 3rem;
}

.header-content h1 {
    font-size: 3rem;
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 700;
}

.header-content p {
    font-size: 1.2rem;
    color: #64748b;
    margin-bottom: 2rem;
}

.search-form {
    max-width: 800px;
    margin: 0 auto;
}

.search-input-group {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.search-input {
    flex: 1;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
}

.search-btn {
    padding: 1rem 2rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-btn:hover {
    background: #2563eb;
}

.filter-controls {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    color: #374151;
    font-size: 0.9rem;
    cursor: pointer;
    min-width: 150px;
    transition: border-color 0.2s;
}

.filter-select:focus {
    outline: none;
    border-color: #3b82f6;
}

.courses-results {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.results-header h2 {
    font-size: 1.5rem;
    color: #1e293b;
    font-weight: 600;
}

.results-count {
    color: #64748b;
    font-size: 0.9rem;
}

.no-results {
    text-align: center;
    padding: 3rem 2rem;
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-results h3 {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 1rem;
}

.no-results p {
    color: #64748b;
    margin-bottom: 2rem;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
}

.course-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.course-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    border-color: #3b82f6;
}

.course-image {
    height: 200px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    position: relative;
    overflow: hidden;
}

.course-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.course-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #94a3b8;
}

.course-content {
    padding: 1.5rem;
}

.course-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.course-category {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

.course-level {
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.level-beginner {
    background: #dcfce7;
    color: #166534;
}

.level-intermediate {
    background: #fef3c7;
    color: #d97706;
}

.level-advanced {
    background: #fee2e2;
    color: #991b1b;
}

.course-title {
    margin-bottom: 1rem;
}

.course-title a {
    text-decoration: none;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.3;
}

.course-title a:hover {
    color: #3b82f6;
}

.course-description {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.course-instructor {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
}

.instructor-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    margin-right: 0.75rem;
}

.instructor-name {
    color: #475569;
    font-size: 0.9rem;
}

.course-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #64748b;
}

.stat-icon {
    font-size: 1rem;
}

.course-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.course-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.price.free {
    color: #10b981;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-align: center;
    display: inline-block;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
    transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .courses-container {
        padding: 1rem;
    }
    
    .header-content h1 {
        font-size: 2rem;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .filter-controls {
        flex-direction: column;
    }
    
    .filter-select {
        min-width: auto;
    }
    
    .courses-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .results-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .course-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .course-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .courses-results {
        padding: 1rem;
    }
    
    .course-content {
        padding: 1rem;
    }
    
    .course-stats {
        padding: 0.75rem;
    }
}</style>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>
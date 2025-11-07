<?php
$page_title = 'Course Enrollment - Forward LMS';
$body_class = 'enrollment-page';

require_once __DIR__ . '/../shared/templates/header.php';

// Include database and auth
require_once __DIR__ . '/../../backend/config/auth.php';
require_once __DIR__ . '/../../backend/config/db.php';

$db = getDBConnection();

// Check if user is logged in
if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$current_user = Auth::getUser();
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

if (!$course_id) {
    header('Location: /frontend/courses.php');
    exit;
}

$message = '';
$error = '';
$success = false;

// Handle enrollment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    if ($action === 'enroll') {
        try {
            // Check if already enrolled
            $check_sql = "SELECT id, status FROM enrollments WHERE user_id = ? AND course_id = ?";
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->execute([$current_user['id'], $course_id]);
            $existing_enrollment = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_enrollment) {
                if ($existing_enrollment['status'] === 'active') {
                    $error = 'You are already enrolled in this course.';
                } else {
                    // Reactivate enrollment
                    $update_sql = "UPDATE enrollments SET status = 'active', enrollment_date = NOW() WHERE id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute([$existing_enrollment['id']]);
                    $success = true;
                    $message = 'Enrollment reactivated successfully!';
                }
            } else {
                // Create new enrollment
                $enroll_sql = "INSERT INTO enrollments (user_id, course_id, enrollment_date, status) VALUES (?, ?, NOW(), 'active')";
                $enroll_stmt = $db->prepare($enroll_sql);
                $enroll_stmt->execute([$current_user['id'], $course_id]);
                
                $success = true;
                $message = 'Successfully enrolled in the course!';
            }
            
        } catch (Exception $e) {
            error_log("Enrollment error: " . $e->getMessage());
            $error = 'An error occurred while processing your enrollment. Please try again.';
        }
    }
}

// Get course information
try {
    $course_sql = "SELECT 
                    c.*,
                    u.first_name as instructor_first_name,
                    u.last_name as instructor_last_name,
                    u.bio as instructor_bio,
                    COUNT(DISTINCT l.id) as lesson_count,
                    COUNT(DISTINCT e.id) as enrollment_count,
                    AVG(s.rating) as average_rating,
                    COUNT(DISTINCT s.id) as review_count
                FROM courses c
                LEFT JOIN users u ON c.instructor_id = u.id
                LEFT JOIN course_lessons l ON c.id = l.course_id
                LEFT JOIN enrollments e ON c.id = e.course_id
                LEFT JOIN student_reviews s ON c.id = s.course_id
                WHERE c.id = ? AND c.status = 'published'
                GROUP BY c.id";
    
    $course_stmt = $db->prepare($course_sql);
    $course_stmt->execute([$course_id]);
    $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        header('Location: /frontend/courses.php');
        exit;
    }
    
    // Check if user is already enrolled
    $enrollment_sql = "SELECT status FROM enrollments WHERE user_id = ? AND course_id = ?";
    $enrollment_stmt = $db->prepare($enrollment_sql);
    $enrollment_stmt->execute([$current_user['id'], $course_id]);
    $user_enrollment = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get course lessons
    $lessons_sql = "SELECT title, description, duration_minutes, lesson_type, order_index
                    FROM course_lessons 
                    WHERE course_id = ? 
                    ORDER BY order_index ASC 
                    LIMIT 10";
    $lessons_stmt = $db->prepare($lessons_sql);
    $lessons_stmt->execute([$course_id]);
    $lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get course reviews
    $reviews_sql = "SELECT 
                    sr.rating,
                    sr.comment,
                    sr.created_at,
                    u.first_name,
                    u.last_name
                    FROM student_reviews sr
                    JOIN users u ON sr.user_id = u.id
                    WHERE sr.course_id = ?
                    ORDER BY sr.created_at DESC
                    LIMIT 5";
    $reviews_stmt = $db->prepare($reviews_sql);
    $reviews_stmt->execute([$course_id]);
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Course data error: " . $e->getMessage());
    header('Location: /frontend/courses.php');
    exit;
}
?>

<div class="enrollment-container">
    <!-- Course Header -->
    <div class="course-header">
        <div class="course-image">
            <?php if (!empty($course['thumbnail'])): ?>
                <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" 
                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="course-image-placeholder" style="display: none;">
                    📚
                </div>
            <?php else: ?>
                <div class="course-image-placeholder">
                    📚
                </div>
            <?php endif; ?>
        </div>
        
        <div class="course-info">
            <div class="course-meta">
                <span class="course-category"><?php echo htmlspecialchars($course['category']); ?></span>
                <span class="course-level level-<?php echo htmlspecialchars($course['difficulty_level']); ?>">
                    <?php echo ucfirst($course['difficulty_level']); ?>
                </span>
            </div>
            
            <h1 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h1>
            
            <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
            
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
                        (<?php echo $course['review_count']; ?> reviews)
                    </span>
                </div>
            </div>
            
            <div class="instructor-info">
                <div class="instructor-avatar">
                    <?php echo strtoupper(substr($course['instructor_first_name'], 0, 1)); ?>
                </div>
                <div class="instructor-details">
                    <div class="instructor-name">
                        <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                    </div>
                    <div class="instructor-title">Course Instructor</div>
                </div>
            </div>
            
            <div class="course-price">
                <?php if ($course['price'] > 0): ?>
                    <span class="price">$<?php echo number_format($course['price'], 2); ?></span>
                <?php else: ?>
                    <span class="price free">Free</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
            <a href="/frontend/student/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="enrollment-content">
        <!-- Left Column: Course Details -->
        <div class="course-details">
            <div class="section">
                <h2>What you'll learn</h2>
                <div class="learning-objectives">
                    <div class="objective">
                        <span class="objective-icon">✅</span>
                        <span>Master the core concepts and principles</span>
                    </div>
                    <div class="objective">
                        <span class="objective-icon">✅</span>
                        <span>Apply knowledge through practical exercises</span>
                    </div>
                    <div class="objective">
                        <span class="objective-icon">✅</span>
                        <span>Build real-world projects and applications</span>
                    </div>
                    <div class="objective">
                        <span class="objective-icon">✅</span>
                        <span>Receive certification upon completion</span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Course Content</h2>
                <div class="lessons-list">
                    <?php if (!empty($lessons)): ?>
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <div class="lesson-item">
                                <div class="lesson-number"><?php echo $index + 1; ?></div>
                                <div class="lesson-content">
                                    <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                    <div class="lesson-meta">
                                        <span class="lesson-type"><?php echo ucfirst($lesson['lesson_type']); ?></span>
                                        <span class="lesson-duration"><?php echo $lesson['duration_minutes']; ?> min</span>
                                    </div>
                                    <?php if (!empty($lesson['description'])): ?>
                                        <div class="lesson-description">
                                            <?php echo htmlspecialchars($lesson['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($lessons) === 10): ?>
                            <div class="lessons-more">
                                And <?php echo $course['lesson_count'] - 10; ?> more lessons...
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Course content details will be available after enrollment.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($reviews)): ?>
                <div class="section">
                    <h2>Student Reviews</h2>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?php echo strtoupper(substr($review['first_name'], 0, 1)); ?>
                                        </div>
                                        <div class="reviewer-name">
                                            <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-content">
                                    <?php echo htmlspecialchars($review['comment']); ?>
                                </div>
                                <div class="review-date">
                                    <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Enrollment Panel -->
        <div class="enrollment-panel">
            <?php if ($user_enrollment && $user_enrollment['status'] === 'active'): ?>
                <div class="already-enrolled">
                    <div class="enrolled-icon">✅</div>
                    <h3>You're enrolled!</h3>
                    <p>You have access to all course materials and can start learning right away.</p>
                    <a href="/frontend/student/dashboard.php" class="btn btn-primary">Continue Learning</a>
                </div>
            <?php else: ?>
                <div class="enrollment-form">
                    <h3>Enroll in this course</h3>
                    <p>Join <?php echo number_format($course['enrollment_count']); ?> other students in this course.</p>
                    
                    <form method="POST" id="enrollment-form">
                        <input type="hidden" name="action" value="enroll">
                        <button type="submit" class="btn btn-primary btn-full">
                            <?php if ($course['price'] > 0): ?>
                                Enroll Now - $<?php echo number_format($course['price'], 2); ?>
                            <?php else: ?>
                                Enroll for Free
                            <?php endif; ?>
                        </button>
                    </form>
                    
                    <div class="enrollment-features">
                        <div class="feature">
                            <span class="feature-icon">🎓</span>
                            <span>Lifetime access</span>
                        </div>
                        <div class="feature">
                            <span class="feature-icon">📱</span>
                            <span>Mobile and desktop access</span>
                        </div>
                        <div class="feature">
                            <span class="feature-icon">🏆</span>
                            <span>Certificate of completion</span>
                        </div>
                        <div class="feature">
                            <span class="feature-icon">💬</span>
                            <span>Instructor Q&A support</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="instructor-bio">
                <h4>About the Instructor</h4>
                <div class="instructor-profile">
                    <div class="instructor-avatar large">
                        <?php echo strtoupper(substr($course['instructor_first_name'], 0, 1)); ?>
                    </div>
                    <div class="instructor-info">
                        <div class="instructor-name">
                            <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                        </div>
                        <div class="instructor-title">Course Instructor</div>
                    </div>
                </div>
                <?php if (!empty($course['instructor_bio'])): ?>
                    <p class="bio-text"><?php echo htmlspecialchars($course['instructor_bio']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.enrollment-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Course Header */
.course-header {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.course-image {
    height: 300px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
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
    font-size: 4rem;
    color: #94a3b8;
}

.course-meta {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.course-category {
    background: #f1f5f9;
    color: #475569;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.course-level {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
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
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 700;
    line-height: 1.2;
}

.course-description {
    color: #64748b;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.course-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
}

.stat-icon {
    font-size: 1.1rem;
}

.instructor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.instructor-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
}

.instructor-details {
    flex: 1;
}

.instructor-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.instructor-title {
    font-size: 0.9rem;
    color: #64748b;
}

.course-price {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
}

.price.free {
    color: #10b981;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Main Content */
.enrollment-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.section h2 {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.learning-objectives {
    list-style: none;
    padding: 0;
}

.objective {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-size: 1rem;
    color: #374151;
}

.objective-icon {
    color: #10b981;
    font-size: 1.1rem;
}

.lessons-list {
    list-style: none;
    padding: 0;
}

.lesson-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    transition: background-color 0.2s;
}

.lesson-item:hover {
    background: #f8fafc;
}

.lesson-number {
    width: 32px;
    height: 32px;
    background: #3b82f6;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.lesson-content {
    flex: 1;
}

.lesson-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.lesson-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.lesson-type {
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.lesson-description {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
}

.lessons-more {
    text-align: center;
    padding: 1rem;
    color: #64748b;
    font-style: italic;
}

/* Reviews */
.reviews-list {
    list-style: none;
    padding: 0;
}

.review-item {
    padding: 1rem;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.review-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.reviewer-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.reviewer-name {
    font-weight: 600;
    color: #1e293b;
}

.review-rating {
    display: flex;
    gap: 0.25rem;
}

.star {
    color: #d1d5db;
    font-size: 1rem;
}

.star.filled {
    color: #f59e0b;
}

.review-content {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 0.75rem;
}

.review-date {
    font-size: 0.8rem;
    color: #64748b;
}

/* Enrollment Panel */
.enrollment-panel {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

.enrollment-form,
.already-enrolled {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    text-align: center;
}

.enrolled-icon {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.already-enrolled h3 {
    color: #10b981;
    margin-bottom: 1rem;
}

.enrollment-form h3 {
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.enrollment-form p {
    color: #64748b;
    margin-bottom: 2rem;
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

.btn-full {
    width: 100%;
    font-size: 1.1rem;
    padding: 1rem 2rem;
}

.enrollment-features {
    margin-top: 2rem;
    text-align: left;
}

.feature {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    color: #374151;
}

.feature-icon {
    color: #3b82f6;
    font-size: 1.1rem;
}

/* Instructor Bio */
.instructor-bio {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.instructor-bio h4 {
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 600;
}

.instructor-profile {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.instructor-avatar.large {
    width: 64px;
    height: 64px;
    font-size: 1.5rem;
}

.bio-text {
    color: #64748b;
    line-height: 1.6;
    font-size: 0.95rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .course-header {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .course-image {
        height: 250px;
    }
    
    .enrollment-content {
        grid-template-columns: 1fr;
    }
    
    .enrollment-panel {
        position: static;
    }
}

@media (max-width: 768px) {
    .enrollment-container {
        padding: 1rem;
    }
    
    .course-header {
        padding: 1.5rem;
    }
    
    .course-title {
        font-size: 2rem;
    }
    
    .course-stats {
        gap: 1rem;
    }
    
    .section {
        padding: 1.5rem;
    }
    
    .enrollment-form {
        padding: 1.5rem;
    }
    
    .instructor-bio {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .course-meta {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .course-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .lesson-item {
        flex-direction: column;
        text-align: center;
    }
    
    .lesson-number {
        align-self: center;
    }
    
    .review-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>
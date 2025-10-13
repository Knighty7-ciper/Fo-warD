<?php
session_start();
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/auth.php';

Auth::requireRole('student');

$current_user = Auth::getUser();

$page_title = 'My Grades - Forward LMS';
$body_class = 'student-grades-page';
$additional_css = ['/frontend/assets/css/student-grades.css'];
$additional_js = ['/frontend/assets/js/student-grades.js'];

require_once __DIR__ . '/../../shared/templates/header.php';

$db = Database::getInstance();

// Get student's enrolled courses
$courses = $db->select("
    SELECT c.id, c.title, c.thumbnail, cg.current_percentage, cg.current_letter_grade
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN course_grades cg ON cg.student_id = e.student_id AND cg.course_id = c.id
    WHERE e.student_id = :student_id AND e.status = 'active'
    ORDER BY c.title
", [':student_id' => $current_user['id']]);
?>

<div class="student-grades-container">
    <header class="page-header">
        <h1>My Grades</h1>
        <p>View your academic performance across all courses</p>
    </header>

     Overall GPA Card 
    <div class="gpa-card">
        <div class="gpa-content">
            <div class="gpa-value" id="overallGPA">0.00</div>
            <div class="gpa-label">Overall GPA</div>
        </div>
        <div class="gpa-stats">
            <div class="stat-item">
                <span class="stat-value" id="totalCourses"><?php echo count($courses); ?></span>
                <span class="stat-label">Courses</span>
            </div>
            <div class="stat-item">
                <span class="stat-value" id="completedCourses">0</span>
                <span class="stat-label">Completed</span>
            </div>
        </div>
    </div>

     Course Grades 
    <div class="courses-section">
        <h2>Course Grades</h2>
        
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <p>You are not enrolled in any courses yet.</p>
                <a href="/frontend/courses/browse.php" class="btn btn-primary">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-grade-card" onclick="viewCourseGrades(<?php echo $course['id']; ?>)">
                        <?php if (!empty($course['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="Course thumbnail" class="course-thumbnail">
                        <?php else: ?>
                            <div class="course-thumbnail-placeholder"></div>
                        <?php endif; ?>
                        
                        <div class="course-info">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            
                            <?php if ($course['current_percentage']): ?>
                                <div class="grade-display">
                                    <span class="grade-percentage"><?php echo number_format($course['current_percentage'], 1); ?>%</span>
                                    <span class="grade-letter grade-<?php echo strtolower($course['current_letter_grade']); ?>">
                                        <?php echo $course['current_letter_grade']; ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="grade-display">
                                    <span class="no-grade">No grades yet</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

 Course Detail Modal 
<div id="courseDetailModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeModal('courseDetailModal')">&times;</span>
        <h2 id="courseDetailTitle">Course Grades</h2>
        
        <div class="course-grade-summary">
            <div class="summary-item">
                <span class="summary-label">Current Grade:</span>
                <span class="summary-value" id="courseCurrentGrade">--</span>
            </div>
        </div>
        
        <div class="grades-table-wrapper">
            <table class="grades-detail-table">
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Type</th>
                        <th>Due Date</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="courseGradesBody">
                    <tr>
                        <td colspan="7" class="loading-cell">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const STUDENT_ID = <?php echo $current_user['id']; ?>;
</script>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

<?php
session_start();
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/auth.php';

Auth::requireRole('teacher');

$current_user = Auth::getUser();

$page_title = 'Gradebook - Forward LMS';
$body_class = 'gradebook-page';
$additional_css = ['/frontend/assets/css/gradebook.css'];
$additional_js = ['/frontend/assets/js/gradebook.js'];

require_once __DIR__ . '/../../shared/templates/header.php';

$db = Database::getInstance();

// Get teacher's courses
$courses = $db->select("
    SELECT id, title FROM courses 
    WHERE teacher_id = :teacher_id AND status = 'published'
    ORDER BY title
", [':teacher_id' => $current_user['id']]);
?>

<div class="gradebook-container">
    <header class="page-header">
        <h1>Gradebook</h1>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="showCategoryModal()">Manage Categories</button>
            <button class="btn btn-secondary" onclick="showGradeItemModal()">Add Grade Item</button>
            <button class="btn btn-primary" onclick="exportGradebook()">Export CSV</button>
        </div>
    </header>

     Course Selection 
    <div class="course-selector">
        <label for="courseSelect">Select Course:</label>
        <select id="courseSelect" class="form-select">
            <option value="">-- Select a Course --</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

     Gradebook Content 
    <div id="gradebookContent" style="display: none;">
         Summary Stats 
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-label">Total Students</div>
                <div class="stat-value" id="totalStudents">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Grade</div>
                <div class="stat-value" id="averageGrade">0%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Passing Rate</div>
                <div class="stat-value" id="passingRate">0%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Grade Items</div>
                <div class="stat-value" id="totalItems">0</div>
            </div>
        </div>

         Gradebook Table 
        <div class="gradebook-table-container">
            <div class="table-controls">
                <input type="text" id="searchStudent" placeholder="Search students..." class="search-input">
                <button class="btn btn-secondary" onclick="toggleView()">Toggle View</button>
            </div>

            <div class="table-wrapper">
                <table class="gradebook-table" id="gradebookTable">
                    <thead>
                        <tr id="tableHeader">
                            <th class="sticky-col">Student</th>
                             Grade items will be added dynamically 
                            <th class="final-grade-col">Final Grade</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="gradebookBody">
                        <tr>
                            <td colspan="100" class="loading-cell">Loading gradebook...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

 Grade Entry Modal 
<div id="gradeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('gradeModal')">&times;</span>
        <h2>Edit Grade</h2>
        <form id="gradeForm">
            <input type="hidden" id="gradeStudentId">
            <input type="hidden" id="gradeItemId">
            
            <div class="form-group">
                <label>Student: <span id="gradeStudentName"></span></label>
            </div>
            
            <div class="form-group">
                <label>Assignment: <span id="gradeItemName"></span></label>
            </div>
            
            <div class="form-group">
                <label for="gradePoints">Points Earned</label>
                <input type="number" id="gradePoints" step="0.01" required>
                <small>Max Points: <span id="gradeMaxPoints"></span></small>
            </div>
            
            <div class="form-group">
                <label for="gradeFeedback">Feedback</label>
                <textarea id="gradeFeedback" rows="4"></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="gradeExcused">
                    <span>Excused</span>
                </label>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="gradeMissing">
                    <span>Missing</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Grade</button>
        </form>
    </div>
</div>

 Category Management Modal 
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('categoryModal')">&times;</span>
        <h2>Manage Grade Categories</h2>
        
        <form id="addCategoryForm" class="inline-form">
            <input type="text" name="name" placeholder="Category name" required>
            <input type="number" name="weight" placeholder="Weight %" step="0.01" min="0" max="100">
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
        
        <div id="categoriesList" class="categories-list"></div>
    </div>
</div>

 Grade Item Modal 
<div id="gradeItemModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('gradeItemModal')">&times;</span>
        <h2>Add Grade Item</h2>
        
        <form id="addGradeItemForm">
            <div class="form-group">
                <label for="itemTitle">Title *</label>
                <input type="text" id="itemTitle" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="itemType">Type *</label>
                <select id="itemType" name="item_type" required>
                    <option value="assignment">Assignment</option>
                    <option value="quiz">Quiz</option>
                    <option value="exam">Exam</option>
                    <option value="participation">Participation</option>
                    <option value="project">Project</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="itemCategory">Category</label>
                <select id="itemCategory" name="category_id">
                    <option value="">No Category</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="itemMaxPoints">Max Points *</label>
                <input type="number" id="itemMaxPoints" name="max_points" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="itemDueDate">Due Date</label>
                <input type="datetime-local" id="itemDueDate" name="due_date">
            </div>
            
            <button type="submit" class="btn btn-primary">Create Grade Item</button>
        </form>
    </div>
</div>

<script>
    const CURRENT_COURSE_ID = null;
</script>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

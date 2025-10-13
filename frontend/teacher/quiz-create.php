<?php
$page_title = 'Create Quiz - Teacher';
require_once __DIR__ . '/../../shared/templates/header.php';

if (!Auth::isAuthenticated() || Auth::getUserRole() !== 'teacher') {
    header('Location: /frontend/login.php');
    exit;
}

$course_id = $_GET['course_id'] ?? null;
?>

<link rel="stylesheet" href="/frontend/assets/css/quiz-builder.css">

<div class="quiz-builder-container">
    <div class="quiz-builder-header">
        <h1>Create New Quiz</h1>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
            <button class="btn btn-primary" onclick="publishQuiz()">Publish Quiz</button>
        </div>
    </div>
    
    <form id="quiz-form">
        <div class="quiz-settings">
            <h3>Quiz Settings</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quiz Title *</label>
                    <input type="text" name="title" required>
                </div>
                
                <div class="form-group">
                    <label>Course *</label>
                    <select name="course_id" required>
                        <option value="">Select Course</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Instructions</label>
                <textarea name="instructions" rows="3" placeholder="Provide instructions for students..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" value="0" min="0">
                    <small>0 = No time limit</small>
                </div>
                
                <div class="form-group">
                    <label>Passing Score (%)</label>
                    <input type="number" name="passing_score" value="70" min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label>Max Attempts</label>
                    <input type="number" name="max_attempts" value="1" min="1">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="shuffle_questions">
                        Shuffle Questions
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="show_correct_answers" checked>
                        Show Correct Answers After Submission
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="show_results_immediately" checked>
                        Show Results Immediately
                    </label>
                </div>
            </div>
        </div>
        
        <div class="quiz-questions">
            <div class="questions-header">
                <h3>Questions</h3>
                <button type="button" class="btn btn-primary" onclick="addQuestion()">+ Add Question</button>
            </div>
            
            <div id="questions-container">
                 Questions will be added here dynamically 
            </div>
        </div>
    </form>
</div>

<script src="/frontend/assets/js/quiz-builder.js"></script>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>

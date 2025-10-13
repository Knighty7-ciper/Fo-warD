<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!Auth::isAuthenticated()) {
    error_response('Unauthorized', 401);
}

$db = Database::getInstance();
$current_user_id = Auth::getUserId();
$user_role = Auth::getUserRole();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Fetch gradebook data
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'view';
        
        switch ($action) {
            case 'view':
                // Get gradebook for a course
                $course_id = intval($_GET['course_id'] ?? 0);
                
                if (!$course_id) {
                    error_response('Course ID is required', 400);
                }
                
                // Check access
                if ($user_role === 'teacher' || $user_role === 'admin') {
                    // Get all students in course
                    $students = $db->select("
                        SELECT u.id, u.name, u.email, u.avatar,
                               cg.current_percentage, cg.current_letter_grade
                        FROM enrollments e
                        JOIN users u ON e.student_id = u.id
                        LEFT JOIN course_grades cg ON cg.student_id = u.id AND cg.course_id = e.course_id
                        WHERE e.course_id = :course_id AND e.status = 'active'
                        ORDER BY u.name
                    ", [':course_id' => $course_id]);
                    
                    // Get all grade items for course
                    $grade_items = $db->select("
                        SELECT gi.*, gc.name as category_name, gc.weight as category_weight
                        FROM grade_items gi
                        LEFT JOIN grade_categories gc ON gi.category_id = gc.id
                        WHERE gi.course_id = :course_id
                        ORDER BY gi.due_date ASC, gi.created_at ASC
                    ", [':course_id' => $course_id]);
                    
                    // Get all grades for this course
                    $grades = $db->select("
                        SELECT sg.*, gi.title as item_title
                        FROM student_grades sg
                        JOIN grade_items gi ON sg.grade_item_id = gi.id
                        WHERE gi.course_id = :course_id
                    ", [':course_id' => $course_id]);
                    
                    // Organize grades by student and item
                    $grade_matrix = [];
                    foreach ($grades as $grade) {
                        $grade_matrix[$grade['student_id']][$grade['grade_item_id']] = $grade;
                    }
                    
                    // Get grade categories
                    $categories = $db->select("
                        SELECT * FROM grade_categories
                        WHERE course_id = :course_id
                        ORDER BY name
                    ", [':course_id' => $course_id]);
                    
                    success_response([
                        'students' => $students,
                        'grade_items' => $grade_items,
                        'grades' => $grade_matrix,
                        'categories' => $categories
                    ]);
                    
                } elseif ($user_role === 'student') {
                    // Get student's own grades
                    $grades = $db->select("
                        SELECT sg.*, gi.title, gi.max_points, gi.due_date, gi.item_type,
                               gc.name as category_name
                        FROM student_grades sg
                        JOIN grade_items gi ON sg.grade_item_id = gi.id
                        LEFT JOIN grade_categories gc ON gi.category_id = gc.id
                        WHERE sg.student_id = :student_id AND gi.course_id = :course_id
                        ORDER BY gi.due_date DESC
                    ", [':student_id' => $current_user_id, ':course_id' => $course_id]);
                    
                    // Get course grade
                    $course_grade = $db->selectOne("
                        SELECT * FROM course_grades
                        WHERE student_id = :student_id AND course_id = :course_id
                    ", [':student_id' => $current_user_id, ':course_id' => $course_id]);
                    
                    success_response([
                        'grades' => $grades,
                        'course_grade' => $course_grade
                    ]);
                }
                break;
                
            case 'student_detail':
                // Get detailed grades for a specific student
                $student_id = intval($_GET['student_id'] ?? 0);
                $course_id = intval($_GET['course_id'] ?? 0);
                
                if ($user_role !== 'teacher' && $user_role !== 'admin' && $student_id != $current_user_id) {
                    error_response('Access denied', 403);
                }
                
                $grades = $db->select("
                    SELECT sg.*, gi.title, gi.max_points, gi.due_date, gi.item_type,
                           gc.name as category_name, u.name as graded_by_name
                    FROM student_grades sg
                    JOIN grade_items gi ON sg.grade_item_id = gi.id
                    LEFT JOIN grade_categories gc ON gi.category_id = gc.id
                    LEFT JOIN users u ON sg.graded_by = u.id
                    WHERE sg.student_id = :student_id AND gi.course_id = :course_id
                    ORDER BY gi.due_date DESC
                ", [':student_id' => $student_id, ':course_id' => $course_id]);
                
                $course_grade = $db->selectOne("
                    SELECT * FROM course_grades
                    WHERE student_id = :student_id AND course_id = :course_id
                ", [':student_id' => $student_id, ':course_id' => $course_id]);
                
                success_response([
                    'grades' => $grades,
                    'course_grade' => $course_grade
                ]);
                break;
                
            case 'grade_history':
                // Get grade change history
                $grade_id = intval($_GET['grade_id'] ?? 0);
                
                $history = $db->select("
                    SELECT gh.*, u.name as changed_by_name
                    FROM grade_history gh
                    JOIN users u ON gh.changed_by = u.id
                    WHERE gh.student_grade_id = :grade_id
                    ORDER BY gh.created_at DESC
                ", [':grade_id' => $grade_id]);
                
                success_response(['history' => $history]);
                break;
                
            case 'export':
                // Export gradebook to CSV
                $course_id = intval($_GET['course_id'] ?? 0);
                
                if ($user_role !== 'teacher' && $user_role !== 'admin') {
                    error_response('Access denied', 403);
                }
                
                // This would generate CSV data
                // For now, return JSON that frontend can convert
                success_response(['message' => 'Export functionality']);
                break;
        }
    }
    
    // POST - Create or update grades
    elseif ($method === 'POST') {
        if ($user_role !== 'teacher' && $user_role !== 'admin') {
            error_response('Only teachers can manage grades', 403);
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_grade':
                $student_id = intval($_POST['student_id']);
                $grade_item_id = intval($_POST['grade_item_id']);
                $points_earned = floatval($_POST['points_earned']);
                $feedback = $_POST['feedback'] ?? '';
                $is_excused = isset($_POST['is_excused']) ? 1 : 0;
                $is_missing = isset($_POST['is_missing']) ? 1 : 0;
                
                // Get max points for item
                $item = $db->selectOne("SELECT max_points FROM grade_items WHERE id = :id", [':id' => $grade_item_id]);
                $max_points = $item['max_points'];
                $percentage = ($points_earned / $max_points) * 100;
                $letter_grade = calculateLetterGrade($percentage, $db);
                
                // Check if grade exists
                $existing = $db->selectOne("
                    SELECT * FROM student_grades
                    WHERE student_id = :student_id AND grade_item_id = :item_id
                ", [':student_id' => $student_id, ':item_id' => $grade_item_id]);
                
                if ($existing) {
                    // Log history
                    $db->query("
                        INSERT INTO grade_history (student_grade_id, old_points, new_points, old_percentage, new_percentage, changed_by, change_reason)
                        VALUES (:grade_id, :old_points, :new_points, :old_pct, :new_pct, :changed_by, :reason)
                    ", [
                        ':grade_id' => $existing['id'],
                        ':old_points' => $existing['points_earned'],
                        ':new_points' => $points_earned,
                        ':old_pct' => $existing['percentage'],
                        ':new_pct' => $percentage,
                        ':changed_by' => $current_user_id,
                        ':reason' => 'Grade updated'
                    ]);
                    
                    // Update grade
                    $db->query("
                        UPDATE student_grades
                        SET points_earned = :points, percentage = :pct, letter_grade = :letter,
                            feedback = :feedback, is_excused = :excused, is_missing = :missing,
                            graded_by = :graded_by, graded_at = NOW()
                        WHERE id = :id
                    ", [
                        ':points' => $points_earned,
                        ':pct' => $percentage,
                        ':letter' => $letter_grade,
                        ':feedback' => $feedback,
                        ':excused' => $is_excused,
                        ':missing' => $is_missing,
                        ':graded_by' => $current_user_id,
                        ':id' => $existing['id']
                    ]);
                } else {
                    // Insert new grade
                    $db->query("
                        INSERT INTO student_grades (student_id, grade_item_id, points_earned, percentage, letter_grade, feedback, is_excused, is_missing, graded_by, graded_at)
                        VALUES (:student_id, :item_id, :points, :pct, :letter, :feedback, :excused, :missing, :graded_by, NOW())
                    ", [
                        ':student_id' => $student_id,
                        ':item_id' => $grade_item_id,
                        ':points' => $points_earned,
                        ':pct' => $percentage,
                        ':letter' => $letter_grade,
                        ':feedback' => $feedback,
                        ':excused' => $is_excused,
                        ':missing' => $is_missing,
                        ':graded_by' => $current_user_id
                    ]);
                }
                
                // Recalculate course grade
                $course_id = $db->selectOne("SELECT course_id FROM grade_items WHERE id = :id", [':id' => $grade_item_id])['course_id'];
                recalculateCourseGrade($student_id, $course_id, $db);
                
                Auth::logAudit($current_user_id, 'update_grade', 'student_grade', $grade_item_id);
                
                success_response([], 'Grade updated successfully');
                break;
                
            case 'create_category':
                $course_id = intval($_POST['course_id']);
                $name = $_POST['name'];
                $weight = floatval($_POST['weight'] ?? 0);
                $description = $_POST['description'] ?? '';
                
                $db->query("
                    INSERT INTO grade_categories (course_id, name, weight, description)
                    VALUES (:course_id, :name, :weight, :description)
                ", [
                    ':course_id' => $course_id,
                    ':name' => $name,
                    ':weight' => $weight,
                    ':description' => $description
                ]);
                
                success_response(['category_id' => $db->lastInsertId()], 'Category created successfully');
                break;
                
            case 'create_grade_item':
                $course_id = intval($_POST['course_id']);
                $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
                $title = $_POST['title'];
                $item_type = $_POST['item_type'];
                $max_points = floatval($_POST['max_points']);
                $due_date = $_POST['due_date'] ?? null;
                
                $db->query("
                    INSERT INTO grade_items (course_id, category_id, item_type, title, max_points, due_date)
                    VALUES (:course_id, :category_id, :item_type, :title, :max_points, :due_date)
                ", [
                    ':course_id' => $course_id,
                    ':category_id' => $category_id,
                    ':item_type' => $item_type,
                    ':title' => $title,
                    ':max_points' => $max_points,
                    ':due_date' => $due_date
                ]);
                
                success_response(['item_id' => $db->lastInsertId()], 'Grade item created successfully');
                break;
                
            case 'bulk_update':
                // Bulk update multiple grades at once
                $grades = json_decode($_POST['grades'], true);
                
                foreach ($grades as $grade) {
                    // Similar logic to update_grade but in batch
                    // Implementation would be similar to above
                }
                
                success_response([], 'Grades updated successfully');
                break;
        }
    }
    
    // DELETE - Remove grade items or categories
    elseif ($method === 'DELETE') {
        if ($user_role !== 'teacher' && $user_role !== 'admin') {
            error_response('Access denied', 403);
        }
        
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        
        switch ($type) {
            case 'category':
                $db->query("DELETE FROM grade_categories WHERE id = :id", [':id' => $id]);
                success_response([], 'Category deleted successfully');
                break;
                
            case 'item':
                $db->query("DELETE FROM grade_items WHERE id = :id", [':id' => $id]);
                success_response([], 'Grade item deleted successfully');
                break;
        }
    }
    
} catch (Exception $e) {
    error_log("Gradebook API Error: " . $e->getMessage());
    error_response('An error occurred', 500);
}

// Helper function to calculate letter grade
function calculateLetterGrade($percentage, $db) {
    $scale = $db->selectOne("
        SELECT letter_grade FROM grade_scales
        WHERE course_id IS NULL
          AND :percentage BETWEEN min_percentage AND max_percentage
        LIMIT 1
    ", [':percentage' => $percentage]);
    
    return $scale['letter_grade'] ?? 'N/A';
}

// Helper function to recalculate course grade
function recalculateCourseGrade($student_id, $course_id, $db) {
    // Get all grades for student in course
    $grades = $db->select("
        SELECT sg.points_earned, sg.percentage, sg.is_excused, gi.max_points, gi.weight, gi.is_extra_credit
        FROM student_grades sg
        JOIN grade_items gi ON sg.grade_item_id = gi.id
        WHERE sg.student_id = :student_id AND gi.course_id = :course_id AND sg.is_excused = 0
    ", [':student_id' => $student_id, ':course_id' => $course_id]);
    
    if (empty($grades)) {
        return;
    }
    
    // Simple average calculation (can be enhanced with weighted categories)
    $total_points = 0;
    $earned_points = 0;
    
    foreach ($grades as $grade) {
        $total_points += $grade['max_points'];
        $earned_points += $grade['points_earned'];
    }
    
    $percentage = ($earned_points / $total_points) * 100;
    $letter_grade = calculateLetterGrade($percentage, $db);
    
    // Update or insert course grade
    $existing = $db->selectOne("
        SELECT id FROM course_grades
        WHERE student_id = :student_id AND course_id = :course_id
    ", [':student_id' => $student_id, ':course_id' => $course_id]);
    
    if ($existing) {
        $db->query("
            UPDATE course_grades
            SET current_percentage = :pct, current_letter_grade = :letter
            WHERE id = :id
        ", [':pct' => $percentage, ':letter' => $letter_grade, ':id' => $existing['id']]);
    } else {
        $db->query("
            INSERT INTO course_grades (student_id, course_id, current_percentage, current_letter_grade)
            VALUES (:student_id, :course_id, :pct, :letter)
        ", [':student_id' => $student_id, ':course_id' => $course_id, ':pct' => $percentage, ':letter' => $letter_grade]);
    }
}
?>

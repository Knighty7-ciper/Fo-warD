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
            if (isset($_GET['id'])) {
                // Get single submission
                $submission_id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT s.*, u.name as student_name, u.email as student_email,
                           a.title as assignment_title, a.max_points
                    FROM assignment_submissions s
                    JOIN users u ON s.student_id = u.id
                    JOIN assignments a ON s.assignment_id = a.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$submission_id]);
                $submission = $stmt->fetch();
                
                if ($submission) {
                    // Get files
                    $stmt = $pdo->prepare("SELECT * FROM assignment_files WHERE submission_id = ?");
                    $stmt->execute([$submission_id]);
                    $submission['files'] = $stmt->fetchAll();
                    
                    echo json_encode($submission);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Submission not found']);
                }
            }
            break;
            
        case 'POST':
            if ($user_role !== 'student') {
                http_response_code(403);
                echo json_encode(['error' => 'Only students can submit assignments']);
                exit;
            }
            
            // Handle file upload
            if (isset($_FILES['files'])) {
                $assignment_id = (int)$_POST['assignment_id'];
                $submission_text = $_POST['submission_text'] ?? '';
                
                // Get assignment
                $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND status = 'published'");
                $stmt->execute([$assignment_id]);
                $assignment = $stmt->fetch();
                
                if (!$assignment) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Assignment not found']);
                    exit;
                }
                
                // Check if late
                $is_late = strtotime($assignment['due_date']) < time();
                
                if ($is_late && !$assignment['allow_late_submission']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Late submissions are not allowed']);
                    exit;
                }
                
                // Create or update submission
                $stmt = $pdo->prepare("
                    INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, is_late)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE submission_text = ?, submitted_at = NOW(), is_late = ?
                ");
                $stmt->execute([$assignment_id, $user_id, $submission_text, $is_late, $submission_text, $is_late]);
                
                $submission_id = $pdo->lastInsertId() ?: $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                
                // Handle file uploads
                $upload_dir = __DIR__ . '/../../uploads/assignments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $files = $_FILES['files'];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_name = basename($files['name'][$i]);
                        $file_size = $files['size'][$i];
                        $file_type = $files['type'][$i];
                        $file_tmp = $files['tmp_name'][$i];
                        
                        // Generate unique filename
                        $unique_name = uniqid() . '_' . $file_name;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Save file record
                            $stmt = $pdo->prepare("
                                INSERT INTO assignment_files (submission_id, file_name, file_path, file_size, file_type)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$submission_id, $file_name, $unique_name, $file_size, $file_type]);
                        }
                    }
                }
                
                // Create notification for teacher
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link)
                    VALUES (?, 'assignment', ?, ?, ?)
                ");
                $stmt->execute([
                    $assignment['teacher_id'],
                    'New Assignment Submission',
                    'A student has submitted an assignment',
                    '/frontend/teacher/assignment-view.php?id=' . $assignment_id
                ]);
                
                echo json_encode(['success' => true, 'submission_id' => $submission_id]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'No files uploaded']);
            }
            break;
            
        case 'PUT':
            // Grade submission
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can grade submissions']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $submission_id = (int)$data['id'];
            $grade = (float)$data['grade'];
            $feedback = $data['feedback'] ?? '';
            
            // Update submission
            $stmt = $pdo->prepare("
                UPDATE assignment_submissions 
                SET grade = ?, feedback = ?, status = 'graded', graded_by = ?, graded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$grade, $feedback, $user_id, $submission_id]);
            
            // Get submission details
            $stmt = $pdo->prepare("
                SELECT s.*, a.max_points, a.course_id
                FROM assignment_submissions s
                JOIN assignments a ON s.assignment_id = a.id
                WHERE s.id = ?
            ");
            $stmt->execute([$submission_id]);
            $submission = $stmt->fetch();
            
            // Add to grades table
            $percentage = ($grade / $submission['max_points']) * 100;
            $letter_grade = calculateLetterGrade($percentage);
            
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, course_id, assignment_id, grade_type, points_earned, max_points, percentage, letter_grade)
                VALUES (?, ?, ?, 'assignment', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $submission['student_id'],
                $submission['course_id'],
                $submission['assignment_id'],
                $grade,
                $submission['max_points'],
                $percentage,
                $letter_grade
            ]);
            
            // Create notification for student
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link)
                VALUES (?, 'grade', ?, ?, ?)
            ");
            $stmt->execute([
                $submission['student_id'],
                'Assignment Graded',
                'Your assignment has been graded',
                '/frontend/student/assignment-view.php?id=' . $submission['assignment_id']
            ]);
            
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function calculateLetterGrade($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}
?>

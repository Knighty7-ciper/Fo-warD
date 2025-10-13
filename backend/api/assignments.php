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
                // Get single assignment
                $assignment_id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT a.*, u.name as teacher_name, c.title as course_title
                    FROM assignments a
                    JOIN users u ON a.teacher_id = u.id
                    JOIN courses c ON a.course_id = c.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$assignment_id]);
                $assignment = $stmt->fetch();
                
                if ($assignment) {
                    // Get student's submission if student
                    if ($user_role === 'student') {
                        $stmt = $pdo->prepare("
                            SELECT s.*, u.name as graded_by_name
                            FROM assignment_submissions s
                            LEFT JOIN users u ON s.graded_by = u.id
                            WHERE s.assignment_id = ? AND s.student_id = ?
                        ");
                        $stmt->execute([$assignment_id, $user_id]);
                        $submission = $stmt->fetch();
                        
                        if ($submission) {
                            // Get files
                            $stmt = $pdo->prepare("SELECT * FROM assignment_files WHERE submission_id = ?");
                            $stmt->execute([$submission['id']]);
                            $submission['files'] = $stmt->fetchAll();
                        }
                        
                        $assignment['submission'] = $submission ?: null;
                    } elseif ($user_role === 'teacher' || $user_role === 'admin') {
                        // Get all submissions for teacher
                        $stmt = $pdo->prepare("
                            SELECT s.*, u.name as student_name, u.email as student_email
                            FROM assignment_submissions s
                            JOIN users u ON s.student_id = u.id
                            WHERE s.assignment_id = ?
                            ORDER BY s.submitted_at DESC
                        ");
                        $stmt->execute([$assignment_id]);
                        $assignment['submissions'] = $stmt->fetchAll();
                        
                        // Get submission stats
                        $stmt = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total_submissions,
                                SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded_count,
                                SUM(CASE WHEN is_late = TRUE THEN 1 ELSE 0 END) as late_count
                            FROM assignment_submissions
                            WHERE assignment_id = ?
                        ");
                        $stmt->execute([$assignment_id]);
                        $assignment['stats'] = $stmt->fetch();
                    }
                    
                    echo json_encode($assignment);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Assignment not found']);
                }
            } elseif (isset($_GET['course_id'])) {
                // Get assignments by course
                $course_id = (int)$_GET['course_id'];
                
                if ($user_role === 'student') {
                    $stmt = $pdo->prepare("
                        SELECT a.*, u.name as teacher_name,
                               s.id as submission_id, s.status as submission_status, 
                               s.grade, s.submitted_at
                        FROM assignments a
                        JOIN users u ON a.teacher_id = u.id
                        LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                        WHERE a.course_id = ? AND a.status = 'published'
                        ORDER BY a.due_date ASC
                    ");
                    $stmt->execute([$user_id, $course_id]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT a.*, u.name as teacher_name,
                               (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
                        FROM assignments a
                        JOIN users u ON a.teacher_id = u.id
                        WHERE a.course_id = ?
                        ORDER BY a.due_date DESC
                    ");
                    $stmt->execute([$course_id]);
                }
                
                echo json_encode($stmt->fetchAll());
            } elseif (isset($_GET['teacher_assignments'])) {
                // Get teacher's assignments
                $stmt = $pdo->prepare("
                    SELECT a.*, c.title as course_title,
                           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
                           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted') as pending_count
                    FROM assignments a
                    JOIN courses c ON a.course_id = c.id
                    WHERE a.teacher_id = ?
                    ORDER BY a.due_date DESC
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'POST':
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can create assignments']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Create assignment
            $stmt = $pdo->prepare("
                INSERT INTO assignments (course_id, teacher_id, title, description, instructions,
                                       max_points, due_date, allow_late_submission, late_penalty_percent,
                                       submission_type, allowed_file_types, max_file_size, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['course_id'],
                $user_id,
                $data['title'],
                $data['description'] ?? '',
                $data['instructions'] ?? '',
                $data['max_points'] ?? 100,
                $data['due_date'],
                $data['allow_late_submission'] ?? true,
                $data['late_penalty_percent'] ?? 0,
                $data['submission_type'] ?? 'both',
                $data['allowed_file_types'] ?? 'pdf,doc,docx,txt',
                $data['max_file_size'] ?? 10485760,
                $data['status'] ?? 'draft'
            ]);
            
            $assignment_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'assignment_id' => $assignment_id]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $assignment_id = (int)$data['id'];
            
            if ($user_role === 'teacher' || $user_role === 'admin') {
                // Update assignment
                $stmt = $pdo->prepare("
                    UPDATE assignments 
                    SET title = ?, description = ?, instructions = ?, max_points = ?,
                        due_date = ?, allow_late_submission = ?, late_penalty_percent = ?,
                        submission_type = ?, status = ?
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $data['title'],
                    $data['description'] ?? '',
                    $data['instructions'] ?? '',
                    $data['max_points'] ?? 100,
                    $data['due_date'],
                    $data['allow_late_submission'] ?? true,
                    $data['late_penalty_percent'] ?? 0,
                    $data['submission_type'] ?? 'both',
                    $data['status'] ?? 'draft',
                    $assignment_id,
                    $user_id
                ]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can delete assignments']);
                exit;
            }
            
            $assignment_id = (int)$_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$assignment_id, $user_id]);
            
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

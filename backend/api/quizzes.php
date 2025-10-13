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
                // Get single quiz
                $quiz_id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT q.*, u.name as teacher_name, c.title as course_title
                    FROM quizzes q
                    JOIN users u ON q.teacher_id = u.id
                    JOIN courses c ON q.course_id = c.id
                    WHERE q.id = ?
                ");
                $stmt->execute([$quiz_id]);
                $quiz = $stmt->fetch();
                
                if ($quiz) {
                    // Get questions
                    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index");
                    $stmt->execute([$quiz_id]);
                    $questions = $stmt->fetchAll();
                    
                    // Get options for each question
                    foreach ($questions as &$question) {
                        $stmt = $pdo->prepare("SELECT * FROM quiz_question_options WHERE question_id = ? ORDER BY order_index");
                        $stmt->execute([$question['id']]);
                        $question['options'] = $stmt->fetchAll();
                    }
                    
                    $quiz['questions'] = $questions;
                    
                    // Get student's attempts if student
                    if ($user_role === 'student') {
                        $stmt = $pdo->prepare("
                            SELECT * FROM quiz_attempts 
                            WHERE quiz_id = ? AND student_id = ? 
                            ORDER BY attempt_number DESC
                        ");
                        $stmt->execute([$quiz_id, $user_id]);
                        $quiz['attempts'] = $stmt->fetchAll();
                    }
                    
                    echo json_encode($quiz);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Quiz not found']);
                }
            } elseif (isset($_GET['course_id'])) {
                // Get quizzes by course
                $course_id = (int)$_GET['course_id'];
                $stmt = $pdo->prepare("
                    SELECT q.*, u.name as teacher_name,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
                    FROM quizzes q
                    JOIN users u ON q.teacher_id = u.id
                    WHERE q.course_id = ? AND q.status = 'published'
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute([$course_id]);
                echo json_encode($stmt->fetchAll());
            } elseif (isset($_GET['teacher_quizzes'])) {
                // Get teacher's quizzes
                $stmt = $pdo->prepare("
                    SELECT q.*, c.title as course_title,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
                    FROM quizzes q
                    JOIN courses c ON q.course_id = c.id
                    WHERE q.teacher_id = ?
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'POST':
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can create quizzes']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Create quiz
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (course_id, teacher_id, title, description, instructions, 
                                   time_limit, passing_score, max_attempts, shuffle_questions,
                                   show_correct_answers, show_results_immediately, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['course_id'],
                $user_id,
                $data['title'],
                $data['description'] ?? '',
                $data['instructions'] ?? '',
                $data['time_limit'] ?? 0,
                $data['passing_score'] ?? 70,
                $data['max_attempts'] ?? 1,
                $data['shuffle_questions'] ?? false,
                $data['show_correct_answers'] ?? true,
                $data['show_results_immediately'] ?? true,
                $data['status'] ?? 'draft'
            ]);
            
            $quiz_id = $pdo->lastInsertId();
            
            // Add questions if provided
            if (isset($data['questions'])) {
                foreach ($data['questions'] as $index => $q) {
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_questions (quiz_id, question_type, question_text, points, order_index, explanation)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $quiz_id,
                        $q['type'],
                        $q['text'],
                        $q['points'] ?? 1,
                        $index,
                        $q['explanation'] ?? ''
                    ]);
                    
                    $question_id = $pdo->lastInsertId();
                    
                    // Add options for multiple choice
                    if ($q['type'] === 'multiple_choice' && isset($q['options'])) {
                        foreach ($q['options'] as $opt_index => $option) {
                            $stmt = $pdo->prepare("
                                INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $question_id,
                                $option['text'],
                                $option['is_correct'] ?? false,
                                $opt_index
                            ]);
                        }
                    }
                }
            }
            
            echo json_encode(['success' => true, 'quiz_id' => $quiz_id]);
            break;
            
        case 'PUT':
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can update quizzes']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $quiz_id = (int)$data['id'];
            
            // Update quiz
            $stmt = $pdo->prepare("
                UPDATE quizzes 
                SET title = ?, description = ?, instructions = ?, time_limit = ?,
                    passing_score = ?, max_attempts = ?, shuffle_questions = ?,
                    show_correct_answers = ?, show_results_immediately = ?, status = ?
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['instructions'] ?? '',
                $data['time_limit'] ?? 0,
                $data['passing_score'] ?? 70,
                $data['max_attempts'] ?? 1,
                $data['shuffle_questions'] ?? false,
                $data['show_correct_answers'] ?? true,
                $data['show_results_immediately'] ?? true,
                $data['status'] ?? 'draft',
                $quiz_id,
                $user_id
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            if ($user_role !== 'teacher' && $user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can delete quizzes']);
                exit;
            }
            
            $quiz_id = (int)$_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$quiz_id, $user_id]);
            
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

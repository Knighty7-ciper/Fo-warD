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
                // Get single attempt with answers
                $attempt_id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT qa.*, q.title as quiz_title, q.show_correct_answers,
                           u.name as student_name
                    FROM quiz_attempts qa
                    JOIN quizzes q ON qa.quiz_id = q.id
                    JOIN users u ON qa.student_id = u.id
                    WHERE qa.id = ?
                ");
                $stmt->execute([$attempt_id]);
                $attempt = $stmt->fetch();
                
                if ($attempt) {
                    // Get answers
                    $stmt = $pdo->prepare("
                        SELECT qa.*, qq.question_text, qq.question_type, qq.points as max_points,
                               qo.option_text, qo.is_correct
                        FROM quiz_answers qa
                        JOIN quiz_questions qq ON qa.question_id = qq.id
                        LEFT JOIN quiz_question_options qo ON qa.selected_option_id = qo.id
                        WHERE qa.attempt_id = ?
                    ");
                    $stmt->execute([$attempt_id]);
                    $attempt['answers'] = $stmt->fetchAll();
                    
                    echo json_encode($attempt);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Attempt not found']);
                }
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['start_attempt'])) {
                // Start new quiz attempt
                $quiz_id = (int)$data['quiz_id'];
                
                // Check if quiz exists and is available
                $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND status = 'published'");
                $stmt->execute([$quiz_id]);
                $quiz = $stmt->fetch();
                
                if (!$quiz) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Quiz not found or not available']);
                    exit;
                }
                
                // Check attempt limit
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
                $stmt->execute([$quiz_id, $user_id]);
                $attempt_count = $stmt->fetch()['count'];
                
                if ($attempt_count >= $quiz['max_attempts']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Maximum attempts reached']);
                    exit;
                }
                
                // Create attempt
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$quiz_id, $user_id, $attempt_count + 1]);
                $attempt_id = $pdo->lastInsertId();
                
                echo json_encode(['success' => true, 'attempt_id' => $attempt_id]);
                
            } elseif (isset($data['submit_attempt'])) {
                // Submit quiz attempt
                $attempt_id = (int)$data['attempt_id'];
                $answers = $data['answers'];
                
                // Get attempt
                $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND student_id = ?");
                $stmt->execute([$attempt_id, $user_id]);
                $attempt = $stmt->fetch();
                
                if (!$attempt) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Attempt not found']);
                    exit;
                }
                
                // Get quiz
                $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
                $stmt->execute([$attempt['quiz_id']]);
                $quiz = $stmt->fetch();
                
                $total_score = 0;
                $max_score = 0;
                
                // Process each answer
                foreach ($answers as $answer) {
                    $question_id = (int)$answer['question_id'];
                    
                    // Get question
                    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
                    $stmt->execute([$question_id]);
                    $question = $stmt->fetch();
                    
                    $max_score += $question['points'];
                    $is_correct = null;
                    $points_earned = 0;
                    $selected_option_id = null;
                    $answer_text = null;
                    
                    if ($question['question_type'] === 'multiple_choice') {
                        $selected_option_id = (int)$answer['selected_option'];
                        
                        // Check if correct
                        $stmt = $pdo->prepare("SELECT is_correct FROM quiz_question_options WHERE id = ?");
                        $stmt->execute([$selected_option_id]);
                        $option = $stmt->fetch();
                        
                        $is_correct = $option['is_correct'];
                        $points_earned = $is_correct ? $question['points'] : 0;
                        
                    } elseif ($question['question_type'] === 'true_false') {
                        $answer_text = $answer['answer'];
                        
                        // Get correct answer
                        $stmt = $pdo->prepare("SELECT id, is_correct FROM quiz_question_options WHERE question_id = ? AND is_correct = TRUE");
                        $stmt->execute([$question_id]);
                        $correct_option = $stmt->fetch();
                        
                        $is_correct = ($answer_text === 'true' && $correct_option['is_correct']) || 
                                     ($answer_text === 'false' && !$correct_option['is_correct']);
                        $points_earned = $is_correct ? $question['points'] : 0;
                        
                    } else {
                        // Short answer, essay - needs manual grading
                        $answer_text = $answer['answer'];
                        $is_correct = null;
                    }
                    
                    // Save answer
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_answers (attempt_id, question_id, answer_text, selected_option_id, is_correct, points_earned)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $attempt_id,
                        $question_id,
                        $answer_text,
                        $selected_option_id,
                        $is_correct,
                        $points_earned
                    ]);
                    
                    if ($is_correct !== null) {
                        $total_score += $points_earned;
                    }
                }
                
                // Calculate time taken
                $time_taken = time() - strtotime($attempt['started_at']);
                $percentage = $max_score > 0 ? ($total_score / $max_score) * 100 : 0;
                
                // Update attempt
                $status = 'submitted';
                // Check if all questions are auto-graded
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM quiz_answers 
                    WHERE attempt_id = ? AND is_correct IS NULL
                ");
                $stmt->execute([$attempt_id]);
                $needs_grading = $stmt->fetch()['count'];
                
                if ($needs_grading == 0) {
                    $status = 'graded';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE quiz_attempts 
                    SET submitted_at = NOW(), time_taken = ?, score = ?, max_score = ?, 
                        percentage = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$time_taken, $total_score, $max_score, $percentage, $status, $attempt_id]);
                
                echo json_encode([
                    'success' => true,
                    'score' => $total_score,
                    'max_score' => $max_score,
                    'percentage' => $percentage,
                    'passed' => $percentage >= $quiz['passing_score']
                ]);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
require_once '../config/db.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = authenticate();
if (!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$quiz_id = $input['quiz_id'] ?? null;
$answers = $input['answers'] ?? [];

if (!$quiz_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing quiz_id']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Create attempt
    $stmt = $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (?, ?)");
    $stmt->execute([$quiz_id, $user['id']]);
    $attempt_id = $pdo->lastInsertId();

    // Get all questions
    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();

    $total_points = 0;
    $earned_points = 0;

    foreach ($questions as $question) {
        $total_points += $question['points'];
        $question_id = $question['id'];
        $student_answer = $answers[$question_id] ?? null;

        $is_correct = false;
        $points_earned = 0;

        if ($question['question_type'] === 'short_answer') {
            // For short answer, we'll mark it for manual grading
            $is_correct = null;
        } else {
            // Check if answer is correct
            $stmt = $pdo->prepare("SELECT is_correct FROM quiz_options WHERE id = ?");
            $stmt->execute([$student_answer]);
            $option = $stmt->fetch();
            
            if ($option && $option['is_correct']) {
                $is_correct = true;
                $points_earned = $question['points'];
                $earned_points += $points_earned;
            }
        }

        // Save answer
        $stmt = $pdo->prepare("
            INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, answer_text, is_correct, points_earned) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $attempt_id,
            $question_id,
            $question['question_type'] !== 'short_answer' ? $student_answer : null,
            $question['question_type'] === 'short_answer' ? $student_answer : null,
            $is_correct,
            $points_earned
        ]);
    }

    // Calculate score
    $score = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;

    // Update attempt
    $stmt = $pdo->prepare("
        UPDATE quiz_attempts 
        SET score = ?, total_points = ?, completed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$score, $total_points, $attempt_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id,
        'score' => round($score, 2),
        'earned_points' => $earned_points,
        'total_points' => $total_points
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit quiz: ' . $e->getMessage()]);
}
?>

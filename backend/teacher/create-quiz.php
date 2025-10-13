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
if (!$user || $user['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$lesson_id = $input['lesson_id'] ?? null;
$title = $input['title'] ?? null;
$description = $input['description'] ?? '';
$passing_score = $input['passing_score'] ?? 70;
$time_limit = $input['time_limit'] ?? 30;
$attempts_allowed = $input['attempts_allowed'] ?? 3;
$questions = $input['questions'] ?? [];

if (!$lesson_id || !$title || empty($questions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Verify teacher owns the lesson
$stmt = $pdo->prepare("
    SELECT c.teacher_id 
    FROM lessons l 
    JOIN courses c ON l.course_id = c.id 
    WHERE l.id = ?
");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson || $lesson['teacher_id'] != $user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this lesson']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Create quiz
    $stmt = $pdo->prepare("
        INSERT INTO quizzes (lesson_id, title, description, passing_score, time_limit, attempts_allowed) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$lesson_id, $title, $description, $passing_score, $time_limit, $attempts_allowed]);
    $quiz_id = $pdo->lastInsertId();

    // Create questions and options
    foreach ($questions as $index => $question) {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_index) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $quiz_id,
            $question['text'],
            $question['type'],
            $question['points'],
            $index
        ]);
        $question_id = $pdo->lastInsertId();

        // Add options for multiple choice and true/false
        if ($question['type'] !== 'short_answer' && !empty($question['options'])) {
            foreach ($question['options'] as $option) {
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_options (question_id, option_text, is_correct) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $question_id,
                    $option['text'],
                    $option['is_correct'] ? 1 : 0
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'quiz_id' => $quiz_id,
        'message' => 'Quiz created successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create quiz: ' . $e->getMessage()]);
}
?>

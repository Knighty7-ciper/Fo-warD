<?php
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

$user = authenticate();
if (!$user || $user['role'] !== 'student') {
    header('Location: /frontend/auth/login.php');
    exit;
}

$attempt_id = $_GET['attempt_id'] ?? null;
if (!$attempt_id) {
    header('Location: /frontend/student/dashboard.php');
    exit;
}

// Get attempt details
$stmt = $pdo->prepare("
    SELECT qa.*, q.title, q.passing_score, l.title as lesson_title, c.title as course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN lessons l ON q.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE qa.id = ? AND qa.student_id = ?
");
$stmt->execute([$attempt_id, $user['id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: /frontend/student/dashboard.php');
    exit;
}

// Get answers with questions
$stmt = $pdo->prepare("
    SELECT 
        qa.*,
        qq.question_text,
        qq.question_type,
        qq.points,
        qo.option_text as selected_option,
        (SELECT option_text FROM quiz_options WHERE question_id = qq.id AND is_correct = 1 LIMIT 1) as correct_answer
    FROM quiz_answers qa
    JOIN quiz_questions qq ON qa.question_id = qq.id
    LEFT JOIN quiz_options qo ON qa.selected_option_id = qo.id
    WHERE qa.attempt_id = ?
    ORDER BY qq.order_index
");
$stmt->execute([$attempt_id]);
$answers = $stmt->fetchAll();

$passed = $attempt['score'] >= $attempt['passing_score'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .results-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .results-header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .score-display {
            font-size: 64px;
            font-weight: bold;
            margin: 20px 0;
        }
        .score-display.passed {
            color: #27ae60;
        }
        .score-display.failed {
            color: #e74c3c;
        }
        .result-status {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .answer-review {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .answer-review.correct {
            border-left: 4px solid #27ae60;
        }
        .answer-review.incorrect {
            border-left: 4px solid #e74c3c;
        }
        .question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .answer-info {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/student-nav.php'; ?>

    <div class="results-container">
        <div class="results-header">
            <h1><?= htmlspecialchars($attempt['title']) ?></h1>
            <p><strong>Course:</strong> <?= htmlspecialchars($attempt['course_title']) ?></p>
            <p><strong>Lesson:</strong> <?= htmlspecialchars($attempt['lesson_title']) ?></p>
            
            <div class="score-display <?= $passed ? 'passed' : 'failed' ?>">
                <?= round($attempt['score'], 1) ?>%
            </div>
            
            <div class="result-status">
                <?php if ($passed): ?>
                    <span style="color: #27ae60;">✓ Passed!</span>
                <?php else: ?>
                    <span style="color: #e74c3c;">✗ Not Passed</span>
                <?php endif; ?>
            </div>
            
            <p>Passing Score: <?= $attempt['passing_score'] ?>%</p>
            <p>Points Earned: <?= $attempt['score'] >= $attempt['passing_score'] ? $attempt['total_points'] : 0 ?> / <?= $attempt['total_points'] ?></p>
        </div>

        <h2>Answer Review</h2>
        <?php foreach ($answers as $index => $answer): ?>
            <div class="answer-review <?= $answer['is_correct'] ? 'correct' : 'incorrect' ?>">
                <div class="question-text">
                    <?= ($index + 1) ?>. <?= htmlspecialchars($answer['question_text']) ?>
                    <span style="color: #7f8c8d;">(<?= $answer['points'] ?> point<?= $answer['points'] > 1 ? 's' : '' ?>)</span>
                </div>

                <?php if ($answer['question_type'] === 'short_answer'): ?>
                    <div class="answer-info">
                        <strong>Your Answer:</strong> <?= htmlspecialchars($answer['answer_text']) ?>
                    </div>
                    <?php if ($answer['is_correct'] === null): ?>
                        <p style="color: #f39c12;">⏳ Pending manual grading</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="answer-info">
                        <strong>Your Answer:</strong> <?= htmlspecialchars($answer['selected_option']) ?>
                    </div>
                    <?php if (!$answer['is_correct']): ?>
                        <div class="answer-info" style="background: #d1fae5;">
                            <strong>Correct Answer:</strong> <?= htmlspecialchars($answer['correct_answer']) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <p style="margin-top: 10px;">
                    <?php if ($answer['is_correct']): ?>
                        <span style="color: #27ae60; font-weight: 600;">✓ Correct (+<?= $answer['points_earned'] ?> points)</span>
                    <?php elseif ($answer['is_correct'] === null): ?>
                        <span style="color: #f39c12; font-weight: 600;">⏳ Pending Review</span>
                    <?php else: ?>
                        <span style="color: #e74c3c; font-weight: 600;">✗ Incorrect (0 points)</span>
                    <?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/frontend/student/enrolled-courses.php" class="btn btn-primary">Back to My Courses</a>
            <?php if (!$passed): ?>
                <a href="/frontend/student/take-quiz.php?quiz_id=<?= $attempt['quiz_id'] ?>" class="btn btn-success">Retry Quiz</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

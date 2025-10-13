<?php
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

$user = authenticate();
if (!$user || $user['role'] !== 'student') {
    header('Location: /frontend/auth/login.php');
    exit;
}

$quiz_id = $_GET['quiz_id'] ?? null;
if (!$quiz_id) {
    header('Location: /frontend/student/dashboard.php');
    exit;
}

// Get quiz details
$stmt = $pdo->prepare("
    SELECT q.*, l.title as lesson_title, c.title as course_title
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: /frontend/student/dashboard.php');
    exit;
}

// Check attempts
$stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quiz_id, $user['id']]);
$attempts = $stmt->fetch();

if ($attempts['attempt_count'] >= $quiz['attempts_allowed']) {
    die('You have used all your attempts for this quiz.');
}

// Get questions
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Get options for each question
foreach ($questions as &$question) {
    if ($question['question_type'] !== 'short_answer') {
        $stmt = $pdo->prepare("SELECT id, option_text FROM quiz_options WHERE question_id = ?");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .quiz-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .quiz-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .timer {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            text-align: center;
            margin: 10px 0;
        }
        .question-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .option-label {
            display: block;
            padding: 12px;
            margin-bottom: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .option-label:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        .option-label input {
            margin-right: 10px;
        }
        .answer-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
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

    <div class="quiz-container">
        <div class="quiz-header">
            <h1><?= htmlspecialchars($quiz['title']) ?></h1>
            <p><?= htmlspecialchars($quiz['description']) ?></p>
            <p><strong>Course:</strong> <?= htmlspecialchars($quiz['course_title']) ?></p>
            <p><strong>Lesson:</strong> <?= htmlspecialchars($quiz['lesson_title']) ?></p>
            <p><strong>Passing Score:</strong> <?= $quiz['passing_score'] ?>%</p>
            <p><strong>Attempts:</strong> <?= $attempts['attempt_count'] ?> / <?= $quiz['attempts_allowed'] ?></p>
            <div class="timer" id="timer"><?= $quiz['time_limit'] ?>:00</div>
        </div>

        <form id="quizForm">
            <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="question-text">
                        <?= ($index + 1) ?>. <?= htmlspecialchars($question['question_text']) ?>
                        <span style="color: #7f8c8d;">(<?= $question['points'] ?> point<?= $question['points'] > 1 ? 's' : '' ?>)</span>
                    </div>

                    <?php if ($question['question_type'] === 'short_answer'): ?>
                        <input type="text" 
                               name="answers[<?= $question['id'] ?>]" 
                               class="answer-input" 
                               placeholder="Type your answer here" 
                               required>
                    <?php else: ?>
                        <?php foreach ($question['options'] as $option): ?>
                            <label class="option-label">
                                <input type="radio" 
                                       name="answers[<?= $question['id'] ?>]" 
                                       value="<?= $option['id'] ?>" 
                                       required>
                                <?= htmlspecialchars($option['option_text']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success">Submit Quiz</button>
        </form>
    </div>

    <script>
        // Timer
        let timeLeft = <?= $quiz['time_limit'] * 60 ?>;
        const timerElement = document.getElementById('timer');

        const countdown = setInterval(function() {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                clearInterval(countdown);
                alert('Time is up! Submitting quiz...');
                document.getElementById('quizForm').submit();
            }
        }, 1000);

        // Submit quiz
        document.getElementById('quizForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            clearInterval(countdown);

            const formData = new FormData(this);
            const data = {
                quiz_id: formData.get('quiz_id'),
                answers: {}
            };

            for (let [key, value] of formData.entries()) {
                if (key.startsWith('answers[')) {
                    const questionId = key.match(/\d+/)[0];
                    data.answers[questionId] = value;
                }
            }

            try {
                const response = await fetch('/backend/student/submit-quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert(`Quiz submitted! Your score: ${result.score}%`);
                    window.location.href = '/frontend/student/quiz-results.php?attempt_id=' + result.attempt_id;
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Failed to submit quiz');
            }
        });
    </script>
</body>
</html>

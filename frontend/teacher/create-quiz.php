<?php
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

$user = authenticate();
if (!$user || $user['role'] !== 'teacher') {
    header('Location: /frontend/auth/login.php');
    exit;
}

$lesson_id = $_GET['lesson_id'] ?? null;
if (!$lesson_id) {
    header('Location: /frontend/teacher/dashboard.php');
    exit;
}

// Verify teacher owns this lesson
$stmt = $pdo->prepare("
    SELECT l.*, c.title as course_title, c.teacher_id 
    FROM lessons l 
    JOIN courses c ON l.course_id = c.id 
    WHERE l.id = ?
");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson || $lesson['teacher_id'] != $user['id']) {
    header('Location: /frontend/teacher/dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .quiz-builder {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .quiz-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .questions-section {
            margin-top: 30px;
        }
        .question-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        .option-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .option-group input[type="text"] {
            flex: 1;
        }
        .btn {
            padding: 10px 20px;
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
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>

    <div class="quiz-builder">
        <h1>Create Quiz for: <?= htmlspecialchars($lesson['title']) ?></h1>
        <p>Course: <?= htmlspecialchars($lesson['course_title']) ?></p>

        <form id="quizForm" class="quiz-form">
            <input type="hidden" name="lesson_id" value="<?= $lesson_id ?>">
            
            <div class="form-group">
                <label for="title">Quiz Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="passing_score">Passing Score (%)</label>
                <input type="number" id="passing_score" name="passing_score" value="70" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label for="time_limit">Time Limit (minutes)</label>
                <input type="number" id="time_limit" name="time_limit" value="30" min="1" required>
            </div>

            <div class="form-group">
                <label for="attempts_allowed">Attempts Allowed</label>
                <input type="number" id="attempts_allowed" name="attempts_allowed" value="3" min="1" required>
            </div>

            <div class="questions-section">
                <h2>Questions</h2>
                <div id="questionsContainer"></div>
                <button type="button" class="btn btn-secondary" onclick="addQuestion()">Add Question</button>
            </div>

            <button type="submit" class="btn btn-success" style="margin-top: 20px;">Create Quiz</button>
        </form>
    </div>

    <script>
        let questionCount = 0;

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questionsContainer');
            const questionCard = document.createElement('div');
            questionCard.className = 'question-card';
            questionCard.id = `question-${questionCount}`;
            questionCard.innerHTML = `
                <h3>Question ${questionCount}</h3>
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="questions[${questionCount}][text]" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label>Question Type</label>
                    <select name="questions[${questionCount}][type]" onchange="updateQuestionType(${questionCount}, this.value)">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Points</label>
                    <input type="number" name="questions[${questionCount}][points]" value="1" min="1" required>
                </div>
                <div id="options-${questionCount}">
                    <label>Options</label>
                    <div class="option-group">
                        <input type="text" name="questions[${questionCount}][options][0][text]" placeholder="Option 1" required>
                        <label><input type="radio" name="questions[${questionCount}][correct]" value="0" required> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${questionCount}][options][1][text]" placeholder="Option 2" required>
                        <label><input type="radio" name="questions[${questionCount}][correct]" value="1"> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${questionCount}][options][2][text]" placeholder="Option 3" required>
                        <label><input type="radio" name="questions[${questionCount}][correct]" value="2"> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${questionCount}][options][3][text]" placeholder="Option 4" required>
                        <label><input type="radio" name="questions[${questionCount}][correct]" value="3"> Correct</label>
                    </div>
                </div>
                <button type="button" class="btn btn-danger" onclick="removeQuestion(${questionCount})">Remove Question</button>
            `;
            container.appendChild(questionCard);
        }

        function removeQuestion(id) {
            document.getElementById(`question-${id}`).remove();
        }

        function updateQuestionType(id, type) {
            const optionsDiv = document.getElementById(`options-${id}`);
            if (type === 'true_false') {
                optionsDiv.innerHTML = `
                    <label>Options</label>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][0][text]" value="True" readonly>
                        <label><input type="radio" name="questions[${id}][correct]" value="0" required> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][1][text]" value="False" readonly>
                        <label><input type="radio" name="questions[${id}][correct]" value="1"> Correct</label>
                    </div>
                `;
            } else if (type === 'short_answer') {
                optionsDiv.innerHTML = `
                    <label>Correct Answer</label>
                    <input type="text" name="questions[${id}][correct_answer]" placeholder="Enter correct answer" required>
                `;
            } else {
                // Reset to multiple choice
                optionsDiv.innerHTML = `
                    <label>Options</label>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][0][text]" placeholder="Option 1" required>
                        <label><input type="radio" name="questions[${id}][correct]" value="0" required> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][1][text]" placeholder="Option 2" required>
                        <label><input type="radio" name="questions[${id}][correct]" value="1"> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][2][text]" placeholder="Option 3" required>
                        <label><input type="radio" name="questions[${id}][correct]" value="2"> Correct</label>
                    </div>
                    <div class="option-group">
                        <input type="text" name="questions[${id}][options][3][text]" placeholder="Option 4" required>
                        <label><input type="radio" name="questions[${id}][correct]" value="3"> Correct</label>
                    </div>
                `;
            }
        }

        document.getElementById('quizForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                lesson_id: formData.get('lesson_id'),
                title: formData.get('title'),
                description: formData.get('description'),
                passing_score: formData.get('passing_score'),
                time_limit: formData.get('time_limit'),
                attempts_allowed: formData.get('attempts_allowed'),
                questions: []
            };

            // Parse questions
            for (let i = 1; i <= questionCount; i++) {
                const questionText = formData.get(`questions[${i}][text]`);
                if (!questionText) continue;

                const question = {
                    text: questionText,
                    type: formData.get(`questions[${i}][type]`),
                    points: formData.get(`questions[${i}][points]`),
                    options: []
                };

                if (question.type === 'short_answer') {
                    question.correct_answer = formData.get(`questions[${i}][correct_answer]`);
                } else {
                    const correctIndex = formData.get(`questions[${i}][correct]`);
                    let optionIndex = 0;
                    while (true) {
                        const optionText = formData.get(`questions[${i}][options][${optionIndex}][text]`);
                        if (!optionText) break;
                        question.options.push({
                            text: optionText,
                            is_correct: optionIndex == correctIndex
                        });
                        optionIndex++;
                    }
                }

                data.questions.push(question);
            }

            try {
                const response = await fetch('/backend/teacher/create-quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert('Quiz created successfully!');
                    window.location.href = '/frontend/teacher/manage-lessons.php?course_id=' + <?= $lesson['course_id'] ?>;
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Failed to create quiz');
            }
        });

        // Add first question by default
        addQuestion();
    </script>
</body>
</html>

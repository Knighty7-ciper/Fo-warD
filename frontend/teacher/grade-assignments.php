<?php
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

$user = authenticate();
if (!$user || $user['role'] !== 'teacher') {
    header('Location: /frontend/auth/login.php');
    exit;
}

$assignment_id = $_GET['assignment_id'] ?? null;
if (!$assignment_id) {
    header('Location: /frontend/teacher/dashboard.php');
    exit;
}

// Get assignment details
$stmt = $pdo->prepare("
    SELECT a.*, l.title as lesson_title, c.title as course_title, c.teacher_id
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment || $assignment['teacher_id'] != $user['id']) {
    header('Location: /frontend/teacher/dashboard.php');
    exit;
}

// Get submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as student_name, u.email as student_email
    FROM assignment_submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignments - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .grading-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .assignment-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .submissions-grid {
            display: grid;
            gap: 20px;
        }
        .submission-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .grading-form {
            display: grid;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group textarea {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-graded {
            background: #27ae60;
            color: white;
        }
        .status-pending {
            background: #f39c12;
            color: white;
        }
        .status-late {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>

    <div class="grading-container">
        <div class="assignment-info">
            <h1><?= htmlspecialchars($assignment['title']) ?></h1>
            <p><strong>Course:</strong> <?= htmlspecialchars($assignment['course_title']) ?></p>
            <p><strong>Lesson:</strong> <?= htmlspecialchars($assignment['lesson_title']) ?></p>
            <p><strong>Due Date:</strong> <?= date('M d, Y g:i A', strtotime($assignment['due_date'])) ?></p>
            <p><strong>Max Points:</strong> <?= $assignment['max_points'] ?></p>
            <p><strong>Submissions:</strong> <?= count($submissions) ?></p>
        </div>

        <h2>Submissions</h2>
        <div class="submissions-grid">
            <?php if (empty($submissions)): ?>
                <p>No submissions yet.</p>
            <?php else: ?>
                <?php foreach ($submissions as $submission): ?>
                    <div class="submission-card">
                        <div class="submission-header">
                            <div>
                                <h3><?= htmlspecialchars($submission['student_name']) ?></h3>
                                <p><?= htmlspecialchars($submission['student_email']) ?></p>
                                <p>Submitted: <?= date('M d, Y g:i A', strtotime($submission['submitted_at'])) ?></p>
                            </div>
                            <div>
                                <?php if ($submission['grade']): ?>
                                    <span class="status-badge status-graded">Graded: <?= $submission['grade'] ?>/<?= $assignment['max_points'] ?></span>
                                <?php elseif (strtotime($submission['submitted_at']) > strtotime($assignment['due_date'])): ?>
                                    <span class="status-badge status-late">Late Submission</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <strong>Submission:</strong>
                            <p><?= nl2br(htmlspecialchars($submission['submission_text'])) ?></p>
                            <?php if ($submission['file_url']): ?>
                                <p><a href="<?= htmlspecialchars($submission['file_url']) ?>" target="_blank">Download Attachment</a></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($submission['feedback']): ?>
                            <div style="margin-top: 15px;">
                                <strong>Your Feedback:</strong>
                                <p><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <form class="grading-form" onsubmit="gradeSubmission(event, <?= $submission['id'] ?>)">
                            <div class="form-group">
                                <label>Grade (out of <?= $assignment['max_points'] ?>)</label>
                                <input type="number" 
                                       name="grade" 
                                       min="0" 
                                       max="<?= $assignment['max_points'] ?>" 
                                       value="<?= $submission['grade'] ?? '' ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label>Feedback</label>
                                <textarea name="feedback" rows="3" required><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Grade</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function gradeSubmission(event, submissionId) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            const data = {
                submission_id: submissionId,
                grade: formData.get('grade'),
                feedback: formData.get('feedback')
            };

            try {
                const response = await fetch('/backend/teacher/grade-submission.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert('Grade saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Failed to save grade');
            }
        }
    </script>
</body>
</html>

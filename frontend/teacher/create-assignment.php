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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $max_points = $_POST['max_points'] ?? 100;
    $allow_late = isset($_POST['allow_late']) ? 1 : 0;

    if ($title) {
        $stmt = $pdo->prepare("
            INSERT INTO assignments (lesson_id, title, description, due_date, max_points, allow_late_submission) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lesson_id, $title, $description, $due_date, $max_points, $allow_late]);
        
        header('Location: /frontend/teacher/manage-lessons.php?course_id=' . $lesson['course_id']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .assignment-form {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background: white;
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
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
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
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>

    <div class="assignment-form">
        <h1>Create Assignment</h1>
        <p>Lesson: <?= htmlspecialchars($lesson['title']) ?></p>
        <p>Course: <?= htmlspecialchars($lesson['course_title']) ?></p>

        <form method="POST">
            <div class="form-group">
                <label for="title">Assignment Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="description">Description/Instructions</label>
                <textarea id="description" name="description" rows="6" required></textarea>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="datetime-local" id="due_date" name="due_date" required>
            </div>

            <div class="form-group">
                <label for="max_points">Maximum Points</label>
                <input type="number" id="max_points" name="max_points" value="100" min="1" required>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="allow_late" name="allow_late" checked>
                <label for="allow_late">Allow late submissions</label>
            </div>

            <button type="submit" class="btn btn-primary">Create Assignment</button>
        </form>
    </div>
</body>
</html>

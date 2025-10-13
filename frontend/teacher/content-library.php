<?php
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

$user = authenticate();
if (!$user || $user['role'] !== 'teacher') {
    header('Location: /frontend/auth/login.php');
    exit;
}

// Get teacher's courses
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

// Get all uploaded content
$stmt = $pdo->prepare("
    SELECT 
        l.id as lesson_id,
        l.title as lesson_title,
        l.video_url,
        l.duration,
        c.id as course_id,
        c.title as course_title,
        (SELECT COUNT(*) FROM lesson_documents WHERE lesson_id = l.id) as document_count
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$user['id']]);
$content = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Library - FowarD LMS</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <style>
        .content-library {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .upload-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .content-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .content-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .content-meta {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .upload-form {
            display: grid;
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group select {
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
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            display: none;
        }
        .progress-fill {
            height: 100%;
            background: #3498db;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>

    <div class="content-library">
        <h1>Content Library</h1>

        <div class="upload-section">
            <h2>Upload New Content</h2>
            <form id="uploadForm" class="upload-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="course_id">Select Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Choose a course...</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lesson_id">Select Lesson</label>
                    <select id="lesson_id" name="lesson_id" required disabled>
                        <option value="">Select a course first...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="content_type">Content Type</label>
                    <select id="content_type" name="content_type" required>
                        <option value="">Choose type...</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                    </select>
                </div>

                <div class="form-group" id="fileGroup" style="display: none;">
                    <label for="file">Select File</label>
                    <input type="file" id="file" name="file" accept="">
                </div>

                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>

                <button type="submit" class="btn btn-primary">Upload Content</button>
            </form>
        </div>

        <h2>Your Content</h2>
        <div class="content-grid">
            <?php foreach ($content as $item): ?>
                <div class="content-card">
                    <h3><?= htmlspecialchars($item['lesson_title']) ?></h3>
                    <div class="content-meta">
                        <p>Course: <?= htmlspecialchars($item['course_title']) ?></p>
                        <?php if ($item['video_url']): ?>
                            <p>Video: <?= $item['duration'] ?> minutes</p>
                        <?php endif; ?>
                        <?php if ($item['document_count'] > 0): ?>
                            <p>Documents: <?= $item['document_count'] ?></p>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-danger" onclick="deleteContent(<?= $item['lesson_id'] ?>)">Delete</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        const courseSelect = document.getElementById('course_id');
        const lessonSelect = document.getElementById('lesson_id');
        const contentTypeSelect = document.getElementById('content_type');
        const fileInput = document.getElementById('file');
        const fileGroup = document.getElementById('fileGroup');
        const uploadForm = document.getElementById('uploadForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');

        courseSelect.addEventListener('change', async function() {
            const courseId = this.value;
            if (!courseId) {
                lessonSelect.disabled = true;
                lessonSelect.innerHTML = '<option value="">Select a course first...</option>';
                return;
            }

            try {
                const response = await fetch(`/backend/teacher/get-lessons.php?course_id=${courseId}`);
                const data = await response.json();
                
                lessonSelect.innerHTML = '<option value="">Choose a lesson...</option>';
                data.lessons.forEach(lesson => {
                    const option = document.createElement('option');
                    option.value = lesson.id;
                    option.textContent = lesson.title;
                    lessonSelect.appendChild(option);
                });
                lessonSelect.disabled = false;
            } catch (error) {
                alert('Failed to load lessons');
            }
        });

        contentTypeSelect.addEventListener('change', function() {
            const type = this.value;
            if (!type) {
                fileGroup.style.display = 'none';
                return;
            }

            fileGroup.style.display = 'block';
            if (type === 'video') {
                fileInput.accept = 'video/mp4,video/webm,video/ogg';
            } else if (type === 'document') {
                fileInput.accept = '.pdf,.doc,.docx,.ppt,.pptx';
            }
        });

        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('course_id', courseSelect.value);
            formData.append('lesson_id', lessonSelect.value);
            
            const contentType = contentTypeSelect.value;
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file');
                return;
            }

            if (contentType === 'video') {
                formData.append('video', file);
            } else {
                formData.append('document', file);
            }

            const endpoint = contentType === 'video' 
                ? '/backend/content/upload-video.php'
                : '/backend/content/upload-document.php';

            progressBar.style.display = 'block';

            try {
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressFill.style.width = percentComplete + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        alert('Content uploaded successfully!');
                        location.reload();
                    } else {
                        alert('Upload failed: ' + xhr.responseText);
                    }
                    progressBar.style.display = 'none';
                });

                xhr.open('POST', endpoint);
                xhr.send(formData);
            } catch (error) {
                alert('Upload failed');
                progressBar.style.display = 'none';
            }
        });

        function deleteContent(lessonId) {
            if (!confirm('Are you sure you want to delete this content?')) return;

            fetch('/backend/teacher/delete-lesson.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_id: lessonId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Content deleted successfully');
                    location.reload();
                } else {
                    alert('Failed to delete content');
                }
            });
        }
    </script>
</body>
</html>

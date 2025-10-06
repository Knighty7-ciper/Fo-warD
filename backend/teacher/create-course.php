<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../shared/utils/sanitize.php';

Auth::requireRole('teacher');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

$title = Sanitize::string($_POST['title'] ?? '');
$description = Sanitize::textarea($_POST['description'] ?? '');
$price = Sanitize::float($_POST['price'] ?? 0);
$status = Sanitize::string($_POST['status'] ?? 'draft');
$thumbnail_url = Sanitize::url($_POST['thumbnail_url'] ?? '');

if (empty($title)) {
    error_response('Course title is required');
}

if (!in_array($status, ['draft', 'published'])) {
    $status = 'draft';
}

try {
    $db = Database::getInstance();
    $teacher_id = Auth::getUserId();

    $db->setUserContext($teacher_id);

    $sql = "INSERT INTO courses (teacher_id, title, description, price, status, thumbnail_url)
            VALUES (:teacher_id, :title, :description, :price, :status, :thumbnail_url)
            RETURNING id";

    $result = $db->query($sql, [
        ':teacher_id' => $teacher_id,
        ':title' => $title,
        ':description' => $description,
        ':price' => $price,
        ':status' => $status,
        ':thumbnail_url' => $thumbnail_url
    ]);

    $course = $result->fetch();

    if (!$course) {
        error_response('Failed to create course');
    }

    Auth::logAudit($teacher_id, 'create_course', 'course', $course['id'], [
        'title' => $title,
        'status' => $status
    ]);

    success_response(['course_id' => $course['id']], 'Course created successfully');

} catch (Exception $e) {
    log_message("Create course error: " . $e->getMessage(), 'ERROR');
    error_response('Failed to create course', 500);
}
?>

<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../shared/utils/sanitize.php';

Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

$course_id = Sanitize::string($_POST['course_id'] ?? '');

if (empty($course_id)) {
    error_response('Course ID is required');
}

try {
    $db = Database::getInstance();
    $student_id = Auth::getUserId();

    $db->setUserContext($student_id);

    $check_course = "SELECT id, title, status FROM courses WHERE id = :course_id";
    $course = $db->selectOne($check_course, [':course_id' => $course_id]);

    if (!$course) {
        error_response('Course not found');
    }

    if ($course['status'] !== 'published') {
        error_response('Course is not available for enrollment');
    }

    $check_enrollment = "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $existing = $db->selectOne($check_enrollment, [
        ':student_id' => $student_id,
        ':course_id' => $course_id
    ]);

    if ($existing) {
        error_response('Already enrolled in this course');
    }

    $sql = "INSERT INTO enrollments (student_id, course_id, progress, enrolled_at)
            VALUES (:student_id, :course_id, 0, NOW())
            RETURNING id";

    $result = $db->query($sql, [
        ':student_id' => $student_id,
        ':course_id' => $course_id
    ]);

    $enrollment = $result->fetch();

    if (!$enrollment) {
        error_response('Failed to enroll in course');
    }

    Auth::logAudit($student_id, 'enroll_course', 'course', $course_id, [
        'course_title' => $course['title']
    ]);

    success_response(['enrollment_id' => $enrollment['id']], 'Enrolled successfully');

} catch (Exception $e) {
    log_message("Enroll course error: " . $e->getMessage(), 'ERROR');
    error_response('Failed to enroll in course', 500);
}
?>

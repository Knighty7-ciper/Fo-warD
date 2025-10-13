<?php
require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update grade
    $user_id = $_POST['user_id'];
    $course_id = $_POST['course_id'];
    $grade_type = $_POST['grade_type'];
    $grade = $_POST['grade'];
    
    $column = $grade_type . '_grade';
    
    $sql = "INSERT INTO grades (user_id, course_id, $column, updated_at) 
            VALUES (:user_id, :course_id, :grade, NOW())
            ON CONFLICT (user_id, course_id) 
            DO UPDATE SET $column = :grade, updated_at = NOW()";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':course_id' => $course_id,
        ':grade' => $grade
    ]);
    
    echo json_encode(['success' => $result]);
    
} else {
    // Get grades for course
    $course_id = $_GET['course_id'];
    
    $sql = "SELECT u.id as user_id, u.first_name, u.last_name, u.email,
                   g.quiz_grade, g.assignment_grade, g.participation_grade
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN grades g ON g.user_id = u.id AND g.course_id = e.course_id
            WHERE e.course_id = :course_id
            ORDER BY u.last_name, u.first_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':course_id' => $course_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($grades);
}
?>

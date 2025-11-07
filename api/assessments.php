<?php
/**
 * Assessments API Endpoints
 * Forward LMS Quiz and Assignment Management
 */

require_once __DIR__ . '/../../config/api.php';

// Middleware to require teacher role
$teacherMiddleware = function($db, $auth) {
    $user = $auth->getCurrentUser();
    if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
        $router->error(403, 'Teacher or admin role required');
        return false;
    }
    return true;
};

// GET /api/assessments - List all assessments for teacher's courses
$router->add('GET', '/assessments', function($params, $db, $auth) {
    $user = $auth->getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $courseId = intval($_GET['course_id'] ?? 0);
    $type = $_GET['type'] ?? ''; // 'quiz' or 'assignment'
    
    $where = [];
    $params = [];
    
    // If teacher, only show their assessments
    if ($user['role'] === 'teacher') {
        $where[] = "q.teacher_id = ?";
        $params[] = $user['id'];
    }
    
    if ($courseId > 0) {
        $where[] = "q.course_id = ?";
        $params[] = $courseId;
    }
    
    if (!empty($type) && in_array($type, ['quiz', 'assignment'])) {
        $where[] = "q.type = ?";
        $params[] = $type;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                q.id, q.course_id, q.teacher_id, q.title, q.description, 
                q.type, q.max_points, q.due_date, q.status, q.created_at,
                c.title as course_title,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id) as attempt_count,
                (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = q.id) as submission_count
            FROM quizzes q
            JOIN courses c ON q.course_id = c.id
            JOIN users u ON q.teacher_id = u.id
            $whereClause
            ORDER BY q.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $assessments = $db->fetchAll($sql, $params);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM quizzes q
                 $whereClause";
    
    $countParams = array_slice($params, 0, -2);
    $total = $db->fetch($countSql, $countParams)['total'];
    
    $router->success([
        'assessments' => $assessments,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ], 'Assessments retrieved successfully');
});

// GET /api/assessments/{id} - Get single assessment details
$router->add('GET', '/assessments/{id}', function($params, $db, $auth) {
    $assessmentId = intval($params['id']);
    
    $sql = "SELECT 
                q.*,
                c.title as course_title,
                u.name as teacher_name
            FROM quizzes q
            JOIN courses c ON q.course_id = c.id
            JOIN users u ON q.teacher_id = u.id
            WHERE q.id = ?";
    
    $assessment = $db->fetch($sql, [$assessmentId]);
    
    if (!$assessment) {
        $router->error(404, 'Assessment not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'teacher' && $assessment['teacher_id'] != $user['id']) {
        $router->error(403, 'You can only view your own assessments');
        return;
    }
    
    // Get questions for quizzes
    if ($assessment['type'] === 'quiz') {
        $questions = $db->fetchAll(
            "SELECT 
                q.id, q.question_type, q.question_text, q.points, q.order_index, q.explanation,
                (SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'id', o.id,
                    'option_text', o.option_text,
                    'order_index', o.order_index
                )) FROM quiz_question_options o WHERE o.question_id = q.id ORDER BY o.order_index) as options
             FROM quiz_questions q 
             WHERE q.quiz_id = ? 
             ORDER BY q.order_index ASC",
            [$assessmentId]
        );
        
        $assessment['questions'] = $questions;
    }
    
    $router->success($assessment, 'Assessment details retrieved');
});

// POST /api/assessments - Create new assessment
$router->add('POST', '/assessments', function($params, $db, $auth) {
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['title', 'course_id'])) {
        return;
    }
    
    $user = $auth->getCurrentUser();
    $type = $data['type'] ?? 'quiz';
    
    // Check if course exists and user owns it (for teachers)
    $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$data['course_id']]);
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    if ($user['role'] === 'teacher' && $course['teacher_id'] != $user['id']) {
        $router->error(403, 'You can only create assessments for your own courses');
        return;
    }
    
    $sql = "INSERT INTO quizzes (
                course_id, teacher_id, title, description, instructions,
                time_limit, passing_score, max_attempts, shuffle_questions,
                show_correct_answers, show_results_immediately, type, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
    
    $params = [
        $data['course_id'],
        $user['id'],
        $data['title'],
        $data['description'] ?? '',
        $data['instructions'] ?? '',
        intval($data['time_limit'] ?? 0),
        floatval($data['passing_score'] ?? 70.00),
        intval($data['max_attempts'] ?? 1),
        isset($data['shuffle_questions']) ? (bool)$data['shuffle_questions'] : false,
        isset($data['show_correct_answers']) ? (bool)$data['show_correct_answers'] : true,
        isset($data['show_results_immediately']) ? (bool)$data['show_results_immediately'] : true,
        $type
    ];
    
    try {
        $db->execute($sql, $params);
        $assessmentId = $db->lastInsertId();
        
        $assessment = $db->fetch("SELECT * FROM quizzes WHERE id = ?", [$assessmentId]);
        $router->success($assessment, 'Assessment created successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to create assessment: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// POST /api/assessments/{id}/questions - Add question to quiz
$router->add('POST', '/assessments/{id}/questions', function($params, $db, $auth) {
    $assessmentId = intval($params['id']);
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['question_type', 'question_text'])) {
        return;
    }
    
    // Check if assessment exists and user owns it
    $assessment = $db->fetch("SELECT * FROM quizzes WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        $router->error(404, 'Assessment not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'teacher' && $assessment['teacher_id'] != $user['id']) {
        $router->error(403, 'You can only add questions to your own assessments');
        return;
    }
    
    // Get next order index
    $maxOrder = $db->fetch(
        "SELECT MAX(order_index) as max_order FROM quiz_questions WHERE quiz_id = ?",
        [$assessmentId]
    )['max_order'] ?? 0;
    
    $sql = "INSERT INTO quiz_questions (
                quiz_id, question_type, question_text, points, order_index, explanation, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $assessmentId,
        $data['question_type'],
        $data['question_text'],
        floatval($data['points'] ?? 1.00),
        $maxOrder + 1,
        $data['explanation'] ?? ''
    ];
    
    try {
        $db->execute($sql, $params);
        $questionId = $db->lastInsertId();
        
        // Add options for multiple choice questions
        if ($data['question_type'] === 'multiple_choice' && isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $index => $option) {
                $optionSql = "INSERT INTO quiz_question_options (
                                question_id, option_text, is_correct, order_index
                            ) VALUES (?, ?, ?, ?)";
                
                $optionParams = [
                    $questionId,
                    $option['text'],
                    isset($option['is_correct']) ? (bool)$option['is_correct'] : false,
                    $index + 1
                ];
                
                $db->execute($optionSql, $optionParams);
            }
        }
        
        $question = $db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        $router->success($question, 'Question added successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to add question: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// GET /api/assessments/{id}/submissions - Get assessment submissions
$router->add('GET', '/assessments/{id}/submissions', function($params, $db, $auth) {
    $assessmentId = intval($params['id']);
    
    // Check if assessment exists and user owns it
    $assessment = $db->fetch("SELECT * FROM quizzes WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        $router->error(404, 'Assessment not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'teacher' && $assessment['teacher_id'] != $user['id']) {
        $router->error(403, 'You can only view submissions for your own assessments');
        return;
    }
    
    if ($assessment['type'] === 'quiz') {
        // Get quiz attempts
        $submissions = $db->fetchAll(
            "SELECT 
                a.*,
                u.name as student_name,
                u.email as student_email
             FROM quiz_attempts a
             JOIN users u ON a.student_id = u.id
             WHERE a.quiz_id = ?
             ORDER BY a.submitted_at DESC",
            [$assessmentId]
        );
    } else {
        // Get assignment submissions
        $submissions = $db->fetchAll(
            "SELECT 
                s.*,
                u.name as student_name,
                u.email as student_email
             FROM assignment_submissions s
             JOIN users u ON s.student_id = u.id
             WHERE s.assignment_id = ?
             ORDER BY s.submitted_at DESC",
            [$assessmentId]
        );
    }
    
    $router->success($submissions, 'Submissions retrieved successfully');
}, [$teacherMiddleware]);

// PUT /api/assessments/{id} - Update assessment
$router->add('PUT', '/assessments/{id}', function($params, $db, $auth) {
    $assessmentId = intval($params['id']);
    $data = $router->getRequestData();
    
    // Check if assessment exists and user owns it
    $assessment = $db->fetch("SELECT * FROM quizzes WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        $router->error(404, 'Assessment not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'teacher' && $assessment['teacher_id'] != $user['id']) {
        $router->error(403, 'You can only edit your own assessments');
        return;
    }
    
    $updateFields = [];
    $updateParams = [];
    
    $allowedFields = ['title', 'description', 'instructions', 'time_limit', 'passing_score', 'max_attempts', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $updateParams[] = $data[$field];
        }
    }
    
    if (empty($updateFields)) {
        $router->error(400, 'No valid fields to update');
        return;
    }
    
    $updateParams[] = $assessmentId;
    
    $sql = "UPDATE quizzes SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    
    try {
        $db->execute($sql, $updateParams);
        $updatedAssessment = $db->fetch("SELECT * FROM quizzes WHERE id = ?", [$assessmentId]);
        $router->success($updatedAssessment, 'Assessment updated successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to update assessment: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// Handle the request
$router->handle();
?>
<?php
/**
 * Enrollments API Endpoints
 * Forward LMS Course Enrollment Management
 */

require_once __DIR__ . '/../../config/api.php';

// Student middleware
$studentMiddleware = function($db, $auth) {
    $user = $auth->getCurrentUser();
    if (!$user || $user['role'] !== 'student') {
        $router->error(403, 'Student role required');
        return false;
    }
    return true;
};

// GET /api/enrollments - Get student's enrollments
$router->add('GET', '/enrollments', function($params, $db, $auth) {
    $user = $auth->getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? ''; // 'active', 'completed', 'dropped'
    
    $where = ['e.student_id = ?'];
    $params = [$user['id']];
    
    if (!empty($status) && in_array($status, ['active', 'completed', 'dropped'])) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT 
                e.id, e.course_id, e.enrolled_at, e.completed_at, e.progress, e.status as enrollment_status,
                c.title, c.description, c.category, c.level, c.duration, c.rating, c.enrollment_count,
                c.price, c.currency,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count,
                (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = e.student_id AND q.course_id = c.id) as attempts_count
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             JOIN users u ON c.teacher_id = u.id
             WHERE $whereClause
             ORDER BY e.enrolled_at DESC
             LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $enrollments = $db->fetchAll($sql, $params);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM enrollments e
                 WHERE $whereClause";
    
    $countParams = array_slice($params, 0, -2);
    $total = $db->fetch($countSql, $countParams)['total'];
    
    $router->success([
        'enrollments' => $enrollments,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ], 'Enrollments retrieved successfully');
});

// POST /api/enrollments - Enroll in a course
$router->add('POST', '/enrollments', function($params, $db, $auth) {
    $user = $auth->getCurrentUser();
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['course_id'])) {
        return;
    }
    
    $courseId = intval($data['course_id']);
    
    // Check if course exists and is published
    $course = $db->fetch(
        "SELECT * FROM courses WHERE id = ? AND status = 'published'",
        [$courseId]
    );
    
    if (!$course) {
        $router->error(404, 'Course not found or not available for enrollment');
        return;
    }
    
    // Check if already enrolled
    $existingEnrollment = $db->fetch(
        "SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?",
        [$user['id'], $courseId]
    );
    
    if ($existingEnrollment) {
        $router->error(400, 'You are already enrolled in this course');
        return;
    }
    
    // For paid courses, you would implement payment logic here
    // For now, we'll treat all courses as free
    $paymentStatus = $course['price'] > 0 ? 'pending' : 'free';
    
    try {
        $db->beginTransaction();
        
        // Create enrollment
        $sql = "INSERT INTO enrollments (
                    student_id, course_id, enrolled_at, status, payment_status
                ) VALUES (?, ?, NOW(), 'active', ?)";
        
        $db->execute($sql, [$user['id'], $courseId, $paymentStatus]);
        $enrollmentId = $db->lastInsertId();
        
        // Update course enrollment count
        $db->execute(
            "UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = ?",
            [$courseId]
        );
        
        $db->commit();
        
        // Get enrollment details with course info
        $enrollment = $db->fetch(
            "SELECT 
                e.*,
                c.title, c.description, c.category, c.level, c.duration,
                u.name as teacher_name
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             JOIN users u ON c.teacher_id = u.id
             WHERE e.id = ?",
            [$enrollmentId]
        );
        
        $router->success($enrollment, 'Successfully enrolled in course');
    } catch (Exception $e) {
        $db->rollback();
        $router->error(500, 'Failed to enroll in course: ' . $e->getMessage());
    }
}, [$studentMiddleware]);

// GET /api/enrollments/{id} - Get specific enrollment
$router->add('GET', '/enrollments/{id}', function($params, $db, $auth) {
    $enrollmentId = intval($params['id']);
    $user = $auth->getCurrentUser();
    
    $sql = "SELECT 
                e.*,
                c.title, c.description, c.category, c.level, c.duration, c.rating, c.enrollment_count,
                c.price, c.currency,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count,
                (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.student_id = e.student_id AND lp.lesson_id IN 
                 (SELECT id FROM lessons WHERE course_id = c.id) AND lp.completed = 1) as completed_lessons
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             JOIN users u ON c.teacher_id = u.id
             WHERE e.id = ? AND e.student_id = ?";
    
    $enrollment = $db->fetch($sql, [$enrollmentId, $user['id']]);
    
    if (!$enrollment) {
        $router->error(404, 'Enrollment not found');
        return;
    }
    
    // Get lessons with progress
    $lessons = $db->fetchAll(
        "SELECT 
            l.*,
            COALESCE(lp.completed, 0) as is_completed,
            COALESCE(lp.completed_at, NULL) as completed_at,
            COALESCE(lp.time_spent, 0) as time_spent
         FROM lessons l
         LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = ?
         WHERE l.course_id = ?
         ORDER BY l.order_index ASC",
        [$user['id'], $enrollment['course_id']]
    );
    
    $enrollment['lessons'] = $lessons;
    
    $router->success($enrollment, 'Enrollment details retrieved');
});

// PUT /api/enrollments/{id}/progress - Update lesson progress
$router->add('PUT', '/enrollments/{id}/progress', function($params, $db, $auth) {
    $enrollmentId = intval($params['id']);
    $data = $router->getRequestData();
    $user = $auth->getCurrentUser();
    
    if (!$router->validateRequired($data, ['lesson_id', 'completed'])) {
        return;
    }
    
    $lessonId = intval($data['lesson_id']);
    $completed = (bool)$data['completed'];
    $timeSpent = intval($data['time_spent'] ?? 0);
    $lastPosition = intval($data['last_position'] ?? 0);
    
    // Verify enrollment exists and belongs to user
    $enrollment = $db->fetch(
        "SELECT * FROM enrollments WHERE id = ? AND student_id = ?",
        [$enrollmentId, $user['id']]
    );
    
    if (!$enrollment) {
        $router->error(404, 'Enrollment not found');
        return;
    }
    
    // Verify lesson belongs to the course
    $lesson = $db->fetch(
        "SELECT * FROM lessons WHERE id = ? AND course_id = ?",
        [$lessonId, $enrollment['course_id']]
    );
    
    if (!$lesson) {
        $router->error(404, 'Lesson not found in this course');
        return;
    }
    
    try {
        // Insert or update lesson progress
        $sql = "INSERT INTO lesson_progress (
                    student_id, lesson_id, completed, completed_at, time_spent, last_position
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    completed = VALUES(completed),
                    completed_at = VALUES(completed_at),
                    time_spent = time_spent + VALUES(time_spent),
                    last_position = VALUES(last_position)";
        
        $params = [
            $user['id'],
            $lessonId,
            $completed,
            $completed ? date('Y-m-d H:i:s') : null,
            $timeSpent,
            $lastPosition
        ];
        
        $db->execute($sql, $params);
        
        // Calculate overall course progress
        $totalLessons = $db->fetch(
            "SELECT COUNT(*) as total FROM lessons WHERE course_id = ?",
            [$enrollment['course_id']]
        )['total'];
        
        $completedLessons = $db->fetch(
            "SELECT COUNT(*) as completed 
             FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             WHERE lp.student_id = ? AND l.course_id = ? AND lp.completed = 1",
            [$user['id'], $enrollment['course_id']]
        )['completed'];
        
        $progressPercentage = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
        
        // Update enrollment progress
        $newStatus = $progressPercentage >= 100 ? 'completed' : 'active';
        $completedAt = $progressPercentage >= 100 ? date('Y-m-d H:i:s') : null;
        
        $db->execute(
            "UPDATE enrollments SET progress = ?, status = ?, completed_at = ? WHERE id = ?",
            [$progressPercentage, $newStatus, $completedAt, $enrollmentId]
        );
        
        // If course is completed, award certificate
        if ($progressPercentage >= 100 && $enrollment['status'] !== 'completed') {
            $certificateNumber = 'CERT-' . $user['id'] . '-' . $enrollment['course_id'] . '-' . time();
            
            $db->execute(
                "INSERT INTO certificates (student_id, course_id, certificate_number, issued_at) 
                 VALUES (?, ?, ?, NOW())",
                [$user['id'], $enrollment['course_id'], $certificateNumber]
            );
        }
        
        $router->success([
            'progress_percentage' => round($progressPercentage, 2),
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'status' => $newStatus
        ], 'Progress updated successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to update progress: ' . $e->getMessage());
    }
}, [$studentMiddleware]);

// DELETE /api/enrollments/{id} - Drop course
$router->add('DELETE', '/enrollments/{id}', function($params, $db, $auth) {
    $enrollmentId = intval($params['id']);
    $user = $auth->getCurrentUser();
    
    // Verify enrollment exists and belongs to user
    $enrollment = $db->fetch(
        "SELECT * FROM enrollments WHERE id = ? AND student_id = ?",
        [$enrollmentId, $user['id']]
    );
    
    if (!$enrollment) {
        $router->error(404, 'Enrollment not found');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Delete enrollment
        $db->execute("DELETE FROM enrollments WHERE id = ?", [$enrollmentId]);
        
        // Update course enrollment count
        $db->execute(
            "UPDATE courses SET enrollment_count = MAX(0, enrollment_count - 1) WHERE id = ?",
            [$enrollment['course_id']]
        );
        
        // Clean up lesson progress
        $db->execute(
            "DELETE FROM lesson_progress WHERE student_id = ? AND lesson_id IN 
             (SELECT id FROM lessons WHERE course_id = ?)",
            [$user['id'], $enrollment['course_id']]
        );
        
        $db->commit();
        
        $router->success(null, 'Successfully dropped course');
    } catch (Exception $e) {
        $db->rollback();
        $router->error(500, 'Failed to drop course: ' . $e->getMessage());
    }
}, [$studentMiddleware]);

// Handle the request
$router->handle();
?>
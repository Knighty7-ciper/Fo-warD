<?php
/**
 * Courses API Endpoints
 * Forward LMS Course Management
 */

require_once __DIR__ . '/../../config/api.php';

// Middleware to require teacher role for course creation/modification
$teacherMiddleware = function($db, $auth) {
    $user = $auth->getCurrentUser();
    if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
        $router->error(403, 'Teacher or admin role required');
        return false;
    }
    return true;
};

// GET /api/courses - List all published courses
$router->add('GET', '/courses', function($params, $db, $auth) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $level = $_GET['level'] ?? '';
    
    $where = ['c.status = ?'];
    $params = ['published'];
    
    if (!empty($search)) {
        $where[] = '(c.title LIKE ? OR c.description LIKE ?)';
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $where[] = 'c.category = ?';
        $params[] = $category;
    }
    
    if (!empty($level)) {
        $where[] = 'c.level = ?';
        $params[] = $level;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT 
                c.id, c.title, c.description, c.category, c.level, 
                c.price, c.duration, c.rating, c.enrollment_count,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            WHERE $whereClause
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $courses = $db->fetchAll($sql, $params);
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total 
                 FROM courses c 
                 WHERE $whereClause";
    
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $total = $db->fetch($countSql, $countParams)['total'];
    
    $router->success([
        'courses' => $courses,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ], 'Courses retrieved successfully');
});

// GET /api/courses/{id} - Get single course details
$router->add('GET', '/courses/{id}', function($params, $db, $auth) {
    $courseId = intval($params['id']);
    
    $sql = "SELECT 
                c.*,
                u.name as teacher_name,
                u.email as teacher_email,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') as current_enrollment
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ? AND c.status IN ('published', 'draft')";
    
    $course = $db->fetch($sql, [$courseId]);
    
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    // Get course lessons
    $lessons = $db->fetchAll(
        "SELECT id, title, description, duration, order_index, is_free 
         FROM lessons 
         WHERE course_id = ? 
         ORDER BY order_index ASC",
        [$courseId]
    );
    
    $course['lessons'] = $lessons;
    
    $router->success($course, 'Course details retrieved');
});

// POST /api/courses - Create new course (teachers only)
$router->add('POST', '/courses', function($params, $db, $auth) {
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['title'])) {
        return;
    }
    
    $user = $auth->getCurrentUser();
    
    $sql = "INSERT INTO courses (
                teacher_id, title, description, category, level, 
                price, currency, duration, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
    
    $params = [
        $user['id'],
        $data['title'],
        $data['description'] ?? '',
        $data['category'] ?? '',
        $data['level'] ?? 'beginner',
        floatval($data['price'] ?? 0),
        $data['currency'] ?? 'KES',
        intval($data['duration'] ?? 0)
    ];
    
    try {
        $db->execute($sql, $params);
        $courseId = $db->lastInsertId();
        
        $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
        $router->success($course, 'Course created successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to create course: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// PUT /api/courses/{id} - Update course
$router->add('PUT', '/courses/{id}', function($params, $db, $auth) {
    $courseId = intval($params['id']);
    $data = $router->getRequestData();
    
    // Check if course exists and user owns it
    $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($course['teacher_id'] != $user['id'] && $user['role'] !== 'admin') {
        $router->error(403, 'You can only edit your own courses');
        return;
    }
    
    $updateFields = [];
    $updateParams = [];
    
    $allowedFields = ['title', 'description', 'category', 'level', 'price', 'currency', 'duration', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            
            if (in_array($field, ['price', 'duration'])) {
                $updateParams[] = floatval($data[$field]);
            } else {
                $updateParams[] = $data[$field];
            }
        }
    }
    
    if (empty($updateFields)) {
        $router->error(400, 'No valid fields to update');
        return;
    }
    
    $updateParams[] = $courseId;
    
    $sql = "UPDATE courses SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    
    try {
        $db->execute($sql, $updateParams);
        $updatedCourse = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
        $router->success($updatedCourse, 'Course updated successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to update course: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// DELETE /api/courses/{id} - Delete course
$router->add('DELETE', '/courses/{id}', function($params, $db, $auth) {
    $courseId = intval($params['id']);
    
    $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($course['teacher_id'] != $user['id'] && $user['role'] !== 'admin') {
        $router->error(403, 'You can only delete your own courses');
        return;
    }
    
    try {
        $db->execute("DELETE FROM courses WHERE id = ?", [$courseId]);
        $router->success(null, 'Course deleted successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to delete course: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// GET /api/courses/{id}/content - Get course content/lessons
$router->add('GET', '/courses/{id}/content', function($params, $db, $auth) {
    $courseId = intval($params['id']);
    
    // Check if course exists
    $course = $db->fetch("SELECT id, status FROM courses WHERE id = ?", [$courseId]);
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    $lessons = $db->fetchAll(
        "SELECT id, title, description, content, video_url, duration, order_index, is_free 
         FROM lessons 
         WHERE course_id = ? 
         ORDER BY order_index ASC",
        [$courseId]
    );
    
    $router->success($lessons, 'Course content retrieved');
});

// POST /api/courses/{id}/content - Add lesson to course
$router->add('POST', '/courses/{id}/content', function($params, $db, $auth) {
    $courseId = intval($params['id']);
    $data = $router->getRequestData();
    
    if (!$router->validateRequired($data, ['title'])) {
        return;
    }
    
    // Check if course exists and user owns it
    $course = $db->fetch("SELECT * FROM courses WHERE id = ?", [$courseId]);
    if (!$course) {
        $router->error(404, 'Course not found');
        return;
    }
    
    $user = $auth->getCurrentUser();
    if ($course['teacher_id'] != $user['id'] && $user['role'] !== 'admin') {
        $router->error(403, 'You can only add content to your own courses');
        return;
    }
    
    // Get next order index
    $maxOrder = $db->fetch(
        "SELECT MAX(order_index) as max_order FROM lessons WHERE course_id = ?",
        [$courseId]
    )['max_order'] ?? 0;
    
    $sql = "INSERT INTO lessons (
                course_id, title, description, content, video_url, 
                duration, order_index, is_free, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $courseId,
        $data['title'],
        $data['description'] ?? '',
        $data['content'] ?? '',
        $data['video_url'] ?? '',
        intval($data['duration'] ?? 0),
        $maxOrder + 1,
        isset($data['is_free']) ? (bool)$data['is_free'] : false
    ];
    
    try {
        $db->execute($sql, $params);
        $lessonId = $db->lastInsertId();
        
        $lesson = $db->fetch("SELECT * FROM lessons WHERE id = ?", [$lessonId]);
        $router->success($lesson, 'Lesson added successfully');
    } catch (Exception $e) {
        $router->error(500, 'Failed to add lesson: ' . $e->getMessage());
    }
}, [$teacherMiddleware]);

// Handle the request
$router->handle();
?>
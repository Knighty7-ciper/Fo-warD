<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth();

// Only admins and teachers can access reports
if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? 'overview';

try {
    switch($action) {
        case 'overview':
            getOverviewStats($conn, $user);
            break;
        case 'enrollment':
            getEnrollmentStats($conn, $user);
            break;
        case 'course-performance':
            getCoursePerformance($conn, $user);
            break;
        case 'student-progress':
            getStudentProgress($conn, $user);
            break;
        case 'revenue':
            getRevenueStats($conn, $user);
            break;
        case 'activity':
            getActivityStats($conn, $user);
            break;
        case 'export':
            exportReport($conn, $user);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getOverviewStats($conn, $user) {
    $stats = [];
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total courses
    $stmt = $conn->query("SELECT COUNT(*) as total FROM courses WHERE status = 'published'");
    $stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total enrollments
    $stmt = $conn->query("SELECT COUNT(*) as total FROM enrollments");
    $stats['total_enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active students (logged in last 30 days)
    $stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM user_activity_log 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Completed courses
    $stmt = $conn->query("SELECT COUNT(*) as total FROM enrollments WHERE status = 'completed'");
    $stats['completed_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Average completion rate
    $stmt = $conn->query("SELECT AVG(progress) as avg_progress FROM enrollments");
    $stats['avg_completion_rate'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_progress'] ?? 0, 2);
    
    // Total revenue
    $stmt = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // New users this month
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users 
                          WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $stats['new_users_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function getEnrollmentStats($conn, $user) {
    $period = $_GET['period'] ?? '30days';
    
    $dateFilter = match($period) {
        '7days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        '90days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
        '1year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    // Enrollments over time
    $stmt = $conn->query("
        SELECT DATE(enrolled_at) as date, COUNT(*) as count
        FROM enrollments
        WHERE enrolled_at >= $dateFilter
        GROUP BY DATE(enrolled_at)
        ORDER BY date ASC
    ");
    $enrollmentTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top courses by enrollment
    $stmt = $conn->query("
        SELECT c.id, c.title, COUNT(e.id) as enrollment_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        WHERE c.status = 'published'
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 10
    ");
    $topCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrollment by status
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count
        FROM enrollments
        GROUP BY status
    ");
    $enrollmentByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'enrollment_trend' => $enrollmentTrend,
        'top_courses' => $topCourses,
        'enrollment_by_status' => $enrollmentByStatus
    ]);
}

function getCoursePerformance($conn, $user) {
    $courseId = $_GET['course_id'] ?? null;
    
    if ($courseId) {
        // Specific course performance
        $stmt = $conn->prepare("
            SELECT 
                c.id,
                c.title,
                COUNT(DISTINCT e.user_id) as total_students,
                AVG(e.progress) as avg_progress,
                COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_count,
                AVG(CASE WHEN s.grade IS NOT NULL THEN s.grade END) as avg_grade
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN submissions s ON e.user_id = s.student_id AND s.course_id = c.id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$courseId]);
        $performance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Student list with progress
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                e.progress,
                e.status,
                e.enrolled_at,
                AVG(s.grade) as avg_grade
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN submissions s ON e.user_id = s.student_id AND e.course_id = s.course_id
            WHERE e.course_id = ?
            GROUP BY u.id
            ORDER BY e.progress DESC
        ");
        $stmt->execute([$courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'performance' => $performance,
            'students' => $students
        ]);
    } else {
        // All courses performance
        $stmt = $conn->query("
            SELECT 
                c.id,
                c.title,
                c.instructor_id,
                u.name as instructor_name,
                COUNT(DISTINCT e.user_id) as total_students,
                AVG(e.progress) as avg_progress,
                COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_count,
                (COUNT(CASE WHEN e.status = 'completed' THEN 1 END) / COUNT(DISTINCT e.user_id) * 100) as completion_rate
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN users u ON c.instructor_id = u.id
            WHERE c.status = 'published'
            GROUP BY c.id
            ORDER BY total_students DESC
        ");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'courses' => $courses]);
    }
}

function getStudentProgress($conn, $user) {
    $studentId = $_GET['student_id'] ?? null;
    
    if ($studentId) {
        // Specific student progress
        $stmt = $conn->prepare("
            SELECT 
                c.id,
                c.title,
                e.progress,
                e.status,
                e.enrolled_at,
                e.completed_at,
                AVG(s.grade) as avg_grade,
                COUNT(DISTINCT a.id) as assignments_completed,
                COUNT(DISTINCT qa.id) as quizzes_completed
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN submissions s ON e.user_id = s.student_id AND e.course_id = s.course_id
            LEFT JOIN assignments a ON c.id = a.course_id
            LEFT JOIN quiz_attempts qa ON e.user_id = qa.user_id
            WHERE e.user_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$studentId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Activity timeline
        $stmt = $conn->prepare("
            SELECT activity_type, activity_description, created_at
            FROM user_activity_log
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$studentId]);
        $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'courses' => $courses,
            'activity' => $activity
        ]);
    } else {
        // All students overview
        $stmt = $conn->query("
            SELECT 
                u.id,
                u.name,
                u.email,
                COUNT(DISTINCT e.course_id) as enrolled_courses,
                AVG(e.progress) as avg_progress,
                COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_courses,
                MAX(ual.created_at) as last_activity
            FROM users u
            LEFT JOIN enrollments e ON u.id = e.user_id
            LEFT JOIN user_activity_log ual ON u.id = ual.user_id
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY last_activity DESC
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'students' => $students]);
    }
}

function getRevenueStats($conn, $user) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $period = $_GET['period'] ?? '30days';
    
    $dateFilter = match($period) {
        '7days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        '90days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
        '1year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    // Revenue over time
    $stmt = $conn->query("
        SELECT DATE(created_at) as date, SUM(amount) as revenue
        FROM payments
        WHERE status = 'completed' AND created_at >= $dateFilter
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $revenueTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue by course
    $stmt = $conn->query("
        SELECT 
            c.id,
            c.title,
            SUM(p.amount) as total_revenue,
            COUNT(p.id) as payment_count
        FROM payments p
        JOIN courses c ON p.course_id = c.id
        WHERE p.status = 'completed'
        GROUP BY c.id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $revenueByCourse = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue by payment method
    $stmt = $conn->query("
        SELECT payment_method, SUM(amount) as total, COUNT(*) as count
        FROM payments
        WHERE status = 'completed'
        GROUP BY payment_method
    ");
    $revenueByMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total stats
    $stmt = $conn->query("
        SELECT 
            SUM(amount) as total_revenue,
            AVG(amount) as avg_transaction,
            COUNT(*) as total_transactions
        FROM payments
        WHERE status = 'completed' AND created_at >= $dateFilter
    ");
    $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'revenue_trend' => $revenueTrend,
        'revenue_by_course' => $revenueByCourse,
        'revenue_by_method' => $revenueByMethod,
        'total_stats' => $totalStats
    ]);
}

function getActivityStats($conn, $user) {
    $period = $_GET['period'] ?? '7days';
    
    $dateFilter = match($period) {
        '24hours' => 'DATE_SUB(NOW(), INTERVAL 24 HOUR)',
        '7days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default => 'DATE_SUB(NOW(), INTERVAL 7 DAY)'
    };
    
    // Activity by type
    $stmt = $conn->query("
        SELECT activity_type, COUNT(*) as count
        FROM user_activity_log
        WHERE created_at >= $dateFilter
        GROUP BY activity_type
        ORDER BY count DESC
    ");
    $activityByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Activity over time
    $stmt = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM user_activity_log
        WHERE created_at >= $dateFilter
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $activityTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most active users
    $stmt = $conn->query("
        SELECT 
            u.id,
            u.name,
            u.role,
            COUNT(ual.id) as activity_count
        FROM user_activity_log ual
        JOIN users u ON ual.user_id = u.id
        WHERE ual.created_at >= $dateFilter
        GROUP BY u.id
        ORDER BY activity_count DESC
        LIMIT 10
    ");
    $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'activity_by_type' => $activityByType,
        'activity_trend' => $activityTrend,
        'active_users' => $activeUsers
    ]);
}

function exportReport($conn, $user) {
    $type = $_GET['type'] ?? 'overview';
    
    // Generate CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch($type) {
        case 'enrollments':
            fputcsv($output, ['Course', 'Student', 'Enrolled Date', 'Progress', 'Status']);
            $stmt = $conn->query("
                SELECT c.title, u.name, e.enrolled_at, e.progress, e.status
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                JOIN users u ON e.user_id = u.id
                ORDER BY e.enrolled_at DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
            break;
            
        case 'students':
            fputcsv($output, ['Name', 'Email', 'Enrolled Courses', 'Avg Progress', 'Completed Courses']);
            $stmt = $conn->query("
                SELECT 
                    u.name,
                    u.email,
                    COUNT(DISTINCT e.course_id) as enrolled_courses,
                    AVG(e.progress) as avg_progress,
                    COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_courses
                FROM users u
                LEFT JOIN enrollments e ON u.id = e.user_id
                WHERE u.role = 'student'
                GROUP BY u.id
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
            break;
    }
    
    fclose($output);
    exit;
}

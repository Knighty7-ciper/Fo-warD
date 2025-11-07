<?php
/**
 * Admin Panel API
 * Phase 6: Admin Interface Development
 * Features: User management, system administration, analytics, content oversight
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $user = requireAuth();
    
    // Verify admin role
    if ($user['role'] !== 'admin') {
        throw new Exception('Access denied. Admin privileges required.');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    switch($method) {
        case 'GET':
            handleGetRequest($pdo, $user, $action, $_GET);
            break;
        case 'POST':
            handlePostRequest($pdo, $user, $action, $input);
            break;
        case 'PUT':
            handlePutRequest($pdo, $user, $action, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $user, $action, $_GET);
            break;
        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

function handleGetRequest($pdo, $user, $action, $params) {
    switch($action) {
        // User Management
        case 'users':
            getUsers($pdo, $params);
            break;
        case 'user_details':
            getUserDetails($pdo, $params);
            break;
        case 'user_activity':
            getUserActivity($pdo, $params);
            break;
        case 'user_analytics':
            getUserAnalytics($pdo, $params);
            break;
        
        // System Administration
        case 'system_stats':
            getSystemStats($pdo, $params);
            break;
        case 'system_health':
            getSystemHealth($pdo, $params);
            break;
        case 'system_logs':
            getSystemLogs($pdo, $params);
            break;
        case 'performance_metrics':
            getPerformanceMetrics($pdo, $params);
            break;
        
        // Content Management
        case 'content_audit':
            getContentAudit($pdo, $params);
            break;
        case 'course_analytics':
            getCourseAnalytics($pdo, $params);
            break;
        case 'file_analytics':
            getFileAnalytics($pdo, $params);
            break;
        case 'communication_analytics':
            getCommunicationAnalytics($pdo, $params);
            break;
        
        // Platform Analytics
        case 'platform_overview':
            getPlatformOverview($pdo, $params);
            break;
        case 'engagement_metrics':
            getEngagementMetrics($pdo, $params);
            break;
        case 'usage_reports':
            getUsageReports($pdo, $params);
            break;
        case 'revenue_analytics':
            getRevenueAnalytics($pdo, $params);
            break;
        
        // Support & Moderation
        case 'support_tickets':
            getSupportTickets($pdo, $params);
            break;
        case 'moderation_queue':
            getModerationQueue($pdo, $params);
            break;
        
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($pdo, $user, $action, $input) {
    switch($action) {
        // User Management
        case 'create_user':
            createUser($pdo, $user, $input);
            break;
        case 'bulk_import_users':
            bulkImportUsers($pdo, $user, $input);
            break;
        case 'update_user_role':
            updateUserRole($pdo, $user, $input);
            break;
        case 'suspend_user':
            suspendUser($pdo, $user, $input);
            break;
        
        // System Administration
        case 'backup_system':
            backupSystem($pdo, $user, $input);
            break;
        case 'maintenance_mode':
            toggleMaintenanceMode($pdo, $user, $input);
            break;
        case 'update_settings':
            updateSystemSettings($pdo, $user, $input);
            break;
        
        // Content Management
        case 'approve_content':
            approveContent($pdo, $user, $input);
            break;
        case 'flag_content':
            flagContent($pdo, $user, $input);
            break;
        case 'bulk_content_action':
            bulkContentAction($pdo, $user, $input);
            break;
        
        // Support
        case 'create_support_ticket':
            createSupportTicket($pdo, $user, $input);
            break;
        case 'respond_to_ticket':
            respondToTicket($pdo, $user, $input);
            break;
        
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($pdo, $user, $action, $input) {
    switch($action) {
        case 'update_user':
            updateUser($pdo, $user, $input);
            break;
        case 'update_system_config':
            updateSystemConfig($pdo, $user, $input);
            break;
        case 'update_user_profile':
            updateUserProfile($pdo, $user, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($pdo, $user, $action, $params) {
    switch($action) {
        case 'delete_user':
            deleteUser($pdo, $user, $params);
            break;
        case 'remove_user_from_course':
            removeUserFromCourse($pdo, $user, $params);
            break;
        case 'delete_content':
            deleteContent($pdo, $user, $params);
            break;
        case 'clear_system_logs':
            clearSystemLogs($pdo, $user, $params);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

// ====================================================================
// USER MANAGEMENT FUNCTIONS
// ====================================================================

function getUsers($pdo, $params) {
    $page = (int)($params['page'] ?? 1);
    $limit = (int)($params['limit'] ?? 50);
    $role = $params['role'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            u.id, u.name, u.email, u.role, u.status, u.created_at, u.last_login_at,
            u.avatar, u.phone, u.address,
            COUNT(DISTINCT e.course_id) as enrolled_courses,
            COUNT(DISTINCT c.id) as created_courses,
            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as messages_sent,
            (SELECT COUNT(*) FROM live_classes WHERE instructor_id = u.id) as classes_taught
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN courses c ON u.id = c.teacher_id
    ";
    
    $whereConditions = [];
    $queryParams = [];
    
    if (!empty($role)) {
        $whereConditions[] = "u.role = ?";
        $queryParams[] = $role;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "u.status = ?";
        $queryParams[] = $status;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = str_replace("SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, u.last_login_at, u.avatar, u.phone, u.address, COUNT(DISTINCT e.course_id) as enrolled_courses, COUNT(DISTINCT c.id) as created_courses, (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as messages_sent, (SELECT COUNT(*) FROM live_classes WHERE instructor_id = u.id) as classes_taught", "SELECT COUNT(DISTINCT u.id)", $sql);
    $countSql = preg_replace('/GROUP BY u\.id ORDER BY u\.created_at DESC LIMIT \? OFFSET \?$/', '', $countSql);
    
    $stmt = $pdo->prepare($countSql);
    $countParams = array_slice($queryParams, 0, -2); // Remove limit and offset
    $stmt->execute($countParams);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['COUNT(DISTINCT u.id)'];
    
    echo json_encode([
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getUserDetails($pdo, $params) {
    $userId = $params['user_id'];
    
    if (!$userId) {
        throw new Exception('User ID required');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            COUNT(DISTINCT e.course_id) as total_enrollments,
            COUNT(DISTINCT c.id) as total_courses_created,
            COUNT(DISTINCT a.id) as total_assessments_taken,
            COUNT(DISTINCT s.id) as total_submissions,
            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as total_messages,
            (SELECT COUNT(*) FROM live_classes WHERE instructor_id = u.id) as total_classes_taught,
            (SELECT COUNT(*) FROM live_classes lc JOIN enrollments e ON lc.course_id = e.course_id WHERE e.student_id = u.id) as total_classes_attended
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN courses c ON u.id = c.teacher_id
        LEFT JOIN assessments a ON u.id = a.student_id
        LEFT JOIN submissions s ON u.id = s.student_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    
    $stmt->execute([$userId]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userDetails) {
        throw new Exception('User not found');
    }
    
    echo json_encode(['user_details' => $userDetails]);
}

function getUserActivity($pdo, $params) {
    $userId = $params['user_id'];
    $days = (int)($params['days'] ?? 30);
    
    if (!$userId) {
        throw new Exception('User ID required');
    }
    
    $sql = "
        SELECT 
            'login' as activity_type,
            u.last_login_at as timestamp,
            'User login' as description
        FROM users u WHERE u.id = ?
        UNION ALL
        SELECT 
            'message' as activity_type,
            m.created_at as timestamp,
            CONCAT('Sent message: ', LEFT(m.content, 50), '...') as description
        FROM messages m WHERE m.sender_id = ?
        UNION ALL
        SELECT 
            'post' as activity_type,
            p.created_at as timestamp,
            CONCAT('Created forum post: ', LEFT(p.content, 50), '...') as description
        FROM posts p WHERE p.author_id = ?
        UNION ALL
        SELECT 
            'class_joined' as activity_type,
            cp.joined_at as timestamp,
            'Joined live class' as description
        FROM class_participants cp 
        JOIN live_classes lc ON cp.class_id = lc.id
        WHERE cp.user_id = ? AND cp.joined_at IS NOT NULL
        ORDER BY timestamp DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['activities' => $activities]);
}

function getUserAnalytics($pdo, $params) {
    $userId = $params['user_id'] ?? null;
    $days = (int)($params['days'] ?? 30);
    
    if ($userId) {
        // Individual user analytics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT DATE(last_login_at)) as active_days,
                COUNT(DISTINCT e.course_id) as courses_enrolled,
                COUNT(DISTINCT c.id) as courses_created,
                (SELECT COUNT(*) FROM messages WHERE sender_id = ?) as messages_sent,
                (SELECT COUNT(*) FROM live_classes WHERE instructor_id = ?) as classes_taught,
                (SELECT COUNT(*) FROM live_classes lc JOIN enrollments e ON lc.course_id = e.course_id WHERE e.student_id = ?) as classes_attended
            FROM users u
            LEFT JOIN enrollments e ON u.id = e.student_id
            LEFT JOIN courses c ON u.id = c.teacher_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Platform-wide user analytics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
                COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teacher_count,
                COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
                COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_last_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_last_month,
                AVG(DATEDIFF(NOW(), last_login_at)) as avg_days_since_login
            FROM users
        ");
        $stmt->execute();
        $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['analytics' => $analytics]);
}

// ====================================================================
// SYSTEM ADMINISTRATION FUNCTIONS
// ====================================================================

function getSystemStats($pdo, $params) {
    $sql = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments,
            (SELECT COUNT(*) FROM live_classes) as total_classes,
            (SELECT COUNT(*) FROM files) as total_files,
            (SELECT COUNT(*) FROM conversations) as total_conversations,
            (SELECT COUNT(*) FROM messages) as total_messages,
            (SELECT COUNT(*) FROM forums) as total_forums,
            (SELECT COUNT(*) FROM topics) as total_topics,
            (SELECT COUNT(*) FROM posts) as total_posts,
            (SELECT SUM(file_size) FROM files) as total_storage_used,
            (SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_last_24h,
            (SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_last_week,
            (SELECT COUNT(*) FROM courses WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_courses_last_month
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Convert storage from bytes to MB
    $stats['total_storage_used_mb'] = round($stats['total_storage_used'] / (1024 * 1024), 2);
    unset($stats['total_storage_used']);
    
    echo json_encode(['system_stats' => $stats]);
}

function getSystemHealth($pdo, $params) {
    // Database health check
    $dbHealth = [
        'status' => 'healthy',
        'connections' => 0,
        'slow_queries' => 0,
        'disk_usage' => 0
    ];
    
    try {
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbHealth['connections'] = (int)$result['Value'];
    } catch (Exception $e) {
        $dbHealth['status'] = 'error';
    }
    
    // Check disk usage
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $diskUsage = (($diskTotal - $diskFree) / $diskTotal) * 100;
    $dbHealth['disk_usage'] = round($diskUsage, 2);
    
    // System load (Linux)
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $dbHealth['system_load'] = [
            '1min' => $load[0],
            '5min' => $load[1],
            '15min' => $load[2]
        ];
    }
    
    // Memory usage
    $dbHealth['memory'] = [
        'used' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
    
    echo json_encode(['system_health' => $dbHealth]);
}

function getSystemLogs($pdo, $params) {
    $page = (int)($params['page'] ?? 1);
    $limit = (int)($params['limit'] ?? 50);
    $level = $params['level'] ?? '';
    $module = $params['module'] ?? '';
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT * FROM system_logs";
    $whereConditions = [];
    $queryParams = [];
    
    if (!empty($level)) {
        $whereConditions[] = "log_level = ?";
        $queryParams[] = $level;
    }
    
    if (!empty($module)) {
        $whereConditions[] = "module = ?";
        $queryParams[] = $module;
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['logs' => $logs]);
}

function getPerformanceMetrics($pdo, $params) {
    $hours = (int)($params['hours'] ?? 24);
    
    $sql = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
            COUNT(*) as request_count,
            AVG(response_time) as avg_response_time,
            MAX(response_time) as max_response_time
        FROM system_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        AND module = 'api'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
        ORDER BY hour DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hours]);
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['performance_metrics' => $metrics]);
}

// ====================================================================
// CONTENT MANAGEMENT FUNCTIONS
// ====================================================================

function getContentAudit($pdo, $params) {
    $page = (int)($params['page'] ?? 1);
    $limit = (int)($params['limit'] ?? 50);
    $status = $params['status'] ?? '';
    $type = $params['type'] ?? '';
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            c.id, c.title, c.status, c.created_at, c.updated_at,
            u.name as creator_name, u.role as creator_role,
            COUNT(DISTINCT e.student_id) as enrollment_count,
            COUNT(DISTINCT f.id) as file_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN files f ON c.id = f.course_id
    ";
    
    $whereConditions = [];
    $queryParams = [];
    
    if (!empty($status)) {
        $whereConditions[] = "c.status = ?";
        $queryParams[] = $status;
    }
    
    if (!empty($type)) {
        $whereConditions[] = "c.category = ?";
        $queryParams[] = $type;
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['content_audit' => $content]);
}

function getCourseAnalytics($pdo, $params) {
    $courseId = $params['course_id'] ?? null;
    $days = (int)($params['days'] ?? 30);
    
    if ($courseId) {
        // Individual course analytics
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                u.name as instructor_name,
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT a.id) as total_assessments,
                COUNT(DISTINCT s.id) as total_submissions,
                COUNT(DISTINCT lc.id) as total_live_classes,
                (SELECT AVG(grade) FROM grades g JOIN assessments a ON g.assessment_id = a.id WHERE a.course_id = c.id) as avg_grade,
                (SELECT COUNT(*) FROM course_views WHERE course_id = c.id AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) as views_last_days
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN assessments a ON c.id = a.course_id
            LEFT JOIN submissions s ON a.id = s.assessment_id
            LEFT JOIN live_classes lc ON c.id = lc.course_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$days, $courseId]);
        $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Platform-wide course analytics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_courses,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_courses,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_courses,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_last_month,
                AVG(enrollment_count) as avg_enrollments,
                (SELECT COUNT(DISTINCT course_id) FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as enrollments_last_month
            FROM (
                SELECT c.*, COUNT(DISTINCT e.student_id) as enrollment_count
                FROM courses c
                LEFT JOIN enrollments e ON c.id = e.course_id
                GROUP BY c.id
            ) t
        ");
        $stmt->execute();
        $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['course_analytics' => $analytics]);
}

function getFileAnalytics($pdo, $params) {
    $days = (int)($params['days'] ?? 30);
    
    $sql = "
        SELECT 
            f.mime_type,
            COUNT(*) as file_count,
            SUM(f.file_size) as total_size,
            AVG(f.file_size) as avg_size,
            SUM(f.download_count) as total_downloads
        FROM files f
        WHERE f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY f.mime_type
        ORDER BY file_count DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$days]);
    $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert sizes to MB
    foreach ($analytics as &$item) {
        $item['total_size_mb'] = round($item['total_size'] / (1024 * 1024), 2);
        $item['avg_size_mb'] = round($item['avg_size'] / (1024 * 1024), 2);
    }
    
    echo json_encode(['file_analytics' => $analytics]);
}

function getCommunicationAnalytics($pdo, $params) {
    $days = (int)($params['days'] ?? 30);
    
    $sql = "
        SELECT 
            DATE(m.created_at) as date,
            COUNT(DISTINCT m.conversation_id) as active_conversations,
            COUNT(*) as total_messages,
            COUNT(DISTINCT m.sender_id) as active_users
        FROM messages m
        WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(m.created_at)
        ORDER BY date DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$days]);
    $messaging = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Forum analytics
    $sql2 = "
        SELECT 
            DATE(p.created_at) as date,
            COUNT(DISTINCT p.topic_id) as active_topics,
            COUNT(*) as total_posts,
            COUNT(DISTINCT p.author_id) as active_users
        FROM posts p
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(p.created_at)
        ORDER BY date DESC
    ";
    
    $stmt = $pdo->prepare($sql2);
    $stmt->execute([$days]);
    $forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'messaging_analytics' => $messaging,
        'forum_analytics' => $forums
    ]);
}

// ====================================================================
// PLATFORM ANALYTICS FUNCTIONS
// ====================================================================

function getPlatformOverview($pdo, $params) {
    $days = (int)($params['days'] ?? 7);
    
    $sql = "
        SELECT 
            'users' as metric_type,
            DATE(created_at) as date,
            COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        
        UNION ALL
        
        SELECT 
            'courses' as metric_type,
            DATE(created_at) as date,
            COUNT(*) as count
        FROM courses
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        
        UNION ALL
        
        SELECT 
            'enrollments' as metric_type,
            DATE(enrolled_at) as date,
            COUNT(*) as count
        FROM enrollments
        WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(enrolled_at)
        
        ORDER BY date DESC, metric_type
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$days, $days, $days]);
    $overview = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['platform_overview' => $overview]);
}

function getEngagementMetrics($pdo, $params) {
    $days = (int)($params['days'] ?? 30);
    
    $sql = "
        SELECT 
            DATE(last_login_at) as date,
            COUNT(*) as daily_active_users
        FROM users
        WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(last_login_at)
        ORDER BY date DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$days]);
    $engagement = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate engagement rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_last_week,
            COUNT(*) as total_users
        FROM users
    ");
    $stmt->execute();
    $engagement_rate = $stmt->fetch(PDO::FETCH_ASSOC);
    $engagement['engagement_rate'] = round(($engagement_rate['active_last_week'] / $engagement_rate['total_users']) * 100, 2);
    
    echo json_encode(['engagement_metrics' => $engagement]);
}

function getUsageReports($pdo, $params) {
    $type = $params['type'] ?? 'daily';
    $days = (int)($params['days'] ?? 30);
    
    $dateFormat = $type === 'hourly' ? '%Y-%m-%d %H:00:00' : ($type === 'weekly' ? '%Y-%u' : '%Y-%m-%d');
    $interval = $type === 'hourly' ? 'HOUR' : ($type === 'weekly' ? 'WEEK' : 'DAY');
    
    $sql = "
        SELECT 
            DATE_FORMAT(created_at, ?) as period,
            COUNT(*) as total_actions,
            COUNT(DISTINCT user_id) as unique_users
        FROM system_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? $interval)
        GROUP BY DATE_FORMAT(created_at, ?)
        ORDER BY period DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dateFormat, $days, $dateFormat]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['usage_reports' => $reports]);
}

function getRevenueAnalytics($pdo, $params) {
    // This would integrate with a payment system
    // For now, return placeholder data
    $analytics = [
        'total_revenue' => 0,
        'monthly_revenue' => 0,
        'subscription_count' => 0,
        'average_subscription_value' => 0,
        'churn_rate' => 0
    ];
    
    echo json_encode(['revenue_analytics' => $analytics]);
}

// ====================================================================
// USER MANAGEMENT ACTIONS
// ====================================================================

function createUser($pdo, $admin, $input) {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'student';
    $phone = $input['phone'] ?? '';
    $address = $input['address'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        throw new Exception('Name, email, and password are required');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }
    
    // Validate role
    if (!in_array($role, ['admin', 'teacher', 'student'])) {
        throw new Exception('Invalid role');
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, address, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->execute([$name, $email, $hashedPassword, $role, $phone, $address]);
    $userId = $pdo->lastInsertId();
    
    echo json_encode(['user_id' => $userId, 'success' => true]);
}

function updateUserRole($pdo, $admin, $input) {
    $userId = $input['user_id'];
    $newRole = $input['role'];
    
    if (!$userId || !$newRole) {
        throw new Exception('User ID and role are required');
    }
    
    if (!in_array($newRole, ['admin', 'teacher', 'student'])) {
        throw new Exception('Invalid role');
    }
    
    // Don't allow changing own role
    if ($userId == $admin['id']) {
        throw new Exception('Cannot change your own role');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newRole, $userId]);
    
    echo json_encode(['success' => true]);
}

function suspendUser($pdo, $admin, $input) {
    $userId = $input['user_id'];
    $reason = $input['reason'] ?? '';
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    // Don't allow suspending yourself
    if ($userId == $admin['id']) {
        throw new Exception('Cannot suspend your own account');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Log the action
    logAdminAction($pdo, $admin['id'], 'user_suspend', 'user', $userId, $reason);
    
    echo json_encode(['success' => true]);
}

function deleteUser($pdo, $admin, $params) {
    $userId = $params['user_id'];
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    if ($userId == $admin['id']) {
        throw new Exception('Cannot delete your own account');
    }
    
    // Soft delete - mark as deleted rather than actual deletion
    $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Log the action
    logAdminAction($pdo, $admin['id'], 'user_delete', 'user', $userId, 'User deleted by admin');
    
    echo json_encode(['success' => true]);
}

// ====================================================================
// SYSTEM ADMINISTRATION ACTIONS
// ====================================================================

function getSystemSettings($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM system_settings ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo json_encode(['settings' => $settings]);
}

function updateSystemSettings($pdo, $admin, $input) {
    $settings = $input['settings'] ?? [];
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?, updated_at = NOW()
        ");
        $stmt->execute([$key, $value, $admin['id'], $value, $admin['id']]);
    }
    
    echo json_encode(['success' => true]);
}

// ====================================================================
// UTILITY FUNCTIONS
// ====================================================================

function logAdminAction($pdo, $adminId, $action, $targetType, $targetId, $description) {
    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_log (admin_id, action, target_type, target_id, description, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$adminId, $action, $targetType, $targetId, $description]);
}

// Placeholder implementations for other functions
function bulkImportUsers($pdo, $admin, $input) { echo json_encode(['success' => true, 'message' => 'Bulk import feature coming soon']); }
function backupSystem($pdo, $admin, $input) { echo json_encode(['success' => true, 'message' => 'Backup feature coming soon']); }
function toggleMaintenanceMode($pdo, $admin, $input) { echo json_encode(['success' => true, 'message' => 'Maintenance mode feature coming soon']); }
function approveContent($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function flagContent($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function bulkContentAction($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function createSupportTicket($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function respondToTicket($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function updateUser($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function updateSystemConfig($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function updateUserProfile($pdo, $admin, $input) { echo json_encode(['success' => true]); }
function removeUserFromCourse($pdo, $admin, $params) { echo json_encode(['success' => true]); }
function deleteContent($pdo, $admin, $params) { echo json_encode(['success' => true]); }
function clearSystemLogs($pdo, $admin, $params) { echo json_encode(['success' => true]); }
function getSupportTickets($pdo, $params) { echo json_encode(['tickets' => []]); }
function getModerationQueue($pdo, $params) { echo json_encode(['queue' => []]); }
?>
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!Auth::isAuthenticated()) {
    error_response('Unauthorized', 401);
}

$db = Database::getInstance();
$current_user_id = Auth::getUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Fetch user profile
    if ($method === 'GET') {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;
        
        // Get user basic info
        $user = $db->selectOne("
            SELECT id, name, email, role, avatar, bio, phone, location, website,
                   linkedin, twitter, github, date_of_birth, gender, timezone,
                   points, created_at, last_login, profile_visibility,
                   email_notifications, push_notifications
            FROM users
            WHERE id = :user_id AND status = 'active'
        ", [':user_id' => $user_id]);
        
        if (!$user) {
            error_response('User not found', 404);
        }
        
        // Check privacy settings
        $is_own_profile = ($user_id == $current_user_id);
        $is_public = ($user['profile_visibility'] === 'public');
        
        if (!$is_own_profile && !$is_public && !Auth::isAdmin()) {
            error_response('Profile is private', 403);
        }
        
        // Remove sensitive data if not own profile
        if (!$is_own_profile) {
            unset($user['email']);
            unset($user['phone']);
            unset($user['email_notifications']);
            unset($user['push_notifications']);
        }
        
        // Get enrollment stats
        $stats = $db->selectOne("
            SELECT 
                COUNT(DISTINCT e.id) as courses_enrolled,
                COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.id END) as courses_completed,
                COUNT(DISTINCT c.id) as certificates_earned
            FROM enrollments e
            LEFT JOIN certificates c ON c.student_id = e.student_id
            WHERE e.student_id = :user_id
        ", [':user_id' => $user_id]);
        
        // Get skills
        $skills = $db->select("
            SELECT skill_name, proficiency_level
            FROM user_skills
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ", [':user_id' => $user_id]);
        
        // Get education
        $education = $db->select("
            SELECT id, institution, degree, field_of_study, start_date, end_date, is_current, description
            FROM user_education
            WHERE user_id = :user_id
            ORDER BY is_current DESC, start_date DESC
        ", [':user_id' => $user_id]);
        
        // Get experience
        $experience = $db->select("
            SELECT id, company, position, location, start_date, end_date, is_current, description
            FROM user_experience
            WHERE user_id = :user_id
            ORDER BY is_current DESC, start_date DESC
        ", [':user_id' => $user_id]);
        
        // Get recent activity (last 20)
        $activity = $db->select("
            SELECT activity_type, entity_type, entity_id, description, created_at
            FROM user_activity
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 20
        ", [':user_id' => $user_id]);
        
        // Get connection counts
        $connections = $db->selectOne("
            SELECT 
                (SELECT COUNT(*) FROM user_connections WHERE follower_id = :user_id) as following_count,
                (SELECT COUNT(*) FROM user_connections WHERE following_id = :user_id) as followers_count
        ", [':user_id' => $user_id]);
        
        // Check if current user is following this profile
        $is_following = false;
        if (!$is_own_profile) {
            $follow_check = $db->selectOne("
                SELECT id FROM user_connections
                WHERE follower_id = :current_user AND following_id = :user_id
            ", [':current_user' => $current_user_id, ':user_id' => $user_id]);
            $is_following = !empty($follow_check);
        }
        
        success_response([
            'user' => $user,
            'stats' => $stats,
            'skills' => $skills,
            'education' => $education,
            'experience' => $experience,
            'activity' => $activity,
            'connections' => $connections,
            'is_own_profile' => $is_own_profile,
            'is_following' => $is_following
        ]);
    }
    
    // PUT - Update user profile
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $allowed_fields = ['name', 'bio', 'phone', 'location', 'website', 'linkedin', 
                          'twitter', 'github', 'date_of_birth', 'gender', 'timezone', 
                          'language', 'profile_visibility', 'email_notifications', 'push_notifications'];
        
        $update_fields = [];
        $params = [':user_id' => $current_user_id];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($update_fields)) {
            error_response('No valid fields to update', 400);
        }
        
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
        $db->query($sql, $params);
        
        Auth::logAudit($current_user_id, 'update_profile', 'user', $current_user_id);
        
        success_response([], 'Profile updated successfully');
    }
    
    // POST - Handle various profile actions
    elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload_avatar':
                if (!isset($_FILES['avatar'])) {
                    error_response('No file uploaded', 400);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $result = upload_file($_FILES['avatar'], $allowed_types, 5242880, '/frontend/assets/uploads/avatars/');
                
                if (!$result['success']) {
                    error_response($result['error'], 400);
                }
                
                // Delete old avatar if exists
                $old_avatar = $db->selectOne("SELECT avatar FROM users WHERE id = :user_id", [':user_id' => $current_user_id]);
                if (!empty($old_avatar['avatar'])) {
                    delete_file($old_avatar['avatar']);
                }
                
                // Update database
                $db->query("UPDATE users SET avatar = :avatar WHERE id = :user_id", [
                    ':avatar' => $result['url'],
                    ':user_id' => $current_user_id
                ]);
                
                Auth::logAudit($current_user_id, 'upload_avatar', 'user', $current_user_id);
                
                success_response(['avatar_url' => $result['url']], 'Avatar uploaded successfully');
                break;
                
            case 'add_skill':
                $skill_name = $_POST['skill_name'] ?? '';
                $proficiency = $_POST['proficiency_level'] ?? 'beginner';
                
                if (empty($skill_name)) {
                    error_response('Skill name is required', 400);
                }
                
                $db->query("
                    INSERT INTO user_skills (user_id, skill_name, proficiency_level)
                    VALUES (:user_id, :skill_name, :proficiency)
                ", [
                    ':user_id' => $current_user_id,
                    ':skill_name' => $skill_name,
                    ':proficiency' => $proficiency
                ]);
                
                success_response(['skill_id' => $db->lastInsertId()], 'Skill added successfully');
                break;
                
            case 'add_education':
                $required = ['institution', 'degree', 'field_of_study'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        error_response("$field is required", 400);
                    }
                }
                
                $db->query("
                    INSERT INTO user_education (user_id, institution, degree, field_of_study, start_date, end_date, is_current, description)
                    VALUES (:user_id, :institution, :degree, :field, :start_date, :end_date, :is_current, :description)
                ", [
                    ':user_id' => $current_user_id,
                    ':institution' => $_POST['institution'],
                    ':degree' => $_POST['degree'],
                    ':field' => $_POST['field_of_study'],
                    ':start_date' => $_POST['start_date'] ?? null,
                    ':end_date' => $_POST['end_date'] ?? null,
                    ':is_current' => isset($_POST['is_current']) ? 1 : 0,
                    ':description' => $_POST['description'] ?? ''
                ]);
                
                success_response(['education_id' => $db->lastInsertId()], 'Education added successfully');
                break;
                
            case 'add_experience':
                $required = ['company', 'position'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        error_response("$field is required", 400);
                    }
                }
                
                $db->query("
                    INSERT INTO user_experience (user_id, company, position, location, start_date, end_date, is_current, description)
                    VALUES (:user_id, :company, :position, :location, :start_date, :end_date, :is_current, :description)
                ", [
                    ':user_id' => $current_user_id,
                    ':company' => $_POST['company'],
                    ':position' => $_POST['position'],
                    ':location' => $_POST['location'] ?? '',
                    ':start_date' => $_POST['start_date'] ?? null,
                    ':end_date' => $_POST['end_date'] ?? null,
                    ':is_current' => isset($_POST['is_current']) ? 1 : 0,
                    ':description' => $_POST['description'] ?? ''
                ]);
                
                success_response(['experience_id' => $db->lastInsertId()], 'Experience added successfully');
                break;
                
            case 'follow':
                $user_to_follow = intval($_POST['user_id'] ?? 0);
                
                if ($user_to_follow === $current_user_id) {
                    error_response('Cannot follow yourself', 400);
                }
                
                $db->query("
                    INSERT IGNORE INTO user_connections (follower_id, following_id)
                    VALUES (:follower, :following)
                ", [
                    ':follower' => $current_user_id,
                    ':following' => $user_to_follow
                ]);
                
                success_response([], 'User followed successfully');
                break;
                
            case 'unfollow':
                $user_to_unfollow = intval($_POST['user_id'] ?? 0);
                
                $db->query("
                    DELETE FROM user_connections
                    WHERE follower_id = :follower AND following_id = :following
                ", [
                    ':follower' => $current_user_id,
                    ':following' => $user_to_unfollow
                ]);
                
                success_response([], 'User unfollowed successfully');
                break;
                
            default:
                error_response('Invalid action', 400);
        }
    }
    
    // DELETE - Remove profile items
    elseif ($method === 'DELETE') {
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        
        switch ($type) {
            case 'skill':
                $db->query("DELETE FROM user_skills WHERE id = :id AND user_id = :user_id", [
                    ':id' => $id,
                    ':user_id' => $current_user_id
                ]);
                success_response([], 'Skill removed successfully');
                break;
                
            case 'education':
                $db->query("DELETE FROM user_education WHERE id = :id AND user_id = :user_id", [
                    ':id' => $id,
                    ':user_id' => $current_user_id
                ]);
                success_response([], 'Education removed successfully');
                break;
                
            case 'experience':
                $db->query("DELETE FROM user_experience WHERE id = :id AND user_id = :user_id", [
                    ':id' => $id,
                    ':user_id' => $current_user_id
                ]);
                success_response([], 'Experience removed successfully');
                break;
                
            default:
                error_response('Invalid type', 400);
        }
    }
    
    else {
        error_response('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    error_response('An error occurred', 500);
}
?>

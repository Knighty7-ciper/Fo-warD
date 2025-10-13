<?php
require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();

// Update each setting
foreach ($_POST as $key => $value) {
    if (is_array($value)) continue;
    
    // Handle checkboxes
    if (!isset($_POST[$key]) && in_array($key, ['allow_registration', 'email_verification', 'course_approval_required', 'enable_mpesa'])) {
        $value = '0';
    } elseif (isset($_POST[$key]) && in_array($key, ['allow_registration', 'email_verification', 'course_approval_required', 'enable_mpesa'])) {
        $value = '1';
    }
    
    $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES (:key, :value, NOW())
            ON CONFLICT (setting_key) 
            DO UPDATE SET setting_value = :value, updated_at = NOW()";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':key' => $key, ':value' => $value]);
}

echo json_encode(['success' => true]);
?>

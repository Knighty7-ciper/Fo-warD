<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listUsers();
        break;
    case 'create':
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'bulk_activate':
        bulkActivate();
        break;
    case 'bulk_deactivate':
        bulkDeactivate();
        break;
    case 'bulk_delete':
        bulkDelete();
        break;
    case 'export':
        exportUsers();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listUsers() {
    global $conn;
    
    $sql = "SELECT id, name, email, role, created_at, 
            (SELECT MAX(created_at) FROM audit_logs WHERE user_id = users.id) as last_login,
            COALESCE(status, 'active') as status
            FROM users 
            ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
}

function createUser() {
    global $conn;
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
}

function updateUser() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        return;
    }
    
    // Check if email already exists for another user
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $email, $hashed_password, $role, $status, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $role, $status, $user_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
}

function deleteUser() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

function bulkActivate() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_ids = $data['user_ids'] ?? [];
    
    if (empty($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'No users selected']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => count($user_ids) . ' user(s) activated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate users']);
    }
}

function bulkDeactivate() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_ids = $data['user_ids'] ?? [];
    
    if (empty($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'No users selected']);
        return;
    }
    
    // Don't allow deactivating yourself
    if (in_array($_SESSION['user_id'], $user_ids)) {
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => count($user_ids) . ' user(s) deactivated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate users']);
    }
}

function bulkDelete() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_ids = $data['user_ids'] ?? [];
    
    if (empty($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'No users selected']);
        return;
    }
    
    // Don't allow deleting yourself
    if (in_array($_SESSION['user_id'], $user_ids)) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => count($user_ids) . ' user(s) deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete users']);
    }
}

function exportUsers() {
    global $conn;
    
    $sql = "SELECT id, name, email, role, created_at, 
            COALESCE(status, 'active') as status
            FROM users 
            ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Joined Date']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['role'],
            $row['status'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

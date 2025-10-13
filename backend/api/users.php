<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    if (isset($_GET['all'])) {
        // Get all users for messaging
        $stmt = $pdo->query("SELECT id, name, email, role, avatar FROM users WHERE status = 'active' ORDER BY name");
        echo json_encode($stmt->fetchAll());
    } elseif (isset($_GET['search'])) {
        // Search users
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE (name LIKE ? OR email LIKE ?) AND status = 'active' LIMIT 20");
        $stmt->execute([$search, $search]);
        echo json_encode($stmt->fetchAll());
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

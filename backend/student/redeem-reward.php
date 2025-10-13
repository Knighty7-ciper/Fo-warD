<?php
require_once '../config/db.php';
require_once '../config/auth.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();

$reward_id = $_POST['reward_id'];
$points = $_POST['points'];
$user_id = $_SESSION['user_id'];

// Check if user has enough points
$sql = "SELECT points_balance FROM users WHERE id = :user_id";
$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['points_balance'] < $points) {
    echo json_encode(['error' => 'Insufficient points']);
    exit;
}

// Deduct points
$sql = "UPDATE users SET points_balance = points_balance - :points WHERE id = :user_id";
$stmt = $db->prepare($sql);
$stmt->execute([':points' => $points, ':user_id' => $user_id]);

// Record redemption
$sql = "INSERT INTO rewards (user_id, points, description, earned_at)
        VALUES (:user_id, :points, :description, NOW())";
$stmt = $db->prepare($sql);
$stmt->execute([
    ':user_id' => $user_id,
    ':points' => -$points,
    ':description' => 'Redeemed reward #' . $reward_id
]);

echo json_encode(['success' => true]);
?>

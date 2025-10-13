<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'My Rewards';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get user's reward balance
$sql = "SELECT points_balance FROM users WHERE id = :user_id";
$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$points_balance = $user['points_balance'] ?? 0;

// Get reward history
$sql = "SELECT * FROM rewards WHERE user_id = :user_id ORDER BY earned_at DESC LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Available rewards to redeem
$redeemable_rewards = [
    ['id' => 1, 'name' => 'Course Discount - 10%', 'points' => 100, 'type' => 'discount'],
    ['id' => 2, 'name' => 'Course Discount - 25%', 'points' => 250, 'type' => 'discount'],
    ['id' => 3, 'name' => 'Free Course Access', 'points' => 500, 'type' => 'free_course'],
    ['id' => 4, 'name' => 'Certificate Frame', 'points' => 150, 'type' => 'physical'],
    ['id' => 5, 'name' => 'Priority Support', 'points' => 300, 'type' => 'service'],
];
?>

<div class="container">
    <div class="page-header">
        <h1>My Rewards</h1>
        <p>Earn points and redeem exciting rewards</p>
    </div>
    
    <div class="rewards-dashboard">
        <div class="points-card">
            <div class="points-icon">★</div>
            <div class="points-info">
                <h2><?= number_format($points_balance) ?></h2>
                <p>Total Points</p>
            </div>
        </div>
        
        <div class="earning-tips">
            <h3>How to Earn Points</h3>
            <ul>
                <li>Complete a lesson: <strong>10 points</strong></li>
                <li>Finish a course: <strong>100 points</strong></li>
                <li>Get a certificate: <strong>50 points</strong></li>
                <li>Daily login: <strong>5 points</strong></li>
                <li>Refer a friend: <strong>200 points</strong></li>
            </ul>
        </div>
    </div>
    
    <div class="rewards-section">
        <h2>Redeem Rewards</h2>
        <div class="rewards-grid">
            <?php foreach ($redeemable_rewards as $reward): ?>
                <div class="reward-card">
                    <div class="reward-icon">
                        <?php
                        $icons = [
                            'discount' => '🎫',
                            'free_course' => '📚',
                            'physical' => '🎁',
                            'service' => '⭐'
                        ];
                        echo $icons[$reward['type']];
                        ?>
                    </div>
                    <h3><?= htmlspecialchars($reward['name']) ?></h3>
                    <p class="reward-points"><?= number_format($reward['points']) ?> points</p>
                    <button class="btn btn-primary" 
                            onclick="redeemReward(<?= $reward['id'] ?>, <?= $reward['points'] ?>)"
                            <?= $points_balance < $reward['points'] ? 'disabled' : '' ?>>
                        <?= $points_balance < $reward['points'] ? 'Not Enough Points' : 'Redeem' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="rewards-section">
        <h2>Reward History</h2>
        <div class="history-list">
            <?php if (empty($rewards)): ?>
                <p class="empty-message">No rewards earned yet. Start learning to earn points!</p>
            <?php else: ?>
                <?php foreach ($rewards as $reward): ?>
                    <div class="history-item">
                        <div class="history-info">
                            <h4><?= htmlspecialchars($reward['description']) ?></h4>
                            <p><?= date('F j, Y', strtotime($reward['earned_at'])) ?></p>
                        </div>
                        <div class="history-points <?= $reward['points'] > 0 ? 'positive' : 'negative' ?>">
                            <?= $reward['points'] > 0 ? '+' : '' ?><?= $reward['points'] ?> pts
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.rewards-dashboard {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.points-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 30px;
}

.points-icon {
    font-size: 4rem;
}

.points-info h2 {
    margin: 0;
    font-size: 3rem;
}

.points-info p {
    margin: 5px 0 0 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.earning-tips {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.earning-tips h3 {
    margin: 0 0 20px 0;
}

.earning-tips ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.earning-tips li {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.earning-tips li:last-child {
    border-bottom: none;
}

.rewards-section {
    margin-bottom: 40px;
}

.rewards-section h2 {
    margin-bottom: 20px;
}

.rewards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.reward-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.reward-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.reward-card h3 {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
}

.reward-points {
    color: #667eea;
    font-weight: bold;
    font-size: 1.25rem;
    margin-bottom: 15px;
}

.history-list {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.history-item:last-child {
    border-bottom: none;
}

.history-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.history-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.history-points {
    font-size: 1.25rem;
    font-weight: bold;
}

.history-points.positive {
    color: #28a745;
}

.history-points.negative {
    color: #dc3545;
}

.empty-message {
    padding: 40px;
    text-align: center;
    color: #666;
}

@media (max-width: 768px) {
    .rewards-dashboard {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function redeemReward(rewardId, points) {
    if (!confirm(`Redeem this reward for ${points} points?`)) return;
    
    const formData = new FormData();
    formData.append('reward_id', rewardId);
    formData.append('points', points);
    
    fetch('../../backend/student/redeem-reward.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Reward redeemed successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    });
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

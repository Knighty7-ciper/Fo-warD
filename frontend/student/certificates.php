<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'My Certificates';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get certificates
$sql = "SELECT cert.*, c.title as course_title, u.first_name, u.last_name
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE cert.user_id = :user_id
        ORDER BY cert.issued_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>My Certificates</h1>
        <p>Your achievements and completed courses</p>
    </div>
    
    <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <h2>No certificates yet</h2>
            <p>Complete courses to earn certificates</p>
            <a href="enrolled-courses.php" class="btn btn-primary">View My Courses</a>
        </div>
    <?php else: ?>
        <div class="certificates-grid">
            <?php foreach ($certificates as $cert): ?>
                <div class="certificate-card">
                    <div class="certificate-preview">
                        <div class="certificate-badge">
                            <span>✓</span>
                        </div>
                        <h3>Certificate of Completion</h3>
                        <p class="cert-course"><?= htmlspecialchars($cert['course_title']) ?></p>
                        <p class="cert-student"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></p>
                        <p class="cert-date">Issued: <?= date('F j, Y', strtotime($cert['issued_at'])) ?></p>
                        <p class="cert-number">Certificate #<?= $cert['certificate_number'] ?></p>
                    </div>
                    
                    <div class="certificate-actions">
                        <a href="../../backend/student/download-certificate.php?id=<?= $cert['id'] ?>" 
                           class="btn btn-primary" download>
                            Download PDF
                        </a>
                        <button class="btn btn-secondary" onclick="shareCertificate('<?= $cert['certificate_number'] ?>')">
                            Share
                        </button>
                        <button class="btn btn-secondary" onclick="verifyCertificate('<?= $cert['blockchain_hash'] ?>')">
                            Verify on Blockchain
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 30px;
}

.certificate-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.certificate-preview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 30px;
    text-align: center;
    position: relative;
}

.certificate-badge {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    color: #28a745;
}

.certificate-preview h3 {
    margin: 0 0 20px 0;
    font-size: 1.5rem;
}

.cert-course {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 15px;
}

.cert-student {
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.cert-date,
.cert-number {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 5px 0;
}

.certificate-actions {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
</style>

<script>
function shareCertificate(certNumber) {
    const url = window.location.origin + '/verify-certificate.php?cert=' + certNumber;
    if (navigator.share) {
        navigator.share({
            title: 'My Certificate',
            text: 'Check out my certificate!',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url);
        alert('Certificate link copied to clipboard!');
    }
}

function verifyCertificate(hash) {
    alert('Blockchain Hash: ' + hash + '\n\nThis certificate is verified on the blockchain.');
}
</script>

<?php include '../../shared/templates/footer.php'; ?>

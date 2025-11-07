<?php
$page_title = 'My Certificates - Forward LMS';
$body_class = 'certificates-page';

require_once __DIR__ . '/../shared/templates/header.php';

// Include database connection
require_once __DIR__ . '/../../backend/config/auth.php';
require_once __DIR__ . '/../../backend/config/db.php';

// Check if user is logged in
if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php');
    exit;
}

$current_user = Auth::getUser();
$db = getDBConnection();

// Get user's certificates
try {
    $sql = "SELECT 
                c.title as course_title,
                c.description as course_description,
                cert.id as certificate_id,
                cert.issue_date,
                cert.certificate_url,
                cert.verification_code,
                u.first_name as instructor_first_name,
                u.last_name as instructor_last_name,
                en.completion_date
            FROM certificates cert
            JOIN courses c ON cert.course_id = c.id
            JOIN users u ON c.instructor_id = u.id
            JOIN enrollments en ON en.course_id = c.id AND en.user_id = cert.user_id
            WHERE cert.user_id = ?
            ORDER BY cert.issue_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$current_user['id']]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get certificate statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_certificates,
                    COUNT(DISTINCT c.category) as categories_learned
                  FROM certificates cert
                  JOIN courses c ON cert.course_id = c.id
                  WHERE cert.user_id = ?";
    
    $stats_stmt = $db->prepare($stats_sql);
    $stats_stmt->execute([$current_user['id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Certificates error: " . $e->getMessage());
    $certificates = [];
    $stats = ['total_certificates' => 0, 'categories_learned' => 0];
}
?>

<div class="certificates-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>My Certificates</h1>
            <p>Celebrating your learning achievements and accomplishments</p>
        </div>
        
        <?php if (Auth::getUserRole() === 'admin'): ?>
        <div class="admin-actions">
            <a href="/frontend/admin/certificate-issuer.php" class="btn btn-secondary">
                <span class="icon">🏆</span>
                Issue Certificate
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Certificate Statistics -->
    <div class="cert-stats">
        <div class="stat-card">
            <div class="stat-icon certificates">
                <span>🏆</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_certificates']); ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon categories">
                <span>📚</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['categories_learned']); ?></div>
                <div class="stat-label">Categories Learned</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon achievement">
                <span>⭐</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_certificates'] > 0 ? 'Achiever' : 'Getting Started'; ?></div>
                <div class="stat-label">Learning Status</div>
            </div>
        </div>
    </div>

    <!-- Certificates Section -->
    <div class="certificates-section">
        <?php if (empty($certificates)): ?>
            <div class="empty-state">
                <div class="empty-icon">🏆</div>
                <h2>No Certificates Yet</h2>
                <p>Complete courses to earn your first certificate and showcase your achievements!</p>
                <a href="/frontend/courses.php" class="btn btn-primary">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="certificates-grid">
                <?php foreach ($certificates as $cert): ?>
                    <div class="certificate-card">
                        <div class="certificate-header">
                            <div class="cert-ribbon">
                                <span class="ribbon-text">CERTIFIED</span>
                            </div>
                            <div class="cert-date">
                                Issued: <?php echo date('M j, Y', strtotime($cert['issue_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="cert-content">
                            <h3 class="course-name"><?php echo htmlspecialchars($cert['course_title']); ?></h3>
                            <p class="course-description">
                                <?php echo htmlspecialchars($cert['course_description']); ?>
                            </p>
                            
                            <div class="cert-instructor">
                                <div class="instructor-info">
                                    <div class="instructor-avatar">
                                        <?php echo strtoupper(substr($cert['instructor_first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="instructor-details">
                                        <div class="instructor-name">
                                            <?php echo htmlspecialchars($cert['instructor_first_name'] . ' ' . $cert['instructor_last_name']); ?>
                                        </div>
                                        <div class="instructor-title">Course Instructor</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="cert-details">
                                <div class="detail-item">
                                    <span class="detail-label">Completion Date:</span>
                                    <span class="detail-value">
                                        <?php echo date('M j, Y', strtotime($cert['completion_date'])); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Verification Code:</span>
                                    <span class="detail-value verification-code">
                                        <?php echo htmlspecialchars($cert['verification_code']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cert-actions">
                            <a href="<?php echo htmlspecialchars($cert['certificate_url']); ?>" 
                               target="_blank" 
                               class="btn btn-primary">
                                <span class="icon">📜</span>
                                View Certificate
                            </a>
                            <button onclick="shareCertificate('<?php echo htmlspecialchars($cert['verification_code']); ?>')" 
                                    class="btn btn-secondary">
                                <span class="icon">🔗</span>
                                Share
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Share Certificate Modal -->
<div id="share-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Share Certificate</h3>
            <button class="modal-close" onclick="closeShareModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Share your certificate achievement:</p>
            <div class="share-options">
                <button onclick="shareViaEmail()" class="share-option">
                    <span class="icon">📧</span>
                    Email
                </button>
                <button onclick="shareViaLinkedIn()" class="share-option">
                    <span class="icon">💼</span>
                    LinkedIn
                </button>
                <button onclick="copyVerificationCode()" class="share-option">
                    <span class="icon">📋</span>
                    Copy Code
                </button>
            </div>
            <div class="share-link" id="share-link" style="display: none;">
                <label>Shareable Link:</label>
                <input type="text" id="certificate-link" readonly>
                <button onclick="copyToClipboard()" class="btn btn-primary">Copy</button>
            </div>
        </div>
    </div>
</div>

<style>
.certificates-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-content h1 {
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.header-content p {
    font-size: 1.1rem;
    color: #64748b;
    margin: 0;
}

.admin-actions {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-align: center;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
    background-color: #e2e8f0;
}

.icon {
    font-size: 1rem;
}

/* Statistics */
.cert-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.certificates {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-icon.categories {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-icon.achievement {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.stat-label {
    color: #64748b;
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

/* Certificates Section */
.certificates-section {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h2 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #64748b;
    font-size: 1.1rem;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.certificate-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.certificate-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 12px 24px rgba(245, 158, 11, 0.1);
    transform: translateY(-4px);
}

.certificate-header {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 1rem 1.5rem;
    position: relative;
}

.cert-ribbon {
    position: absolute;
    top: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 1rem;
    clip-path: polygon(0 0, 100% 0, 100% 100%, 0 80%);
}

.ribbon-text {
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.cert-date {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 1.5rem;
}

.cert-content {
    padding: 1.5rem;
}

.course-name {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 0.75rem;
    font-weight: 600;
    line-height: 1.3;
}

.course-description {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.cert-instructor {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.instructor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.instructor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
}

.instructor-details {
    flex: 1;
}

.instructor-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.instructor-title {
    font-size: 0.85rem;
    color: #64748b;
}

.cert-details {
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 0.9rem;
    color: #64748b;
}

.detail-value {
    font-size: 0.9rem;
    color: #1e293b;
    font-weight: 500;
}

.verification-code {
    font-family: 'Courier New', monospace;
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.cert-actions {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.modal-close:hover {
    background: #f1f5f9;
}

.modal-body {
    padding: 1.5rem;
}

.share-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.share-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: #374151;
}

.share-option:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
}

.share-option .icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.share-link {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.share-link label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.share-link input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    margin-bottom: 0.5rem;
    font-family: 'Courier New', monospace;
}

/* Responsive Design */
@media (max-width: 768px) {
    .certificates-container {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    
    .header-content h1 {
        font-size: 2rem;
    }
    
    .cert-stats {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .certificates-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .cert-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .cert-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .certificates-section {
        padding: 1rem;
    }
    
    .certificate-card .cert-content {
        padding: 1rem;
    }
    
    .cert-date {
        margin-top: 1rem;
    }
    
    .share-options {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
    }
}

/* Print Styles */
@media print {
    .certificates-container {
        padding: 0;
    }
    
    .page-header,
    .cert-stats,
    .cert-actions,
    .modal {
        display: none;
    }
    
    .certificate-card {
        page-break-inside: avoid;
        border: 2px solid #000;
    }
    
    .certificate-header {
        background: #f59e0b !important;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<script>
function shareCertificate(verificationCode) {
    const modal = document.getElementById('share-modal');
    modal.style.display = 'flex';
    
    // Generate shareable link (in a real app, this would be more sophisticated)
    const shareLink = `${window.location.origin}/frontend/verify-certificate.php?code=${verificationCode}`;
    document.getElementById('certificate-link').value = shareLink;
}

function closeShareModal() {
    document.getElementById('share-modal').style.display = 'none';
}

function shareViaEmail() {
    const code = document.querySelector('.verification-code')?.textContent || '';
    const subject = 'Check out my Forward LMS certificate!';
    const body = `I'm proud to share my learning achievement from Forward LMS. Verification code: ${code}`;
    window.open(`mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`);
}

function shareViaLinkedIn() {
    const code = document.querySelector('.verification-code')?.textContent || '';
    const text = `Just earned a new certificate from Forward LMS! Verification: ${code}`;
    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(window.location.origin)}&summary=${encodeURIComponent(text)}`);
}

function copyVerificationCode() {
    const code = document.querySelector('.verification-code')?.textContent || '';
    navigator.clipboard.writeText(code).then(() => {
        alert('Verification code copied to clipboard!');
    });
}

function copyToClipboard() {
    const link = document.getElementById('certificate-link').value;
    navigator.clipboard.writeText(link).then(() => {
        alert('Link copied to clipboard!');
    });
}

// Close modal when clicking outside
document.getElementById('share-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeShareModal();
    }
});
</script>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>
<?php
session_start();
require_once '../../backend/config/db.php';
require_once '../../backend/config/auth.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Certificate Issuer - Admin Panel';
$body_class = 'admin-page';
$additional_css = ['/frontend/assets/css/admin-dashboard.css'];

require_once '../../shared/templates/header.php';

$db = getDBConnection();
$message = '';
$error = '';

// Handle certificate issuance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    if ($action === 'issue_certificate') {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        
        if (!$student_id || !$course_id) {
            $error = 'Please select both a student and a course.';
        } else {
            try {
                // Check if student is enrolled in the course
                $enrollment_check = "SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'";
                $enrollment_stmt = $db->prepare($enrollment_check);
                $enrollment_stmt->execute([$student_id, $course_id]);
                
                if (!$enrollment_stmt->fetch()) {
                    $error = 'Student is not enrolled in this course.';
                } else {
                    // Check if certificate already exists
                    $cert_check = "SELECT id FROM certificates WHERE user_id = ? AND course_id = ?";
                    $cert_stmt = $db->prepare($cert_check);
                    $cert_stmt->execute([$student_id, $course_id]);
                    
                    if ($cert_stmt->fetch()) {
                        $error = 'A certificate for this student and course already exists.';
                    } else {
                        // Generate certificate
                        $verification_code = strtoupper(bin2hex(random_bytes(8)));
                        $certificate_url = "/frontend/certificates.php?code={$verification_code}";
                        
                        $issue_sql = "INSERT INTO certificates (user_id, course_id, issue_date, verification_code, certificate_url) 
                                     VALUES (?, ?, NOW(), ?, ?)";
                        $issue_stmt = $db->prepare($issue_sql);
                        $issue_stmt->execute([$student_id, $course_id, $verification_code, $certificate_url]);
                        
                        $message = 'Certificate issued successfully!';
                    }
                }
            } catch (Exception $e) {
                error_log("Certificate issuance error: " . $e->getMessage());
                $error = 'An error occurred while issuing the certificate.';
            }
        }
    }
    
    if ($action === 'revoke_certificate') {
        $certificate_id = filter_input(INPUT_POST, 'certificate_id', FILTER_VALIDATE_INT);
        
        if ($certificate_id) {
            try {
                $revoke_sql = "DELETE FROM certificates WHERE id = ?";
                $revoke_stmt = $db->prepare($revoke_sql);
                $revoke_stmt->execute([$certificate_id]);
                
                $message = 'Certificate revoked successfully!';
            } catch (Exception $e) {
                error_log("Certificate revocation error: " . $e->getMessage());
                $error = 'An error occurred while revoking the certificate.';
            }
        }
    }
}

// Get all students and courses for dropdowns
try {
    $students_sql = "SELECT id, first_name, last_name, email FROM users WHERE role = 'student' ORDER BY first_name, last_name";
    $students_stmt = $db->query($students_sql);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $courses_sql = "SELECT id, title FROM courses WHERE status = 'published' ORDER BY title";
    $courses_stmt = $db->query($courses_sql);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing certificates
    $certificates_sql = "SELECT 
                            cert.id,
                            cert.issue_date,
                            cert.verification_code,
                            cert.certificate_url,
                            u.first_name,
                            u.last_name,
                            u.email,
                            c.title as course_title
                        FROM certificates cert
                        JOIN users u ON cert.user_id = u.id
                        JOIN courses c ON cert.course_id = c.id
                        ORDER BY cert.issue_date DESC";
    $certificates_stmt = $db->query($certificates_sql);
    $certificates = $certificates_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Data fetch error: " . $e->getMessage());
    $students = [];
    $courses = [];
    $certificates = [];
}
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Certificate Issuer</h1>
        <p>Issue and manage course completion certificates</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="admin-content">
        <!-- Issue Certificate Form -->
        <div class="form-section">
            <h2>Issue New Certificate</h2>
            
            <form method="POST" class="certificate-form">
                <input type="hidden" name="action" value="issue_certificate">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id">Select Student</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_id">Select Course</label>
                        <select name="course_id" id="course_id" required>
                            <option value="">Choose a course...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Issue Certificate</button>
                </div>
            </form>
        </div>

        <!-- Existing Certificates -->
        <div class="certificates-section">
            <h2>Issued Certificates</h2>
            
            <?php if (empty($certificates)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <h3>No Certificates Issued</h3>
                    <p>Start issuing certificates to students who have completed courses.</p>
                </div>
            <?php else: ?>
                <div class="certificates-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Issue Date</th>
                                <th>Verification Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $cert): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($cert['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="student-details">
                                                <div class="student-name">
                                                    <?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?>
                                                </div>
                                                <div class="student-email"><?php echo htmlspecialchars($cert['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="course-title">
                                            <?php echo htmlspecialchars($cert['course_title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="issue-date">
                                            <?php echo date('M j, Y', strtotime($cert['issue_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="verification-code"><?php echo htmlspecialchars($cert['verification_code']); ?></code>
                                    </td>
                                    <td>
                                        <div class="cert-actions">
                                            <button onclick="viewCertificate('<?php echo $cert['verification_code']; ?>')" 
                                                    class="btn btn-sm btn-secondary">View</button>
                                            <button onclick="revokeCertificate(<?php echo $cert['id']; ?>)" 
                                                    class="btn btn-sm btn-danger">Revoke</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Certificate View Modal -->
<div id="certificate-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Certificate Details</h3>
            <button class="modal-close" onclick="closeModal('certificate-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="certificate-details">
                <p>Loading certificate details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Confirmation Modal -->
<div id="revoke-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Revoke Certificate</h3>
            <button class="modal-close" onclick="closeModal('revoke-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to revoke this certificate? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('revoke-modal')">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="revoke_certificate">
                    <input type="hidden" name="certificate_id" id="revoke-certificate-id">
                    <button type="submit" class="btn btn-danger">Revoke Certificate</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.admin-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.admin-header h1 {
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.admin-header p {
    font-size: 1.1rem;
    color: #64748b;
    margin: 0;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Admin Content */
.admin-content {
    display: flex;
    flex-direction: column;
    gap: 3rem;
}

.form-section,
.certificates-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.form-section h2,
.certificates-section h2 {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 2rem;
    font-weight: 600;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

/* Form Styles */
.certificate-form {
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

/* Table Styles */
.certificates-table {
    overflow-x: auto;
}

.certificates-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.certificates-table th {
    background: #f8fafc;
    color: #374151;
    font-weight: 600;
    text-align: left;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.certificates-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.certificates-table tbody tr:hover {
    background: #f8fafc;
}

/* Student Info */
.student-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.student-details {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.student-email {
    font-size: 0.85rem;
    color: #64748b;
}

.course-title {
    font-weight: 500;
    color: #1e293b;
}

.issue-date {
    color: #64748b;
    font-size: 0.9rem;
}

.verification-code {
    background: #f1f5f9;
    color: #374151;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Actions */
.cert-actions {
    display: flex;
    gap: 0.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-align: center;
    display: inline-block;
    font-size: 0.85rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
}

.btn-secondary {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
    background-color: #e2e8f0;
}

.btn-danger {
    background-color: #ef4444;
    color: white;
}

.btn-danger:hover {
    background-color: #dc2626;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #94a3b8;
}

.empty-state h3 {
    color: #64748b;
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
}

.empty-state p {
    color: #94a3b8;
    margin: 0;
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
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0.5rem;
    width: 40px;
    height: 40px;
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
    padding: 2rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-container {
        padding: 1rem;
    }
    
    .admin-header {
        padding: 1.5rem;
    }
    
    .admin-header h1 {
        font-size: 2rem;
    }
    
    .form-section,
    .certificates-section {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .certificates-table {
        font-size: 0.8rem;
    }
    
    .certificates-table th,
    .certificates-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .cert-actions {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .modal-content {
        width: 95%;
    }
    
    .modal-body {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 1.75rem;
    }
    
    .form-section h2,
    .certificates-section h2 {
        font-size: 1.25rem;
    }
    
    .student-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .modal-actions .btn {
        width: 100%;
    }
}

/* Table scrolling for mobile */
@media (max-width: 640px) {
    .certificates-table {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow-x: auto;
    }
    
    .certificates-table table {
        min-width: 600px;
    }
}

/* Focus states for accessibility */
button:focus,
select:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Loading states */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #3b82f6;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>

<script>
function viewCertificate(verificationCode) {
    // In a real implementation, this would fetch certificate details from the server
    const certificateData = {
        code: verificationCode,
        status: 'Valid',
        issuedDate: 'November 7, 2025',
        student: 'Student Name',
        course: 'Course Title'
    };
    
    document.getElementById('certificate-details').innerHTML = `
        <div class="certificate-preview">
            <h4>Certificate Verification</h4>
            <div class="cert-info">
                <p><strong>Verification Code:</strong> <code>${certificateData.code}</code></p>
                <p><strong>Status:</strong> <span class="status valid">${certificateData.status}</span></p>
                <p><strong>Issued Date:</strong> ${certificateData.issuedDate}</p>
                <p><strong>Student:</strong> ${certificateData.student}</p>
                <p><strong>Course:</strong> ${certificateData.course}</p>
            </div>
            <div class="cert-actions">
                <a href="/frontend/certificates.php?code=${verificationCode}" class="btn btn-primary" target="_blank">View Full Certificate</a>
            </div>
        </div>
    `;
    
    document.getElementById('certificate-modal').style.display = 'flex';
}

function revokeCertificate(certificateId) {
    document.getElementById('revoke-certificate-id').value = certificateId;
    document.getElementById('revoke-modal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.certificate-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const studentId = document.getElementById('student_id').value;
            const courseId = document.getElementById('course_id').value;
            
            if (!studentId || !courseId) {
                e.preventDefault();
                alert('Please select both a student and a course.');
            }
        });
    }
});
</script>

<style>
.certificate-preview h4 {
    color: #1e293b;
    margin-bottom: 1rem;
    font-size: 1.25rem;
}

.cert-info {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.cert-info p {
    margin-bottom: 0.75rem;
    color: #374151;
}

.cert-info p:last-child {
    margin-bottom: 0;
}

.cert-info code {
    background: #f1f5f9;
    color: #374151;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
}

.status {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status.valid {
    background: #dcfce7;
    color: #166534;
}

.cert-actions {
    text-align: center;
}
</style>

<?php
require_once '../../shared/templates/footer.php';
?>
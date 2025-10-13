<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../../shared/utils/pdf-generator.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Unauthorized');
}

$cert_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$db = getDBConnection();

// Get certificate details
$sql = "SELECT cert.*, c.title as course_title, u.first_name, u.last_name
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        JOIN users u ON cert.user_id = u.id
        WHERE cert.id = :cert_id AND cert.user_id = :user_id";

$stmt = $db->prepare($sql);
$stmt->execute([':cert_id' => $cert_id, ':user_id' => $user_id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    exit('Certificate not found');
}

// Generate PDF
$pdf = new PDFGenerator();
$pdf->generateCertificate(
    $cert['first_name'] . ' ' . $cert['last_name'],
    $cert['course_title'],
    $cert['issued_at'],
    $cert['certificate_number']
);
?>

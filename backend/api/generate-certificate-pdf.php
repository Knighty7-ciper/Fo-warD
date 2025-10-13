<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$certificate_id = $_GET['id'] ?? null;

if (!$certificate_id) {
    http_response_code(400);
    die('Certificate ID required');
}

try {
    $db = getDBConnection();
    
    // Get certificate details
    $query = "SELECT c.*, u.name as student_name, u.email as student_email,
              co.title as course_title, co.description as course_description,
              t.name as instructor_name, ct.*
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN courses co ON c.course_id = co.id
              LEFT JOIN users t ON co.teacher_id = t.id
              LEFT JOIN certificate_templates ct ON c.template_id = ct.id
              WHERE c.id = ? AND c.user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$certificate_id, $_SESSION['user_id']]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cert) {
        http_response_code(404);
        die('Certificate not found');
    }
    
    // If no template, use default
    if (!$cert['template_id']) {
        $stmt = $db->prepare("SELECT * FROM certificate_templates WHERE is_default = TRUE LIMIT 1");
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) {
            foreach ($template as $key => $value) {
                if (!isset($cert[$key]) || $cert[$key] === null) {
                    $cert[$key] = $value;
                }
            }
        }
    }
    
    // Generate HTML certificate
    $html = generateCertificateHTML($cert);
    
    // Check if TCPDF or mPDF is available
    if (class_exists('TCPDF')) {
        generatePDFWithTCPDF($html, $cert);
    } elseif (class_exists('Mpdf\Mpdf')) {
        generatePDFWithMPDF($html, $cert);
    } else {
        // Fallback: output HTML
        header('Content-Type: text/html');
        echo $html;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error generating certificate: ' . $e->getMessage());
}

function generateCertificateHTML($cert) {
    $orientation = $cert['orientation'] ?? 'landscape';
    $width = $orientation === 'landscape' ? '297mm' : '210mm';
    $height = $orientation === 'landscape' ? '210mm' : '297mm';
    
    $body_text = $cert['body_template'] ?? 'This is to certify that <strong>{{student_name}}</strong> has successfully completed the course <strong>{{course_title}}</strong>.';
    
    // Replace variables
    $variables = [
        '{{student_name}}' => $cert['student_name'],
        '{{course_title}}' => $cert['course_title'],
        '{{completion_date}}' => date('F j, Y', strtotime($cert['issued_at'])),
        '{{certificate_number}}' => $cert['certificate_number'],
        '{{instructor_name}}' => $cert['instructor_name'] ?? 'FowarD LMS'
    ];
    
    foreach ($variables as $key => $value) {
        $body_text = str_replace($key, $value, $body_text);
    }
    
    $signature_fields = json_decode($cert['signature_fields'] ?? '[]', true);
    $signatures_html = '';
    
    if ($signature_fields) {
        $signatures_html = '<div class="signatures">';
        foreach ($signature_fields as $sig) {
            $name = str_replace(array_keys($variables), array_values($variables), $sig['name'] ?? '');
            $signatures_html .= '<div class="signature">
                <div class="signature-line"></div>
                <div class="signature-name">' . htmlspecialchars($name) . '</div>
                <div class="signature-label">' . htmlspecialchars($sig['label'] ?? '') . '</div>
            </div>';
        }
        $signatures_html .= '</div>';
    }
    
    $border_styles = [
        'none' => 'border: none;',
        'simple' => 'border: 2px solid ' . ($cert['border_color'] ?? '#000000') . ';',
        'elegant' => 'border: 4px double ' . ($cert['border_color'] ?? '#000000') . '; padding: 20mm;',
        'modern' => 'border: 8px solid ' . ($cert['border_color'] ?? '#000000') . '; border-radius: 10px;'
    ];
    
    $border_style = $border_styles[$cert['border_style'] ?? 'elegant'];
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: ' . $width . ' ' . $height . ';
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", serif;
            background-color: ' . ($cert['background_color'] ?? '#FFFFFF') . ';
        }
        .certificate {
            width: ' . $width . ';
            height: ' . $height . ';
            padding: 15mm;
            box-sizing: border-box;
            position: relative;
        }
        .certificate-inner {
            width: 100%;
            height: 100%;
            ' . $border_style . '
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }
        .logo {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 100px;
            max-height: 100px;
        }
        .title {
            font-size: ' . ($cert['title_font_size'] ?? 36) . 'pt;
            font-weight: bold;
            color: ' . ($cert['title_color'] ?? '#000000') . ';
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .body {
            font-size: 16pt;
            line-height: 1.8;
            max-width: 80%;
            margin: 20px auto;
        }
        .certificate-number {
            font-size: 10pt;
            color: #666;
            margin-top: 30px;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            width: 80%;
            margin-top: 40px;
        }
        .signature {
            text-align: center;
            min-width: 150px;
        }
        .signature-line {
            border-top: 2px solid #000;
            margin-bottom: 5px;
        }
        .signature-name {
            font-weight: bold;
            font-size: 12pt;
        }
        .signature-label {
            font-size: 10pt;
            color: #666;
        }
        .verification {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-inner">';
    
    if ($cert['logo_url']) {
        $html .= '<img src="' . htmlspecialchars($cert['logo_url']) . '" class="logo" alt="Logo">';
    }
    
    $html .= '
            <div class="title">' . htmlspecialchars($cert['title_text'] ?? 'Certificate of Completion') . '</div>
            <div class="body">' . $body_text . '</div>
            <div class="certificate-number">Certificate No: ' . htmlspecialchars($cert['certificate_number']) . '</div>
            ' . $signatures_html . '
            <div class="verification">Blockchain Hash: ' . substr($cert['blockchain_hash'], 0, 16) . '...</div>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

function generatePDFWithTCPDF($html, $cert) {
    $pdf = new TCPDF($cert['orientation'] ?? 'L', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('FowarD LMS');
    $pdf->SetAuthor('FowarD LMS');
    $pdf->SetTitle('Certificate - ' . $cert['student_name']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('certificate-' . $cert['certificate_number'] . '.pdf', 'D');
}

function generatePDFWithMPDF($html, $cert) {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-' . ($cert['orientation'] === 'landscape' ? 'L' : 'P'),
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output('certificate-' . $cert['certificate_number'] . '.pdf', 'D');
}

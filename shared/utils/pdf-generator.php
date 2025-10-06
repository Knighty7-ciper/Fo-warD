<?php

require_once __DIR__ . '/../../vendor/autoload.php';

class PDFGenerator {
    public static function generateCertificate($certificate_data) {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 32);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 20, 'Certificate of Completion', 0, 1, 'C');

        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');

        $pdf->Ln(5);

        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(44, 62, 80);
        $student_name = $certificate_data['student_name'] ?? 'Student Name';
        $pdf->Cell(0, 15, $student_name, 0, 1, 'C');

        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'has successfully completed the course', 0, 1, 'C');

        $pdf->Ln(5);

        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(41, 128, 185);
        $course_title = $certificate_data['course_title'] ?? 'Course Title';
        $pdf->Cell(0, 12, $course_title, 0, 1, 'C');

        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(127, 140, 141);
        $issue_date = $certificate_data['issue_date'] ?? date('F j, Y');
        $pdf->Cell(0, 8, 'Issued on: ' . $issue_date, 0, 1, 'C');

        $cert_number = $certificate_data['certificate_number'] ?? 'CERT-000000';
        $pdf->Cell(0, 8, 'Certificate Number: ' . $cert_number, 0, 1, 'C');

        if (isset($certificate_data['blockchain_hash']) && !empty($certificate_data['blockchain_hash'])) {
            $pdf->SetFont('Arial', '', 10);
            $hash_short = substr($certificate_data['blockchain_hash'], 0, 16) . '...';
            $pdf->Cell(0, 8, 'Blockchain: ' . $hash_short, 0, 1, 'C');
        }

        $pdf->Ln(15);

        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(149, 165, 166);
        $pdf->Cell(0, 8, 'Forward LMS - Community-Driven Learning Platform', 0, 1, 'C');

        $filename = 'certificate_' . $cert_number . '.pdf';
        $output_path = __DIR__ . '/../../frontend/assets/certificates/' . $filename;

        if (!file_exists(dirname($output_path))) {
            mkdir(dirname($output_path), 0755, true);
        }

        $pdf->Output('F', $output_path);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $output_path,
            'url' => '/frontend/assets/certificates/' . $filename
        ];
    }

    public static function generateTranscript($student_data) {
        $pdf = new FPDF();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 20);
        $pdf->Cell(0, 15, 'Academic Transcript', 0, 1, 'C');

        $pdf->Ln(5);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Student Information', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Name: ' . ($student_data['name'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 6, 'Email: ' . ($student_data['email'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 6, 'Student ID: ' . ($student_data['id'] ?? 'N/A'), 0, 1);

        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Completed Courses', 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(100, 8, 'Course Title', 1);
        $pdf->Cell(40, 8, 'Completion Date', 1);
        $pdf->Cell(30, 8, 'Grade', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 10);
        if (isset($student_data['courses']) && is_array($student_data['courses'])) {
            foreach ($student_data['courses'] as $course) {
                $pdf->Cell(100, 8, $course['title'], 1);
                $pdf->Cell(40, 8, $course['completed_at'], 1);
                $pdf->Cell(30, 8, $course['grade'] ?? 'Pass', 1);
                $pdf->Ln();
            }
        }

        $filename = 'transcript_' . ($student_data['id'] ?? 'unknown') . '.pdf';
        $output_path = __DIR__ . '/../../frontend/assets/transcripts/' . $filename;

        if (!file_exists(dirname($output_path))) {
            mkdir(dirname($output_path), 0755, true);
        }

        $pdf->Output('F', $output_path);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $output_path,
            'url' => '/frontend/assets/transcripts/' . $filename
        ];
    }
}

class FPDF {
    protected $orientation = 'P';
    protected $unit = 'mm';
    protected $format = 'A4';
    protected $content = '';

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        $this->orientation = $orientation;
        $this->unit = $unit;
        $this->format = $format;
    }

    public function AddPage() {
        $this->content .= "\n<!-- New Page -->\n";
    }

    public function SetFont($family, $style = '', $size = 0) {
        $this->content .= "<!-- Font: {$family} {$style} {$size} -->\n";
    }

    public function SetTextColor($r, $g = null, $b = null) {
        $this->content .= "<!-- Color: RGB({$r}, {$g}, {$b}) -->\n";
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        $this->content .= "Cell: {$txt}\n";
    }

    public function Ln($h = null) {
        $this->content .= "\n";
    }

    public function Output($dest = '', $name = '', $isUTF8 = false) {
        if ($dest === 'F' && !empty($name)) {
            file_put_contents($name, $this->content);
        } else {
            echo $this->content;
        }
    }
}

?>

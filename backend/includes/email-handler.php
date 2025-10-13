<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

class EmailHandler {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->config = require __DIR__ . '/../config/email.php';
    }
    
    /**
     * Queue an email for sending
     */
    public function queueEmail($recipient_email, $subject, $body_html, $body_text = null, $options = []) {
        $defaults = [
            'recipient_name' => null,
            'template_id' => null,
            'priority' => 'normal',
            'scheduled_at' => null,
            'metadata' => []
        ];
        
        $options = array_merge($defaults, $options);
        
        // Check if user has email preferences
        if (!$this->shouldSendEmail($recipient_email, $options['metadata']['notification_type'] ?? null)) {
            return false;
        }
        
        $query = "INSERT INTO email_queue 
                  (recipient_email, recipient_name, subject, body_html, body_text, 
                   template_id, priority, scheduled_at, metadata)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $metadata_json = json_encode($options['metadata']);
        
        $stmt->execute([
            $recipient_email,
            $options['recipient_name'],
            $subject,
            $body_html,
            $body_text,
            $options['template_id'],
            $options['priority'],
            $options['scheduled_at'],
            $metadata_json
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Send email using template
     */
    public function sendFromTemplate($template_name, $recipient_email, $variables, $options = []) {
        // Get template
        $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE name = ? AND is_active = TRUE");
        $stmt->execute([$template_name]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception("Email template not found: $template_name");
        }
        
        // Replace variables in subject and body
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body_html = $this->replaceVariables($template['body_html'], $variables);
        $body_text = $this->replaceVariables($template['body_text'], $variables);
        
        $options['template_id'] = $template['id'];
        $options['metadata']['template_name'] = $template_name;
        $options['metadata']['notification_type'] = $this->getNotificationTypeFromTemplate($template_name);
        
        return $this->queueEmail($recipient_email, $subject, $body_html, $body_text, $options);
    }
    
    /**
     * Process email queue
     */
    public function processQueue($batch_size = null) {
        $batch_size = $batch_size ?? $this->config['queue']['batch_size'];
        
        // Get pending emails
        $query = "SELECT * FROM email_queue 
                  WHERE status = 'pending' 
                  AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                  AND attempts < max_attempts
                  ORDER BY priority DESC, created_at ASC
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$batch_size]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($emails as $email) {
            try {
                // Update status to sending
                $this->updateEmailStatus($email['id'], 'sending');
                
                // Send email
                if ($this->sendEmail($email)) {
                    $this->updateEmailStatus($email['id'], 'sent', null, date('Y-m-d H:i:s'));
                    $this->logEmail($email['id'], $email['recipient_email'], $email['subject'], 'sent');
                    $sent_count++;
                } else {
                    throw new Exception("Failed to send email");
                }
            } catch (Exception $e) {
                $this->updateEmailStatus($email['id'], 'failed', $e->getMessage());
                $this->logEmail($email['id'], $email['recipient_email'], $email['subject'], 'failed', $e->getMessage());
                $failed_count++;
            }
        }
        
        return [
            'processed' => count($emails),
            'sent' => $sent_count,
            'failed' => $failed_count
        ];
    }
    
    /**
     * Send individual email
     */
    private function sendEmail($email) {
        // Testing mode - just log the email
        if ($this->config['testing_mode']) {
            error_log("TEST EMAIL: To: {$email['recipient_email']}, Subject: {$email['subject']}");
            return true;
        }
        
        // Use PHPMailer or native mail() function
        if ($this->config['method'] === 'smtp') {
            return $this->sendViaSMTP($email);
        } else {
            return $this->sendViaMail($email);
        }
    }
    
    /**
     * Send via SMTP using PHPMailer
     */
    private function sendViaSMTP($email) {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Fallback to mail() if PHPMailer not available
            return $this->sendViaMail($email);
        }
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];
            $mail->Timeout = $this->config['smtp']['timeout'];
            
            // Recipients
            $mail->setFrom($this->config['from']['address'], $this->config['from']['name']);
            $mail->addAddress($email['recipient_email'], $email['recipient_name']);
            $mail->addReplyTo($this->config['reply_to']['address'], $this->config['reply_to']['name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            $mail->Body = $email['body_html'];
            $mail->AltBody = $email['body_text'] ?? strip_tags($email['body_html']);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            throw new Exception("SMTP Error: {$mail->ErrorInfo}");
        }
    }
    
    /**
     * Send via native mail() function
     */
    private function sendViaMail($email) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['address'] . '>',
            'Reply-To: ' . $this->config['reply_to']['address'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail(
            $email['recipient_email'],
            $email['subject'],
            $email['body_html'],
            implode("\r\n", $headers)
        );
    }
    
    /**
     * Replace variables in template
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Check if email should be sent based on user preferences
     */
    private function shouldSendEmail($recipient_email, $notification_type) {
        if (!$notification_type) {
            return true; // Always send if no notification type specified
        }
        
        $query = "SELECT ep.* FROM email_preferences ep
                  JOIN users u ON ep.user_id = u.id
                  WHERE u.email = ? AND ep.email_enabled = TRUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$recipient_email]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prefs) {
            return true; // No preferences set, send by default
        }
        
        // Check specific notification type
        $pref_map = [
            'new_message' => 'notify_new_message',
            'assignment_due' => 'notify_assignment_due',
            'quiz_available' => 'notify_quiz_available',
            'grade_posted' => 'notify_grade_posted',
            'course_update' => 'notify_course_update',
            'forum_reply' => 'notify_forum_reply',
            'announcement' => 'notify_announcement',
            'certificate' => 'notify_certificate'
        ];
        
        $pref_key = $pref_map[$notification_type] ?? null;
        
        if ($pref_key && isset($prefs[$pref_key])) {
            return (bool)$prefs[$pref_key];
        }
        
        return true;
    }
    
    /**
     * Get notification type from template name
     */
    private function getNotificationTypeFromTemplate($template_name) {
        $map = [
            'new_message' => 'new_message',
            'assignment_due' => 'assignment_due',
            'quiz_available' => 'quiz_available',
            'grade_posted' => 'grade_posted',
            'course_enrollment' => 'course_update',
            'announcement' => 'announcement',
            'certificate_issued' => 'certificate'
        ];
        
        return $map[$template_name] ?? null;
    }
    
    /**
     * Update email status
     */
    private function updateEmailStatus($email_id, $status, $error_message = null, $sent_at = null) {
        $query = "UPDATE email_queue 
                  SET status = ?, attempts = attempts + 1, error_message = ?, sent_at = ?, updated_at = NOW()
                  WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$status, $error_message, $sent_at, $email_id]);
    }
    
    /**
     * Log email
     */
    private function logEmail($queue_id, $recipient_email, $subject, $status, $error_message = null) {
        $query = "INSERT INTO email_logs (queue_id, recipient_email, subject, status, error_message)
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$queue_id, $recipient_email, $subject, $status, $error_message]);
    }
    
    /**
     * Get email statistics
     */
    public function getStatistics($days = 7) {
        $query = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM email_queue
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

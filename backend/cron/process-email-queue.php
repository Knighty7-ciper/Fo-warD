<?php
/**
 * Email Queue Processor
 * Run this script via cron job every 5-10 minutes
 * Example crontab: */5 * * * * php /path/to/backend/cron/process-email-queue.php
 */

require_once __DIR__ . '/../includes/email-handler.php';

$emailHandler = new EmailHandler();

try {
    $result = $emailHandler->processQueue();
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Email queue processed: {$result['processed']} total, {$result['sent']} sent, {$result['failed']} failed\n";
    
    // Log to file
    $log_file = __DIR__ . '/../../logs/email-queue.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(
        $log_file,
        "[$timestamp] Processed: {$result['processed']}, Sent: {$result['sent']}, Failed: {$result['failed']}\n",
        FILE_APPEND
    );
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Error processing email queue: " . $e->getMessage() . "\n";
    
    error_log("Email queue error: " . $e->getMessage());
}

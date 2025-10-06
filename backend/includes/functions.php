<?php

function redirect($url, $status_code = 302) {
    header("Location: {$url}", true, $status_code);
    exit();
}

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function error_response($message, $status_code = 400) {
    json_response(['success' => false, 'error' => $message], $status_code);
}

function success_response($data = [], $message = 'Success') {
    json_response(['success' => true, 'message' => $message, 'data' => $data], 200);
}

function log_message($message, $level = 'INFO') {
    $log_file = __DIR__ . '/../logs/app.log';
    $log_dir = dirname($log_file);

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function format_date($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

function time_ago($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return format_date($datetime, 'M j, Y');
    }
}

function truncate_text($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . $suffix;
}

function generate_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}

function upload_file($file, $allowed_types = [], $max_size = 5242880, $upload_dir = '/frontend/assets/uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error occurred'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!empty($allowed_types) && !in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    $full_upload_dir = __DIR__ . '/../../' . trim($upload_dir, '/') . '/';

    if (!file_exists($full_upload_dir)) {
        mkdir($full_upload_dir, 0755, true);
    }

    $destination = $full_upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $destination,
        'url' => $upload_dir . $filename
    ];
}

function delete_file($file_path) {
    $full_path = __DIR__ . '/../../' . ltrim($file_path, '/');

    if (file_exists($full_path)) {
        return unlink($full_path);
    }

    return false;
}

function paginate($total_items, $items_per_page = 10, $current_page = 1) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));

    $offset = ($current_page - 1) * $items_per_page;

    return [
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

function send_email($to, $subject, $message, $from = 'noreply@forward.local') {
    $headers = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

function generate_certificate_number() {
    $prefix = 'FWRD';
    $year = date('Y');
    $random = strtoupper(bin2hex(random_bytes(4)));

    return "{$prefix}-{$year}-{$random}";
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

?>

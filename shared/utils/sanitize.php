<?php

class Sanitize {
    public static function string($input) {
        if ($input === null) return '';
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function email($input) {
        $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    public static function int($input) {
        return filter_var($input, FILTER_VALIDATE_INT) !== false ?
            filter_var($input, FILTER_SANITIZE_NUMBER_INT) : 0;
    }

    public static function float($input) {
        return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ?
            filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0;
    }

    public static function boolean($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public static function url($input) {
        $url = filter_var(trim($input), FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
    }

    public static function filename($input) {
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $input);
        return basename($filename);
    }

    public static function alphanumeric($input) {
        return preg_replace('/[^a-zA-Z0-9]/', '', $input);
    }

    public static function textarea($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function html($input, $allowed_tags = '<p><br><strong><em><ul><ol><li><a>') {
        return strip_tags(trim($input), $allowed_tags);
    }

    public static function array($input) {
        if (!is_array($input)) return [];

        return array_map(function($item) {
            if (is_array($item)) {
                return self::array($item);
            }
            return self::string($item);
        }, $input);
    }

    public static function json($input) {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return null;
    }

    public static function sql($input) {
        return addslashes($input);
    }

    public static function removeXSS($input) {
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $input);

        return $input;
    }

    public static function validatePassword($password, $min_length = 8) {
        if (strlen($password) < $min_length) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        return true;
    }

    public static function cleanPath($path) {
        $path = str_replace(['../', './'], '', $path);
        $path = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $path);
        return $path;
    }
}

?>

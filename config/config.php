<?php

// Main Application Configuration

if (! defined('APP_NAME')) {
    define('APP_NAME', 'مدرسة الشمامسة - Deacons School');
}

if (! defined('BASE_URL')) {
    if (getenv('APP_URL')) {
        $baseUrl = rtrim(getenv('APP_URL'), '/').'/';
    } else {
        $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
        $baseUrl = $protocol.'://'.$host.'/';
    }
    define('BASE_URL', $baseUrl);
}

if (! defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__.'/../uploads/');
}

return [];

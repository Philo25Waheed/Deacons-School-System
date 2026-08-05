<?php

// Main Application Configuration

if (! defined('APP_NAME')) {
    define('APP_NAME', 'مدرسة الشمامسة - Deacons School');
}

if (! defined('BASE_URL')) {
    $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    define('BASE_URL', $protocol.'://'.$host.'/');
}

if (! defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__.'/../uploads/');
}

return [];

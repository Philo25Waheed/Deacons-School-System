<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/auth_check.php';

if (! headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= isset($pageTitle) ? sanitize($pageTitle).' | '.APP_NAME : APP_NAME ?></title>
    
    <!-- Favicon / Brand Logo -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/images/logo.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    
    <!-- Cairo Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Main Style CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= file_exists(__DIR__.'/../assets/css/style.css') ? filemtime(__DIR__.'/../assets/css/style.css') : '1.1' ?>">
    
    <!-- Inline Theme Initializer -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>

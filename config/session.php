<?php

// Session Management & Security (8 Hour Timeout)

if (session_status() === PHP_SESSION_NONE) {
    // Session timeout set to 8 hours = 28800 seconds
    ini_set('session.gc_maxlifetime', 28800);
    ini_set('session.cookie_lifetime', 28800);

    $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || getenv('APP_ENV') === 'production';

    $sameSite = getenv('SESSION_SAMESITE') ?: 'Lax';

    session_set_cookie_params([
        'lifetime' => 28800,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);

    session_start();
}

// 8 Hour Inactivity check
$max_inactivity = 28800; // 8 hours
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $max_inactivity)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'انتهت الجلسة بعد 8 ساعات من بعد عدم النشاط. يرجى تسجيل الدخول مجدداً.';
}
$_SESSION['LAST_ACTIVITY'] = time();

return [];

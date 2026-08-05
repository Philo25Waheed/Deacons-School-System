<?php

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/helpers.php';

if (isset($_SESSION['user']['id'])) {
    log_action($_SESSION['user']['id'], 'LOGOUT', 'User logged out');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();
session_start();
$_SESSION['flash_success'] = 'تم تسجيل الخروج بنجاح.';
header('Location: '.BASE_URL.'authentication/login.php');
exit;

<?php

require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/session.php';
require_once __DIR__.'/includes/auth_check.php';

if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    header('Location: '.BASE_URL."{$role}/index.php");
    exit;
} else {
    header('Location: '.BASE_URL.'authentication/login.php');
    exit;
}

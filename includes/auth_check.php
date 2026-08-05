<?php

// Authorization & Access Control Middleware

require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/config.php';

if (! function_exists('getCurrentUser')) {
    function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (! function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return isset($_SESSION['user']) && ! empty($_SESSION['user']['id']);
    }
}

if (! function_exists('require_login')) {
    function require_login(): void
    {
        if (! isLoggedIn()) {
            $_SESSION['flash_error'] = 'يرجى تسجيل الدخول للوصول إلى هذه الصفحة.';
            header('Location: '.BASE_URL.'authentication/login.php');
            exit;
        }
    }
}

if (! function_exists('require_role')) {
    function require_role(string ...$roles): void
    {
        require_login();
        $userRole = $_SESSION['user']['role'] ?? '';
        if (! in_array($userRole, $roles, true)) {
            $_SESSION['flash_error'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة.';

            // Redirect to their assigned dashboard
            switch ($userRole) {
                case 'admin':
                    header('Location: '.BASE_URL.'admin/index.php');
                    break;
                case 'servant':
                    header('Location: '.BASE_URL.'servant/index.php');
                    break;
                case 'student':
                    header('Location: '.BASE_URL.'student/index.php');
                    break;
                case 'parent':
                    header('Location: '.BASE_URL.'parent/index.php');
                    break;
                default:
                    header('Location: '.BASE_URL.'authentication/login.php');
                    break;
            }
            exit;
        }
    }
}


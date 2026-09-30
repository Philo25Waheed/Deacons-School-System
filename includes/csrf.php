<?php

// CSRF Protection Helper Functions

require_once __DIR__.'/../config/session.php';

if (! function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string
    {
        if (function_exists('csrf_token')) {
            try {
                $token = csrf_token();
                if ($token) {
                    $_SESSION['csrf_token'] = $token;

                    return $token;
                }
            } catch (Throwable $e) {
                // Fallback to custom session token
            }
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (! function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool
    {
        if (empty($token)) {
            $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? null;
        }

        if (empty($token) && function_exists('app') && app()->bound('request')) {
            $token = request('csrf_token') ?? request('_token') ?? request()->header('X-CSRF-TOKEN') ?? request()->header('X-XSRF-TOKEN');
        }

        if (empty($token) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if (empty($token) && isset($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        if (empty($token)) {
            return false;
        }

        // Check native $_SESSION token if set
        if (! empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }

        // Check Laravel session token if available
        if (function_exists('app') && app()->bound('session') && function_exists('session') && session()->isStarted()) {
            $larToken = session()->token();
            if (! empty($larToken) && hash_equals($larToken, $token)) {
                return true;
            }
        }

        $sessionToken = generate_csrf_token();
        if (! empty($sessionToken) && hash_equals($sessionToken, $token)) {
            return true;
        }

        return false;
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = generate_csrf_token();

        return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'">';
    }
}

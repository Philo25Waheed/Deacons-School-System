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
            } catch (\Throwable $e) {
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
    function verify_csrf_token(?string $token): bool
    {
        if (empty($token)) {
            $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? null;
        }

        $sessionToken = generate_csrf_token();

        if (empty($sessionToken) || empty($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = generate_csrf_token();

        return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'">';
    }
}

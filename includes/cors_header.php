<?php

// Cross-Origin Resource Sharing (CORS) Helper for Standalone PHP Endpoints

if (! defined('CORS_INITIALIZED')) {
    define('CORS_INITIALIZED', true);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $allowedOrigins = getenv('CORS_ALLOWED_ORIGINS') ? array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS'))) : ['*'];

    if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: {$allowedOrigins[0]}");
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        }
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        http_response_code(200);
        exit(0);
    }
}

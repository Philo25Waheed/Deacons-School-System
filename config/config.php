<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use Dotenv\Dotenv;

// Main Application Configuration

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
}

if (class_exists(Dotenv::class) && file_exists(__DIR__.'/../.env')) {
    try {
        $dotenv = Dotenv::createUnsafeImmutable(__DIR__.'/../');
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        // Continue if safe load encounters non-critical error
    }
}

// Fallback .env parser when Dotenv package is not loaded
if (file_exists(__DIR__.'/../.env') && (empty($_ENV['DB_HOST']) && empty(getenv('DB_HOST')))) {
    $envLines = @file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim(trim($v), '"\'');
            if (! isset($_ENV[$k])) {
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
}

if (! defined('APP_NAME')) {
    define('APP_NAME', $_ENV['APP_NAME'] ?? (getenv('APP_NAME') ?: 'مدرسة الشهيد إسطفانوس'));
}

if (! defined('CHURCH_NAME')) {
    define('CHURCH_NAME', $_ENV['CHURCH_NAME'] ?? (getenv('CHURCH_NAME') ?: 'كنيسة السيدة العذراء والأنبا رويس - حدائق الأهرام'));
}

if (! defined('BASE_URL')) {
    if (! empty($_SERVER['HTTP_HOST'])) {
        $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $projectRoot = str_replace('\\', '/', dirname(__DIR__));

        $basePath = '/';
        if (! empty($scriptName) && ! empty($scriptFilename) && stripos($scriptFilename, $projectRoot) === 0) {
            $rel = substr($scriptFilename, strlen($projectRoot));
            if (! empty($rel) && strcasecmp(substr($scriptName, -strlen($rel)), $rel) === 0) {
                $sub = substr($scriptName, 0, strlen($scriptName) - strlen($rel));
                $trimmed = trim($sub, '/');
                $basePath = ! empty($trimmed) ? '/'.$trimmed.'/' : '/';
            }
        }
        $baseUrl = $protocol.'://'.$host.$basePath;
    } elseif (! empty($_ENV['APP_URL']) || getenv('APP_URL')) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL'), '/').'/';
    } else {
        $baseUrl = 'http://127.0.0.1:8000/';
    }
    define('BASE_URL', $baseUrl);
}

if (! defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__.'/../uploads/');
}

return [];

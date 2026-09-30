<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    ob_start();
    require base_path('index.php');
    $content = ob_get_clean();

    return response($content);
});

Route::any('{any}', function (string $any) {
    // Prevent directory traversal
    $cleanPath = ltrim(str_replace(['../', '..\\', "\0"], '', $any), '/\\');
    $basePath = realpath(base_path());
    $targetPath = realpath($basePath.DIRECTORY_SEPARATOR.$cleanPath);

    // Must exist and be strictly within the base path
    if (! $targetPath || ! str_starts_with($targetPath, $basePath)) {
        abort(404);
    }

    // Explicitly block sensitive files and internal directories
    $relativePath = str_replace('\\', '/', substr($targetPath, strlen($basePath) + 1));
    $blockedPatterns = [
        '^\.', // hidden files like .env, .git, .editorconfig
        '^config\/',
        '^database\/',
        '^storage\/',
        '^vendor\/',
        '^app\/',
        '^bootstrap\/',
        '^tests\/',
        '^scratch\/',
        'composer\.',
        'package.*\.json',
        'artisan',
        'phpunit\.xml',
        'vite\.config\.js',
        'boost\.json',
    ];

    foreach ($blockedPatterns as $pattern) {
        if (preg_match('/'.$pattern.'/i', $relativePath)) {
            abort(404);
        }
    }

    if (is_file($targetPath)) {
        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'ogg' => 'audio/ogg',
        ];

        if (isset($mimes[$ext])) {
            return response()->file($targetPath, [
                'Content-Type' => $mimes[$ext],
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Only allow execution of approved PHP files
        if ($ext === 'php') {
            $allowedPrefixes = [
                'index.php', 'login.php', 'register.php', 'logout.php', 'profile.php', 'books.php', 'forgot-password.php',
                'admin/', 'servant/', 'student/', 'parent/', 'authentication/', 'api/',
            ];

            $isAllowed = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                ob_start();
                require $targetPath;
                $content = ob_get_clean();

                return response($content);
            }
        }

        abort(404);
    }

    if (is_dir($targetPath) && file_exists($targetPath.DIRECTORY_SEPARATOR.'index.php')) {
        $indexRelative = $relativePath ? $relativePath.'/index.php' : 'index.php';
        $allowedDirs = ['admin', 'servant', 'student', 'parent', 'authentication', 'api'];
        if (in_array(trim($relativePath, '/'), $allowedDirs, true) || $relativePath === '') {
            ob_start();
            require $targetPath.DIRECTORY_SEPARATOR.'index.php';
            $content = ob_get_clean();

            return response($content);
        }
    }

    abort(404);
})->where('any', '.*');

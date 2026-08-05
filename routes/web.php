<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/authentication/login.php');
});

Route::any('{any}', function (string $any) {
    $path = base_path($any);

    if (file_exists($path) && ! is_dir($path)) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'json' => 'application/json',
        ];

        if (isset($mimes[$ext])) {
            return response()->file($path, ['Content-Type' => $mimes[$ext]]);
        }

        require $path;

        return;
    }

    if (file_exists($path.'/index.php')) {
        require $path.'/index.php';

        return;
    }

    abort(404);
})->where('any', '.*');

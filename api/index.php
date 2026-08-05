<?php

// Ensure writable temporary directories exist in Vercel environment (/tmp)
$tmpStorage = [
    '/tmp/views',
    '/tmp/sessions',
    '/tmp/cache',
];

foreach ($tmpStorage as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Forward Vercel serverless requests to Laravel public index
require __DIR__.'/../public/index.php';

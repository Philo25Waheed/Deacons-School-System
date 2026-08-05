<?php

// Config: Database Connection Singleton (PDO)

if (! defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (! defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (! defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'deacons_db'));
}
if (! defined('DB_USER')) {
    define('DB_USER', getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root'));
}
if (! defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
}

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbHost = getenv('DB_HOST') ?: DB_HOST;
    $dbPort = getenv('DB_PORT') ?: DB_PORT;
    $dbName = getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: DB_NAME);
    $dbUser = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: DB_USER);
    $dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : DB_PASS;

    if (getenv('DB_HOST') || getenv('DB_CONNECTION') === 'mysql') {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

            return $pdo;
        } catch (PDOException $e) {
            // Fallthrough to SQLite if MySQL fails
        }
    }

    // SQLite fallback
    $sqlitePath = __DIR__.'/../database/database.sqlite';
    $tmpSqlite = '/tmp/database.sqlite';

    if (file_exists($sqlitePath)) {
        $dbFile = $sqlitePath;
        if (is_dir('/tmp') && ! is_writable($sqlitePath)) {
            if (! file_exists($tmpSqlite)) {
                @copy($sqlitePath, $tmpSqlite);
            }
            if (file_exists($tmpSqlite)) {
                $dbFile = $tmpSqlite;
            }
        }
    } else {
        $dbFile = is_dir('/tmp') ? $tmpSqlite : $sqlitePath;
    }

    try {
        $pdo = new PDO("sqlite:{$dbFile}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    } catch (PDOException $e) {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'deacons_db'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];

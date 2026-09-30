<?php

use Dotenv\Dotenv;

// Config: Database Connection Singleton (PDO)

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
}

if (file_exists(__DIR__.'/../.env')) {
    if (class_exists(Dotenv::class)) {
        try {
            $dotenv = Dotenv::createUnsafeImmutable(__DIR__.'/../');
            $dotenv->safeLoad();
        } catch (Throwable $e) {
            // Safe fallback
        }
    }
    // Safe manual fallback parser for environments where Dotenv is not loaded
    if (empty($_ENV['DB_HOST']) && empty(getenv('DB_HOST'))) {
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
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default instanceof Closure ? $default() : $default;
        }

        return match (is_string($val) ? strtolower($val) : $val) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $val,
        };
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return __DIR__.'/../database'.($path ? DIRECTORY_SEPARATOR.$path : '');
    }
}

if (! defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: '127.0.0.1'));
}
if (! defined('DB_PORT')) {
    define('DB_PORT', $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: '3306'));
}
if (! defined('DB_NAME')) {
    define('DB_NAME', $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'deacons_db')));
}
if (! defined('DB_USER')) {
    define('DB_USER', $_ENV['DB_USERNAME'] ?? (getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root')));
}
if (! defined('DB_PASS')) {
    define('DB_PASS', $_ENV['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''));
}

if (! function_exists('getDB')) {
    function getDB(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $dbHost = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: DB_HOST);
        $dbPort = $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: DB_PORT);
        $dbName = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: DB_NAME));
        $dbUser = $_ENV['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: DB_USER));
        $dbPass = $_ENV['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : DB_PASS);

        $dbConnection = $_ENV['DB_CONNECTION'] ?? (getenv('DB_CONNECTION') ?: (defined('DB_CONNECTION') ? DB_CONNECTION : 'mysql'));

        if ($dbConnection === 'mysql') {
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
                // If running in CLI / Pest tests, allow SQLite fallback
                if (php_sapi_name() === 'cli' || defined('PHPUNIT_COMPOSER_INSTALL') || env('APP_ENV') === 'testing') {
                    // Fallthrough to SQLite for tests
                } else {
                    http_response_code(500);
                    echo "<div style='font-family:Segoe UI, Tahoma, sans-serif; direction:rtl; text-align:right; padding:2rem; max-width:720px; margin:3rem auto; border:2px solid #ef4444; border-radius:12px; background:#fff; box-shadow:0 10px 30px rgba(0,0,0,0.1); color:#1f2937;'>
                        <h2 style='color:#dc2626; margin-top:0; display:flex; align-items:center; gap:0.5rem;'>⚠️ تعذر الاتصال بقاعدة البيانات (MySQL Connection Error)</h2>
                        <p style='color:#4b5563; font-size:1.05rem;'>واجه النظام مشكلة أثناء محاولة الاتصال بسيرفر قاعدة البيانات MySQL:</p>
                        <div style='background:#fef2f2; border:1px solid #fca5a5; padding:1rem; border-radius:8px; color:#991b1b; font-family:monospace; direction:ltr; text-align:left; word-break:break-all; margin-bottom:1.5rem;'>
                            ".htmlspecialchars($e->getMessage())."
                        </div>
                        <div style='background:#f8fafc; border:1px solid #e2e8f0; padding:1rem; border-radius:8px; font-size:0.95rem; line-height:1.8;'>
                            <strong>البيانات المستخدمة حالياً في الاتصال:</strong><br>
                            • <strong>السيرفر (DB_HOST):</strong> <code>".htmlspecialchars($dbHost).'</code><br>
                            • <strong>اسم القاعدة (DB_DATABASE):</strong> <code>'.htmlspecialchars($dbName).'</code><br>
                            • <strong>المستخدم (DB_USERNAME):</strong> <code>'.htmlspecialchars($dbUser)."</code><br>
                        </div>
                        <p style='margin-top:1.5rem; color:#4b5563; font-size:0.95rem; line-height:1.7;'>
                            💡 <strong>تنبيه هام لاستضافة InfinityFree:</strong><br>
                            1. افتح لوحة التحكم vPanel أو صفحة حسابك في InfinityFree واعرف الـ <strong>MySQL Hostname</strong> (مثل: <code>sql108.infinityfree.com</code>) وليس localhost.<br>
                            2. تأكد أن اسم القاعدة واسم المستخدم يبدأ برقم حسابك (مثل: <code>if0_42894195_...</code>).<br>
                            3. تأكد من إنشاء أو تعديل ملف <code>.env</code> بهذه البيانات في مجلد الموقع <code>htdocs</code>.
                        </p>
                    </div>";
                    exit;
                }
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
}

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', __DIR__.'/../database/database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
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
    'migrations' => 'migrations',
];

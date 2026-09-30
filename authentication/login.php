<?php

use Illuminate\Support\Facades\RateLimiter;

$pageTitle = 'تسجيل الدخول';
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/auth_check.php';

// Safe fallback for audit logging in case helpers.php is outdated or missing on production host
if (! function_exists('log_action')) {
    function log_action(?int $userId, string $action, string $details = ''): void
    {
        try {
            if (function_exists('getDB')) {
                $db = getDB();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stmt = $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $action, $details, $ip]);
            }
        } catch (Throwable $e) {
            // Silently fail logging to prevent blocking user actions
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    header('Location: '.BASE_URL."{$role}/index.php");
    exit;
}

$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$isLaravelBound = function_exists('app') && app()->bound('request');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || ($isLaravelBound && request()->isMethod('POST'));

if ($isPost) {
    $loginInput = sanitize($_POST['login_input'] ?? ($isLaravelBound ? request('login_input', '') : ''));
    $password = $_POST['password'] ?? ($isLaravelBound ? request('password', '') : '');
    $csrfToken = $_POST['csrf_token'] ?? ($isLaravelBound ? request('csrf_token', '') : '');

    $clientIp = $isLaravelBound ? request()->ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $throttleKey = 'login:'.sha1($clientIp.'|'.strtolower($loginInput));
    $isThrottled = false;
    $remainingSeconds = 0;

    $hasLaravelRateLimiter = function_exists('app') && app()->bound('rate_limiter');

    if ($hasLaravelRateLimiter) {
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $remainingSeconds = RateLimiter::availableIn($throttleKey);
            $isThrottled = true;
        }
    } else {
        $attempts = $_SESSION['login_attempts'][$throttleKey] ?? ['count' => 0, 'last_attempt' => 0];
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) < 300) {
            $remainingSeconds = 300 - (time() - $attempts['last_attempt']);
            $isThrottled = true;
        }
    }

    if ($isThrottled) {
        $minutes = max(1, (int) ceil($remainingSeconds / 60));
        $error = "تم حظر المحاولات مؤقتاً لتجاوز الحد الأقصى للمحاولات الخاطئة (5 محاولات). يرجى الانتظار {$minutes} دقيقة ثم المحاولة مجدداً.";
    } elseif (! verify_csrf_token($csrfToken)) {
        $error = 'رمز CSRF غير صالح. يرجى إعادة المحاولة.';
    } elseif (empty($loginInput) || empty($password)) {
        $error = 'يرجى أدخال رقم الهاتف أو البريد الإلكتروني وكلمة المرور.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1');
        $stmt->execute([$loginInput, $loginInput]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'حسابك ما زال قيد المراجعة والموافقة من قِبل إدارة المدرسة (Admin). يرجى الانتظار حتى يتم تفعيل الحساب.';
            } elseif ($user['status'] === 'suspended') {
                $error = 'تم إيقاف هذا الحساب مؤقتاً. يرجى التواصل مع إدارة مدرسة الشهيد إسطفانوس.';
            } elseif ($user['status'] !== 'active') {
                $error = 'حالة الحساب غير مفعلة. يرجى التواصل مع المسؤول.';
            } else {
                // Clear throttle on successful login
                if ($hasLaravelRateLimiter) {
                    RateLimiter::clear($throttleKey);
                }
                unset($_SESSION['login_attempts'][$throttleKey]);

                // Password match & active user -> Log in
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'church_name' => $user['church_name'],
                    'stage_id' => $user['stage_id'],
                    'grade_id' => $user['grade_id'],
                    'class_id' => $user['class_id'],
                ];

                if (function_exists('log_action')) {
                    log_action($user['id'], 'LOGIN_SUCCESS', 'User logged in successfully');
                }

                // Redirect based on user role
                header('Location: '.BASE_URL."{$user['role']}/index.php");
                exit;
            }
        } else {
            // Increment failed attempt count
            if ($hasLaravelRateLimiter) {
                RateLimiter::hit($throttleKey, 300);
            } else {
                $prevCount = $_SESSION['login_attempts'][$throttleKey]['count'] ?? 0;
                $_SESSION['login_attempts'][$throttleKey] = [
                    'count' => $prevCount + 1,
                    'last_attempt' => time(),
                ];
            }

            $error = 'بيانات الدخول غير صحيحة (رقم الهاتف/البريد أو كلمة المرور).';
            if (function_exists('log_action')) {
                log_action(null, 'LOGIN_FAILED', "Failed login attempt for: {$loginInput}");
            }
        }
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:clamp(1rem, 4vw, 2rem) clamp(0.6rem, 2.5vw, 1rem);">
    <div class="glass-card" style="width:100%; max-width:440px;">
        <div style="text-align:center; margin-bottom:1.75rem;">
            <div style="margin-bottom:0.75rem;">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="شعار مدرسة الشهيد إسطفانوس" style="width:80px; height:80px; object-fit:contain; filter:drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
            </div>
            <h2 style="color:var(--royal-blue); font-weight:800;">تسجيل الدخول</h2>
            <p style="color:var(--text-muted); font-size:0.88rem; line-height:1.5;">مدرسة الشهيد إسطفانوس للألحان والتسبحة - كنيسة السيدة العذراء والأنبا رويس بحدائق الأهرام</p>
        </div>

        <?php if ($error) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center; font-size:0.9rem;">
                <?= $error ?>
            </div>
        <?php } ?>

        <?php if ($success) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center; font-size:0.9rem;">
                <?= $success ?>
            </div>
        <?php } ?>

        <form action="" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="login_input">رقم الهاتف أو البريد الإلكتروني</label>
                <input type="text" id="login_input" name="login_input" class="form-control" placeholder="مثال: 01000000000 أو user@domain.com" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem; font-size:0.85rem;">
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                    <input type="checkbox" name="remember_me"> تذكرني
                </label>
                <a href="<?= BASE_URL ?>authentication/forgot-password.php" style="color:var(--royal-blue); font-weight:600;">نسيت كلمة المرور؟</a>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.85rem; font-size:1.05rem;">
                تسجيل الدخول
            </button>
        </form>

        <div style="text-align:center; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border-color); font-size:0.9rem;">
            ليس لديك حساب بعد؟ <a href="<?= BASE_URL ?>authentication/register.php" style="color:var(--gold); font-weight:800;">تسجيل حساب جديد</a>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

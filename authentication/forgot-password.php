<?php
use Illuminate\Support\Facades\RateLimiter;

$pageTitle = 'استعادة كلمة المرور';
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';

$msg = '';
$error = '';

$isLaravelBound = function_exists('app') && app()->bound('request');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || ($isLaravelBound && request()->isMethod('POST'));

if ($isPost) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $clientIp = $isLaravelBound ? request()->ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $throttleKey = 'forgot-pw:'.$clientIp;
    $hasLaravelRateLimiter = function_exists('app') && app()->bound('rate_limiter');

    $isThrottled = false;
    $remainingSeconds = 0;

    if ($hasLaravelRateLimiter) {
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $remainingSeconds = RateLimiter::availableIn($throttleKey);
            $isThrottled = true;
        }
    } else {
        $attempts = $_SESSION['forgot_attempts'][$throttleKey] ?? ['count' => 0, 'last_attempt' => 0];
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) < 900) {
            $remainingSeconds = 900 - (time() - $attempts['last_attempt']);
            $isThrottled = true;
        }
    }

    if ($isThrottled) {
        $minutes = max(1, (int) ceil($remainingSeconds / 60));
        $error = "تم تجاوز الحد الأقصى لمحاولات الاستعادة. يرجى الانتظار {$minutes} دقيقة.";
    } elseif (! verify_csrf_token($csrfToken)) {
        $error = 'رمز CSRF غير صالح. يرجى إعادة المحاولة.';
    } else {
        if ($hasLaravelRateLimiter) {
            RateLimiter::hit($throttleKey, 900);
        } else {
            $prevCount = $_SESSION['forgot_attempts'][$throttleKey]['count'] ?? 0;
            $_SESSION['forgot_attempts'][$throttleKey] = [
                'count' => $prevCount + 1,
                'last_attempt' => time(),
            ];
        }
        $identity = sanitize($_POST['identity'] ?? '');
        if (! empty($identity)) {
            if (function_exists('log_action')) {
                log_action(null, 'PASSWORD_RESET_REQUEST', "Requested password reset for: {$identity}");
            }
        }
        $msg = 'إذا كان رقم الهاتف أو البريد الإلكتروني مسجلاً لدينا، فتم إرسال تعليمات إعادة تعيين كلمة المرور أو يرجى التواصل مع الخادم المسؤول.';
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div class="glass-card" style="width:100%; max-width:440px; text-align:center;">
        <div style="font-size:3rem; margin-bottom:0.5rem;">🔑</div>
        <h2 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem;">استعادة كلمة المرور</h2>
        
        <?php if ($error) { ?>
            <div class="badge badge-danger" style="width:100%; padding:1rem; margin-bottom:1.5rem; line-height:1.6;">
                <?= sanitize($error) ?>
            </div>
        <?php } elseif ($msg) { ?>
            <div class="badge badge-info" style="width:100%; padding:1rem; margin-bottom:1.5rem; line-height:1.6;">
                <?= $msg ?>
            </div>
        <?php } else { ?>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">أدخل رقم الهاتف أو البريد الإلكتروني المسجل لارسال كود إعادة التعيين.</p>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <input type="text" name="identity" class="form-control" placeholder="رقم الهاتف أو البريد" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:0.85rem;">متابعة الاستعادة</button>
            </form>
        <?php } ?>

        <div style="margin-top:1.5rem;">
            <a href="<?= BASE_URL ?>authentication/login.php" style="color:var(--royal-blue); font-weight:700;">العودة لتسجيل الدخول</a>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

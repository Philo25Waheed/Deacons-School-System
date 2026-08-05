<?php
$pageTitle = 'تسجيل الدخول';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/auth_check.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    header('Location: '.BASE_URL."{$role}/index.php");
    exit;
}

$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = sanitize($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (! verify_csrf_token($csrfToken)) {
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
                $error = 'حسابك قيد المراجعة والموافقة من قِبل مسؤول النظام (Admin).';
            } elseif ($user['status'] === 'suspended') {
                $error = 'تم إيقاف هذا الحساب. يرجى التواصل مع خادم النظام.';
            } else {
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

                log_action($user['id'], 'LOGIN_SUCCESS', 'User logged in successfully');

                // Redirect based on user role
                header('Location: '.BASE_URL."{$user['role']}/index.php");
                exit;
            }
        } else {
            $error = 'بيانات الدخول غير صحيحة (رقم الهاتف/البريد أو كلمة المرور).';
            log_action(null, 'LOGIN_FAILED', "Failed login attempt for: {$loginInput}");
        }
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div class="glass-card" style="width:100%; max-width:440px;">
        <div style="text-align:center; margin-bottom:2rem;">
            <div style="font-size:3rem; margin-bottom:0.5rem;">⛪</div>
            <h2 style="color:var(--royal-blue); font-weight:800;">تسجيل الدخول</h2>
            <p style="color:var(--text-muted); font-size:0.9rem;">مدرسة الشهيد إسطفانوس للإلحان و التسبحة - كنيسة السيدة العذراء و الانبا رويس بحدائق الأهرام</p>
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

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; font-size:0.85rem;">
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                    <input type="checkbox" name="remember_me"> تذكرني
                </label>
                <a href="<?= BASE_URL ?>authentication/forgot-password.php" style="color:var(--royal-blue); font-weight:600;">نسيت كلمة المرور؟</a>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.9rem; font-size:1.05rem;">
                تسجيل الدخول
            </button>
        </form>

        <div style="text-align:center; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border-color); font-size:0.9rem;">
            ليس لديك حساب بعد؟ <a href="<?= BASE_URL ?>authentication/register.php" style="color:var(--gold); font-weight:800;">تسجيل حساب جديد</a>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

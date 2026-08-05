<?php
$pageTitle = 'استعادة كلمة المرور';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = 'إذا كان رقم الهاتف أو البريد الإلكتروني مسجلاً لدينا، فتم إرسال تعليمات إعادة تعيين كلمة المرور أو يرجى التواصل مع الخادم المسؤول.';
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div class="glass-card" style="width:100%; max-width:440px; text-align:center;">
        <div style="font-size:3rem; margin-bottom:0.5rem;">🔑</div>
        <h2 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem;">استعادة كلمة المرور</h2>
        
        <?php if ($msg) { ?>
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

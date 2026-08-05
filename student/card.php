<?php
$pageTitle = 'كارت الشماس الرقمي QR Card';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin', 'servant', 'parent');

$db = getDB();
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: $_SESSION['user']['id'];

$stmt = $db->prepare('
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.id = ?
');
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (! $student) {
    exit('بيانات الشماس غير موجودة');
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php if (isLoggedIn()) {
        require_once __DIR__.'/../includes/sidebar.php';
    } ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1 style="color:var(--royal-blue); font-weight:800;">بطاقة الشماس الرقمية 🪪</h1>
            <button onclick="window.print()" class="btn btn-gold">🖨️ طباعة بطاقة الشماس</button>
        </div>

        <div class="printable-area">
            <div class="id-card-wrapper">
                <div class="id-card-header">
                    <h3>مدرسة الشمامسة ⛪</h3>
                    <span class="badge badge-gold" style="margin-top:0.3rem;">✝️ <?= sanitize($student['deacon_rank'] ?? 'إبصالتس (مرتل)') ?></span>
                </div>

                <div class="id-card-body">
                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($student['profile_pic'] ?? 'default-avatar.png') ?>" class="id-photo" alt="صورة الشماس" onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                    <div class="id-info">
                        <h4><?= sanitize($student['full_name']) ?></h4>
                        <p><strong>المرحلة:</strong> <?= sanitize($student['stage_name'] ?? 'غير محدد') ?></p>
                        <p><strong>الصف:</strong> <?= sanitize($student['grade_name'] ?? 'غير محدد') ?> (<?= sanitize($student['class_name'] ?? '-') ?>)</p>
                        <p><strong>الكود:</strong> <code><?= sanitize($student['qr_code_token']) ?></code></p>
                    </div>
                </div>

                <div class="id-card-qr">
                    <div id="qrcodeContainer"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        QRCodeGenerator.render('qrcodeContainer', '<?= sanitize($student['qr_code_token']) ?>');
    });
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'طباعة كروت الشمامسة بالجملة';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin', 'servant');

$db = getDB();
$stageId = filter_input(INPUT_GET, 'stage_id', FILTER_VALIDATE_INT);
$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

$query = "
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.role = 'student' AND u.status = 'active'
";
$params = [];
if ($stageId) {
    $query .= ' AND u.stage_id = ?';
    $params[] = $stageId;
}
if ($classId) {
    $query .= ' AND u.class_id = ?';
    $params[] = $classId;
}

$query .= ' ORDER BY u.full_name ASC';
$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$stages = $db->query('SELECT id, name_ar FROM stages')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">طباعة كروت الشمامسة بالجملة 🖨️</h1>
                <p style="color:var(--text-muted);">تصفية وتصميم كروت الصف أو المرحلة بالكامل للطباعة والتسليم</p>
            </div>
            <button onclick="window.print()" class="btn btn-gold">🖨️ طباعة الدفعة كاملة</button>
        </div>

        <div class="glass-card" style="margin-bottom:1.5rem;">
            <form action="" method="GET" style="display:flex; gap:1rem;">
                <select name="stage_id" class="form-control">
                    <option value="">كل المراحل</option>
                    <?php foreach ($stages as $stg) { ?>
                        <option value="<?= $stg['id'] ?>" <?= $stageId == $stg['id'] ? 'selected' : '' ?>><?= sanitize($stg['name_ar']) ?></option>
                    <?php } ?>
                </select>
                <button type="submit" class="btn btn-primary">تصفية وتجهيز الكروت</button>
            </form>
        </div>

        <div class="printable-area" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
            <?php foreach ($students as $stu) { ?>
                <div class="id-card-wrapper" style="margin:0; width:100%;">
                    <div class="id-card-header">
                        <h3 style="font-size:1.1rem;">مدرسة الشمامسة ⛪</h3>
                        <p style="font-size:0.75rem; opacity:0.85;"><?= sanitize($stu['church_name']) ?></p>
                    </div>

                    <div class="id-card-body">
                        <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($stu['profile_pic'] ?? 'default-avatar.png') ?>" class="id-photo" style="width:70px; height:70px;" alt="صورة الشماس" onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                        <div class="id-info">
                            <h4 style="font-size:1rem;"><?= sanitize($stu['full_name']) ?></h4>
                            <p style="font-size:0.75rem;"><strong>المرحلة:</strong> <?= sanitize($stu['stage_name'] ?? '-') ?></p>
                            <p style="font-size:0.75rem;"><strong>الصف:</strong> <?= sanitize($stu['grade_name'] ?? '-') ?></p>
                            <p style="font-size:0.75rem;"><strong>الكود:</strong> <code><?= sanitize($stu['qr_code_token']) ?></code></p>
                        </div>
                    </div>

                    <div class="id-card-qr" style="margin-top:1rem; padding:0.5rem;">
                        <div id="bulkQr_<?= $stu['id'] ?>"></div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php foreach ($students as $stu) { ?>
            QRCodeGenerator.render('bulkQr_<?= $stu['id'] ?>', '<?= sanitize($stu['qr_code_token']) ?>');
        <?php } ?>
    });
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

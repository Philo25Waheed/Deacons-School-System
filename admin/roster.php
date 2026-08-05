<?php
$pageTitle = 'جدول خورس خدمة القداسات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title']);
        $serviceDate = sanitize($_POST['service_date']);
        $hymnRequired = sanitize($_POST['hymn_required']);
        $notes = sanitize($_POST['notes']);
        $studentIds = $_POST['students'] ?? [];

        $stmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, hymn_required, notes, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$title, $serviceDate, $hymnRequired, $notes, $_SESSION['user']['id']]);
        $rosterId = $db->lastInsertId();

        $stmtStu = $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id) VALUES (?, ?)');
        foreach ($studentIds as $sId) {
            $stmtStu->execute([$rosterId, filter_var($sId, FILTER_VALIDATE_INT)]);
        }

        $_SESSION['flash_success'] = 'تم نشر جدول خورس القداس وتنبيه الشمامسة المترتبين للخدمة بنجاح!';
        header('Location: '.BASE_URL.'admin/roster.php');
        exit;
    }
}

$students = $db->query("SELECT id, full_name, qr_code_token FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$rosters = $db->query('
    SELECT r.*, COUNT(rs.student_id) as student_count
    FROM liturgy_roster r
    LEFT JOIN liturgy_roster_students rs ON r.id = rs.roster_id
    GROUP BY r.id ORDER BY r.service_date DESC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">جدول خورس خدمة القداسات الإلهية ⛪</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">تجهيز جدول خدمة قداس جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">عنوان الخدمة / المناسبة *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: قداس الأحد - خورس المرحلة الإبتدائية" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ القداس *</label>
                        <input type="date" name="service_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">اللحن المطلوب تجهيزه من الشمامسة</label>
                    <input type="text" name="hymn_required" class="form-control" placeholder="مثال: لحن إكإسماروؤوت + الهيتنيات">
                </div>

                <div class="form-group">
                    <label class="form-label">اختيار الشمامسة المترتبين لخدمة هذا القداس *</label>
                    <div style="max-height:200px; overflow-y:auto; background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.5rem;">
                        <?php foreach ($students as $stu) { ?>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" name="students[]" value="<?= $stu['id'] ?>">
                                <span><?= sanitize($stu['full_name']) ?></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold" style="width:100%;">نشر جدول الخورس</button>
            </form>
        </div>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">جداول القداسات القادمة والسابقة</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>القداس والمناسبة</th>
                            <th>التاريخ</th>
                            <th>اللحن المطلوب</th>
                            <th>عدد الشمامسة المكلفين</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rosters as $ros) { ?>
                            <tr>
                                <td><strong><?= sanitize($ros['title']) ?></strong></td>
                                <td><?= format_arabic_date($ros['service_date']) ?></td>
                                <td><span class="badge badge-info"><?= sanitize($ros['hymn_required'] ?? 'عام') ?></span></td>
                                <td><span class="badge badge-gold"><?= $ros['student_count'] ?> شماس</span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

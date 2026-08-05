<?php
$pageTitle = 'متابعة الافتقاد الرعوي';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();
$servantId = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $type = sanitize($_POST['type']);
        $notes = sanitize($_POST['notes']);
        $visitDate = sanitize($_POST['visit_date']);

        $stmt = $db->prepare('INSERT INTO pastoral_visitations (student_id, servant_id, type, notes, visit_date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$studentId, $servantId, $type, $notes, $visitDate]);

        $_SESSION['flash_success'] = 'تم تسجيل الافتقاد الرعوي للشماس بنجاح!';
        header('Location: '.BASE_URL.'servant/visitations.php');
        exit;
    }
}

$students = $db->query("SELECT id, full_name, phone FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$visitations = $db->query('
    SELECT pv.*, u.full_name as student_name, srv.full_name as servant_name
    FROM pastoral_visitations pv
    JOIN users u ON pv.student_id = u.id
    JOIN users srv ON pv.servant_id = srv.id
    ORDER BY pv.id DESC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نظام متابعة الافتقاد الرعوي 🏠📞</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Log Visitation Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">تسجيل افتقاد جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">الشماس المستهدف *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">اختر الشماس...</option>
                            <?php foreach ($students as $stu) { ?>
                                <option value="<?= $stu['id'] ?>"><?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['phone']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">وسيلة الافتقاد *</label>
                        <select name="type" class="form-control" required>
                            <option value="phone">مكالمة هاتفية 📞</option>
                            <option value="home_visit">زيارة منزلية 🏠</option>
                            <option value="church_chat">افتقاد في الكنيسة ⛪</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">تاريخ الافتقاد *</label>
                        <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات ونتائج الافتقاد *</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات وتفاصيل الافتقاد والظروف..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">حفظ الافتقاد</button>
            </form>
        </div>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">سجل الافتقاد الرعوي</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الشماس</th>
                            <th>النوع</th>
                            <th>الملاحظات والتوجيهات</th>
                            <th>الخادم افتقد بواسطة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitations as $v) { ?>
                            <tr>
                                <td><strong><?= sanitize($v['student_name']) ?></strong></td>
                                <td>
                                    <?php if ($v['type'] === 'phone') { ?>
                                        <span class="badge badge-info">📞 تلفوني</span>
                                    <?php } elseif ($v['type'] === 'home_visit') { ?>
                                        <span class="badge badge-gold">🏠 زيارة منزلية</span>
                                    <?php } else { ?>
                                        <span class="badge badge-success">⛪ بالكنيسة</span>
                                    <?php } ?>
                                </td>
                                <td><?= sanitize($v['notes']) ?></td>
                                <td><?= sanitize($v['servant_name']) ?></td>
                                <td><?= format_arabic_date($v['visit_date']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

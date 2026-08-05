<?php
$pageTitle = 'التقييمات السلوكية والروحية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $behavior = filter_input(INPUT_POST, 'behavior_score', FILTER_VALIDATE_INT);
        $hymn = filter_input(INPUT_POST, 'hymn_memorization', FILTER_VALIDATE_INT);
        $church = filter_input(INPUT_POST, 'church_attending', FILTER_VALIDATE_INT);
        $notes = sanitize($_POST['notes']);

        $stmt = $db->prepare('INSERT INTO evaluations (student_id, servant_id, behavior_score, hymn_memorization, church_attending, notes, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$studentId, $_SESSION['user']['id'], $behavior, $hymn, $church, $notes, date('Y-m-d')]);

        $_SESSION['flash_success'] = 'تم حفظ التقييم السلوكي للشماس بنجاح!';
        header('Location: '.BASE_URL.'servant/evaluations.php');
        exit;
    }
}

$students = $db->query("SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$evaluations = $db->query('
    SELECT ev.*, u.full_name as student_name, srv.full_name as servant_name
    FROM evaluations ev
    JOIN users u ON ev.student_id = u.id
    JOIN users srv ON ev.servant_id = srv.id
    ORDER BY ev.id DESC LIMIT 30
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">التقييمات السلوكية والروحية 📝</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إدخال تقييم سلوكي جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">الشماس *</label>
                    <select name="student_id" class="form-control" required>
                        <?php foreach ($students as $stu) { ?>
                            <option value="<?= $stu['id'] ?>"><?= sanitize($stu['full_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">السلوك بالكنيسة (من 10)</label>
                        <input type="number" name="behavior_score" class="form-control" value="10" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">حفظ الألحان (من 10)</label>
                        <input type="number" name="hymn_memorization" class="form-control" value="10" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">مواظبة القداسات (من 10)</label>
                        <input type="number" name="church_attending" class="form-control" value="10" min="1" max="10" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات الخادم والتوجيهات</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="أية ملاحظات خاصة بالسلوك أو التوجيه..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">حفظ التقييم</button>
            </form>
        </div>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">سجل التقييمات الأخيرة</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الشماس</th>
                            <th>السلوك</th>
                            <th>حفظ الألحان</th>
                            <th>القداسات</th>
                            <th>الملاحظات</th>
                            <th>تاريخ التقييم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $ev) { ?>
                            <tr>
                                <td><strong><?= sanitize($ev['student_name']) ?></strong></td>
                                <td><span class="badge badge-success"><?= $ev['behavior_score'] ?> / 10</span></td>
                                <td><span class="badge badge-info"><?= $ev['hymn_memorization'] ?> / 10</span></td>
                                <td><span class="badge badge-gold"><?= $ev['church_attending'] ?> / 10</span></td>
                                <td><?= sanitize($ev['notes'] ?? '-') ?></td>
                                <td><?= format_arabic_date($ev['evaluation_date']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

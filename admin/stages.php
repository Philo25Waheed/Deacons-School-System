<?php
$pageTitle = 'إدارة المراحل والصفوف والفصول';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrfToken)) {
        if ($action === 'add_stage') {
            $nameAr = sanitize($_POST['name_ar']);
            $db->prepare('INSERT INTO stages (name_ar) VALUES (?)')->execute([$nameAr]);
            $_SESSION['flash_success'] = 'تم إضافة المرحلة الدراسية بنجاح.';
        } elseif ($action === 'add_grade') {
            $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
            $nameAr = sanitize($_POST['name_ar']);
            $db->prepare('INSERT INTO grades (stage_id, name_ar) VALUES (?, ?)')->execute([$stageId, $nameAr]);
            $_SESSION['flash_success'] = 'تم إضافة الصف الدراسي بنجاح.';
        } elseif ($action === 'assign_servant') {
            $servantId = filter_input(INPUT_POST, 'servant_id', FILTER_VALIDATE_INT);
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $db->prepare('INSERT IGNORE INTO servant_classes (servant_id, class_id) VALUES (?, ?)')->execute([$servantId, $classId]);
            $_SESSION['flash_success'] = 'تم إسناد الخادم للفصل بنجاح.';
        }
        header('Location: '.BASE_URL.'admin/stages.php');
        exit;
    }
}

$stages = $db->query('SELECT * FROM stages ORDER BY id ASC')->fetchAll();
$servants = $db->query("SELECT id, full_name FROM users WHERE role = 'servant' AND status = 'active'")->fetchAll();
$allClasses = $db->query('SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name FROM classes c JOIN grades g ON c.grade_id = g.id')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">إدارة المراحل والصفوف وإسناد الخدام 🏫</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- Add Stage / Grade -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة صف دراسي جديد</h3>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_grade">
                    <div class="form-group">
                        <label class="form-label">المرحلة الأساسية</label>
                        <select name="stage_id" class="form-control" required>
                            <?php foreach ($stages as $stg) { ?>
                                <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم الصف الجديد</label>
                        <input type="text" name="name_ar" class="form-control" placeholder="مثال: الصف الرابع الإبتدائي" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">حفظ الصف</button>
                </form>
            </div>

            <!-- Assign Servant to Class -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إسناد خادم لفصل دراسي</h3>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_servant">
                    <div class="form-group">
                        <label class="form-label">اختر الخادم</label>
                        <select name="servant_id" class="form-control" required>
                            <?php foreach ($servants as $srv) { ?>
                                <option value="<?= $srv['id'] ?>"><?= sanitize($srv['full_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اختر الفصل</label>
                        <select name="class_id" class="form-control" required>
                            <?php foreach ($allClasses as $cls) { ?>
                                <option value="<?= $cls['id'] ?>"><?= sanitize($cls['grade_name']) ?> - <?= sanitize($cls['class_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-gold" style="width:100%;">إسناد الخادم</button>
                </form>
            </div>
        </div>

        <!-- Stage Tree View -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">المراحل والصفوف الحالية بالمنظومة</h3>
            <?php foreach ($stages as $stg) { ?>
                <div style="background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1rem;">
                    <h4 style="color:var(--gold); font-size:1.1rem; font-weight:800;"><?= sanitize($stg['name_ar']) ?></h4>
                    <?php
                $stmtGrades = $db->prepare('SELECT * FROM grades WHERE stage_id = ?');
                $stmtGrades->execute([$stg['id']]);
                $gradesList = $stmtGrades->fetchAll();
                ?>
                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.5rem;">
                        <?php foreach ($gradesList as $grd) { ?>
                            <span class="badge badge-info" style="font-size:0.9rem;"><?= sanitize($grd['name_ar']) ?></span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

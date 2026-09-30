<?php
$pageTitle = 'إدارة المراحل والصفوف والفصول';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
            $patronSaint = sanitize($_POST['patron_saint'] ?? '');
            $timeFrom = sanitize($_POST['time_from'] ?? '');
            $timeTo = sanitize($_POST['time_to'] ?? '');
            $location = sanitize($_POST['location'] ?? '');

            $stmt = $db->prepare('INSERT INTO grades (stage_id, name_ar, patron_saint, time_from, time_to, location) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $stageId,
                $nameAr,
                $patronSaint !== '' ? $patronSaint : null,
                $timeFrom !== '' ? $timeFrom : null,
                $timeTo !== '' ? $timeTo : null,
                $location !== '' ? $location : null,
            ]);
            $_SESSION['flash_success'] = 'تم إضافة الصف الدراسي ببياناته بنجاح.';
        } elseif ($action === 'delete_grade') {
            $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT);
            if ($gradeId) {
                $stmtG = $db->prepare('SELECT name_ar FROM grades WHERE id = ?');
                $stmtG->execute([$gradeId]);
                $gradeName = $stmtG->fetchColumn();

                if (! $gradeName) {
                    $_SESSION['flash_error'] = 'الصف الدراسي المحدد غير موجود.';
                } else {
                    $stuStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE grade_id = ?');
                    $stuStmt->execute([$gradeId]);
                    $stuCount = $stuStmt->fetchColumn();

                    if ($stuCount > 0) {
                        $_SESSION['flash_error'] = "لا يمكن حذف صف ({$gradeName}) لوجود ({$stuCount}) من الشمامسة مسجلين به. يرجى نقلهم أولاً.";
                    } else {
                        $clsStmt = $db->prepare('SELECT id FROM classes WHERE grade_id = ?');
                        $clsStmt->execute([$gradeId]);
                        $classIds = $clsStmt->fetchAll(PDO::FETCH_COLUMN);

                        if (! empty($classIds)) {
                            $inClasses = implode(',', array_fill(0, count($classIds), '?'));
                            $db->prepare("DELETE FROM servant_classes WHERE class_id IN ($inClasses)")->execute($classIds);
                            $db->prepare('DELETE FROM classes WHERE grade_id = ?')->execute([$gradeId]);
                        }

                        $db->prepare('UPDATE courses SET grade_id = NULL WHERE grade_id = ?')->execute([$gradeId]);
                        $db->prepare('UPDATE exams SET grade_id = NULL WHERE grade_id = ?')->execute([$gradeId]);

                        $db->prepare('DELETE FROM grades WHERE id = ?')->execute([$gradeId]);

                        if (function_exists('log_action')) {
                            log_action($_SESSION['user']['id'], 'GRADE_DELETED', "Deleted grade: {$gradeName} (ID: {$gradeId})");
                        }
                        $_SESSION['flash_success'] = "تم حذف الصف الدراسي ({$gradeName}) بنجاح.";
                    }
                }
            } else {
                $_SESSION['flash_error'] = 'معرّف الصف غير صحيح.';
            }
        } elseif ($action === 'delete_stage') {
            $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
            if ($stageId) {
                $stuStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE stage_id = ?');
                $stuStmt->execute([$stageId]);
                $stuCount = $stuStmt->fetchColumn();

                if ($stuCount > 0) {
                    $_SESSION['flash_error'] = "لا يمكن حذف هذه المرحلة لوجود ({$stuCount}) من الشمامسة مسجلين بها. يرجى نقلهم لمرحلة أخرى أولاً.";
                } else {
                    $stmtS = $db->prepare('SELECT name_ar FROM stages WHERE id = ?');
                    $stmtS->execute([$stageId]);
                    $stageName = $stmtS->fetchColumn();

                    $stmtG = $db->prepare('SELECT id FROM grades WHERE stage_id = ?');
                    $stmtG->execute([$stageId]);
                    $gradeIds = $stmtG->fetchAll(PDO::FETCH_COLUMN);

                    if (! empty($gradeIds)) {
                        $inGrades = implode(',', array_fill(0, count($gradeIds), '?'));
                        $stmtC = $db->prepare("SELECT id FROM classes WHERE grade_id IN ($inGrades)");
                        $stmtC->execute($gradeIds);
                        $classIds = $stmtC->fetchAll(PDO::FETCH_COLUMN);

                        if (! empty($classIds)) {
                            $inClasses = implode(',', array_fill(0, count($classIds), '?'));
                            $db->prepare("DELETE FROM servant_classes WHERE class_id IN ($inClasses)")->execute($classIds);
                            $db->prepare("DELETE FROM classes WHERE id IN ($inClasses)")->execute($classIds);
                        }

                        $db->prepare("UPDATE courses SET grade_id = NULL WHERE grade_id IN ($inGrades)")->execute($gradeIds);
                        $db->prepare("UPDATE exams SET grade_id = NULL WHERE grade_id IN ($inGrades)")->execute($gradeIds);
                        $db->prepare("DELETE FROM grades WHERE id IN ($inGrades)")->execute($gradeIds);
                    }

                    $db->prepare('UPDATE courses SET stage_id = NULL WHERE stage_id = ?')->execute([$stageId]);
                    $db->prepare('UPDATE exams SET stage_id = NULL WHERE stage_id = ?')->execute([$stageId]);
                    $db->prepare('DELETE FROM stages WHERE id = ?')->execute([$stageId]);

                    if (function_exists('log_action')) {
                        log_action($_SESSION['user']['id'], 'STAGE_DELETED', "Deleted stage: {$stageName} (ID: {$stageId})");
                    }
                    $_SESSION['flash_success'] = "تم حذف المرحلة ({$stageName}) وكافة صفوفها وفصولها بنجاح.";
                }
            } else {
                $_SESSION['flash_error'] = 'معرّف المرحلة غير صحيح.';
            }
        } elseif ($action === 'delete_class') {
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            if ($classId) {
                $stuClassStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE class_id = ?');
                $stuClassStmt->execute([$classId]);
                $stuClassCount = $stuClassStmt->fetchColumn();

                if ($stuClassCount > 0) {
                    $_SESSION['flash_error'] = "لا يمكن حذف هذا الفصل لوجود ({$stuClassCount}) شماس مسجلين به. يرجى نقلهم أولاً.";
                } else {
                    $clsNameStmt = $db->prepare('SELECT name_ar FROM classes WHERE id = ?');
                    $clsNameStmt->execute([$classId]);
                    $clsName = $clsNameStmt->fetchColumn();

                    $db->prepare('DELETE FROM servant_classes WHERE class_id = ?')->execute([$classId]);
                    $db->prepare('UPDATE exams SET class_id = NULL WHERE class_id = ?')->execute([$classId]);
                    $db->prepare('DELETE FROM classes WHERE id = ?')->execute([$classId]);

                    if (function_exists('log_action')) {
                        log_action($_SESSION['user']['id'], 'CLASS_DELETED', "Deleted class: {$clsName} (ID: {$classId})");
                    }
                    $_SESSION['flash_success'] = "تم حذف الفصل ({$clsName}) بنجاح.";
                }
            }
        } elseif ($action === 'assign_servant') {
            $servantId = filter_input(INPUT_POST, 'servant_id', FILTER_VALIDATE_INT);
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $db->prepare('INSERT IGNORE INTO servant_classes (servant_id, class_id) VALUES (?, ?)')->execute([$servantId, $classId]);

            // Sync servant primary stage/grade/class in users table
            $cStmt = $db->prepare('SELECT c.grade_id, g.stage_id FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?');
            $cStmt->execute([$classId]);
            $cRow = $cStmt->fetch();
            if ($cRow) {
                $db->prepare('UPDATE users SET stage_id = ?, grade_id = ?, class_id = ? WHERE id = ?')->execute([$cRow['stage_id'], $cRow['grade_id'], $classId, $servantId]);
            }

            $_SESSION['flash_success'] = 'تم إسناد الخادم للفصل وتحديث بياناته بنجاح.';
        } elseif ($action === 'remove_servant_assignment') {
            $servantId = filter_input(INPUT_POST, 'servant_id', FILTER_VALIDATE_INT);
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $db->prepare('DELETE FROM servant_classes WHERE servant_id = ? AND class_id = ?')->execute([$servantId, $classId]);
            $_SESSION['flash_success'] = 'تم إلغاء إسناد الخادم للفصل بنجاح.';
        }
        header('Location: '.BASE_URL.'admin/stages.php');
        exit;
    }
}

$stages = $db->query('SELECT * FROM stages ORDER BY id ASC')->fetchAll();
$servants = $db->query("SELECT id, full_name, phone FROM users WHERE role = 'servant' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$allClasses = $db->query('SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name FROM classes c JOIN grades g ON c.grade_id = g.id JOIN stages s ON g.stage_id = s.id ORDER BY s.id, g.id, c.id')->fetchAll();

// Current Servant Assignments
$assignments = $db->query('
    SELECT sc.servant_id, sc.class_id, u.full_name as servant_name, u.phone as servant_phone,
           c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
    FROM servant_classes sc
    JOIN users u ON sc.servant_id = u.id
    JOIN classes c ON sc.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    JOIN stages s ON g.stage_id = s.id
    ORDER BY s.id, g.id, u.full_name ASC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.6rem; margin:0 0 0.25rem 0;">إدارة المراحل والصفوف وإسناد الخدام 🏫</h1>
                <p style="color:var(--text-muted); font-size:0.92rem; margin:0;">إدارة الهيكل الدراسي وإسناد وتوزيع الخدام على الفصول</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>admin/transfer.php" class="btn btn-gold btn-sm">🔄 نقل وتوزيع الفصول والشمامسة</a>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
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
                    <div class="form-group">
                        <label class="form-label">شفيع الفصل / الصف ✝️</label>
                        <input type="text" name="patron_saint" class="form-control" placeholder="مثال: الشهيد مارجرجس، القديس أبانوب">
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">وقت الحصة (من) ⏰</label>
                            <input type="time" name="time_from" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">وقت الحصة (إلى) ⏰</label>
                            <input type="time" name="time_to" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">مكان الحصة 📍</label>
                        <input type="text" name="location" class="form-control" placeholder="مثال: مبنى الخدمات - قاعة القديس أثناسيوس (الدور الثاني)">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">حفظ الصف الدراسي</button>
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
                                <option value="<?= $srv['id'] ?>"><?= sanitize($srv['full_name']) ?> (<?= sanitize($srv['phone']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اختر الفصل</label>
                        <select name="class_id" class="form-control" required>
                            <?php foreach ($allClasses as $cls) { ?>
                                <option value="<?= $cls['id'] ?>"><?= sanitize($cls['stage_name']) ?> - <?= sanitize($cls['grade_name']) ?> - <?= sanitize($cls['class_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-gold" style="width:100%;">إسناد الخادم</button>
                </form>
            </div>
        </div>

        <!-- Current Servant Assignments Table -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">📋 جدول تسكين وتوزيع الخدام على الفصول</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الخادم</th>
                            <th>رقم الهاتف</th>
                            <th>المرحلة الدراسية</th>
                            <th>الصف</th>
                            <th>الفصل</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)) { ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">لا توجد تسكينات مسجلة حالياً.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($assignments as $asg) { ?>
                                <tr>
                                    <td><strong><?= sanitize($asg['servant_name']) ?></strong></td>
                                    <td><?= sanitize($asg['servant_phone']) ?></td>
                                    <td><?= sanitize($asg['stage_name']) ?></td>
                                    <td><?= sanitize($asg['grade_name']) ?></td>
                                    <td><span class="badge badge-info"><?= sanitize($asg['class_name']) ?></span></td>
                                    <td>
                                        <form action="" method="POST" style="display:inline;" onsubmit="return confirm('هل تريد إلغاء إسناد هذا الخادم من الفصل؟')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_servant_assignment">
                                            <input type="hidden" name="servant_id" value="<?= $asg['servant_id'] ?>">
                                            <input type="hidden" name="class_id" value="<?= $asg['class_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">إلغاء الإسناد</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stage Tree View -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">🏫 المراحل والصفوف الحالية بالمنظومة</h3>
            <?php foreach ($stages as $stg) { ?>
                <div style="background:var(--bg-primary); padding:1.25rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; border:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid var(--border-color); padding-bottom:0.5rem; flex-wrap:wrap; gap:0.5rem;">
                        <h4 style="color:var(--gold); font-size:1.15rem; font-weight:800; margin:0;">
                            ✝️ <?= sanitize($stg['name_ar']) ?>
                        </h4>
                        <form action="" method="POST" style="margin:0;" onsubmit="return confirm('هل أنت متأكد من حذف المرحلة بالكامل (<?= sanitize($stg['name_ar']) ?>) وكافة الصفوف والفصول التابعة لها؟');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_stage">
                            <input type="hidden" name="stage_id" value="<?= $stg['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:rgba(239,68,68,0.3); font-size:0.8rem; padding:0.25rem 0.65rem;" title="حذف هذه المرحلة بالكامل">
                                🗑️ حذف المرحلة
                            </button>
                        </form>
                    </div>
                    <?php
                    $stmtGrades = $db->prepare('
                        SELECT g.*, 
                               (SELECT COUNT(*) FROM users u WHERE u.grade_id = g.id AND u.role = "student") as student_count,
                               (SELECT COUNT(*) FROM classes c WHERE c.grade_id = g.id) as class_count
                        FROM grades g 
                        WHERE g.stage_id = ? 
                        ORDER BY g.id ASC
                    ');
                $stmtGrades->execute([$stg['id']]);
                $gradesList = $stmtGrades->fetchAll();
                ?>
                    <?php if (empty($gradesList)) { ?>
                        <div style="color:var(--text-muted); font-size:0.88rem; padding:0.5rem 0;">لا توجد صفوف مضافة في هذه المرحلة بعد.</div>
                    <?php } else { ?>
                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap:0.85rem;">
                            <?php foreach ($gradesList as $grd) { ?>
                                <div style="display:flex; flex-direction:column; justify-content:space-between; gap:0.4rem; background:var(--bg-card); border:1px solid var(--border-color); padding:0.65rem 0.95rem; border-radius:var(--radius-sm); box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem;">
                                        <span style="font-weight:800; color:var(--text-primary); font-size:0.95rem;"><?= sanitize($grd['name_ar']) ?></span>
                                        <div style="display:flex; align-items:center; gap:0.4rem;">
                                            <span class="badge badge-info" style="font-size:0.75rem; padding:0.2rem 0.5rem;" title="عدد الشمامسة">
                                                👦 <?= $grd['student_count'] ?>
                                            </span>
                                            <form action="" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('هل أنت متأكد من حذف الصف الدراسي (<?= sanitize($grd['name_ar']) ?>)؟<?= ($grd['student_count'] > 0) ? '\n\n⚠️ تنبيه: يوجد '.$grd['student_count'].' شماس مسجلين بهذا الصف!' : '' ?>');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_grade">
                                                <input type="hidden" name="grade_id" value="<?= $grd['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.5rem; font-size:0.75rem; line-height:1; border-radius:4px;" title="حذف الصف الدراسي">
                                                    🗑️ حذف
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php if (! empty($grd['patron_saint']) || ! empty($grd['location']) || ! empty($grd['time_from'])) { ?>
                                        <div style="display:flex; flex-direction:column; gap:0.2rem; font-size:0.8rem; color:var(--text-muted); border-top:1px dashed var(--border-color); padding-top:0.4rem; margin-top:0.2rem;">
                                            <?php if (! empty($grd['patron_saint'])) { ?>
                                                <div>🕊️ الشفيع: <strong style="color:var(--gold);"><?= sanitize($grd['patron_saint']) ?></strong></div>
                                            <?php } ?>
                                            <?php if (! empty($grd['time_from'])) { ?>
                                                <div>⏰ الميعاد: <strong><?= sanitize($grd['time_from']) ?></strong> <?= ! empty($grd['time_to']) ? 'إلى <strong>'.sanitize($grd['time_to']).'</strong>' : '' ?></div>
                                            <?php } ?>
                                            <?php if (! empty($grd['location'])) { ?>
                                                <div>📍 المكان: <strong><?= sanitize($grd['location']) ?></strong></div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

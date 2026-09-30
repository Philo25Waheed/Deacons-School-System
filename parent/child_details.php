<?php
$pageTitle = 'تفاصيل متابعة الابن';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('parent', 'admin');

$db = getDB();
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (! $studentId) {
    exit('لم يتم التمكن من تحديد الابن المطلوب.');
}

// IDOR Protection: Verify that current parent is linked to this student
if ($_SESSION['user']['role'] === 'parent') {
    $authStmt = $db->prepare('SELECT 1 FROM parent_student WHERE parent_id = ? AND student_id = ?');
    $authStmt->execute([$_SESSION['user']['id'], $studentId]);
    if (! $authStmt->fetchColumn()) {
        $_SESSION['flash_error'] = 'غير مصرح لك بالوصول لبيانات هذا الطالب.';
        header('Location: '.BASE_URL.'parent/index.php');
        exit;
    }
}

// Fetch student details
$stuStmt = $db->prepare('
    SELECT u.*, s.name_ar as stage, g.name_ar as grade, c.name_ar as class
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.id = ?
');
$stuStmt->execute([$studentId]);
$child = $stuStmt->fetch();

if (! $child) {
    exit('بيانات الشماس غير موجودة.');
}

// Attendance history
$attendance = $db->prepare('SELECT * FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC');
$attendance->execute([$studentId]);
$attList = $attendance->fetchAll();

// Points history
$points = $db->prepare('SELECT * FROM points WHERE student_id = ? ORDER BY id DESC');
$points->execute([$studentId]);
$ptsList = $points->fetchAll();

// Exam results history for child
$examResults = $db->prepare('
    SELECT r.*, e.title as exam_title, u.full_name as servant_name
    FROM exam_results r
    JOIN exams e ON r.exam_id = e.id
    LEFT JOIN users u ON e.servant_id = u.id
    WHERE r.student_id = ?
    ORDER BY r.id DESC
');
$examResults->execute([$studentId]);
$childExams = $examResults->fetchAll();

// Behavior evaluations
$evals = $db->prepare('SELECT * FROM evaluations WHERE student_id = ? ORDER BY id DESC');
$evals->execute([$studentId]);
$evalList = $evals->fetchAll();

// Event & Trip registrations
$eventsStmt = $db->prepare('
    SELECT er.*, e.title as event_title, e.event_date, e.location, e.event_type, e.price
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.user_id = ?
    ORDER BY e.event_date DESC
');
$eventsStmt->execute([$studentId]);
$childEvents = $eventsStmt->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">تقرير متابعة الشماس: <?= sanitize($child['full_name']) ?></h1>
                <p style="color:var(--text-muted);"><?= sanitize($child['stage'] ?? '') ?> - <?= sanitize($child['grade'] ?? '') ?> (رتبة: <?= sanitize($child['deacon_rank'] ?? 'شماس') ?>)</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>parent/report.php?id=<?= $child['id'] ?>" class="btn btn-primary" style="font-weight:800;">📊 التقرير الشامل الرسمي</a>
                <a href="<?= BASE_URL ?>student/card.php?id=<?= $child['id'] ?>" class="btn btn-gold">🖨️ طباعة كارت الشماس</a>
            </div>
        </div>

        <!-- Attendance & Points Summary -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">سجل الحضور والغياب</h3>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>وقت الحضور</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attList as $a) { ?>
                                <tr>
                                    <td><?= format_arabic_date($a['attendance_date']) ?></td>
                                    <td><?= date('H:i', strtotime($a['scanned_at'])) ?></td>
                                    <td><span class="badge badge-success">حاضر ✅</span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card">
                <h3 style="color:var(--gold); margin-bottom:1rem;">سجل الطايو والتشجيع</h3>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>الطايو</th>
                                <th>السبب</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ptsList as $p) { ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $p['type'] === 'positive' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $p['type'] === 'positive' ? '+' : '-' ?><?= $p['points'] ?> طايو
                                        </span>
                                    </td>
                                    <td><?= sanitize($p['reason']) ?></td>
                                    <td><?= format_arabic_date($p['created_at']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Child Exam Results Section -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">📝 نتائج امتحانات واختبارات الابن</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>عنوان الامتحان</th>
                            <th>الخادم المسؤول</th>
                            <th>الدرجة المحصلة</th>
                            <th>النسبة المئوية</th>
                            <th>حالة التصحيح</th>
                            <th>ملاحظات الخادم</th>
                            <th>تاريخ الاختبار</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($childExams)) { ?>
                            <tr>
                                <td colspan="7" style="text-align:center; color:var(--text-muted);">لا توجد نتائج اختبارات مسجلة للابن حتى الآن.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($childExams as $ce) {
                                $pct = ($ce['total_marks'] > 0) ? round(($ce['score'] / $ce['total_marks']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($ce['exam_title']) ?></strong></td>
                                    <td><?= sanitize($ce['servant_name'] ?? 'الخادم المسؤول') ?></td>
                                    <td>
                                        <strong style="color:var(--royal-blue); font-size:1.15rem;"><?= $ce['score'] ?> / <?= $ce['total_marks'] ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $pct >= 85 ? 'badge-success' : ($pct >= 65 ? 'badge-info' : 'badge-warning') ?>">
                                            <?= $pct ?>% (<?= $pct >= 85 ? 'ممتاز 🌟' : ($pct >= 75 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : 'يحتاج مراجعة')) ?>)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ce['status'] === 'needs_grading') { ?>
                                            <span class="badge badge-warning">جاري تصحيح السؤال المقالي ⏳</span>
                                        <?php } else { ?>
                                            <span class="badge badge-success">تم اعتماد النتيجة ✅</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= sanitize($ce['servant_feedback'] ?? '-') ?></td>
                                    <td><?= format_arabic_date($ce['taken_at']) ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Evaluations -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">التقييمات السلوكية والروحية</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>السلوك</th>
                            <th>حفظ الألحان</th>
                            <th>المواظبة</th>
                            <th>ملاحظات الخادم</th>
                            <th>تاريخ التقييم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evalList as $ev) { ?>
                            <tr>
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

        <!-- Trips & Events Registrations -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <span>🚌</span> الرحلات والأنشطة المشترك بها الابن
            </h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>اسم الفعالية / الرحلة</th>
                            <th>تاريخ الرحلة</th>
                            <th>المكان</th>
                            <th>عدد المقاعد</th>
                            <th>حالة الحجز</th>
                            <th>تاريخ التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($childEvents)) { ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                                    لم يقم الابن بالحجز في أي رحلات أو مناسبات حتى الآن.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($childEvents as $ce) { ?>
                                <tr>
                                    <td><strong><?= sanitize($ce['event_title']) ?></strong></td>
                                    <td><?= format_arabic_date($ce['event_date']) ?></td>
                                    <td><?= sanitize($ce['location'] ?? '-') ?></td>
                                    <td><span class="badge badge-info"><?= $ce['seats_count'] ?> مقاعد</span></td>
                                    <td>
                                        <?php if ($ce['status'] === 'confirmed') { ?>
                                            <span class="badge badge-success">حجز مؤكد ✅</span>
                                        <?php } elseif ($ce['status'] === 'cancelled') { ?>
                                            <span class="badge badge-danger">حجز ملغي ❌</span>
                                        <?php } else { ?>
                                            <span class="badge badge-warning">قيد المراجعة ⏳</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= format_arabic_date(substr($ce['created_at'], 0, 10)) ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'تقرير المخدوم الشامل والمفصل';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin', 'servant');

$db = getDB();
$currentUserId = $_SESSION['user']['id'];
$currentUserRole = $_SESSION['user']['role'];
$accessibleClassIds = get_user_accessible_class_ids();

// 1. Fetch available classes for filter (scoped for servants)
$classesQuery = '
    SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
    FROM classes c
    JOIN grades g ON c.grade_id = g.id
    JOIN stages s ON g.stage_id = s.id
';
$classesParams = [];
if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $classesQuery .= ' WHERE 1=0';
    } else {
        $inCls = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $classesQuery .= " WHERE c.id IN ({$inCls})";
        $classesParams = $accessibleClassIds;
    }
}
$classesQuery .= ' ORDER BY s.id, g.id, c.name_ar ASC';
$stmtClasses = $db->prepare($classesQuery);
$stmtClasses->execute($classesParams);
$availableClasses = $stmtClasses->fetchAll();

// 2. Determine class filter
$selectedClassId = null;
if (isset($_GET['class_id'])) {
    if ($_GET['class_id'] !== '' && $_GET['class_id'] !== 'all') {
        $selectedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: null;
    }
}

// 3. Determine selected student ID if explicitly provided
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If student is specified but class_id was not explicitly in GET, auto-align class_id
if ($studentId && ! isset($_GET['class_id'])) {
    $stuClassStmt = $db->prepare('SELECT class_id FROM users WHERE id = ?');
    $stuClassStmt->execute([$studentId]);
    $stuClassId = $stuClassStmt->fetchColumn();
    if ($stuClassId) {
        $selectedClassId = (int) $stuClassId;
    }
}

// 4. Build list of students for switcher dropdown (filtered by selected class if set)
$stuListQuery = "
    SELECT u.id, u.full_name, u.qr_code_token, u.class_id, c.name_ar as class_name, g.name_ar as grade_name
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.id
    LEFT JOIN grades g ON u.grade_id = g.id
    WHERE u.role = 'student' AND u.status = 'active'
";
$stuListParams = [];
if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $stuListQuery .= ' AND 1=0';
    } else {
        $inStudents = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $stuListQuery .= " AND u.class_id IN ({$inStudents})";
        $stuListParams = $accessibleClassIds;
    }
}
if ($selectedClassId) {
    $stuListQuery .= ' AND u.class_id = ?';
    $stuListParams[] = $selectedClassId;
}
$stuListQuery .= ' ORDER BY u.full_name ASC';
$stmtStudents = $db->prepare($stuListQuery);
$stmtStudents->execute($stuListParams);
$availableStudents = $stmtStudents->fetchAll();

// 5. If studentId is not set or not in this class's students, pick first student
if (! empty($availableStudents)) {
    $availableIds = array_column($availableStudents, 'id');
    if (! $studentId || ! in_array($studentId, $availableIds)) {
        $studentId = (int) $availableStudents[0]['id'];
    }
} else {
    $studentId = null;
}

// Servant access guard
if ($studentId && ! can_servant_access_student($currentUserId, $studentId, $currentUserRole)) {
    $_SESSION['flash_error'] = 'عذراً، هذا الشماس ليس ضمن الفصول المخصصة لخدمتك.';
    header('Location: '.BASE_URL.'servant/students.php');
    exit;
}

// Fetch student profile
$student = null;
if ($studentId) {
    $stuStmt = $db->prepare('
        SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name,
               g.patron_saint, g.time_from, g.time_to, g.location,
               (SELECT phone FROM users p JOIN parent_student ps ON p.id = ps.parent_id WHERE ps.student_id = u.id LIMIT 1) as parent_phone
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.id = ? AND u.role = \'student\'
    ');
    $stuStmt->execute([$studentId]);
    $student = $stuStmt->fetch();
}

// 1. Attendance & Absence stats
$attStats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'attendance_rate' => 0,
    'absence_rate' => 0,
];
$attHistory = [];
if ($studentId) {
    $stmtAtt = $db->prepare('
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = \'present\' THEN 1 ELSE 0 END) as present_cnt,
            SUM(CASE WHEN status = \'absent\' THEN 1 ELSE 0 END) as absent_cnt,
            SUM(CASE WHEN status = \'late\' THEN 1 ELSE 0 END) as late_cnt
        FROM attendance
        WHERE student_id = ?
    ');
    $stmtAtt->execute([$studentId]);
    $row = $stmtAtt->fetch();
    if ($row && $row['total'] > 0) {
        $attStats['total'] = (int) $row['total'];
        $attStats['present'] = (int) $row['present_cnt'];
        $attStats['absent'] = (int) $row['absent_cnt'];
        $attStats['late'] = (int) $row['late_cnt'];
        $attStats['attendance_rate'] = round(($attStats['present'] / $attStats['total']) * 100);
        $attStats['absence_rate'] = round(($attStats['absent'] / $attStats['total']) * 100);
    }

    $stmtAttHist = $db->prepare('
        SELECT a.*, u.full_name as scanned_by_name
        FROM attendance a
        LEFT JOIN users u ON a.servant_id = u.id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC
    ');
    $stmtAttHist->execute([$studentId]);
    $attHistory = $stmtAttHist->fetchAll();
}

// 2. Exam scores & evaluations
$examResults = [];
$examAvg = 0;
if ($studentId) {
    $stmtExams = $db->prepare('
        SELECT r.*, e.title as exam_title, r.total_marks as max_marks, u.full_name as servant_name
        FROM exam_results r
        JOIN exams e ON r.exam_id = e.id
        LEFT JOIN users u ON e.servant_id = u.id
        WHERE r.student_id = ?
        ORDER BY r.taken_at DESC, r.id DESC
    ');
    $stmtExams->execute([$studentId]);
    $examResults = $stmtExams->fetchAll();

    if (! empty($examResults)) {
        $pctSum = 0;
        foreach ($examResults as $ex) {
            $totalM = (float) ($ex['max_marks'] ?: 100);
            $score = (float) $ex['score'];
            $pctSum += round(($score / $totalM) * 100);
        }
        $examAvg = round($pctSum / count($examResults));
    }
}

// 3. Liturgy service schedule & history ("كان عليه خدمة في القداس امتى")
$liturgyHistory = [];
if ($studentId) {
    $stmtLiturgy = $db->prepare('
        SELECT r.id as roster_id, r.title, r.service_date, r.notes as liturgy_notes,
               rs.role_name, rs.status as student_status, rs.response_notes, rs.responded_at,
               rs.student_id, rs.substitute_student_id,
               sub.full_name as substitute_name
        FROM liturgy_roster_students rs
        JOIN liturgy_roster r ON rs.roster_id = r.id
        LEFT JOIN users sub ON rs.substitute_student_id = sub.id
        WHERE rs.student_id = ? OR rs.substitute_student_id = ?
        ORDER BY r.service_date DESC
    ');
    $stmtLiturgy->execute([$studentId, $studentId]);
    $liturgyHistory = $stmtLiturgy->fetchAll();
}

// 4. Tayou (الطايو) balance & history ("معاه كام طايو")
$totalTayou = 0;
$positiveTayou = 0;
$negativeTayou = 0;
$tayouHistory = [];
if ($studentId) {
    $stmtTayou = $db->prepare('
        SELECT p.*, srv.full_name as servant_name
        FROM points p
        LEFT JOIN users srv ON p.servant_id = srv.id
        WHERE p.student_id = ?
        ORDER BY p.id DESC
    ');
    $stmtTayou->execute([$studentId]);
    $tayouHistory = $stmtTayou->fetchAll();

    foreach ($tayouHistory as $th) {
        $pts = (int) $th['points'];
        if ($th['type'] === 'positive') {
            $positiveTayou += $pts;
            $totalTayou += $pts;
        } else {
            $negativeTayou += $pts;
            $totalTayou -= $pts;
        }
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.report-paper {
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 2.5rem;
    max-width: 1050px;
    margin: 1.5rem auto 3rem auto;
    box-shadow: var(--shadow-md);
}

.report-section-title {
    color: var(--royal-blue);
    font-size: 1.15rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0.5rem;
    margin-top: 2rem;
    margin-bottom: 1.25rem;
}

.stat-grid-4 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card-custom {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 1.2rem;
    text-align: center;
    transition: transform 0.2s ease;
}

.stat-card-custom:hover {
    transform: translateY(-2px);
}

.stat-num {
    font-size: 2rem;
    font-weight: 900;
    line-height: 1.1;
    margin-top: 0.35rem;
}

.badge-tayou {
    background: var(--gold-glow);
    color: var(--gold);
    border: 1px solid var(--gold);
    font-weight: 800;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

@media print {
    .no-print, header, .app-sidebar, footer, .navbar-custom {
        display: none !important;
    }
    .app-container {
        display: block !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
    .report-paper {
        background: #ffffff !important;
        color: #000000 !important;
        border: 2px solid #000 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 1rem !important;
    }
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Toolbar (No-Print) -->
        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.25rem;">التقرير المفصل للمخدوم 📋</h1>
                <p style="color:var(--text-muted); font-size:0.95rem;">نسبة الحضور والغياب، درجات الاختبارات، جدول خدمة القداسات، ورصيد الطايو</p>
            </div>

            <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                <!-- Class Filter Dropdown -->
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <label style="font-weight:700; color:var(--text-main); white-space:nowrap;">🏫 الفصل الدراسي:</label>
                    <select id="classFilterSelect" class="form-control" style="min-width:200px;" onchange="window.location.href='<?= BASE_URL ?>admin/student_report.php?class_id=' + this.value">
                        <option value="all" <?= ($selectedClassId === null) ? 'selected' : '' ?>>-- جميع الفصول --</option>
                        <?php foreach ($availableClasses as $cls) { ?>
                            <option value="<?= $cls['id'] ?>" <?= ($selectedClassId == $cls['id']) ? 'selected' : '' ?>>
                                <?= sanitize($cls['stage_name']) ?> - <?= sanitize($cls['grade_name']) ?> (<?= sanitize($cls['class_name']) ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Student Switcher Dropdown -->
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <label style="font-weight:700; color:var(--text-main); white-space:nowrap;">👦 الشماس:</label>
                    <select class="form-control" style="min-width:230px;" onchange="if(this.value) window.location.href='<?= BASE_URL ?>admin/student_report.php?id=' + this.value + '<?= $selectedClassId ? "&class_id={$selectedClassId}" : '' ?>'">
                        <?php if (empty($availableStudents)) { ?>
                            <option value="">لا يوجد مخدومين بهذا الفصل</option>
                        <?php } else { ?>
                            <?php foreach ($availableStudents as $s) { ?>
                                <option value="<?= $s['id'] ?>" <?= $studentId == $s['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($s['full_name']) ?> (<?= sanitize($s['grade_name'] ?? '') ?> - <?= sanitize($s['class_name'] ?? '') ?>)
                                </option>
                            <?php } ?>
                        <?php } ?>
                    </select>
                </div>

                <button onclick="window.print()" class="btn btn-gold" style="font-weight:800;">
                    🖨️ طباعة التقرير الرسمي
                </button>
            </div>
        </div>

        <?php if (! $student) { ?>
            <div class="glass-card" style="text-align:center; padding:3rem;">
                <div style="font-size:3rem; margin-bottom:1rem;">👦</div>
                <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">لا يوجد مخدومين في هذا الفصل</h3>
                <p style="color:var(--text-muted); margin-bottom:1.5rem;">لم يتم العثور على شمامسة مسجلين ضمن الفصل المختار. يمكنك اختيار فصل آخر أو عرض جميع الفصول.</p>
                <a href="<?= BASE_URL ?>admin/student_report.php?class_id=all" class="btn btn-primary">عرض جميع الفصول</a>
            </div>
        <?php } else { ?>

            <!-- The Report Paper for Viewing & Official Printing -->
            <div class="report-paper">
                <!-- Header Banner -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #e2e8f0; padding-bottom:1.5rem; margin-bottom:1.5rem;">
                    <div style="text-align:right;">
                        <h2 style="color:#1e3a8a; font-weight:900; margin:0 0 0.25rem 0;">مدرسة الشهيد إسطفانوس للشمامسة</h2>
                        <div style="font-size:0.9rem; color:#64748b;"><?= CHURCH_NAME ?></div>
                        <div style="font-size:0.85rem; color:#94a3b8; margin-top:0.25rem;">
                            تاريخ إصدار التقرير: <strong><?= format_arabic_date(date('Y-m-d')) ?></strong>
                        </div>
                    </div>

                    <div style="text-align:center;">
                        <img src="<?= BASE_URL ?>assets/images/logo.png" style="height:65px; width:auto; object-fit:contain; margin-bottom:0.35rem;" alt="شعار المدرسة">
                        <div style="font-size:0.9rem; font-weight:800; color:#b45309; letter-spacing:0.5px;">التقرير الشامل لأداء المخدوم</div>
                    </div>

                    <div style="text-align:left;">
                        <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($student['profile_pic'] ?: 'default-avatar.png') ?>" 
                             style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid #d4af37;"
                             onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'" alt="صورة الشماس">
                    </div>
                </div>

                <!-- Deacon Info Card -->
                <div style="background:var(--bg-primary); border:1px solid var(--border-color); padding:1.25rem; border-radius:var(--radius-sm); margin-bottom:1.5rem; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">اسم الشماس:</span>
                        <strong style="font-size:1.15rem; color:var(--royal-blue);"><?= sanitize($student['full_name']) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">الرتبة الكنسية:</span>
                        <strong style="color:var(--gold);"><?= sanitize($student['deacon_rank'] ?: 'إبصالتيس (مرتل)') ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">المرحلة والصف:</span>
                        <strong><?= sanitize($student['stage_name'] ?? '') ?> - <?= sanitize($student['grade_name'] ?? '') ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">الفصل الدراسي:</span>
                        <strong><?= sanitize($student['class_name'] ?? 'عام') ?></strong>
                    </div>
                    <?php if (! empty($student['patron_saint'])) { ?>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">شفيع الصف:</span>
                        <strong style="color:var(--royal-blue);">✨ <?= sanitize($student['patron_saint']) ?></strong>
                    </div>
                    <?php } ?>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">كود الشماس (QR):</span>
                        <code class="code-badge"><?= sanitize($student['qr_code_token'] ?? '-') ?></code>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--text-muted); display:block;">هاتف الشماس / ولي الأمر:</span>
                        <strong><?= sanitize($student['phone']) ?> <?= $student['parent_phone'] ? ' / '.sanitize($student['parent_phone']) : '' ?></strong>
                    </div>
                </div>

                <!-- 4 High Level Summary Cards -->
                <div class="stat-grid-4">
                    <!-- Attendance Rate -->
                    <div class="stat-card-custom" style="border-top:4px solid #16a34a;">
                        <span style="font-size:0.85rem; color:#64748b; font-weight:700;">نسبة الحضور</span>
                        <div class="stat-num" style="color:#16a34a;"><?= $attStats['attendance_rate'] ?>%</div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:0.25rem;">
                            حضر <?= $attStats['present'] ?> من أصل <?= $attStats['total'] ?> حصة
                        </div>
                    </div>

                    <!-- Absence Rate -->
                    <div class="stat-card-custom" style="border-top:4px solid #dc2626;">
                        <span style="font-size:0.85rem; color:#64748b; font-weight:700;">نسبة الغياب</span>
                        <div class="stat-num" style="color:#dc2626;"><?= $attStats['absence_rate'] ?>%</div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:0.25rem;">
                            غياب <?= $attStats['absent'] ?> حصة <?= ($attStats['late'] > 0) ? "({$attStats['late']} تأخير)" : '' ?>
                        </div>
                    </div>

                    <!-- Tayou Balance -->
                    <div class="stat-card-custom" style="border-top:4px solid #d4af37;">
                        <span style="font-size:0.85rem; color:#64748b; font-weight:700;">رصيد الطايو الحالي</span>
                        <div class="stat-num" style="color:#b45309;">
                            ⭐ <?= number_format($totalTayou) ?>
                        </div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:0.25rem;">
                            المكتسب: +<?= $positiveTayou ?> | المستبدل: -<?= $negativeTayou ?>
                        </div>
                    </div>

                    <!-- Exam Average -->
                    <div class="stat-card-custom" style="border-top:4px solid #2563eb;">
                        <span style="font-size:0.85rem; color:#64748b; font-weight:700;">متوسط الامتحانات</span>
                        <div class="stat-num" style="color:#1e3a8a;"><?= $examAvg ?>%</div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:0.25rem;">
                            <?= count($examResults) ?> اختبارات مسجلة
                        </div>
                    </div>
                </div>

                <!-- 1. ATTENDANCE & ABSENCE DETAILS -->
                <div class="report-section-title">
                    <span>📅</span>
                    <span>1. تفاصيل الحضور والغياب (المواظبة الدراسية والطقسية)</span>
                </div>
                <?php if (empty($attHistory)) { ?>
                    <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:1.5rem;">لا توجد سجلات حضور مسجلة للشماس حتى الآن.</p>
                <?php } else { ?>
                    <div class="table-responsive" style="margin-bottom:1.5rem;">
                        <table class="custom-table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>تاريخ الحصة</th>
                                    <th>الحالة</th>
                                    <th>وقت التسجيل</th>
                                    <th>الخادم المسجل</th>
                                    <th>ملاحظات الحضور</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attHistory as $att) { ?>
                                    <tr>
                                        <td><strong><?= format_arabic_date($att['attendance_date']) ?></strong></td>
                                        <td>
                                            <?php if ($att['status'] === 'present') { ?>
                                                <span class="badge badge-success">حاضر ✅</span>
                                            <?php } elseif ($att['status'] === 'late') { ?>
                                                <span class="badge badge-warning">تأخير ⏰</span>
                                            <?php } elseif ($att['status'] === 'excused') { ?>
                                                <span class="badge badge-info">عذر مقبول 📝</span>
                                            <?php } else { ?>
                                                <span class="badge badge-danger">غائب ❌</span>
                                            <?php } ?>
                                        </td>
                                        <td><?= ! empty($att['scanned_at']) ? date('h:i A', strtotime($att['scanned_at'])) : '-' ?></td>
                                        <td><?= sanitize($att['scanned_by_name'] ?? 'النظام الآلي') ?></td>
                                        <td><?= sanitize($att['notes'] ?? '-') ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <!-- 2. EXAM SCORES -->
                <div class="report-section-title">
                    <span>📝</span>
                    <span>2. درجات الامتحانات والاختبارات التقييمية</span>
                </div>
                <?php if (empty($examResults)) { ?>
                    <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:1.5rem;">لم يقم الشماس بأداء أي امتحانات حتى الآن.</p>
                <?php } else { ?>
                    <div class="table-responsive" style="margin-bottom:1.5rem;">
                        <table class="custom-table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>عنوان الامتحان</th>
                                    <th>الدرجة المحصلة</th>
                                    <th>الدرجة الكلية</th>
                                    <th>النسبة المئوية والتقدير</th>
                                    <th>تاريخ أداء الامتحان</th>
                                    <th>الخادم الممتحِن</th>
                                    <th>إجابات المخدوم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examResults as $ex) {
                                    $tot = (float) ($ex['max_marks'] ?: 100);
                                    $sc = (float) $ex['score'];
                                    $pct = ($tot > 0) ? round(($sc / $tot) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?= sanitize($ex['exam_title']) ?></strong></td>
                                        <td><span style="font-weight:900; color:#1e3a8a; font-size:1.1rem;"><?= $sc ?></span></td>
                                        <td><?= $tot ?></td>
                                        <td>
                                            <span class="badge <?= $pct >= 85 ? 'badge-success' : ($pct >= 70 ? 'badge-info' : ($pct >= 50 ? 'badge-warning' : 'badge-danger')) ?>">
                                                <?= $pct ?>% - <?= $pct >= 85 ? 'ممتاز 🌟' : ($pct >= 75 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : ($pct >= 50 ? 'مقبول' : 'راسب'))) ?>
                                            </span>
                                        </td>
                                        <td><?= ! empty($ex['taken_at']) ? format_arabic_date(substr($ex['taken_at'], 0, 10)) : '-' ?></td>
                                        <td><?= sanitize($ex['servant_name'] ?? 'خادم الفصل') ?></td>
                                        <td>
                                            <a href="<?= ($currentUserRole === 'servant') ? BASE_URL.'servant/student_answers.php?result_id='.$ex['id'] : BASE_URL.'admin/student_answers.php?result_id='.$ex['id'] ?>" 
                                               class="btn btn-primary btn-sm" 
                                               style="padding:0.3rem 0.65rem; font-size:0.8rem;" 
                                               title="مشاهدة إجابات الشماس وما جاوبه صح وخطأ">
                                                👁️ عرض الإجابات
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <!-- 3. LITURGY SERVICE SCHEDULE -->
                <div class="report-section-title">
                    <span>⛪</span>
                    <span>3. جدول خدمة القداسات الإلهية (مواعيد وأدوار الخدمة)</span>
                </div>
                <?php if (empty($liturgyHistory)) { ?>
                    <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:1.5rem;">لم يتم تكليف الشماس بخدمة في جدول القداسات بعد.</p>
                <?php } else { ?>
                    <div class="table-responsive" style="margin-bottom:1.5rem;">
                        <table class="custom-table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>تاريخ القداس</th>
                                    <th>المناسبة / القداس</th>
                                    <th>الدور المكلف به</th>
                                    <th>حالة الخدمة / التأكيد</th>
                                    <th>الشماس البديل (إن وجد)</th>
                                    <th>ملاحظات وتوجيهات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liturgyHistory as $lit) {
                                    $isSubstitute = ($lit['substitute_student_id'] == $studentId);
                                    ?>
                                    <tr>
                                        <td><strong><?= format_arabic_date($lit['service_date']) ?></strong></td>
                                        <td><?= sanitize($lit['title']) ?></td>
                                        <td>
                                            <span class="badge badge-gold" style="font-weight:700;">
                                                <?= sanitize($lit['role_name'] ?: 'خدمة مذبح') ?>
                                            </span>
                                            <?php if ($isSubstitute) { ?>
                                                <span class="badge badge-info" style="font-size:0.75rem;">شماس بديل</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($lit['student_status'] === 'confirmed') { ?>
                                                <span class="badge badge-success">تم التأكيد بحضور القداس ✅</span>
                                            <?php } elseif ($lit['student_status'] === 'apologized') { ?>
                                                <span class="badge badge-danger">اعتذار عن الخدمة ⚠️</span>
                                            <?php } else { ?>
                                                <span class="badge badge-warning">قيد المتابعة والتأكيد ⏳</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?= ! empty($lit['substitute_name']) ? sanitize($lit['substitute_name']) : '-' ?>
                                        </td>
                                        <td>
                                            <?= sanitize($lit['liturgy_notes'] ?: ($lit['response_notes'] ?: '-')) ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <!-- 4. TAYOU (الطايو) BALANCE & TRANSACTIONS -->
                <div class="report-section-title">
                    <span>⭐</span>
                    <span>4. رصيد وسجل الطايو (مكافآت التميز والتشجيع الكنسي)</span>
                </div>
                <?php if (empty($tayouHistory)) { ?>
                    <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:1.5rem;">لا توجد حركات طايو مسجلة للشماس حتى الآن.</p>
                <?php } else { ?>
                    <div class="table-responsive" style="margin-bottom:2rem;">
                        <table class="custom-table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>قيمة الطايو</th>
                                    <th>النوع</th>
                                    <th>الداعي / سبب منح أو خصم الطايو</th>
                                    <th>الخادم المسؤول</th>
                                    <th>تاريخ الحركة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tayouHistory as $th) { ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= $th['type'] === 'positive' ? 'badge-success' : 'badge-danger' ?>" style="font-weight:800; font-size:0.95rem;">
                                                <?= $th['type'] === 'positive' ? '+' : '-' ?><?= $th['points'] ?> طايو
                                            </span>
                                        </td>
                                        <td>
                                            <?= $th['type'] === 'positive' ? 'طايو تشجيعي 🌟' : 'خصم / استبدال 🛍️' ?>
                                        </td>
                                        <td><strong><?= sanitize($th['reason']) ?></strong></td>
                                        <td><?= sanitize($th['servant_name'] ?? 'خادم الفصل') ?></td>
                                        <td><?= format_arabic_date(substr($th['created_at'], 0, 10)) ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <!-- Official Signature Footer for Printing -->
                <div style="display:flex; justify-content:space-around; align-items:center; border-top:1px solid #e2e8f0; padding-top:2rem; margin-top:2rem;">
                    <div style="text-align:center;">
                        <div style="font-size:0.95rem; font-weight:800; color:#1e3a8a;">خادم الفصل المسؤول</div>
                        <div style="margin-top:2.5rem; width:150px; border-top:1px dashed #94a3b8;"></div>
                    </div>

                    <div style="text-align:center;">
                        <div style="font-size:2rem; color:#d4af37;">⛪</div>
                        <div style="font-size:0.8rem; color:#94a3b8; font-weight:700;">خاتم مدرسة الشمامسة</div>
                    </div>

                    <div style="text-align:center;">
                        <div style="font-size:0.95rem; font-weight:800; color:#1e3a8a;">أب اعتراف ومسؤول الخدمة</div>
                        <div style="margin-top:2.5rem; width:150px; border-top:1px dashed #94a3b8;"></div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

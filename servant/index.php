<?php
$pageTitle = 'لوحة الخادم';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('servant', 'admin');

$db = getDB();
$servantId = $_SESSION['user']['id'];

// Servant assigned classes
$assignedClasses = $db->prepare('
    SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
    FROM servant_classes sc
    JOIN classes c ON sc.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    JOIN stages s ON g.stage_id = s.id
    WHERE sc.servant_id = ?
');
$assignedClasses->execute([$servantId]);
$classes = $assignedClasses->fetchAll();

// Total students in servant's classes
$totalClassStudents = 0;
if ($classes) {
    $classIds = array_column($classes, 'id');
    $inClause = implode(',', array_fill(0, count($classIds), '?'));
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM users WHERE class_id IN ($inClause) AND role = 'student'");
    $stmtCount->execute($classIds);
    $totalClassStudents = $stmtCount->fetchColumn();
}

$today = date('Y-m-d');
$scannedToday = $db->prepare('SELECT COUNT(*) FROM attendance WHERE servant_id = ? AND attendance_date = ?');
$scannedToday->execute([$servantId, $today]);
$countScannedToday = $scannedToday->fetchColumn();

// Upcoming liturgy services for servant's classes
$upcomingRosterStudents = [];
if ($classes) {
    $classIds = array_column($classes, 'id');
    $inClause = implode(',', array_fill(0, count($classIds), '?'));
    $stmtRoster = $db->prepare("
        SELECT rs.*, r.title as liturgy_title, r.service_date, u.full_name as student_name, c.name_ar as class_name
        FROM liturgy_roster_students rs
        JOIN liturgy_roster r ON rs.roster_id = r.id
        JOIN users u ON rs.student_id = u.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.class_id IN ($inClause) AND r.service_date >= ?
        ORDER BY r.service_date ASC
        LIMIT 6
    ");
    $stmtRoster->execute(array_merge($classIds, [$today]));
    $upcomingRosterStudents = $stmtRoster->fetchAll();
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">لوحة الخادم المسؤول ✝️</h1>
                <p style="color:var(--text-muted);">مرحباً بك، الخادم/ة <?= sanitize($_SESSION['user']['full_name']) ?></p>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>servant/attendance.php" class="btn btn-gold">
                    📷 ماسح الحضور QR
                </a>
                <a href="<?= BASE_URL ?>servant/exams.php" class="btn btn-primary">
                    📝 إدارة وتصحيح الامتحانات
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">عدد الشمامسة بالفصول الموكلة</div>
                    <div class="stat-val"><?= $totalClassStudents ?></div>
                </div>
                <div class="stat-icon">👦</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">تسجيلات حضور اليوم</div>
                    <div class="stat-val"><?= $countScannedToday ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(34, 197, 94, 0.15); color:#16a34a;">✅</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">الفصول المخصصة لي</div>
                    <div class="stat-val"><?= count($classes) ?></div>
                </div>
                <div class="stat-icon" style="background:var(--royal-blue-glow); color:var(--royal-blue);">🏫</div>
            </div>
        </div>

        <!-- Upcoming Liturgy Duties for Servant's Classes -->
        <div class="glass-card" style="margin-bottom:2rem; border-top:4px solid var(--gold);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
                <div>
                    <h3 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.5rem;">
                        <span>⛪</span> تكليفات خدمة القداسات القادمة لمخدومي فصولك
                    </h3>
                    <span style="font-size:0.85rem; color:var(--text-muted);">الشمامسة من فصولك الذين اختارهم الآدمن لخدمة القداسات القادمة</span>
                </div>
                <a href="<?= BASE_URL ?>admin/roster.php" class="btn btn-secondary btn-sm" style="font-weight:700;">
                    عرض جدول الخدمة بالكامل ➔
                </a>
            </div>

            <?php if (empty($upcomingRosterStudents)) { ?>
                <p style="color:var(--text-muted); text-align:center; padding:1.25rem; margin:0;">
                    لا يوجد حالياً شمامسة من فصولك عليهم خدمة في القداسات القادمة.
                </p>
            <?php } else { ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                    <?php foreach ($upcomingRosterStudents as $urs) {
                        $uBadge = 'badge-info';
                        if ($urs['role_name'] === 'خدمة مذبح') {
                            $uBadge = 'badge-gold';
                        } elseif ($urs['role_name'] === 'خدمة باكر') {
                            $uBadge = 'badge-primary';
                        } elseif (str_contains($urs['role_name'] ?? '', 'إنجيل')) {
                            $uBadge = 'badge-success';
                        }
                        ?>
                        <div style="background:var(--bg-primary); padding:1rem 1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); display:flex; flex-direction:column; justify-content:space-between; gap:0.5rem;">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <strong style="color:var(--royal-blue); font-size:1rem;"><?= sanitize($urs['student_name']) ?></strong>
                                    <span class="badge <?= $uBadge ?>" style="font-weight:800; font-size:0.8rem;"><?= sanitize($urs['role_name']) ?></span>
                                </div>
                                <span style="font-size:0.8rem; color:var(--text-muted); display:block; margin-top:0.2rem;">
                                    فصل: <?= sanitize($urs['class_name'] ?: '-') ?>
                                </span>
                                <div style="font-size:0.85rem; font-weight:700; color:var(--text-primary); margin-top:0.4rem;">
                                    ⛪ <?= sanitize($urs['liturgy_title']) ?>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed var(--border-color); padding-top:0.5rem; font-size:0.8rem;">
                                <span style="color:var(--text-muted);">📅 <?= format_arabic_date($urs['service_date']) ?></span>
                                <?php if (($urs['status'] ?? '') === 'confirmed') { ?>
                                    <span class="badge badge-success" style="font-size:0.75rem;">مؤكد ✅</span>
                                <?php } elseif (($urs['status'] ?? '') === 'declined') { ?>
                                    <span class="badge badge-danger" style="font-size:0.75rem;">اعتذار ⚠️</span>
                                <?php } else { ?>
                                    <span class="badge badge-warning" style="font-size:0.75rem;">قيد الانتظار ⏳</span>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem;">الفصول المسندة إليك</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
                <?php foreach ($classes as $c) { ?>
                    <div style="background:var(--bg-primary); padding:1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <h4 style="color:var(--gold); font-weight:800;"><?= sanitize($c['stage_name']) ?></h4>
                        <p style="font-weight:700; color:var(--text-primary); margin-top:0.25rem;"><?= sanitize($c['grade_name']) ?> - <?= sanitize($c['class_name']) ?></p>
                        <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                            <a href="<?= BASE_URL ?>servant/attendance.php?class_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">تسجيل الحضور</a>
                            <a href="<?= BASE_URL ?>servant/exams.php?class_id=<?= $c['id'] ?>" class="btn btn-gold btn-sm">الامتحانات</a>
                            <a href="<?= BASE_URL ?>servant/students.php?class_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">الطلاب</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

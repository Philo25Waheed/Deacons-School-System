<?php
$pageTitle = 'تقارير المدرسة الشاملة';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin');

$db = getDB();
$isMysql = ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
$groupConcatServant = $isMysql
    ? "GROUP_CONCAT(u2.full_name SEPARATOR '، ')"
    : "GROUP_CONCAT(u2.full_name, '، ')";

// 1. High-level Overview Counters (Defensive)
try {
    $totalStudents = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
    $totalServants = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'servant' AND status = 'active'")->fetchColumn();
    $totalParents = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();
    $totalClasses = (int) $db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
    $totalStages = (int) $db->query('SELECT COUNT(*) FROM stages')->fetchColumn();

    // Check both liturgy_roster and liturgy_rosters
    $totalLiturgies = 0;
    try {
        $totalLiturgies = (int) $db->query('SELECT COUNT(*) FROM liturgy_roster')->fetchColumn();
    } catch (Throwable $e) {
        try {
            $totalLiturgies = (int) $db->query('SELECT COUNT(*) FROM liturgy_rosters')->fetchColumn();
        } catch (Throwable $e2) {
        }
    }

    $totalExams = (int) $db->query('SELECT COUNT(*) FROM exams')->fetchColumn();
    $totalExamResults = 0;
    try {
        $totalExamResults = (int) $db->query('SELECT COUNT(*) FROM exam_results')->fetchColumn();
    } catch (Throwable $e) {
    }

    // Overall Attendance Rate
    $attTotal = (int) $db->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    $attPresent = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE status = 'present'")->fetchColumn();
    $overallAttendanceRate = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 100;

    // Total Points in System
    $totalPoints = (int) $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) FROM points")->fetchColumn();
} catch (Throwable $e) {
    $totalStudents = $totalStudents ?? 0;
    $totalServants = $totalServants ?? 0;
    $totalParents = $totalParents ?? 0;
    $totalClasses = $totalClasses ?? 0;
    $totalStages = $totalStages ?? 0;
    $totalLiturgies = $totalLiturgies ?? 0;
    $totalExams = $totalExams ?? 0;
    $totalExamResults = $totalExamResults ?? 0;
    $overallAttendanceRate = $overallAttendanceRate ?? 100;
    $totalPoints = $totalPoints ?? 0;
}

// 2. Deacon Ranks Distribution
try {
    $ranksStmt = $db->query("
        SELECT COALESCE(NULLIF(deacon_rank, ''), 'غير محدد') as rank_name, COUNT(*) as rank_count
        FROM users 
        WHERE role = 'student' AND status = 'active'
        GROUP BY COALESCE(NULLIF(deacon_rank, ''), 'غير محدد')
        ORDER BY rank_count DESC
    ");
    $ranksDistribution = $ranksStmt ? $ranksStmt->fetchAll() : [];
} catch (Throwable $e) {
    $ranksDistribution = [];
}

// 3. Classes and Grades Breakdown
try {
    $classesReport = $db->query("
        SELECT c.id, c.name_ar as class_name, g.patron_saint, g.time_from, g.time_to, g.location,
               g.name_ar as grade_name, s.name_ar as stage_name,
               (SELECT {$groupConcatServant} FROM servant_classes cs JOIN users u2 ON cs.servant_id = u2.id WHERE cs.class_id = c.id) as servant_name,
               (SELECT COUNT(*) FROM users WHERE class_id = c.id AND role = 'student' AND status = 'active') as student_count,
               (SELECT COUNT(*) FROM attendance a JOIN users u ON a.student_id = u.id WHERE u.class_id = c.id AND a.status = 'present') as present_count,
               (SELECT COUNT(*) FROM attendance a JOIN users u ON a.student_id = u.id WHERE u.class_id = c.id) as total_attendance,
               (SELECT COALESCE(AVG(er.score * 100.0 / NULLIF(er.total_marks, 0)), 0) FROM exam_results er JOIN users u ON er.student_id = u.id WHERE u.class_id = c.id) as avg_exam_percentage,
               (SELECT COALESCE(SUM(CASE WHEN pt.type = 'positive' THEN pt.points ELSE -pt.points END), 0) FROM points pt JOIN users u ON pt.student_id = u.id WHERE u.class_id = c.id) as class_total_points
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        JOIN stages s ON g.stage_id = s.id
        ORDER BY s.id, g.id, c.name_ar
    ")->fetchAll();
} catch (Throwable $e) {
    $classesReport = [];
}

// 4. Liturgy Roles and Assignments Statistics
try {
    $rosterRolesReport = $db->query("
        SELECT COALESCE(NULLIF(role_name, ''), 'عام') as role_title, COUNT(*) as count
        FROM liturgy_roster_students
        GROUP BY role_title
        ORDER BY 
            CASE role_title
                WHEN 'إنجيل باكر' THEN 1
                WHEN 'بولس' THEN 2
                WHEN 'كاثوليكون' THEN 3
                WHEN 'إبركسيس' THEN 4
                WHEN 'إنجيل القداس' THEN 5
                WHEN 'خدمة مذبح' THEN 6
                WHEN 'خدمة باكر' THEN 7
                ELSE 8
            END
    ")->fetchAll();

    $rosterStatusReport = $db->query('
        SELECT status, COUNT(*) as count
        FROM liturgy_roster_students
        GROUP BY status
    ')->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {
    $rosterRolesReport = [];
    $rosterStatusReport = [];
}

// 5. Honor Roll: Top 10 Active Deacons
try {
    $topStudents = $db->query("
        SELECT u.id, u.full_name, u.phone, u.deacon_rank, c.name_ar as class_name,
               COALESCE(SUM(CASE WHEN pt.type = 'positive' THEN pt.points ELSE -pt.points END), 0) as total_points,
               (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'present') as attendances_count,
               (SELECT COUNT(*) FROM exam_results er WHERE er.student_id = u.id) as exams_completed
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.id
        LEFT JOIN points pt ON u.id = pt.student_id
        WHERE u.role = 'student' AND u.status = 'active'
        GROUP BY u.id, u.full_name, u.phone, u.deacon_rank, c.name_ar
        ORDER BY total_points DESC, attendances_count DESC
        LIMIT 10
    ")->fetchAll();
} catch (Throwable $e) {
    $topStudents = [];
}

// 6. Exams & Academics Summary
try {
    $examsReport = $db->query('
        SELECT e.id, e.title, e.duration_minutes, e.deadline,
               (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
               (SELECT COUNT(*) FROM exam_results WHERE exam_id = e.id) as submissions_count,
               (SELECT AVG(score * 100.0 / NULLIF(total_marks, 0)) FROM exam_results WHERE exam_id = e.id) as avg_percentage,
               (SELECT MAX(score) FROM exam_results WHERE exam_id = e.id) as max_score
        FROM exams e
        ORDER BY e.id DESC
        LIMIT 10
    ')->fetchAll();
} catch (Throwable $e) {
    $examsReport = [];
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
@media print {
    .app-sidebar, .app-navbar, .header-actions, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
    }
    .glass-card, .metric-card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
}

.report-hero {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--royal-blue-glow) 100%);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.metrics-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.metric-stat-card {
    background: var(--bg-surface);
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}

.metric-stat-card:hover {
    border-color: var(--royal-blue);
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.metric-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: var(--royal-blue-glow);
    color: var(--royal-blue);
    flex-shrink: 0;
}

.progress-bar-bg {
    background: var(--border-color);
    border-radius: 999px;
    height: 9px;
    width: 100%;
    overflow: hidden;
    margin-top: 0.4rem;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--royal-blue), var(--gold));
    border-radius: 999px;
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Hero Title & Print Actions -->
        <div class="report-hero">
            <div>
                <div class="badge badge-gold" style="margin-bottom:0.5rem; font-size:0.85rem;">التقرير الإداري الشامل 📑</div>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.75rem; margin-bottom:0.4rem;">
                    تقارير وإحصائيات مدرسة الشهيد إسطفانوس الكنسية
                </h1>
                <p style="color:var(--text-secondary); max-width:650px; font-size:0.95rem; line-height:1.5;">
                    لوحة بيانات متكاملة ترصد أعداد المخدومين، أداء الفصول، نسب حضور القداسات، توزيع الرتب الشماسية، والنتائج الأكاديمية.
                </p>
            </div>
            <div class="no-print" style="display:flex; gap:0.75rem; align-items:center;">
                <button type="button" onclick="window.print()" class="btn btn-primary" style="font-weight:800; padding:0.75rem 1.5rem; display:flex; align-items:center; gap:0.5rem;">
                    <span>🖨️ طباعة التقرير الشامل</span>
                </button>
            </div>
        </div>

        <!-- High-level Metric Counters -->
        <div class="metrics-overview-grid">
            <div class="metric-stat-card">
                <div class="metric-icon-box">👦</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">إجمالي الشمامسة</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= number_format($totalStudents) ?></strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">🏫</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">الفصول والمراحل</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= $totalClasses ?> فصل (<?= $totalStages ?> مراحل)</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">👨‍🏫</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">هيئة الخدام</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= number_format($totalServants) ?> خادم</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">👨‍👩‍👦</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">أولياء الأمور</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= number_format($totalParents) ?> ولي أمر</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">📊</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">نسبة الحضور العام</span>
                    <strong style="font-size:1.5rem; color:var(--gold);"><?= $overallAttendanceRate ?>%</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">⛪</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">القداسات المجدولة</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= number_format($totalLiturgies) ?> قداس</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">📝</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">الاختبارات المنجزة</span>
                    <strong style="font-size:1.5rem; color:var(--royal-blue);"><?= number_format($totalExamResults) ?> تسليم</strong>
                </div>
            </div>

            <div class="metric-stat-card">
                <div class="metric-icon-box">⭐</div>
                <div>
                    <span style="font-size:0.82rem; color:var(--text-muted); display:block; font-weight:700;">بنك الطايو التراكمي</span>
                    <strong style="font-size:1.5rem; color:var(--gold);"><?= number_format($totalPoints) ?> طايو</strong>
                </div>
            </div>
        </div>

        <!-- Section 1: Classes & Stages Detailed Breakdown -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
                <h3 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.5rem;">
                    🏫 تقرير الفصول والمراحل الدراسية الشامل
                </h3>
                <span class="badge badge-gold"><?= count($classesReport) ?> فصل دراسي مسجل</span>
            </div>

            <div class="table-responsive">
                <table class="custom-table" style="font-size:0.88rem;">
                    <thead>
                        <tr>
                            <th>المرحلة والصف</th>
                            <th>اسم الفصل</th>
                            <th>شفيع الفصل 🕊️</th>
                            <th>الموعد والمكان 🕒</th>
                            <th>الخادم المسؤول</th>
                            <th>عدد الشمامسة</th>
                            <th>نسبة الحضور</th>
                            <th>متوسط الامتحانات</th>
                            <th>طايو الفصل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classesReport)) { ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:2rem;">لا توجد فصول دراسية مسجلة حالياً.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($classesReport as $cr) {
                                $attPct = $cr['total_attendance'] > 0 ? round(($cr['present_count'] / $cr['total_attendance']) * 100, 1) : 100;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($cr['stage_name']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($cr['grade_name']) ?></div>
                                    </td>
                                    <td><strong style="color:var(--royal-blue); font-size:0.95rem;"><?= sanitize($cr['class_name']) ?></strong></td>
                                    <td><?= sanitize($cr['patron_saint'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($cr['time_from'] || $cr['location']) { ?>
                                            <span><?= sanitize($cr['time_from'] ? ($cr['time_to'] ? "{$cr['time_from']} - {$cr['time_to']}" : $cr['time_from']) : '') ?></span>
                                            <span style="font-size:0.75rem; color:var(--text-muted); display:block;"><?= sanitize($cr['location'] ?: '') ?></span>
                                        <?php } else { ?>
                                            <span style="color:var(--text-muted);">-</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= sanitize($cr['servant_name'] ?? 'غير معين') ?></td>
                                    <td><span class="badge badge-info"><?= (int) $cr['student_count'] ?> شماس</span></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.5rem;">
                                            <strong><?= $attPct ?>%</strong>
                                            <div class="progress-bar-bg" style="width:60px; margin:0;">
                                                <div class="progress-bar-fill" style="width:<?= min(100, $attPct) ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color:var(--gold);"><?= round($cr['avg_exam_percentage'], 1) ?>%</strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-gold" style="font-weight:700;">
                                            <?= number_format($cr['class_total_points']) ?> طايو ⭐
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
            <!-- Section 2: Deacon Ranks Distribution -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                    📜 توزيع الرتب الكنسية للشمامسة
                </h3>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <?php if (empty($ranksDistribution)) { ?>
                        <p style="color:var(--text-muted); text-align:center;">لا توجد بيانات مسجلة.</p>
                    <?php } else { ?>
                        <?php foreach ($ranksDistribution as $rd) {
                            $pct = $totalStudents > 0 ? round(($rd['rank_count'] / $totalStudents) * 100, 1) : 0;
                            ?>
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem; font-weight:700; margin-bottom:0.25rem;">
                                    <span><?= sanitize($rd['rank_name']) ?></span>
                                    <span style="color:var(--royal-blue);"><?= (int) $rd['rank_count'] ?> شماس (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width:<?= $pct ?>%;"></div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <!-- Section 3: Liturgy Roles & Attendance Status -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                    ⛪ إحصائيات خدمة القداسات وتوزيع الأدوار
                </h3>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.25rem;">
                    <div style="flex:1; background:var(--bg-surface); padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); text-align:center;">
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">مؤكد الحضور</span>
                        <strong style="font-size:1.2rem; color:#10b981;"><?= (int) ($rosterStatusReport['confirmed'] ?? 0) ?> ✅</strong>
                    </div>
                    <div style="flex:1; background:var(--bg-surface); padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); text-align:center;">
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">اعتذار</span>
                        <strong style="font-size:1.2rem; color:#ef4444;"><?= (int) ($rosterStatusReport['declined'] ?? 0) ?> ⚠️</strong>
                    </div>
                    <div style="flex:1; background:var(--bg-surface); padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); text-align:center;">
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">تم الاستبدال</span>
                        <strong style="font-size:1.2rem; color:var(--royal-blue);"><?= (int) ($rosterStatusReport['swapped'] ?? 0) ?> 🔄</strong>
                    </div>
                    <div style="flex:1; background:var(--bg-surface); padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); text-align:center;">
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block;">قيد الانتظار</span>
                        <strong style="font-size:1.2rem; color:var(--gold);"><?= (int) ($rosterStatusReport['pending'] ?? 0) ?> ⏳</strong>
                    </div>
                </div>

                <h5 style="color:var(--text-secondary); font-size:0.88rem; font-weight:800; margin-bottom:0.75rem;">توزيع التكليفات حسب الأدوار الـ 7:</h5>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; font-size:0.85rem;">
                    <?php foreach ($rosterRolesReport as $rr) { ?>
                        <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:0.5rem 0.75rem; display:flex; justify-content:space-between; align-items:center;">
                            <span><?= sanitize($rr['role_title']) ?></span>
                            <span class="badge badge-gold"><?= (int) $rr['count'] ?> تكليف</span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Section 4: Honor Roll (Top 10 Active Deacons) -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
                <h3 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.5rem;">
                    🏆 لوحة الشرف: الشمامسة الأكثر تميزاً والتزاماً
                </h3>
                <span class="badge badge-gold">أعلى 10 شمامسة تفوقاً</span>
            </div>

            <div class="table-responsive">
                <table class="custom-table" style="font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th>المركز</th>
                            <th>الشماس</th>
                            <th>الرتبة</th>
                            <th>الفصل الدراسي</th>
                            <th>مرات الحضور</th>
                            <th>امتحانات منجزة</th>
                            <th>رصيد الطايو</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topStudents)) { ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:2rem;">لا توجد بيانات متاحة.</td></tr>
                        <?php } else { ?>
                            <?php $rankIdx = 1;
                            foreach ($topStudents as $ts) { ?>
                                <tr>
                                    <td>
                                        <?php if ($rankIdx === 1) { ?>
                                            <span style="font-size:1.2rem;">🥇 الأول</span>
                                        <?php } elseif ($rankIdx === 2) { ?>
                                            <span style="font-size:1.2rem;">🥈 الثاني</span>
                                        <?php } elseif ($rankIdx === 3) { ?>
                                            <span style="font-size:1.2rem;">🥉 الثالث</span>
                                        <?php } else { ?>
                                            <span class="badge badge-secondary"><?= $rankIdx ?></span>
                                        <?php } ?>
                                    </td>
                                    <td><strong><?= sanitize($ts['full_name']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= sanitize($ts['deacon_rank'] ?: 'شماس') ?></span></td>
                                    <td><?= sanitize($ts['class_name'] ?: 'بدون فصل') ?></td>
                                    <td><strong><?= (int) $ts['attendances_count'] ?></strong> حضور</td>
                                    <td><?= (int) $ts['exams_completed'] ?></td>
                                    <td>
                                        <strong style="color:var(--gold); font-size:1.05rem;">
                                            <?= number_format($ts['total_points']) ?> طايو ⭐
                                        </strong>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>admin/student_report.php?id=<?= $ts['id'] ?>" class="btn btn-secondary btn-sm" style="font-weight:700;">
                                            📊 التقرير المفصل
                                        </a>
                                    </td>
                                </tr>
                            <?php $rankIdx++;
                            } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 5: Exams & Academics Report -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                📝 تقرير الاختبارات والتحصيل الأكاديمي
            </h3>

            <div class="table-responsive">
                <table class="custom-table" style="font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th>عنوان الاختبار</th>
                            <th>عدد الأسئلة</th>
                            <th>عدد المتقدمين</th>
                            <th>متوسط الدرجات</th>
                            <th>أعلى درجة</th>
                            <th>الموعد النهائي (Deadline)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($examsReport)) { ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">لا توجد اختبارات مضافة بعد.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($examsReport as $ex) { ?>
                                <tr>
                                    <td><strong><?= sanitize($ex['title']) ?></strong></td>
                                    <td><?= (int) $ex['question_count'] ?> سؤال</td>
                                    <td><span class="badge badge-info"><?= (int) $ex['submissions_count'] ?> طالب</span></td>
                                    <td>
                                        <strong style="color:var(--gold);">
                                            <?= $ex['avg_percentage'] !== null ? round($ex['avg_percentage'], 1).'%' : '-' ?>
                                        </strong>
                                    </td>
                                    <td><?= $ex['max_score'] !== null ? (int) $ex['max_score'] : '-' ?></td>
                                    <td>
                                        <?php if ($ex['deadline']) { ?>
                                            <span><?= format_arabic_date(substr($ex['deadline'], 0, 10)) ?> <?= substr($ex['deadline'], 11, 5) ?></span>
                                        <?php } else { ?>
                                            <span style="color:var(--text-muted);">مفتوح بدون حد</span>
                                        <?php } ?>
                                    </td>
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

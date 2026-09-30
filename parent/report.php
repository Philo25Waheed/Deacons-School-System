<?php
$pageTitle = 'التقرير الروحي والدراسي الشامل للابن';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('parent', 'admin', 'servant');

$db = getDB();
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userRole = $_SESSION['user']['role'] ?? '';
$currentUserId = $_SESSION['user']['id'] ?? 0;

if (! $studentId) {
    // If not given, pick first linked child or first student
    if ($userRole === 'parent') {
        $chStmt = $db->prepare('SELECT student_id FROM parent_student WHERE parent_id = ? LIMIT 1');
        $chStmt->execute([$currentUserId]);
        $studentId = $chStmt->fetchColumn();
    } else {
        $studentId = $db->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    }
}

if (! $studentId) {
    header('Location: '.BASE_URL.'parent/index.php');
    exit;
}

// IDOR Authorization checks
if ($userRole === 'parent') {
    $authStmt = $db->prepare('SELECT 1 FROM parent_student WHERE parent_id = ? AND student_id = ?');
    $authStmt->execute([$currentUserId, $studentId]);
    if (! $authStmt->fetchColumn()) {
        $_SESSION['flash_error'] = 'غير مصرح لك بالوصول لتقرير هذا الطالب.';
        header('Location: '.BASE_URL.'parent/index.php');
        exit;
    }
} elseif ($userRole === 'servant') {
    if (! can_servant_access_student($currentUserId, $studentId, $userRole)) {
        $_SESSION['flash_error'] = 'غير مصرح لك بالوصول لتقرير طالب خارج فصول خدمتك.';
        header('Location: '.BASE_URL.'servant/index.php');
        exit;
    }
}

// Fetch student full record
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

// Analytics: Attendance
$attTotal = $db->prepare('SELECT COUNT(*) FROM attendance WHERE student_id = ?');
$attTotal->execute([$studentId]);
$totalSessions = $attTotal->fetchColumn() ?: 0;

$attPresent = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'");
$attPresent->execute([$studentId]);
$presentSessions = $attPresent->fetchColumn() ?: 0;

$attLate = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'late'");
$attLate->execute([$studentId]);
$lateSessions = $attLate->fetchColumn() ?: 0;

$attAbsent = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'absent'");
$attAbsent->execute([$studentId]);
$absentSessions = $attAbsent->fetchColumn() ?: 0;

$attendanceRate = ($totalSessions > 0) ? round((($presentSessions + ($lateSessions * 0.5)) / $totalSessions) * 100) : 100;

// Analytics: Points
$ptsStmt = $db->prepare('SELECT SUM(points) FROM points WHERE student_id = ?');
$ptsStmt->execute([$studentId]);
$totalPoints = $ptsStmt->fetchColumn() ?: 0;

// Exams
$examResults = $db->prepare('
    SELECT r.*, e.title as exam_title
    FROM exam_results r
    JOIN exams e ON r.exam_id = e.id
    WHERE r.student_id = ?
    ORDER BY r.id DESC
');
$examResults->execute([$studentId]);
$childExams = $examResults->fetchAll();

// Evaluations
$evals = $db->prepare('SELECT * FROM evaluations WHERE student_id = ? ORDER BY id DESC LIMIT 5');
$evals->execute([$studentId]);
$evalList = $evals->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.report-card-paper {
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 2.5rem;
    max-width: 900px;
    margin: 2rem auto;
    box-shadow: var(--shadow-md);
}

.report-header-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
}

.stat-metric-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 1.25rem;
    text-align: center;
}

.stat-metric-val {
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--royal-blue);
    margin-top: 0.25rem;
}

@media print {
    .no-print, header, .app-sidebar, footer {
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
    .report-card-paper {
        border: 2px solid #000 !important;
        box-shadow: none !important;
        padding: 1rem !important;
        background: #fff !important;
        color: #000 !important;
    }
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">التقرير الروحي والدراسي الشامل 📊</h1>
                <p style="color:var(--text-muted); font-size:0.95rem;">كشف حساب وتقييم أداء رسمي للشماس</p>
            </div>
            <button onclick="window.print()" class="btn btn-gold" style="font-weight:800; padding:0.75rem 1.5rem;">
                🖨️ طباعة التقرير (PDF)
            </button>
        </div>

        <!-- Official Report Paper -->
        <div class="report-card-paper">
            <div class="report-header-banner">
                <div style="text-align:right;">
                    <h3 style="color:#1e3a8a; font-weight:900; margin-bottom:0.25rem;">مدرسة الشهيد إسطفانوس</h3>
                    <div style="font-size:0.88rem; color:#64748b;"><?= CHURCH_NAME ?></div>
                    <div style="font-size:0.82rem; color:#94a3b8;">تاريخ استخراج التقرير: <?= format_arabic_date(date('Y-m-d')) ?></div>
                </div>

                <div style="text-align:center;">
                    <img src="<?= BASE_URL ?>assets/images/logo.png" style="height:55px; width:auto; object-fit:contain; margin-bottom:0.25rem;" alt="شعار المدرسة">
                    <div style="font-size:0.85rem; font-weight:700; color:#b45309;">تقرير المتابعة الشهري</div>
                </div>

                <div style="text-align:left;">
                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($child['profile_pic'] ?: 'default-avatar.png') ?>" 
                         style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:3px solid #d4af37;"
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'" alt="صورة الشماس">
                </div>
            </div>

            <!-- Student Bio Bar -->
            <div style="background:var(--bg-primary); border:1px solid var(--border-color); padding:1.25rem; border-radius:var(--radius-sm); margin-bottom:2rem; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">اسم الشماس:</span>
                    <strong style="font-size:1.1rem; color:var(--royal-blue);"><?= sanitize($child['full_name']) ?></strong>
                </div>
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">الرتبة الشموسية:</span>
                    <strong style="color:var(--gold);"><?= sanitize($child['deacon_rank'] ?? 'شماس') ?></strong>
                </div>
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">المرحلة والصف:</span>
                    <strong><?= sanitize($child['stage'] ?? '') ?> - <?= sanitize($child['grade'] ?? '') ?></strong>
                </div>
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">كود الشماس:</span>
                    <span class="badge badge-info" style="font-family:monospace;"><?= sanitize($child['qr_code_token'] ?? '') ?></span>
                </div>
            </div>

            <!-- 4 Key Analytics Cards -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem;">
                <div class="stat-metric-card">
                    <span style="font-size:0.85rem; color:var(--text-muted);">نسبة الالتزام بالحضور</span>
                    <div class="stat-metric-val" style="color:<?= $attendanceRate >= 80 ? '#16a34a' : '#ea580c' ?>;"><?= $attendanceRate ?>%</div>
                </div>

                <div class="stat-metric-card">
                    <span style="font-size:0.85rem; color:var(--text-muted);">رصيد الطايو والأوسمة</span>
                    <div class="stat-metric-val" style="color:var(--gold);">⭐ <?= $totalPoints ?> طايو</div>
                </div>

                <div class="stat-metric-card">
                    <span style="font-size:0.85rem; color:var(--text-muted);">القداسات المحضورة</span>
                    <div class="stat-metric-val"><?= $presentSessions ?> <span style="font-size:0.9rem; color:var(--text-muted);">/ <?= $totalSessions ?></span></div>
                </div>

                <div class="stat-metric-card">
                    <span style="font-size:0.85rem; color:var(--text-muted);">الامتحانات المنجزة</span>
                    <div class="stat-metric-val"><?= count($childExams) ?> <span style="font-size:0.9rem; color:var(--text-muted);">اختبار</span></div>
                </div>
            </div>

            <!-- Exam Performance Table -->
            <div style="margin-bottom:2rem;">
                <h4 style="color:var(--royal-blue); font-weight:800; border-bottom:1px solid var(--border-color); padding-bottom:0.5rem; margin-bottom:1rem;">
                    📝 درجات الاختبارات والألحان
                </h4>
                <?php if (empty($childExams)) { ?>
                    <p style="color:var(--text-muted); font-size:0.9rem;">لا توجد اختبارات مسجلة للشماس في هذه الفترة.</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="custom-table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>اسم الاختبار</th>
                                    <th>الدرجة المحصلة</th>
                                    <th>الدرجة الكلية</th>
                                    <th>النسبة والتقدير</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($childExams as $ex) {
                                    $totalMarks = $ex['total_marks'] ?? 0;
                                    $pct = ($totalMarks > 0) ? round(($ex['score'] / $totalMarks) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?= sanitize($ex['exam_title']) ?></strong></td>
                                        <td><span style="font-weight:800; color:var(--royal-blue); font-size:1.05rem;"><?= $ex['score'] ?></span></td>
                                        <td><?= $totalMarks ?></td>
                                        <td>
                                            <span class="badge <?= $pct >= 85 ? 'badge-success' : ($pct >= 65 ? 'badge-info' : 'badge-warning') ?>">
                                                <?= $pct ?>% (<?= $pct >= 85 ? 'ممتاز 🌟' : ($pct >= 75 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : 'يحتاج مراجعة')) ?>)
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>

            <!-- Behavioral & Spiritual Remarks -->
            <div style="margin-bottom:2.5rem;">
                <h4 style="color:var(--royal-blue); font-weight:800; border-bottom:1px solid var(--border-color); padding-bottom:0.5rem; margin-bottom:1rem;">
                    🕊️ تقييم السلوك والمواظبة الروحية
                </h4>
                <?php if (empty($evalList)) { ?>
                    <p style="color:var(--text-muted); font-size:0.9rem;">الشماس مواظب وذو سيرة حسنة ومثال طيب لزملائه في الخدمة.</p>
                <?php } else { ?>
                    <?php foreach ($evalList as $ev) { ?>
                        <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:0.85rem 1rem; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-size:0.85rem; color:var(--text-muted);"><?= format_arabic_date($ev['evaluation_date']) ?>:</span>
                                <strong style="color:var(--text-primary); margin-right:0.5rem;"><?= sanitize($ev['notes'] ?: 'التزام ممتاز بالهدوء والوقار داخل الهيكل') ?></strong>
                            </div>
                            <div>
                                <span class="badge badge-gold">السلوك: <?= $ev['behavior_score'] ?>/5</span>
                                <span class="badge badge-info">حفظ الألحان: <?= $ev['hymn_memorization'] ?>/5</span>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>

            <!-- Signatures line -->
            <div style="display:flex; justify-content:space-around; align-items:center; border-top:1px solid #e2e8f0; padding-top:1.5rem;">
                <div style="text-align:center;">
                    <div style="font-size:0.9rem; font-weight:700; color:#1e3a8a;">أمين أسرة الخدام</div>
                    <div style="margin-top:2rem; width:140px; border-top:1px dashed #94a3b8;"></div>
                </div>

                <div style="text-align:center;">
                    <div style="font-size:1.8rem; color:#d4af37;">⛪</div>
                    <div style="font-size:0.75rem; color:#94a3b8;">خاتم المدرسة الرسمي</div>
                </div>

                <div style="text-align:center;">
                    <div style="font-size:0.9rem; font-weight:700; color:#1e3a8a;">راعي الكنيسة المشرف</div>
                    <div style="margin-top:2rem; width:140px; border-top:1px dashed #94a3b8;"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'لوحة الشماس الطالب';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];

// Total Points
$totalPoints = $db->prepare("
    SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0)
    FROM points WHERE student_id = ?
");
$totalPoints->execute([$studentId]);
$pointsBalance = $totalPoints->fetchColumn();

// Total Attendance
$totalAttendance = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'");
$totalAttendance->execute([$studentId]);
$attendanceDays = $totalAttendance->fetchColumn();

// Announcements
$announcements = $db->query("SELECT * FROM announcements WHERE target_type IN ('everyone', 'students') ORDER BY id DESC LIMIT 5")->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">أهلاً بك يا شماس، <?= sanitize($_SESSION['user']['full_name']) ?> ⛪</h1>
                <p style="color:var(--text-muted);">مدرسة الشمامسة - الكنيسة القبطية الأرثوذكسية</p>
            </div>
            <a href="<?= BASE_URL ?>student/card.php" class="btn btn-gold">
                🪪 كارت الشماس الرقمي
            </a>
        </div>

        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">رصيد نقاط التشجيع</div>
                    <div class="stat-val" style="color:var(--gold);">⭐ <?= number_format($pointsBalance) ?></div>
                </div>
                <div class="stat-icon" style="background:var(--gold-glow); color:var(--gold);">🏆</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">عدد مرات الحضور</div>
                    <div class="stat-val"><?= $attendanceDays ?> يوم</div>
                </div>
                <div class="stat-icon" style="background:rgba(34, 197, 94, 0.15); color:#16a34a;">📅</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">الأوسمة المكتسبة</div>
                    <div class="stat-val"><?= ($pointsBalance >= 20) ? '3 أوسمة' : 'وسام واحد' ?></div>
                </div>
                <div class="stat-icon" style="background:var(--royal-blue-glow); color:var(--royal-blue);">🎖️</div>
            </div>
        </div>

        <!-- Announcements Feed -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem;">📢 الإعلانات والتنبيهات الهامة</h3>
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <?php foreach ($announcements as $ann) { ?>
                    <div style="background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); border-right:4px solid var(--royal-blue);">
                        <h4 style="color:var(--royal-blue); font-weight:700;"><?= sanitize($ann['title']) ?></h4>
                        <p style="color:var(--text-secondary); margin-top:0.4rem; white-space:pre-line;"><?= sanitize($ann['content']) ?></p>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.5rem;"><?= format_arabic_date($ann['created_at']) ?></div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'لوحة تحكم المدير المسئول';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin');

$db = getDB();

// Statistics Queries
$totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
$totalServants = $db->query("SELECT COUNT(*) FROM users WHERE role = 'servant' AND status = 'active'")->fetchColumn();
$totalParents = $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();

// Attendance today
$today = date('Y-m-d');
$presentToday = $db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = '$today' AND status = 'present'")->fetchColumn();
$attendanceRate = ($totalStudents > 0) ? round(($presentToday / $totalStudents) * 100, 1) : 0;

$totalPointsSum = $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) FROM points")->fetchColumn();
$pendingUsersCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// Newest Users
$newestUsers = $db->query('
    SELECT u.id, u.full_name, u.role, u.status, u.created_at, s.name_ar as stage
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    ORDER BY u.id DESC LIMIT 6
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-size:1.8rem; font-weight:800;">لوحة إدارة مدرسة الشمامسة ⛪</h1>
                <p style="color:var(--text-muted);">أهلاً بك، <?= sanitize($_SESSION['user']['full_name']) ?> (مدير النظام)</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>admin/users.php?status=pending" class="btn btn-gold">
                    🔔 الطلبات المعلقة (<?= $pendingUsersCount ?>)
                </a>
            </div>
        </div>

        <!-- Stat Tiles -->
        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">عدد الشمامسة (الطلاب)</div>
                    <div class="stat-val"><?= number_format($totalStudents) ?></div>
                </div>
                <div class="stat-icon">👦</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">عدد الخدام</div>
                    <div class="stat-val"><?= number_format($totalServants) ?></div>
                </div>
                <div class="stat-icon" style="background:var(--gold-glow); color:var(--gold);">✝️</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">أولياء الأمور</div>
                    <div class="stat-val"><?= number_format($totalParents) ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(34, 197, 94, 0.15); color:#16a34a;">👨‍👩‍👦</div>
            </div>

            <div class="glass-card stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">نسبة حضور اليوم</div>
                    <div class="stat-val"><?= $attendanceRate ?>%</div>
                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= $presentToday ?> من <?= $totalStudents ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(239, 68, 68, 0.15); color:#dc2626;">📊</div>
            </div>
        </div>

        <!-- Quick Actions & Recent Users -->
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <div class="glass-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                    <h3 style="color:var(--royal-blue); font-weight:800;">أحدث المستخدمين المسجلين</h3>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary btn-sm">عرض الكل</a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>نوع الحساب</th>
                                <th>المرحلة</th>
                                <th>الحالة</th>
                                <th>تاريخ التسجيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newestUsers as $u) { ?>
                                <tr>
                                    <td><strong><?= sanitize($u['full_name']) ?></strong></td>
                                    <td>
                                        <span class="badge badge-info"><?= $u['role'] ?></span>
                                    </td>
                                    <td><?= sanitize($u['stage'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($u['status'] === 'active') { ?>
                                            <span class="badge badge-success">نشط</span>
                                        <?php } elseif ($u['status'] === 'pending') { ?>
                                            <span class="badge badge-warning">قيد الانتظار</span>
                                        <?php } else { ?>
                                            <span class="badge badge-danger">موقوف</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= format_arabic_date($u['created_at']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Action Cards -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.25rem;">إجراءات سريعة</h3>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <a href="<?= BASE_URL ?>admin/users.php?action=add" class="btn btn-primary" style="justify-content:flex-start;">
                        <span>➕</span> إضافة مستخدم جديد
                    </a>
                    <a href="<?= BASE_URL ?>admin/announcements.php" class="btn btn-gold" style="justify-content:flex-start;">
                        <span>📢</span> إرسال تنبيه أو إعلان
                    </a>
                    <a href="<?= BASE_URL ?>admin/courses.php" class="btn btn-secondary" style="justify-content:flex-start;">
                        <span>📚</span> رفع منهج دراسي
                    </a>
                    <a href="<?= BASE_URL ?>api/export.php?type=students" class="btn btn-secondary" style="justify-content:flex-start;">
                        <span>📊</span> تصدير بيانات الشمامسة Excel
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

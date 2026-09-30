<?php
$pageTitle = 'سجل حضور الشماس';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];

$attendanceRecords = $db->prepare('
    SELECT a.*, srv.full_name as servant_name
    FROM attendance a
    JOIN users srv ON a.servant_id = srv.id
    WHERE a.student_id = ?
    ORDER BY a.attendance_date DESC
');
$attendanceRecords->execute([$studentId]);
$list = $attendanceRecords->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">سجل الحضور الشخصي 📅</h1>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>وقت التسجيل</th>
                            <th>الخادم المسجل</th>
                            <th>📖 الحصة</th>
                            <th>📜 البامفلت</th>
                            <th>⛪ القداس</th>
                            <th>🪙 نقاط الطايو</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)) { ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                                    لم يتم تسجيل أي حضور حتى الآن.
                                </td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($list as $rec) {
                            $hasL = (bool) ($rec['attended_lesson'] ?? true);
                            $hasP = (bool) ($rec['attended_pamphlet'] ?? false);
                            $hasLit = (bool) ($rec['attended_liturgy'] ?? false);
                            $pts = (int) ($rec['points_awarded'] ?? (($hasL ? 1 : 0) + ($hasP ? 1 : 0) + ($hasLit ? 1 : 0)));
                            ?>
                            <tr>
                                <td><strong><?= format_arabic_date($rec['attendance_date']) ?></strong></td>
                                <td><?= date('H:i:s', strtotime($rec['scanned_at'])) ?></td>
                                <td><?= sanitize($rec['servant_name']) ?></td>
                                <td>
                                    <?= $hasL ? '<span class="badge badge-success">حاضر (+1) ✅</span>' : '<span class="badge badge-secondary">غائب ❌</span>' ?>
                                </td>
                                <td>
                                    <?= $hasP ? '<span class="badge badge-gold">مُستلم (+1) 📜</span>' : '<span class="badge badge-secondary">لم يستلم ✖</span>' ?>
                                </td>
                                <td>
                                    <?= $hasLit ? '<span class="badge badge-info">حاضر (+1) ⛪</span>' : '<span class="badge badge-secondary">غائب ✖</span>' ?>
                                </td>
                                <td>
                                    <span class="badge badge-gold" style="font-weight:700;">🪙 +<?= $pts ?> طايو</span>
                                </td>
                                <td>
                                    <?php if ($rec['status'] === 'present') { ?>
                                        <span class="badge badge-success">حاضر ✅</span>
                                    <?php } elseif ($rec['status'] === 'late') { ?>
                                        <span class="badge badge-warning">متأخر ⏰</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">غائب ❌</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

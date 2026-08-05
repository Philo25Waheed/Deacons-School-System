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
                            <th>وقت التسجيل المسحي</th>
                            <th>الخادم المسجل</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $rec) { ?>
                            <tr>
                                <td><strong><?= format_arabic_date($rec['attendance_date']) ?></strong></td>
                                <td><?= date('H:i:s', strtotime($rec['scanned_at'])) ?></td>
                                <td><?= sanitize($rec['servant_name']) ?></td>
                                <td><span class="badge badge-success">حاضر ✅</span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

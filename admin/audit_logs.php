<?php
$pageTitle = 'سجل الأمان والأحداث';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin');

$db = getDB();
$logs = $db->query('
    SELECT l.*, u.full_name, u.role
    FROM audit_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC LIMIT 100
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">سجل الأمان والعمليات (Audit Log) 🛡️</h1>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الحدث / الإجراء</th>
                            <th>التفاصيل</th>
                            <th>عنوان IP</th>
                            <th>التاريخ والوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <tr>
                                <td><strong><?= sanitize($log['full_name'] ?? 'زائر / غير مسجل') ?></strong> (<?= $log['role'] ?? '-' ?>)</td>
                                <td><span class="badge badge-info"><?= sanitize($log['action']) ?></span></td>
                                <td><?= sanitize($log['details']) ?></td>
                                <td><code><?= sanitize($log['ip_address']) ?></code></td>
                                <td><?= format_arabic_date($log['created_at']) ?> <?= date('H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

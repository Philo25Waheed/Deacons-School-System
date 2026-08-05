<?php
$pageTitle = 'التقارير ومشاركة واتساب';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('servant', 'admin');

$db = getDB();

// Fetch students for individual report & whatsapp link generator
$students = $db->query("
    SELECT u.id, u.full_name, u.phone,
           (SELECT phone FROM users p JOIN parent_student ps ON p.id = ps.parent_id WHERE ps.student_id = u.id LIMIT 1) as parent_phone,
           COALESCE(SUM(CASE WHEN pt.type = 'positive' THEN pt.points ELSE -pt.points END), 0) as total_points,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'present') as present_count
    FROM users u
    LEFT JOIN points pt ON u.id = pt.student_id
    WHERE u.role = 'student' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.full_name ASC
")->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">تقارير الشمامسة ومشاركة واتساب 📄📱</h1>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إرسال تقرير المتابعة لولي الأمر عبر واتساب</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الشماس</th>
                            <th>عدد مرات الحضور</th>
                            <th>رصيد النقاط</th>
                            <th>هاتف ولي الأمر</th>
                            <th>إرسال تقرير WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu) { ?>
                            <?php
                            $targetPhone = $stu['parent_phone'] ?: $stu['phone'];
                            $waMessage = "سلام ونعمة ⛪\nتقرير متابعة الشماس: {$stu['full_name']}\n- مرات الحضور: {$stu['present_count']} مرة\n- رصيد النقاط: {$stu['total_points']} نقطة\nدمتم مباركين وفي ملء النعمة.";
                            $waUrl = build_whatsapp_link($targetPhone, $waMessage);
                            ?>
                            <tr>
                                <td><strong><?= sanitize($stu['full_name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= $stu['present_count'] ?> مرات</span></td>
                                <td><span class="badge badge-gold">⭐ <?= $stu['total_points'] ?></span></td>
                                <td><?= sanitize($targetPhone) ?></td>
                                <td>
                                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-gold btn-sm" style="background:#25D366; color:#ffffff; border:none;">
                                        💬 إرسال عبر WhatsApp
                                    </a>
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

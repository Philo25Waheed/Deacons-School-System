<?php
$pageTitle = 'نقاطي وأوسمتي';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];

$pointsHistory = $db->prepare('
    SELECT p.*, srv.full_name as servant_name
    FROM points p
    JOIN users srv ON p.servant_id = srv.id
    WHERE p.student_id = ?
    ORDER BY p.id DESC
');
$pointsHistory->execute([$studentId]);
$history = $pointsHistory->fetchAll();

$totalPointsSum = 0;
foreach ($history as $h) {
    if ($h['type'] === 'positive') {
        $totalPointsSum += $h['points'];
    } else {
        $totalPointsSum -= $h['points'];
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نقاطي وأوسمة التميز 🏆</h1>

        <!-- Badges Grid -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
            <div class="glass-card" style="text-align:center;">
                <div style="font-size:2.5rem;">🏅</div>
                <h4 style="color:var(--royal-blue); font-weight:800;">وسام الشماس المواظب</h4>
                <p style="font-size:0.8rem; color:var(--text-muted);">حضور أكثر من 5 قداسات</p>
                <span class="badge badge-success" style="margin-top:0.5rem;">مكتمل ✅</span>
            </div>

            <div class="glass-card" style="text-align:center;">
                <div style="font-size:2.5rem;">🎵</div>
                <h4 style="color:var(--gold); font-weight:800;">حافظ الألحان الذهبي</h4>
                <p style="font-size:0.8rem; color:var(--text-muted);">تجميع 20 نقطة ألحان</p>
                <span class="badge <?= ($totalPointsSum >= 20) ? 'badge-success' : 'badge-warning' ?>" style="margin-top:0.5rem;">
                    <?= ($totalPointsSum >= 20) ? 'مكتمل ✅' : 'قيد التقدم' ?>
                </span>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem;">سجل حركة النقاط</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>النوع والعدد</th>
                            <th>السبب</th>
                            <th>الخادم</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $p) { ?>
                            <tr>
                                <td>
                                    <?php if ($p['type'] === 'positive') { ?>
                                        <span class="badge badge-success">+<?= $p['points'] ?> نقطة تشجيع</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">-<?= $p['points'] ?> نقطة خصم</span>
                                    <?php } ?>
                                </td>
                                <td><?= sanitize($p['reason']) ?></td>
                                <td><?= sanitize($p['servant_name']) ?></td>
                                <td><?= format_arabic_date($p['created_at']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

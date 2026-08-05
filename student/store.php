<?php
$pageTitle = 'متجر استبدال النقاط';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];

// Handle Point Redemption Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $rewardId = filter_input(INPUT_POST, 'redeem_id', FILTER_VALIDATE_INT);

        // Fetch reward cost & stock
        $stmtR = $db->prepare('SELECT * FROM rewards WHERE id = ?');
        $stmtR->execute([$rewardId]);
        $reward = $stmtR->fetch();

        // Calculate student current points
        $totalPoints = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) FROM points WHERE student_id = ?");
        $totalPoints->execute([$studentId]);
        $myBalance = $totalPoints->fetchColumn();

        if ($reward && $reward['stock_quantity'] > 0) {
            if ($myBalance >= $reward['points_cost']) {
                // Deduct points
                $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, 1, ?, 'negative', ?)")
                    ->execute([$studentId, $reward['points_cost'], "استبدال مكافأة: {$reward['title']}"]);

                // Record reward order
                $db->prepare("INSERT INTO reward_orders (reward_id, student_id, points_spent, status) VALUES (?, ?, ?, 'pending')")
                    ->execute([$rewardId, $studentId, $reward['points_cost']]);

                // Reduce stock
                $db->prepare('UPDATE rewards SET stock_quantity = stock_quantity - 1 WHERE id = ?')->execute([$rewardId]);

                $_SESSION['flash_success'] = "مبروك! تم تقديم طلب استبدال الهدية ({$reward['title']}) بنجاح. يرجى استلامها من الخادم الكنسي المسؤول.";
            } else {
                $_SESSION['flash_error'] = "رصيد نقاطك الحالي ({$myBalance} نقطة) لا يكفي لاستبدال هذه الهدية (تتطلب {$reward['points_cost']} نقطة).";
            }
        }
        header('Location: '.BASE_URL.'student/store.php');
        exit;
    }
}

// Student total points
$myPoints = $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) FROM points WHERE student_id = $studentId")->fetchColumn();
$rewards = $db->query('SELECT * FROM rewards WHERE stock_quantity > 0 ORDER BY points_cost ASC')->fetchAll();

$myOrders = $db->query("
    SELECT o.*, r.title as reward_name
    FROM reward_orders o
    JOIN rewards r ON o.reward_id = r.id
    WHERE o.student_id = $studentId ORDER BY o.id DESC
")->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">متجر استبدال الهدايا والمكافآت 🛍️</h1>
                <p style="color:var(--text-muted);">استبدل نقاط مواظبتك وحفظك بأفضل الهدايا التذكارية</p>
            </div>
            <div class="badge badge-gold" style="font-size:1.2rem; padding:0.75rem 1.25rem;">
                رصيدك الحالي: ⭐ <?= number_format($myPoints) ?> نقطة
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:1rem; margin-bottom:1.5rem; text-align:center;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:1rem; margin-bottom:1.5rem; text-align:center;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Catalog Grid -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
            <?php foreach ($rewards as $rw) { ?>
                <div class="glass-card" style="text-align:center;">
                    <div style="font-size:3rem; margin-bottom:0.5rem;">🎁</div>
                    <h3 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($rw['title']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;"><?= sanitize($rw['description'] ?? '') ?></p>
                    
                    <form action="" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="redeem_id" value="<?= $rw['id'] ?>">
                        <button type="submit" class="btn btn-gold" style="width:100%;" <?= ($myPoints < $rw['points_cost']) ? 'disabled' : '' ?>>
                            استبدال مقابل ⭐ <?= $rw['points_cost'] ?> نقطة
                        </button>
                    </form>
                </div>
            <?php } ?>
        </div>

        <!-- My Redemption Orders -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">طلبات الاستبدال الخاصة بي</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الهدية</th>
                            <th>النقاط المستبدلة</th>
                            <th>حالة التسليم</th>
                            <th>تاريخ الطلب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myOrders as $ord) { ?>
                            <tr>
                                <td><strong><?= sanitize($ord['reward_name']) ?></strong></td>
                                <td><span class="badge badge-gold">⭐ <?= $ord['points_spent'] ?></span></td>
                                <td>
                                    <?php if ($ord['status'] === 'fulfilled') { ?>
                                        <span class="badge badge-success">تم التسليم بنجاح ✅</span>
                                    <?php } else { ?>
                                        <span class="badge badge-warning">جاري التجهيز للتسليم ⏳</span>
                                    <?php } ?>
                                </td>
                                <td><?= format_arabic_date($ord['ordered_at']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

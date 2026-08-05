<?php
$pageTitle = 'إدارة تسليم طلبات المكافآت';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();

if (isset($_GET['fulfill_id'])) {
    $orderId = filter_input(INPUT_GET, 'fulfill_id', FILTER_VALIDATE_INT);
    if ($orderId) {
        $db->prepare("UPDATE reward_orders SET status = 'fulfilled' WHERE id = ?")->execute([$orderId]);
        $_SESSION['flash_success'] = 'تم إثبات تسليم الهدية للشماس بنجاح!';
        header('Location: '.BASE_URL.'servant/orders.php');
        exit;
    }
}

$orders = $db->query('
    SELECT o.*, r.title as reward_name, u.full_name as student_name, u.phone as student_phone
    FROM reward_orders o
    JOIN rewards r ON o.reward_id = r.id
    JOIN users u ON o.student_id = u.id
    ORDER BY o.id DESC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">إدارة تسليم هدايا ومكافآت الشمامسة 🎁</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>اسم الشماس</th>
                            <th>رقم الهاتف</th>
                            <th>الهدية المطلوبة</th>
                            <th>النقاط الخصومية</th>
                            <th>الحالة والتاريخ</th>
                            <th>إجراء تسليم الهدية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord) { ?>
                            <tr>
                                <td><strong><?= sanitize($ord['student_name']) ?></strong></td>
                                <td><?= sanitize($ord['student_phone']) ?></td>
                                <td><?= sanitize($ord['reward_name']) ?></td>
                                <td><span class="badge badge-gold">⭐ <?= $ord['points_spent'] ?></span></td>
                                <td>
                                    <?php if ($ord['status'] === 'fulfilled') { ?>
                                        <span class="badge badge-success">تم التسليم ✅</span>
                                    <?php } else { ?>
                                        <span class="badge badge-warning">قيد الانتظار ⏳</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($ord['status'] === 'pending') { ?>
                                        <a href="?fulfill_id=<?= $ord['id'] ?>" class="btn btn-primary btn-sm" onclick="return confirm('تأكيد تسليم الهدية للشماس؟')">
                                            🎁 إثبات التسليم
                                        </a>
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

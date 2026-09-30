<?php
$pageTitle = 'تسليم هدايا معرض الطايو';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();

$userRole = $_SESSION['user']['role'] ?? '';
$userId = $_SESSION['user']['id'] ?? 0;
$accessibleClasses = get_user_accessible_class_ids();

// Handle Reward Fulfillment via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fulfill_order') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if ($orderId) {
            $chk = $db->prepare('SELECT student_id FROM reward_orders WHERE id = ?');
            $chk->execute([$orderId]);
            $studentId = $chk->fetchColumn();

            if ($studentId && can_servant_access_student($userId, (int) $studentId, $userRole)) {
                $db->prepare("UPDATE reward_orders SET status = 'fulfilled' WHERE id = ?")->execute([$orderId]);
                $_SESSION['flash_success'] = 'تم إثبات تسليم الهدية للشماس بنجاح!';
            } else {
                $_SESSION['flash_error'] = 'ليس لديك صلاحية لتسليم هدية لشماس خارج نطاق فصول خدمتك.';
            }
        }
    } else {
        $_SESSION['flash_error'] = 'رمز CSRF غير صالح.';
    }
    header('Location: '.BASE_URL.'servant/orders.php');
    exit;
}

$whereClause = '';
$params = [];
if ($userRole === 'servant') {
    if (! empty($accessibleClasses)) {
        $inClasses = implode(',', array_fill(0, count($accessibleClasses), '?'));
        $whereClause = "WHERE u.class_id IN ({$inClasses})";
        $params = $accessibleClasses;
    } else {
        $whereClause = 'WHERE 1 = 0';
    }
}

$stmt = $db->prepare("
    SELECT o.*, r.title as reward_name, u.full_name as student_name, u.phone as student_phone
    FROM reward_orders o
    JOIN rewards r ON o.reward_id = r.id
    JOIN users u ON o.student_id = u.id
    {$whereClause}
    ORDER BY o.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">تسليم هدايا معرض الطايو 🎁</h1>

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
                            <th>الطايو المستبدل</th>
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
                                <td><span class="badge badge-gold">⭐ <?= $ord['points_spent'] ?> طايو</span></td>
                                <td>
                                    <?php if ($ord['status'] === 'fulfilled') { ?>
                                        <span class="badge badge-success">تم التسليم ✅</span>
                                    <?php } else { ?>
                                        <span class="badge badge-warning">قيد الانتظار ⏳</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($ord['status'] === 'pending') { ?>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('تأكيد تسليم الهدية للشماس؟');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="fulfill_order">
                                            <input type="hidden" name="order_id" value="<?= (int) $ord['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">🎁 إثبات التسليم</button>
                                        </form>
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

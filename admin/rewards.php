<?php
$pageTitle = 'إدارة متجر الجوائز والمكافآت';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $pointsCost = filter_input(INPUT_POST, 'points_cost', FILTER_VALIDATE_INT) ?: 10;
        $stock = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT) ?: 5;

        $stmt = $db->prepare('INSERT INTO rewards (title, description, points_cost, stock_quantity) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $description, $pointsCost, $stock]);

        $_SESSION['flash_success'] = 'تم إضافة الهدية لمتجر المكافآت بنجاح!';
        header('Location: '.BASE_URL.'admin/rewards.php');
        exit;
    }
}

$rewards = $db->query('SELECT * FROM rewards ORDER BY id DESC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">إدارة متجر هدايا الشمامسة 🛍️</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Create Reward Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة مكافأة جديدة للمتجر</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">اسم الهدية / المكافأة *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: كتاب الخولاجي الملحن" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تكلفة النقاط المطلوب *</label>
                        <input type="number" name="points_cost" class="form-control" value="15" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الكمية المتاحة (المخزون)</label>
                        <input type="number" name="stock_quantity" class="form-control" value="10" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف والملخص</label>
                    <input type="text" name="description" class="form-control" placeholder="وصف تفصيلي للهدية...">
                </div>

                <button type="submit" class="btn btn-gold">حفظ الهدية في المتجر</button>
            </form>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem;">
            <?php foreach ($rewards as $rw) { ?>
                <div class="glass-card">
                    <div style="font-size:2.5rem; text-align:center; margin-bottom:0.5rem;">🎁</div>
                    <h3 style="color:var(--royal-blue); text-align:center; font-weight:800;"><?= sanitize($rw['title']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; text-align:center; margin-bottom:1rem;"><?= sanitize($rw['description'] ?? '') ?></p>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-primary); padding:0.75rem; border-radius:var(--radius-sm);">
                        <span class="badge badge-gold" style="font-size:1rem;">⭐ <?= $rw['points_cost'] ?> نقطة</span>
                        <span style="font-size:0.85rem; color:var(--text-muted);">المتاح: <?= $rw['stock_quantity'] ?> قطعة</span>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

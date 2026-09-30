<?php
$pageTitle = 'إدارة معرض الطايو';
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
        $action = $_POST['action'] ?? 'add_reward';

        if ($action === 'toggle_store') {
            if (($_SESSION['user']['role'] ?? '') !== 'admin') {
                $_SESSION['flash_error'] = 'عذراً، التحكم في حالة المعرض متاح للمدير (Admin) فقط.';
                header('Location: '.BASE_URL.'admin/rewards.php');
                exit;
            }

            $currentStoreState = is_store_enabled($db);
            $newStoreState = ! $currentStoreState;
            set_store_enabled($newStoreState, $db);

            if (function_exists('log_action')) {
                log_action(
                    $_SESSION['user']['id'],
                    'STORE_VISIBILITY_TOGGLED',
                    $newStoreState ? 'Admin enabled Tayo store for students' : 'Admin disabled/hid Tayo store from students'
                );
            }

            $_SESSION['flash_success'] = $newStoreState
                ? 'تم إظهار وتفعيل معرض الطايو للشمامسة بنجاح! 🟢'
                : 'تم إخفاء وإغلاق معرض الطايو عن الشمامسة بنجاح! 🔴';

            header('Location: '.BASE_URL.'admin/rewards.php');
            exit;
        }

        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $pointsCost = filter_input(INPUT_POST, 'points_cost', FILTER_VALIDATE_INT) ?: 10;
        $stock = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT) ?: 5;

        $stmt = $db->prepare('INSERT INTO rewards (title, description, points_cost, stock_quantity) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $description, $pointsCost, $stock]);

        $_SESSION['flash_success'] = 'تم إضافة الهدية لمعرض الطايو بنجاح!';
        header('Location: '.BASE_URL.'admin/rewards.php');
        exit;
    }
}

$isStoreEnabled = is_store_enabled($db);
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

try {
    $rewards = $db->query('SELECT * FROM rewards ORDER BY id DESC')->fetchAll();
} catch (Throwable $e) {
    $rewards = [];
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
            <h1 style="color:var(--royal-blue); font-weight:800; margin:0;">إدارة معرض الطايو 🛍️</h1>
            <a href="<?= BASE_URL ?>student/store.php" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.4rem;">
                👁️ معاينة صفحة المعرض
            </a>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Store Visibility Control Banner (Admin Toggle Button) -->
        <div class="glass-card" style="margin-bottom:2rem; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-right:5px solid <?= $isStoreEnabled ? '#16a34a' : '#dc2626' ?>;">
            <div style="display:flex; align-items:center; gap:1rem;">
                <div style="font-size:2.2rem;">
                    <?= $isStoreEnabled ? '🛍️' : '🔒' ?>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:0.6rem;">
                        <h3 style="color:var(--royal-blue); margin:0; font-weight:800; font-size:1.15rem;">التحكم في ظهور معرض الطايو</h3>
                        <?php if ($isStoreEnabled) { ?>
                            <span class="badge badge-success" style="font-size:0.85rem; padding:0.25rem 0.6rem;">ظاهر ومفعل للشمامسة 🟢</span>
                        <?php } else { ?>
                            <span class="badge badge-danger" style="font-size:0.85rem; padding:0.25rem 0.6rem;">مخفي ومغلق عن الشمامسة 🔴</span>
                        <?php } ?>
                    </div>
                    <p style="color:var(--text-muted); font-size:0.88rem; margin:0.35rem 0 0 0;">
                        <?= $isStoreEnabled
                            ? 'معرض الطايو يظهر حالياً في القائمة الجانبية للشمامسة ويتاح لهم استبدال رصيدهم بهدايا.'
                            : 'معرض الطايو مخفي حالياً من القائمة الجانبية للشمامسة ولا يمكنهم استبدال الهدايا مؤقتاً.' ?>
                    </p>
                </div>
            </div>

            <?php if ($isAdmin) { ?>
                <form action="" method="POST" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_store">
                    <?php if ($isStoreEnabled) { ?>
                        <button type="submit" class="btn btn-danger" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.7rem 1.25rem; font-weight:700;" onclick="return confirm('هل تريد بالتأكيد إخفاء معرض الطايو عن الشمامسة؟');">
                            🔒 إخفاء المعرض عن الشمامسة
                        </button>
                    <?php } else { ?>
                        <button type="submit" class="btn btn-success" style="background-color:#16a34a; border-color:#16a34a; color:#fff; display:inline-flex; align-items:center; gap:0.4rem; padding:0.7rem 1.25rem; font-weight:700;">
                            🔓 إظهار وتفعيل المعرض للشمامسة
                        </button>
                    <?php } ?>
                </form>
            <?php } ?>
        </div>

        <!-- Create Reward Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة هدية جديدة لمعرض الطايو</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">اسم الهدية / المكافأة *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: كتاب الخولاجي الملحن" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تكلفة الطايو المطلوب *</label>
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

                <button type="submit" class="btn btn-gold">حفظ الهدية في معرض الطايو</button>
            </form>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem;">
            <?php foreach ($rewards as $rw) { ?>
                <div class="glass-card">
                    <div style="font-size:2.5rem; text-align:center; margin-bottom:0.5rem;">🎁</div>
                    <h3 style="color:var(--royal-blue); text-align:center; font-weight:800;"><?= sanitize($rw['title']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; text-align:center; margin-bottom:1rem;"><?= sanitize($rw['description'] ?? '') ?></p>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-primary); padding:0.75rem; border-radius:var(--radius-sm);">
                        <span class="badge badge-gold" style="font-size:1rem;">⭐ <?= $rw['points_cost'] ?> طايو</span>
                        <span style="font-size:0.85rem; color:var(--text-muted);">المتاح: <?= $rw['stock_quantity'] ?> قطعة</span>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

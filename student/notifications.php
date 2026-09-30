<?php
$pageTitle = 'الإشعارات والتنبيهات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'admin', 'servant', 'parent');

$db = getDB();
$userId = $_SESSION['user']['id'];

// Handle Delete Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $action = sanitize($_POST['action'] ?? '');
        if ($action === 'delete_single') {
            $notifId = filter_input(INPUT_POST, 'notif_id', FILTER_VALIDATE_INT);
            if ($notifId) {
                $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')->execute([$notifId, $userId]);
                $_SESSION['flash_success'] = 'تم حذف الإشعار بنجاح.';
            }
        } elseif ($action === 'clear_all') {
            $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
            $_SESSION['flash_success'] = 'تم مسح كافة الإشعارات.';
        }
    }
    header('Location: '.BASE_URL.'student/notifications.php');
    exit;
}

// Trigger daily birthday notifications
if (function_exists('trigger_daily_birthday_notifications')) {
    trigger_daily_birthday_notifications($db);
}

// Mark all as read
$db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);

$notifs = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC');
$notifs->execute([$userId]);
$list = $notifs->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; margin:0 0 0.25rem 0;">صندوق الإشعارات والتنبيهات 🔔</h1>
                <span style="font-size:0.88rem; color:var(--text-muted);">جميع الرسائل والتنبيهات الواردة لك من إدارة المدرسة والخدمة</span>
            </div>

            <?php if (! empty($list)) { ?>
                <form method="POST" action="" onsubmit="return confirm('هل أنت متأكد من رغبتك في مسح كافة الإشعارات؟');" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700; color:#ef4444;">
                        🗑️ مسح كافة الإشعارات
                    </button>
                </form>
            <?php } ?>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div class="glass-card">
            <div style="display:flex; flex-direction:column; gap:0.85rem;">
                <?php if (empty($list)) { ?>
                    <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <span style="font-size:2.5rem; display:block; margin-bottom:0.75rem;">📭</span>
                        <p style="margin:0;">صندوق الإشعارات فارغ حالياً.</p>
                    </div>
                <?php } else { ?>
                    <?php foreach ($list as $n) { ?>
                        <div style="background:var(--bg-surface); padding:1.15rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); border-right:4px solid var(--gold); display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                            <div style="flex:1;">
                                <h4 style="color:var(--royal-blue); font-weight:800; margin:0 0 0.4rem 0;"><?= sanitize($n['title']) ?></h4>
                                <p style="color:var(--text-primary); line-height:1.6; margin:0 0 0.5rem 0;"><?= sanitize($n['message']) ?></p>
                                <div style="font-size:0.78rem; color:var(--text-muted);">
                                    📅 <?= format_arabic_date(substr($n['created_at'], 0, 10)) ?> <?= date('H:i', strtotime($n['created_at'])) ?>
                                </div>
                            </div>

                            <form method="POST" action="" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_single">
                                <input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding:0.35rem 0.65rem;" title="حذف هذا الإشعار">
                                    ✕
                                </button>
                            </form>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'الإشعارات التنبيهات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin', 'servant', 'parent');

$db = getDB();
$userId = $_SESSION['user']['id'];

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
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">صندوق الإشعارات والتنبيهات 🔔</h1>

        <div class="glass-card">
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                <?php if (empty($list)) { ?>
                    <p style="text-align:center; color:var(--text-muted); padding:2rem;">لا يوجد إشعارات حالياً.</p>
                <?php } else { ?>
                    <?php foreach ($list as $n) { ?>
                        <div style="background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); border-right:4px solid var(--gold);">
                            <h4 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($n['title']) ?></h4>
                            <p style="color:var(--text-secondary); margin-top:0.3rem;"><?= sanitize($n['message']) ?></p>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.4rem;"><?= format_arabic_date($n['created_at']) ?> <?= date('H:i', strtotime($n['created_at'])) ?></div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

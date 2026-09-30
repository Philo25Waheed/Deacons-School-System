<?php
require_once __DIR__.'/../includes/coptic_date.php';
require_once __DIR__.'/../includes/helpers.php';
$user = getCurrentUser();
$copticInfo = getCopticDateDetails();

// Fetch latest profile_pic if logged in
if ($user && isset($user['id'])) {
    $db = getDB();
    if (function_exists('trigger_daily_birthday_notifications')) {
        trigger_daily_birthday_notifications($db);
    }
    $picStmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
    $picStmt->execute([$user['id']]);
    $userPic = $picStmt->fetchColumn() ?: 'default-avatar.png';
} else {
    $userPic = 'default-avatar.png';
}
?>
<header class="top-navbar">
    <div class="nav-brand">
        <button id="sidebarToggleBtn" class="btn btn-secondary btn-sm sidebar-toggle-btn" aria-label="القائمة">☰</button>
        <a href="<?= BASE_URL ?>" class="nav-brand-link" style="display:flex; align-items:center; gap:0.6rem; text-decoration:none; color:inherit; min-width:0;">
            <img src="<?= BASE_URL ?>assets/images/logo.png" alt="شعار مدرسة الشهيد إسطفانوس" style="height:36px; width:36px; object-fit:contain; border-radius:8px; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.15)); flex-shrink:0;">
            <span class="nav-brand-text" style="font-weight:800; font-size:1.15rem; color:var(--royal-blue); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">مدرسة الشهيد إسطفانوس</span>
        </a>
    </div>

    <!-- Coptic Calendar Widget Bar -->
    <div class="nav-coptic-widget" style="background:var(--royal-blue-glow); padding:0.35rem 0.85rem; border-radius:20px; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem; flex-shrink:0;">
        <span style="color:var(--gold); font-weight:800;">☦️ <?= $copticInfo['full_str'] ?></span>
        <span class="badge badge-info" style="font-size:0.75rem;"><?= $copticInfo['tone'] ?></span>
    </div>

    <div class="nav-actions" style="flex-shrink:0;">
        <!-- Dark/Light Theme Toggle -->
        <button id="themeToggleBtn" class="btn btn-secondary btn-sm" title="تبديل المظهر">🌙</button>

        <?php if ($user) { ?>
            <!-- Notifications Icon -->
            <a href="<?= BASE_URL ?>student/notifications.php" class="nav-notif-btn" style="position:relative; font-size:1.25rem;" title="الإشعارات">
                🔔
                <span id="notifBadge" class="badge badge-danger" style="position:absolute; top:-5px; right:-8px; font-size:0.65rem; display:none;">0</span>
            </a>

            <!-- User Profile Avatar & Info Badge -->
            <a href="<?= BASE_URL ?>profile.php" class="nav-user-profile" style="display:flex; align-items:center; gap:0.6rem; color:inherit;" title="تعديل الملف الشخصي">
                <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($userPic) ?>" 
                     style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid var(--gold); flex-shrink:0;" 
                     alt="صورة المستخدم" 
                     onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                <div class="nav-user-details" style="text-align:left;">
                    <div style="font-weight:700; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:110px;"><?= sanitize($user['full_name']) ?></div>
                    <div style="font-size:0.7rem; color:var(--text-muted); text-transform:capitalize;">
                        <?php
                        $roleAr = ['admin' => 'مدير النظام', 'servant' => 'خادم', 'student' => 'شماس / طالب', 'parent' => 'ولي أمر'];
            echo $roleAr[$user['role']] ?? $user['role'];
            ?>
                    </div>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>authentication/logout.php" class="btn btn-secondary btn-sm nav-logout-btn" title="تسجيل الخروج" style="color:#ef4444;">
                <span>🚪</span><span class="nav-logout-text"> خروج</span>
            </a>
        <?php } else { ?>
            <a href="<?= BASE_URL ?>authentication/login.php" class="btn btn-primary btn-sm nav-btn-login">دخول</a>
            <a href="<?= BASE_URL ?>authentication/register.php" class="btn btn-gold btn-sm nav-btn-register">حساب جديد</a>
        <?php } ?>
    </div>
</header>

<?php
require_once __DIR__.'/../includes/coptic_date.php';
$user = getCurrentUser();
$copticInfo = getCopticDateDetails();

// Fetch latest profile_pic if logged in
if ($user && isset($user['id'])) {
    $db = getDB();
    $picStmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
    $picStmt->execute([$user['id']]);
    $userPic = $picStmt->fetchColumn() ?: 'default-avatar.png';
} else {
    $userPic = 'default-avatar.png';
}
?>
<header class="top-navbar">
    <div class="nav-brand">
        <button id="sidebarToggleBtn" class="btn btn-secondary btn-sm" style="display:none;" aria-label="القائمة">☰</button>
        <span style="font-size:1.5rem;">⛪</span>
        <span>مدرسة الشمامسة</span>
    </div>

    <!-- Coptic Calendar Widget Bar -->
    <div style="background:var(--royal-blue-glow); padding:0.35rem 0.85rem; border-radius:20px; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
        <span style="color:var(--gold); font-weight:800;">☦️ <?= $copticInfo['full_str'] ?></span>
        <span class="badge badge-info" style="font-size:0.75rem;"><?= $copticInfo['tone'] ?></span>
    </div>

    <div class="nav-actions">
        <!-- Dark/Light Theme Toggle -->
        <button id="themeToggleBtn" class="btn btn-secondary btn-sm" title="تبديل المظهر">🌙</button>

        <?php if ($user) { ?>
            <!-- Notifications Icon -->
            <a href="<?= BASE_URL ?>student/notifications.php" style="position:relative; font-size:1.25rem;" title="الإشعارات">
                🔔
                <span id="notifBadge" class="badge badge-danger" style="position:absolute; top:-5px; right:-8px; font-size:0.65rem; display:none;">0</span>
            </a>

            <!-- User Profile Avatar & Info Badge -->
            <a href="<?= BASE_URL ?>profile.php" style="display:flex; align-items:center; gap:0.75rem; color:inherit;" title="تعديل الملف الشخصي">
                <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($userPic) ?>" 
                     style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" 
                     alt="صورة المستخدم" 
                     onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                <div style="text-align:left;">
                    <div style="font-weight:700; font-size:0.9rem;"><?= sanitize($user['full_name']) ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted); text-transform:capitalize;">
                        <?php
                        $roleAr = ['admin' => 'مدير النظام', 'servant' => 'خادم', 'student' => 'شماس / طالب', 'parent' => 'ولي أمر'];
            echo $roleAr[$user['role']] ?? $user['role'];
            ?>
                    </div>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>authentication/logout.php" class="btn btn-secondary btn-sm" title="تسجيل الخروج" style="color:#ef4444;">🚪 خروج</a>
        <?php } else { ?>
            <a href="<?= BASE_URL ?>authentication/login.php" class="btn btn-primary btn-sm">دخول</a>
            <a href="<?= BASE_URL ?>authentication/register.php" class="btn btn-gold btn-sm">حساب جديد</a>
        <?php } ?>
    </div>
</header>

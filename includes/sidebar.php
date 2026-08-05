<?php
$user = getCurrentUser();
$role = $user['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <div style="padding-bottom:1rem; border-bottom:1px solid var(--border-color); margin-bottom:1rem;">
        <h4 style="color:var(--royal-blue); font-size:1.1rem; font-weight:800;">
            القائمة الرئيسية
        </h4>
    </div>

    <?php if ($role === 'admin') { ?>
        <a href="<?= BASE_URL ?>admin/index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="icon">📊</span> لوحة التحكم
        </a>
        <a href="<?= BASE_URL ?>admin/users.php" class="sidebar-link <?= ($currentPage === 'users.php') ? 'active' : '' ?>">
            <span class="icon">👥</span> إدارة المستخدمين
        </a>
        <a href="<?= BASE_URL ?>admin/stages.php" class="sidebar-link <?= ($currentPage === 'stages.php') ? 'active' : '' ?>">
            <span class="icon">🏫</span> المراحل والصفوف
        </a>
        <a href="<?= BASE_URL ?>admin/exams.php" class="sidebar-link <?= ($currentPage === 'exams.php') ? 'active' : '' ?>">
            <span class="icon">📝</span> بنك الاختيارات والامتحانات
        </a>
        <a href="<?= BASE_URL ?>admin/roster.php" class="sidebar-link <?= ($currentPage === 'roster.php') ? 'active' : '' ?>">
            <span class="icon">⛪</span> جدول خدمة القداسات
        </a>
        <a href="<?= BASE_URL ?>admin/rewards.php" class="sidebar-link <?= ($currentPage === 'rewards.php') ? 'active' : '' ?>">
            <span class="icon">🛍️</span> متجر الهدايا والمكافآت
        </a>
        <a href="<?= BASE_URL ?>admin/bulk_cards.php" class="sidebar-link <?= ($currentPage === 'bulk_cards.php') ? 'active' : '' ?>">
            <span class="icon">🖨️</span> طباعة الكروت بالجملة
        </a>
        <a href="<?= BASE_URL ?>admin/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> المناهج والدروس
        </a>
        <a href="<?= BASE_URL ?>admin/hymns.php" class="sidebar-link <?= ($currentPage === 'hymns.php') ? 'active' : '' ?>">
            <span class="icon">🎵</span> مكتبة الألحان
        </a>
        <a href="<?= BASE_URL ?>admin/announcements.php" class="sidebar-link <?= ($currentPage === 'announcements.php') ? 'active' : '' ?>">
            <span class="icon">📢</span> الإعلانات والتنبيهات
        </a>
        <a href="<?= BASE_URL ?>admin/audit_logs.php" class="sidebar-link <?= ($currentPage === 'audit_logs.php') ? 'active' : '' ?>">
            <span class="icon">🛡️</span> سجل الأمان والأحداث
        </a>

    <?php } elseif ($role === 'servant') { ?>
        <a href="<?= BASE_URL ?>servant/index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="icon">📊</span> لوحة الخادم
        </a>
        <a href="<?= BASE_URL ?>servant/attendance.php" class="sidebar-link <?= ($currentPage === 'attendance.php') ? 'active' : '' ?>">
            <span class="icon">📷</span> تسجيل الحضور بالماسح
        </a>
        <a href="<?= BASE_URL ?>servant/exams.php" class="sidebar-link <?= ($currentPage === 'exams.php') ? 'active' : '' ?>">
            <span class="icon">📝</span> إدارة وتصحيح الامتحانات
        </a>
        <a href="<?= BASE_URL ?>servant/students.php" class="sidebar-link <?= ($currentPage === 'students.php') ? 'active' : '' ?>">
            <span class="icon">👦</span> دليل الشمامسة
        </a>
        <a href="<?= BASE_URL ?>servant/points.php" class="sidebar-link <?= ($currentPage === 'points.php') ? 'active' : '' ?>">
            <span class="icon">⭐</span> نظام النقاط والتشجيع
        </a>
        <a href="<?= BASE_URL ?>servant/visitations.php" class="sidebar-link <?= ($currentPage === 'visitations.php') ? 'active' : '' ?>">
            <span class="icon">🏠</span> متابعة الافتقاد الرعوي
        </a>
        <a href="<?= BASE_URL ?>servant/orders.php" class="sidebar-link <?= ($currentPage === 'orders.php') ? 'active' : '' ?>">
            <span class="icon">🎁</span> تسليم هدايا المتجر
        </a>
        <a href="<?= BASE_URL ?>servant/evaluations.php" class="sidebar-link <?= ($currentPage === 'evaluations.php') ? 'active' : '' ?>">
            <span class="icon">📝</span> التقييمات السلوكية
        </a>
        <a href="<?= BASE_URL ?>servant/reports.php" class="sidebar-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
            <span class="icon">📄</span> التقارير وواتساب
        </a>

    <?php } elseif ($role === 'student') { ?>
        <a href="<?= BASE_URL ?>student/index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="icon">🏠</span> الرئيسة
        </a>
        <a href="<?= BASE_URL ?>student/card.php" class="sidebar-link <?= ($currentPage === 'card.php') ? 'active' : '' ?>">
            <span class="icon">🪪</span> كارت الشماس الرقمي
        </a>
        <a href="<?= BASE_URL ?>student/exams.php" class="sidebar-link <?= ($currentPage === 'exams.php') ? 'active' : '' ?>">
            <span class="icon">✏️</span> الاختبارات أونلاين
        </a>
        <a href="<?= BASE_URL ?>student/roster.php" class="sidebar-link <?= ($currentPage === 'roster.php') ? 'active' : '' ?>">
            <span class="icon">⛪</span> جدول خدمتي بالقداسات
        </a>
        <a href="<?= BASE_URL ?>student/store.php" class="sidebar-link <?= ($currentPage === 'store.php') ? 'active' : '' ?>">
            <span class="icon">🛍️</span> متجر استبدال الهدايا
        </a>
        <a href="<?= BASE_URL ?>student/attendance.php" class="sidebar-link <?= ($currentPage === 'attendance.php') ? 'active' : '' ?>">
            <span class="icon">📅</span> سجل الحضور
        </a>
        <a href="<?= BASE_URL ?>student/points.php" class="sidebar-link <?= ($currentPage === 'points.php') ? 'active' : '' ?>">
            <span class="icon">🏆</span> درجاتي وأوسمتي
        </a>
        <a href="<?= BASE_URL ?>student/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📖</span> المناهج والدروس
        </a>
        <a href="<?= BASE_URL ?>student/hymns.php" class="sidebar-link <?= ($currentPage === 'hymns.php') ? 'active' : '' ?>">
            <span class="icon">🎶</span> مكتبة الألحان
        </a>
        <a href="<?= BASE_URL ?>student/notifications.php" class="sidebar-link <?= ($currentPage === 'notifications.php') ? 'active' : '' ?>">
            <span class="icon">🔔</span> التنبيهات
        </a>

    <?php } elseif ($role === 'parent') { ?>
        <a href="<?= BASE_URL ?>parent/index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="icon">🏠</span> لوحة ولي الأمر
        </a>
        <a href="<?= BASE_URL ?>parent/children.php" class="sidebar-link <?= ($currentPage === 'children.php') ? 'active' : '' ?>">
            <span class="icon">👨‍👩‍👦</span> أبنائي المسجلين
        </a>
    <?php } ?>

    <div style="margin-top:auto; padding-top:1rem; border-top:1px solid var(--border-color); display:flex; flex-direction:column; gap:0.25rem;">
        <a href="<?= BASE_URL ?>profile.php" class="sidebar-link <?= ($currentPage === 'profile.php') ? 'active' : '' ?>">
            <span class="icon">⚙️</span> تعديل الملف الشخصي
        </a>
        <a href="<?= BASE_URL ?>authentication/logout.php" class="sidebar-link" style="color:#ef4444;">
            <span class="icon">🚪</span> خروج
        </a>
    </div>
</aside>

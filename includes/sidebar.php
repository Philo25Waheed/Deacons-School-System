<?php
$user = getCurrentUser();
$role = $user['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<?php
$sidebarCoptic = function_exists('getCopticDateDetails') ? getCopticDateDetails() : null;
?>
<aside class="app-sidebar">
    <div class="sidebar-header" style="padding-bottom:0.75rem; border-bottom:1px solid var(--border-color); margin-bottom:0.75rem; display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
        <div style="display:flex; align-items:center; gap:0.75rem; min-width:0;">
            <img src="<?= BASE_URL ?>assets/images/logo.png" alt="لوجو المدرسة" style="width:38px; height:38px; object-fit:contain; border-radius:8px; box-shadow:0 2px 8px var(--royal-blue-glow); flex-shrink:0;">
            <div style="min-width:0;">
                <h4 style="color:var(--royal-blue); font-size:0.95rem; font-weight:800; margin:0; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    مدرسة الشهيد إسطفانوس
                </h4>
                <span style="font-size:0.75rem; color:var(--gold); font-weight:600;">القائمة الرئيسية</span>
            </div>
        </div>
        <button id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="إغلاق القائمة" title="إغلاق القائمة">✕</button>
    </div>

    <?php if ($sidebarCoptic) { ?>
    <div class="sidebar-coptic-widget" style="background:var(--royal-blue-glow); padding:0.45rem 0.75rem; border-radius:10px; font-size:0.8rem; margin-bottom:0.75rem; border:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
        <span style="color:var(--gold); font-weight:700;">☦️ <?= $sidebarCoptic['full_str'] ?></span>
        <span class="badge badge-info" style="font-size:0.7rem;"><?= $sidebarCoptic['tone'] ?></span>
    </div>
    <?php } ?>


    <?php if ($role === 'admin') { ?>
        <a href="<?= BASE_URL ?>admin/index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="icon">📊</span> لوحة التحكم
        </a>
        <a href="<?= BASE_URL ?>admin/attendance.php" class="sidebar-link <?= ($currentPage === 'attendance.php') ? 'active' : '' ?>">
            <span class="icon">📷</span> تسجيل الحضور بالماسح
        </a>
        <a href="<?= BASE_URL ?>admin/reports.php" class="sidebar-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
            <span class="icon">📈</span> تقارير المدرسة الشاملة
        </a>
        <a href="<?= BASE_URL ?>admin/student_report.php" class="sidebar-link <?= ($currentPage === 'student_report.php') ? 'active' : '' ?>">
            <span class="icon">📋</span> تقرير المخدوم المفصل
        </a>
        <a href="<?= BASE_URL ?>admin/users.php" class="sidebar-link <?= ($currentPage === 'users.php') ? 'active' : '' ?>">
            <span class="icon">👥</span> إدارة المستخدمين
        </a>
        <a href="<?= BASE_URL ?>admin/transfer.php" class="sidebar-link <?= ($currentPage === 'transfer.php') ? 'active' : '' ?>">
            <span class="icon">🔄</span> نقل وتوزيع الفصول
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
        <a href="<?= BASE_URL ?>admin/events.php" class="sidebar-link <?= ($currentPage === 'events.php') ? 'active' : '' ?>">
            <span class="icon">📅</span> الأنشطة والرحلات
        </a>
        <a href="<?= BASE_URL ?>admin/books.php" class="sidebar-link <?= ($currentPage === 'books.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> إدارة الكتب الشماسية
        </a>
        <a href="<?= BASE_URL ?>admin/rewards.php" class="sidebar-link <?= ($currentPage === 'rewards.php') ? 'active' : '' ?>">
            <span class="icon">🛍️</span> معرض الطايو
        </a>
        <a href="<?= BASE_URL ?>admin/bulk_cards.php" class="sidebar-link <?= ($currentPage === 'bulk_cards.php') ? 'active' : '' ?>">
            <span class="icon">🖨️</span> طباعة الكروت بالجملة
        </a>
        <a href="<?= BASE_URL ?>admin/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> المناهج والدروس
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
        <a href="<?= BASE_URL ?>servant/visitations.php" class="sidebar-link <?= ($currentPage === 'visitations.php') ? 'active' : '' ?>">
            <span class="icon">🚨</span> رادار الافتقاد والغياب
        </a>
        <a href="<?= BASE_URL ?>admin/roster.php" class="sidebar-link <?= ($currentPage === 'roster.php') ? 'active' : '' ?>">
            <span class="icon">⛪</span> جدول خدمة القداسات
        </a>
        <a href="<?= BASE_URL ?>student/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📖</span> المناهج والدروس
        </a>
        <a href="<?= BASE_URL ?>student/books.php" class="sidebar-link <?= ($currentPage === 'books.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> الكتب الشماسية
        </a>
        <a href="<?= BASE_URL ?>servant/students.php" class="sidebar-link <?= ($currentPage === 'students.php') ? 'active' : '' ?>">
            <span class="icon">👦</span> دليل الشمامسة
        </a>
        <a href="<?= BASE_URL ?>admin/student_report.php" class="sidebar-link <?= ($currentPage === 'student_report.php') ? 'active' : '' ?>">
            <span class="icon">📋</span> تقرير المخدوم المفصل
        </a>
        <a href="<?= BASE_URL ?>servant/points.php" class="sidebar-link <?= ($currentPage === 'points.php') ? 'active' : '' ?>">
            <span class="icon">⭐</span> نظام الطايو والتشجيع
        </a>
        <a href="<?= BASE_URL ?>servant/orders.php" class="sidebar-link <?= ($currentPage === 'orders.php') ? 'active' : '' ?>">
            <span class="icon">🎁</span> تسليم هدايا معرض الطايو
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
        <a href="<?= BASE_URL ?>student/books.php" class="sidebar-link <?= ($currentPage === 'books.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> الكتب الشماسية
        </a>
        <a href="<?= BASE_URL ?>student/roster.php" class="sidebar-link <?= ($currentPage === 'roster.php') ? 'active' : '' ?>">
            <span class="icon">⛪</span> جدول خدمتي بالقداسات
        </a>
        <a href="<?= BASE_URL ?>student/events.php" class="sidebar-link <?= ($currentPage === 'events.php') ? 'active' : '' ?>">
            <span class="icon">📅</span> الأنشطة والرحلات
        </a>
        <a href="<?= BASE_URL ?>student/exams.php" class="sidebar-link <?= ($currentPage === 'exams.php') ? 'active' : '' ?>">
            <span class="icon">✏️</span> الاختبارات أونلاين
        </a>
        <?php if (! function_exists('is_store_enabled') || is_store_enabled()) { ?>
        <a href="<?= BASE_URL ?>student/store.php" class="sidebar-link <?= ($currentPage === 'store.php') ? 'active' : '' ?>">
            <span class="icon">🛍️</span> معرض الطايو
        </a>
        <?php } ?>
        <a href="<?= BASE_URL ?>student/attendance.php" class="sidebar-link <?= ($currentPage === 'attendance.php') ? 'active' : '' ?>">
            <span class="icon">📅</span> سجل الحضور
        </a>
        <a href="<?= BASE_URL ?>student/points.php" class="sidebar-link <?= ($currentPage === 'points.php') ? 'active' : '' ?>">
            <span class="icon">🏆</span> رصيد الطايو وأوسمتي
        </a>
        <a href="<?= BASE_URL ?>student/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📖</span> المناهج والدروس
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
        <a href="<?= BASE_URL ?>student/courses.php" class="sidebar-link <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
            <span class="icon">📖</span> المناهج والدروس
        </a>
        <a href="<?= BASE_URL ?>student/books.php" class="sidebar-link <?= ($currentPage === 'books.php') ? 'active' : '' ?>">
            <span class="icon">📚</span> الكتب الشماسية
        </a>
        <a href="<?= BASE_URL ?>parent/report.php" class="sidebar-link <?= ($currentPage === 'report.php') ? 'active' : '' ?>">
            <span class="icon">📊</span> التقرير الشهري الشامل
        </a>
        <a href="<?= BASE_URL ?>student/events.php" class="sidebar-link <?= ($currentPage === 'events.php') ? 'active' : '' ?>">
            <span class="icon">📅</span> الأنشطة والرحلات
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

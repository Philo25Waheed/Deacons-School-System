<?php
$pageTitle = 'نظام التنبيهات والإعلانات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

// Handle Delete Announcement via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $annId = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
        if ($annId) {
            $stmt = $db->prepare('SELECT title FROM announcements WHERE id = ?');
            $stmt->execute([$annId]);
            $annTitle = $stmt->fetchColumn();

            $db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$annId]);

            // Also clean up broadcasted notifications for this announcement
            if ($annTitle) {
                $db->prepare('DELETE FROM notifications WHERE title = ?')->execute([$annTitle]);
            }

            $_SESSION['flash_success'] = 'تم حذف التنبيه وإلغاؤه من إشعارات المستخدمين بنجاح!';
        }
    } else {
        $_SESSION['flash_error'] = 'رمز CSRF غير صالح.';
    }
    header('Location: '.BASE_URL.'admin/announcements.php');
    exit;
}

// Handle Add/Broadcast New Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (! isset($_POST['action']) || $_POST['action'] === 'create_announcement')) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $targetType = sanitize($_POST['target_type'] ?? 'everyone');

        if (empty($title) || empty($content)) {
            $_SESSION['flash_error'] = 'يرجى كتابة عنوان التنبيه ونص الرسالة.';
            header('Location: '.BASE_URL.'admin/announcements.php');
            exit;
        }

        $stmt = $db->prepare('INSERT INTO announcements (title, content, target_type, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $content, $targetType, $_SESSION['user']['id']]);

        // Send internal notifications based on target type
        $query = "SELECT id FROM users WHERE status = 'active'";
        if ($targetType === 'students') {
            $query .= " AND role = 'student'";
        } elseif ($targetType === 'servants') {
            $query .= " AND role = 'servant'";
        } elseif ($targetType === 'parents') {
            $query .= " AND role = 'parent'";
        }

        $targetUsers = $db->query($query)->fetchAll();
        $notifStmt = $db->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
        foreach ($targetUsers as $tu) {
            $notifStmt->execute([$tu['id'], $title, $content]);
        }

        $_SESSION['flash_success'] = 'تم نشر التنبيه وإرسال الإشعارات لجميع المستهدفين بنجاح!';
        header('Location: '.BASE_URL.'admin/announcements.php');
        exit;
    }
}

$announcements = $db->query('
    SELECT a.*, u.full_name as author_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.id DESC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نظام الإعلانات والتنبيهات العامة 📢</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Create Announcement Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1.25rem; font-weight:800;">➕ نشر تنبيه / إعلان جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_announcement">

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" for="title">عنوان التنبيه *</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="مثال: تنبيه هام بخصوص موعد القداس الإلهي القادم" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="target_type">الفئة المستهدفة *</label>
                        <select id="target_type" name="target_type" class="form-control" required>
                            <option value="everyone">الجميع (خدام، شمامسة، أولياء أمور) 🌟</option>
                            <option value="students">الشمامسة فقط (الطلاب) 👦</option>
                            <option value="servants">الخدام فقط 👨‍🏫</option>
                            <option value="parents">أولياء الأمور فقط 👨‍👩‍👦</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" for="content">نص التنبيه والتفاصيل *</label>
                    <textarea id="content" name="content" class="form-control" rows="4" placeholder="اكتب تفاصيل التنبيه أو الإعلان هنا..." required></textarea>
                </div>

                <button type="submit" class="btn btn-gold" style="padding:0.85rem 2rem; font-weight:800; font-size:1rem;">
                    📢 نشر التنبيه وإرسال الإشعارات فوراً
                </button>
            </form>
        </div>

        <!-- Announcements Archive / Feed -->
        <div class="glass-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
                <h3 style="color:var(--royal-blue); margin:0; font-weight:800;">أرشيف التنبيهات والإعلانات المنشورة</h3>
                <span class="badge badge-gold"><?= count($announcements) ?> تنبيه مسجل</span>
            </div>

            <div style="display:flex; flex-direction:column; gap:1rem;">
                <?php if (empty($announcements)) { ?>
                    <p style="text-align:center; color:var(--text-muted); padding:2.5rem;">لا توجد تنبيهات منشورة بعد.</p>
                <?php } else { ?>
                    <?php foreach ($announcements as $ann) {
                        $targetLabels = [
                            'everyone' => ['الجميع 🌟', 'badge-gold'],
                            'students' => ['الشمامسة 👦', 'badge-info'],
                            'servants' => ['الخدام 👨‍🏫', 'badge-primary'],
                            'parents' => ['أولياء الأمور 👨‍👩‍👦', 'badge-success'],
                        ];
                        $targetInfo = $targetLabels[$ann['target_type']] ?? ['الجميع', 'badge-gold'];
                        ?>
                        <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-right:5px solid var(--royal-blue); padding:1.25rem; border-radius:var(--radius-sm); transition:var(--transition);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.75rem;">
                                <div>
                                    <h4 style="color:var(--royal-blue); font-weight:800; margin:0 0 0.35rem 0; font-size:1.15rem;">
                                        <?= sanitize($ann['title']) ?>
                                    </h4>
                                    <span class="badge <?= $targetInfo[1] ?>" style="font-size:0.75rem;">
                                        <?= $targetInfo[0] ?>
                                    </span>
                                </div>

                                <!-- Delete Announcement Form -->
                                <form method="POST" action="" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا التنبيه نهائياً؟ سيتم سحبه أيضاً من إشعارات المستخدمين.');" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $ann['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="font-weight:700; padding:0.4rem 0.8rem; display:flex; align-items:center; gap:0.35rem;" title="حذف التنبيه">
                                        <span>🗑️ مسح التنبيه</span>
                                    </button>
                                </form>
                            </div>

                            <p style="color:var(--text-primary); white-space:pre-line; font-size:0.95rem; line-height:1.6; margin-bottom:0.85rem;">
                                <?= sanitize($ann['content']) ?>
                            </p>

                            <div style="font-size:0.8rem; color:var(--text-muted); border-top:1px dashed var(--border-color); padding-top:0.6rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                <span>✍️ الناشر: <strong><?= sanitize($ann['author_name'] ?? 'الإدارة') ?></strong></span>
                                <span>📅 <?= format_arabic_date(substr($ann['created_at'], 0, 10)) ?> <?= substr($ann['created_at'], 11, 5) ?></span>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

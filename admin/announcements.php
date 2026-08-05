<?php
$pageTitle = 'نظام التنبيهات والإعلانات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $targetType = sanitize($_POST['target_type']);

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

        $_SESSION['flash_success'] = 'تم نشر الإعلان وإرسال الإشعارات لجميع المستهدفين بنجاح!';
        header('Location: '.BASE_URL.'admin/announcements.php');
        exit;
    }
}

$announcements = $db->query('
    SELECT a.*, u.full_name as author_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
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
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Create Announcement Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">نشر إعلان / تنبيه جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">عنوان الإعلان *</label>
                        <input type="text" name="title" class="form-control" placeholder="عنوان التنبيه..." required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الفئة المستهدفة *</label>
                        <select name="target_type" class="form-control" required>
                            <option value="everyone">الجميع (خدام، شمامسة، أولياء أمور)</option>
                            <option value="students">الشمامسة فقط (الطلاب)</option>
                            <option value="servants">الخدام فقط</option>
                            <option value="parents">أولياء الأمور فقط</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">نص الإعلان *</label>
                    <textarea name="content" class="form-control" rows="4" placeholder="تفاصيل الإعلان أو التنبيه..." required></textarea>
                </div>

                <button type="submit" class="btn btn-gold" style="padding:0.85rem 2rem;">📢 إرسال ونشر الإعلان فوراً</button>
            </form>
        </div>

        <!-- Announcements Feed -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">أرشيف الإعلانات المنشورة</h3>
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <?php foreach ($announcements as $ann) { ?>
                    <div style="background:var(--bg-primary); border-right:4px solid var(--gold); padding:1.25rem; border-radius:var(--radius-sm);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <h4 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($ann['title']) ?></h4>
                            <span class="badge badge-info"><?= $ann['target_type'] ?></span>
                        </div>
                        <p style="color:var(--text-secondary); white-space:pre-line; font-size:0.95rem; margin-bottom:0.75rem;"><?= sanitize($ann['content']) ?></p>
                        <div style="font-size:0.8rem; color:var(--text-muted);">
                            الناشر: <?= sanitize($ann['author_name']) ?> | التاريخ: <?= format_arabic_date($ann['created_at']) ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

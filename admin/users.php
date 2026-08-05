<?php
$pageTitle = 'إدارة المستخدمين';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();
$message = '';
$error = '';

// Handle Status Toggle (Activate / Suspend / Delete)
if (isset($_GET['do']) && isset($_GET['id'])) {
    $targetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = sanitize($_GET['do']);

    if ($targetId && $targetId != $_SESSION['user']['id']) {
        if ($action === 'activate') {
            $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
            log_action($_SESSION['user']['id'], 'USER_ACTIVATED', "Activated user ID {$targetId}");
            $_SESSION['flash_success'] = 'تم تفعيل الحساب بنجاح!';
        } elseif ($action === 'suspend') {
            $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$targetId]);
            log_action($_SESSION['user']['id'], 'USER_SUSPENDED', "Suspended user ID {$targetId}");
            $_SESSION['flash_success'] = 'تم إيقاف الحساب!';
        } elseif ($action === 'delete') {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            log_action($_SESSION['user']['id'], 'USER_DELETED', "Deleted user ID {$targetId}");
            $_SESSION['flash_success'] = 'تم حذف المستخدم نهائياً.';
        }
        header('Location: '.BASE_URL.'admin/users.php');
        exit;
    }
}

// Search & Filtering
$search = sanitize($_GET['search'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$query = '
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE 1=1
';
$params = [];

if ($search) {
    $query .= ' AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR u.qr_code_token LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($roleFilter) {
    $query .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $query .= ' AND u.status = ?';
    $params[] = $statusFilter;
}

$query .= ' ORDER BY u.id DESC';
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">إدارة كافة المستخدمين 👥</h1>
                <p style="color:var(--text-muted);">إضافة، تفعيل، إيقاف، وتعديل بيانات الشمامسة والخدام وأولياء الأمور</p>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <a href="<?= BASE_URL ?>api/export.php?type=students" class="btn btn-secondary">📊 تصدير Excel</a>
                <button onclick="window.print()" class="btn btn-secondary">🖨️ طباعة القائمة</button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Search & Filters -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <form action="" method="GET" style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:1rem;">
                <input type="text" name="search" class="form-control" placeholder="بحث بالاسم، رقم الهاتف، أو كود الشماس..." value="<?= sanitize($search) ?>">
                <select name="role" class="form-control">
                    <option value="">كل الرتب والأدوار</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>شماس / طالب</option>
                    <option value="servant" <?= $roleFilter === 'servant' ? 'selected' : '' ?>>خادم</option>
                    <option value="parent" <?= $roleFilter === 'parent' ? 'selected' : '' ?>>ولي أمر</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>مدير نظام</option>
                </select>
                <select name="status" class="form-control">
                    <option value="">كل الحالات</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>مفعل (Active)</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>قيد الانتظار (Pending)</option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>موقوف (Suspended)</option>
                </select>
                <button type="submit" class="btn btn-primary">بحث وتصفية</button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="glass-card printable-area">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الكود</th>
                            <th>الاسم بالكامل</th>
                            <th>رقم الهاتف</th>
                            <th>الدور</th>
                            <th>المرحلة والصف</th>
                            <th>الحالة</th>
                            <th>إجراءات التحكم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u) { ?>
                            <tr>
                                <td>
                                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($u['profile_pic'] ?? 'default-avatar.png') ?>" 
                                         style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" 
                                         alt="الصورة" 
                                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                                </td>
                                <td><code><?= sanitize($u['qr_code_token'] ?? '-') ?></code></td>
                                <td><strong><?= sanitize($u['full_name']) ?></strong></td>
                                <td><?= sanitize($u['phone']) ?></td>
                                <td><span class="badge badge-info"><?= $u['role'] ?></span></td>
                                <td><?= sanitize($u['stage_name'] ?? '-') ?> - <?= sanitize($u['grade_name'] ?? '') ?></td>
                                <td>
                                    <?php if ($u['status'] === 'active') { ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php } elseif ($u['status'] === 'pending') { ?>
                                        <span class="badge badge-warning">معلق</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">موقوف</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] !== 'active') { ?>
                                        <a href="?do=activate&id=<?= $u['id'] ?>" class="btn btn-gold btn-sm" onclick="return confirm('هل تريد تفعيل الحساب؟')">تفعيل</a>
                                    <?php } else { ?>
                                        <a href="?do=suspend&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('هل تريد إيقاف الحساب؟')">إيقاف</a>
                                    <?php } ?>
                                    <?php if ($u['id'] != $_SESSION['user']['id']) { ?>
                                        <a href="?do=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت تأكد من الحذف النهائي؟')">حذف</a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

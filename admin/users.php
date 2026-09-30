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

// Handle Status Toggle via POST (Activate / Suspend / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $targetId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $action = sanitize($_POST['action']);

        if ($targetId && $targetId != $_SESSION['user']['id']) {
            if ($action === 'activate') {
                $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
                if (function_exists('log_action')) {
                    log_action($_SESSION['user']['id'], 'USER_ACTIVATED', "Activated user ID {$targetId}");
                }
                $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تفعيل الحساب 🎉', 'تمت الموافقة على حسابك وتفعيله بنجاح من قِبل إدارة مدرسة الشهيد إسطفانوس!')")
                    ->execute([$targetId]);
                $_SESSION['flash_success'] = 'تم تفعيل الحساب وإشعار المستخدم بنجاح!';
            } elseif ($action === 'suspend') {
                $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$targetId]);
                if (function_exists('log_action')) {
                    log_action($_SESSION['user']['id'], 'USER_SUSPENDED', "Suspended user ID {$targetId}");
                }
                $_SESSION['flash_success'] = 'تم إيقاف الحساب!';
            } elseif ($action === 'delete') {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                if (function_exists('log_action')) {
                    log_action($_SESSION['user']['id'], 'USER_DELETED', "Deleted user ID {$targetId}");
                }
                $_SESSION['flash_success'] = 'تم حذف المستخدم نهائياً.';
            } elseif ($action === 'transfer_class') {
                $newClassId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
                if ($newClassId) {
                    $uStmt = $db->prepare('SELECT id, full_name, role, class_id FROM users WHERE id = ?');
                    $uStmt->execute([$targetId]);
                    $targetUser = $uStmt->fetch();

                    $clsStmt = $db->prepare('
                        SELECT c.id as class_id, c.name_ar as class_name,
                               g.id as grade_id, g.name_ar as grade_name,
                               s.id as stage_id, s.name_ar as stage_name
                        FROM classes c
                        JOIN grades g ON c.grade_id = g.id
                        JOIN stages s ON g.stage_id = s.id
                        WHERE c.id = ?
                    ');
                    $clsStmt->execute([$newClassId]);
                    $newClass = $clsStmt->fetch();

                    if ($targetUser && $newClass && in_array($targetUser['role'], ['student', 'servant'], true)) {
                        $oldClassId = $targetUser['class_id'];
                        $db->prepare('UPDATE users SET stage_id = ?, grade_id = ?, class_id = ? WHERE id = ?')
                            ->execute([$newClass['stage_id'], $newClass['grade_id'], $newClass['class_id'], $targetId]);

                        if ($targetUser['role'] === 'servant') {
                            if ($oldClassId) {
                                $db->prepare('DELETE FROM servant_classes WHERE servant_id = ? AND class_id = ?')->execute([$targetId, $oldClassId]);
                            }
                            $db->prepare('INSERT IGNORE INTO servant_classes (servant_id, class_id) VALUES (?, ?)')->execute([$targetId, $newClass['class_id']]);

                            $msg = "تم نقلك وإسنادك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                            $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل الفصل والتكليف 👨‍🏫', ?)")
                                ->execute([$targetId, $msg]);

                            if (function_exists('log_action')) {
                                log_action($_SESSION['user']['id'], 'SERVANT_TRANSFERRED', "Transferred servant {$targetUser['full_name']} (ID: {$targetId}) to class {$newClass['class_name']}");
                            }
                        } else {
                            $msg = "تم نقلك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                            $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل الفصل الدراسي 🏫', ?)")
                                ->execute([$targetId, $msg]);

                            $pStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
                            $pStmt->execute([$targetId]);
                            $parentIds = $pStmt->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($parentIds as $pId) {
                                $parentMsg = "نود إحاطتكم بأنه تم نقل ابنكم {$targetUser['full_name']} إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}).";
                                $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تحديث فصل الشماس 📢', ?)")
                                    ->execute([$pId, $parentMsg]);
                            }

                            if (function_exists('log_action')) {
                                log_action($_SESSION['user']['id'], 'STUDENT_TRANSFERRED', "Transferred student {$targetUser['full_name']} (ID: {$targetId}) to class {$newClass['class_name']}");
                            }
                        }

                        $_SESSION['flash_success'] = "تم نقل {$targetUser['full_name']} إلى فصل ({$newClass['class_name']}) بنجاح!";
                    } else {
                        $_SESSION['flash_error'] = 'بيانات المستخدم أو الفصل المحدد غير صالحة.';
                    }
                } else {
                    $_SESSION['flash_error'] = 'يرجى اختيار الفصل الجديد المطلوب.';
                }
            }
            header('Location: '.BASE_URL.'admin/users.php');
            exit;
        }
    } else {
        $_SESSION['flash_error'] = 'رمز CSRF غير صالح.';
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
$stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.6rem; margin-bottom:0.25rem;">إدارة كافة المستخدمين 👥</h1>
                <p style="color:var(--text-muted); font-size:0.92rem; margin:0;">إضافة، تفعيل، إيقاف، وتعديل بيانات الشمامسة والخدام وأولياء الأمور (إجمالي: <?= count($users) ?>)</p>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>admin/transfer.php" class="btn btn-gold btn-sm">🔄 نقل وتوزيع الفصول</a>
                <a href="<?= BASE_URL ?>admin/import_students.php" class="btn btn-primary btn-sm">📥 استيراد شمامسة</a>
                <a href="<?= BASE_URL ?>api/export.php?type=students" class="btn btn-secondary btn-sm">📊 تصدير Excel</a>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ طباعة</button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:8px; font-size:0.95rem; text-align:right;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:8px; font-size:0.95rem; text-align:right;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Search & Filters -->
        <div class="glass-card" style="margin-bottom:1.5rem; padding:1.25rem;">
            <form action="" method="GET" class="users-filter-bar">
                <input type="text" name="search" class="form-control" placeholder="بحث بالاسم، الهاتف، أو الكود..." value="<?= sanitize($search) ?>">
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
                <button type="submit" class="btn btn-primary" style="white-space:nowrap; padding:0.8rem 1.2rem;">🔍 تصفية</button>
                <?php if ($search || $roleFilter || $statusFilter) { ?>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary" style="white-space:nowrap; padding:0.8rem 1rem;">إلغاء</a>
                <?php } ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="glass-card printable-area" style="padding:0; overflow:hidden;">
            <div class="table-responsive">
                <table class="custom-table users-table">
                    <thead>
                        <tr>
                            <th style="width:55px; text-align:center;">#</th>
                            <th style="width:110px;">الكود</th>
                            <th style="min-width:180px;">الاسم بالكامل</th>
                            <th style="min-width:120px;">الهاتف</th>
                            <th style="min-width:110px;">الدور</th>
                            <th style="min-width:170px;">المرحلة والصف والفصل</th>
                            <th style="min-width:85px; text-align:center;">الحالة</th>
                            <th style="min-width:160px; text-align:center;">إجراءات التحكم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)) { ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:2.5rem; color:var(--text-muted);">
                                    لم يتم العثور على أي مستخدمين مطابقين لمعايير البحث.
                                </td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($users as $u) { ?>
                            <tr>
                                <td style="text-align:center;">
                                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($u['profile_pic'] ?? 'default-avatar.png') ?>" 
                                         style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid var(--gold); display:inline-block;" 
                                         alt="الصورة" 
                                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                                </td>
                                <td>
                                    <span class="code-badge"><?= sanitize($u['qr_code_token'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <strong><?= sanitize($u['full_name']) ?></strong>
                                    <?php if (! empty($u['deacon_rank'])) { ?>
                                        <div class="text-muted" style="font-size:0.78rem;"><?= sanitize($u['deacon_rank']) ?></div>
                                    <?php } ?>
                                </td>
                                <td dir="ltr" style="text-align:right; font-family:monospace; font-size:0.9rem;">
                                    <?= sanitize($u['phone']) ?>
                                </td>
                                <td>
                                    <?php
                                    switch ($u['role']) {
                                        case 'student':
                                            echo '<span class="badge badge-info">شماس / طالب</span>';
                                            break;
                                        case 'servant':
                                            echo '<span class="badge badge-gold">خادم</span>';
                                            break;
                                        case 'parent':
                                            echo '<span class="badge badge-purple">ولي أمر</span>';
                                            break;
                                        case 'admin':
                                            echo '<span class="badge badge-danger">مدير نظام</span>';
                                            break;
                                        default:
                                            echo '<span class="badge badge-secondary">'.sanitize($u['role']).'</span>';
                                    }
                            ?>
                                </td>
                                <td>
                                    <?php if ($u['stage_name'] || $u['grade_name']) { ?>
                                        <span style="font-weight:600;"><?= sanitize($u['stage_name'] ?? '') ?></span>
                                        <?php if ($u['grade_name']) { ?>
                                            <span class="text-muted"> - <?= sanitize($u['grade_name']) ?></span>
                                        <?php } ?>
                                        <?php if (! empty($u['class_name'])) { ?>
                                            <div><span class="badge badge-info" style="font-size:0.75rem; margin-top:0.25rem;">فصل: <?= sanitize($u['class_name']) ?></span></div>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <span class="text-muted">-</span>
                                    <?php } ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($u['status'] === 'active') { ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php } elseif ($u['status'] === 'pending') { ?>
                                        <span class="badge badge-warning">معلق</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">موقوف</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.35rem; align-items:center; justify-content:center; flex-wrap:nowrap;">
                                        <?php if ($u['role'] === 'student') { ?>
                                            <a href="<?= BASE_URL ?>admin/student_report.php?id=<?= $u['id'] ?>" class="btn btn-primary btn-sm" style="padding:0.35rem 0.6rem; font-size:0.8rem;" title="التقرير المفصل للشماس">📊</a>
                                        <?php } ?>
                                        <?php if ($u['role'] === 'student' || $u['role'] === 'servant') { ?>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="openTransferModal(<?= htmlspecialchars(json_encode([
                                                'id' => (int) $u['id'],
                                                'name' => $u['full_name'],
                                                'role' => $u['role'],
                                                'role_ar' => ($u['role'] === 'servant' ? 'خادم' : 'شماس / مخدوم'),
                                                'stage_name' => $u['stage_name'] ?? 'غير محدد',
                                                'grade_name' => $u['grade_name'] ?? 'غير محدد',
                                                'class_name' => $u['class_name'] ?? 'بدون فصل',
                                                'stage_id' => (int) ($u['stage_id'] ?? 0),
                                                'grade_id' => (int) ($u['grade_id'] ?? 0),
                                                'class_id' => (int) ($u['class_id'] ?? 0),
                                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="نقل إلى فصل آخر" style="padding:0.35rem 0.6rem; font-size:0.8rem; background:#f59e0b; border-color:#d97706; color:#fff;">🔄 نقل</button>
                                        <?php } ?>
                                        <?php if ($u['status'] !== 'active') { ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل تريد تفعيل الحساب؟');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-gold btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">تفعيل</button>
                                            </form>
                                        <?php } else { ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل تريد إيقاف الحساب؟');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">إيقاف</button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($u['id'] != $_SESSION['user']['id']) { ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من الحذف النهائي؟');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">حذف</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Transfer Class Modal -->
<div id="transferModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
    <div class="glass-card" style="width:100%; max-width:500px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:0 10px 30px rgba(0,0,0,0.3); padding:1.75rem; position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <h3 style="color:var(--royal-blue); margin:0; font-weight:800; font-size:1.2rem; display:flex; align-items:center; gap:0.5rem;">
                <span>🔄</span>
                <span>نقل بين الفصول الدراسية</span>
            </h3>
            <button type="button" onclick="closeTransferModal()" style="background:none; border:none; font-size:1.5rem; color:var(--text-muted); cursor:pointer; line-height:1;">&times;</button>
        </div>

        <form action="" method="POST" id="transferForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="transfer_class">
            <input type="hidden" name="user_id" id="modal_user_id" value="">

            <div style="background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); margin-bottom:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                    <strong id="modal_user_name" style="font-size:1.05rem; color:var(--text-primary);"></strong>
                    <span id="modal_user_role" class="badge"></span>
                </div>
                <div style="font-size:0.85rem; color:var(--text-muted);">
                    الفصل الحالي: <strong id="modal_current_class" style="color:var(--gold);"></strong>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="font-weight:700;">المرحلة الدراسية الجديدة *</label>
                <select id="modal_stage_id" class="form-control" required onchange="onModalStageChange()">
                    <option value="">اختر المرحلة...</option>
                    <?php foreach ($stages as $stg) { ?>
                        <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="font-weight:700;">الصف الدراسي الجديد *</label>
                <select id="modal_grade_id" class="form-control" required onchange="onModalGradeChange()">
                    <option value="">اختر الصف...</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label" style="font-weight:700;">الفصل الجديد المستهدف *</label>
                <select id="modal_class_id" name="class_id" class="form-control" required>
                    <option value="">اختر الفصل...</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeTransferModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold" style="font-weight:800;">تأكيد ونقل المستخدم 🚀</button>
            </div>
        </form>
    </div>
</div>

<script>
const baseUrl = '<?= BASE_URL ?>';

function openTransferModal(userData) {
    document.getElementById('modal_user_id').value = userData.id;
    document.getElementById('modal_user_name').textContent = userData.name;
    
    const roleBadge = document.getElementById('modal_user_role');
    roleBadge.textContent = userData.role_ar;
    roleBadge.className = 'badge ' + (userData.role === 'servant' ? 'badge-gold' : 'badge-info');
    
    document.getElementById('modal_current_class').textContent = 
        userData.stage_name + ' ➔ ' + userData.grade_name + ' ➔ ' + userData.class_name;
    
    document.getElementById('modal_stage_id').value = '';
    document.getElementById('modal_grade_id').innerHTML = '<option value="">اختر الصف...</option>';
    document.getElementById('modal_class_id').innerHTML = '<option value="">اختر الفصل...</option>';
    
    const modal = document.getElementById('transferModal');
    modal.style.display = 'flex';
}

function closeTransferModal() {
    document.getElementById('transferModal').style.display = 'none';
}

function onModalStageChange() {
    const stageId = document.getElementById('modal_stage_id').value;
    const gradeSelect = document.getElementById('modal_grade_id');
    const classSelect = document.getElementById('modal_class_id');
    
    gradeSelect.innerHTML = '<option value="">اختر الصف...</option>';
    classSelect.innerHTML = '<option value="">اختر الفصل...</option>';
    
    if (!stageId) return;
    
    fetch(`${baseUrl}api/get_grades.php?stage_id=${stageId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                res.data.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = g.name_ar;
                    gradeSelect.appendChild(opt);
                });
            }
        });
}

function onModalGradeChange() {
    const gradeId = document.getElementById('modal_grade_id').value;
    const classSelect = document.getElementById('modal_class_id');
    
    classSelect.innerHTML = '<option value="">اختر الفصل...</option>';
    if (!gradeId) return;
    
    fetch(`${baseUrl}api/get_classes.php?grade_id=${gradeId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                res.data.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name_ar;
                    classSelect.appendChild(opt);
                });
            }
        });
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeTransferModal();
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>


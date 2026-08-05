<?php
$pageTitle = 'تعديل الملف الشخصي';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/session.php';
require_once __DIR__.'/includes/auth_check.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/csrf.php';

require_login();

$db = getDB();
$userId = $_SESSION['user']['id'];

$error = '';
$success = '';

// Fetch current user details
$stmt = $db->prepare('
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.id = ?
');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (! verify_csrf_token($csrfToken)) {
        $error = 'رمز CSRF غير صالح.';
    } else {
        $fullName = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $gender = sanitize($_POST['gender'] ?? $user['gender']);
        $address = sanitize($_POST['address']);
        $dob = sanitize($_POST['dob']);

        $deaconRank = ($user['role'] === 'student' && $gender === 'male') ? sanitize($_POST['deacon_rank'] ?? 'إبصالتس (مرتل)') : null;

        $fatherName = sanitize($_POST['father_name'] ?? '');
        $fatherPhone = sanitize($_POST['father_phone'] ?? '');
        $motherName = sanitize($_POST['mother_name'] ?? '');
        $motherPhone = sanitize($_POST['mother_phone'] ?? '');

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($fullName) || empty($phone)) {
            $error = 'يرجى أدخال الاسم ورقم الهاتف.';
        } else {
            // Check email or phone duplicate for other users
            $dupStmt = $db->prepare("SELECT id FROM users WHERE (phone = ? OR (email IS NOT NULL AND email = ? AND email != '')) AND id != ?");
            $dupStmt->execute([$phone, $email, $userId]);
            if ($dupStmt->fetch()) {
                $error = 'رقم الهاتف أو البريد الإلكتروني مستخدم بالفعل بحساب آخر.';
            } else {
                $profilePic = $user['profile_pic'];

                // Profile Image Upload
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $profilePic = 'avatar_'.time().'_'.rand(1000, 9999).'.'.$ext;
                        move_uploaded_file($_FILES['profile_pic']['tmp_name'], UPLOAD_PATH.'profile/'.$profilePic);
                    }
                }

                // Update Password if provided
                if (! empty($newPassword)) {
                    if (! password_verify($currentPassword, $user['password'])) {
                        $error = 'كلمة المرور الحالية غير صحيحة.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
                    } else {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updStmt = $db->prepare('UPDATE users SET full_name=?, phone=?, email=?, gender=?, address=?, dob=?, deacon_rank=?, father_name=?, father_phone=?, mother_name=?, mother_phone=?, profile_pic=?, password=? WHERE id=?');
                        $updStmt->execute([$fullName, $phone, $email ?: null, $gender, $address ?: null, $dob ?: null, $deaconRank, $fatherName ?: null, $fatherPhone ?: null, $motherName ?: null, $motherPhone ?: null, $profilePic, $newHash, $userId]);
                        $success = 'تم تحديث البيانات وكلمة المرور بنجاح!';
                    }
                } else {
                    $updStmt = $db->prepare('UPDATE users SET full_name=?, phone=?, email=?, gender=?, address=?, dob=?, deacon_rank=?, father_name=?, father_phone=?, mother_name=?, mother_phone=?, profile_pic=? WHERE id=?');
                    $updStmt->execute([$fullName, $phone, $email ?: null, $gender, $address ?: null, $dob ?: null, $deaconRank, $fatherName ?: null, $fatherPhone ?: null, $motherName ?: null, $motherPhone ?: null, $profilePic, $userId]);
                    $success = 'تم تحديث البيانات الشخصية بنجاح!';
                }

                if (empty($error)) {
                    auto_link_parents((int) $userId);

                    $_SESSION['user']['full_name'] = $fullName;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['email'] = $email;
                    log_action($userId, 'PROFILE_UPDATED', 'User updated profile information');

                    // Refresh local user variable
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                }
            }
        }
    }
}

require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">تعديل البيانات والملف الشخصي 👤</h1>

        <?php if ($error) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center;">
                <?= $error ?>
            </div>
        <?php } ?>

        <?php if ($success) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center;">
                <?= $success ?>
            </div>
        <?php } ?>

        <div class="glass-card">
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div style="display:flex; align-items:center; gap:1.5rem; margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border-color);">
                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($user['profile_pic'] ?? 'default-avatar.png') ?>" style="width:90px; height:90px; border-radius:50%; object-fit:cover; border:3px solid var(--gold);" alt="الصورة الشخصية" onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                    <div>
                        <h3 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($user['full_name']) ?></h3>
                        <p style="color:var(--text-muted); font-size:0.85rem;">كود الحساب: <code><?= sanitize($user['qr_code_token']) ?></code></p>
                        <?php if ($user['role'] === 'student' && $user['gender'] === 'male' && $user['deacon_rank']) { ?>
                            <span class="badge badge-gold" style="margin-top:0.4rem;">✝️ <?= sanitize($user['deacon_rank']) ?></span>
                        <?php } ?>
                        <div style="margin-top:0.5rem;">
                            <input type="file" name="profile_pic" accept="image/*" class="form-control" style="font-size:0.85rem;">
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
                    <div class="form-group">
                        <label class="form-label">الاسم بالكامل *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الهاتف *</label>
                        <input type="text" name="phone" class="form-control" value="<?= sanitize($user['phone']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?= sanitize($user['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الجنس (ولد / بنت)</label>
                        <select name="gender" id="profileGender" class="form-control" onchange="toggleProfileRankUI()">
                            <option value="male" <?= ($user['gender'] === 'male') ? 'selected' : '' ?>>ذكر (ولد)</option>
                            <option value="female" <?= ($user['gender'] === 'female') ? 'selected' : '' ?>>أنثى (بنت)</option>
                        </select>
                    </div>

                    <?php if ($user['role'] === 'student') { ?>
                        <div class="form-group" id="profileRankGroup">
                            <label class="form-label">الرتبة الشموسية (للأولاد فقط)</label>
                            <select name="deacon_rank" class="form-control">
                                <option value="إبصالتس (مرتل)" <?= ($user['deacon_rank'] === 'إبصالتس (مرتل)') ? 'selected' : '' ?>>إبصالتس (مرتل)</option>
                                <option value="أغنسطس (قارئ)" <?= ($user['deacon_rank'] === 'أغنسطس (قارئ)') ? 'selected' : '' ?>>أغنسطس (قارئ)</option>
                                <option value="إبديدياكون (معاون)" <?= ($user['deacon_rank'] === 'إبديدياكون (معاون)') ? 'selected' : '' ?>>إبديدياكون (معاون)</option>
                                <option value="دياكون (شماس كامل)" <?= ($user['deacon_rank'] === 'دياكون (شماس كامل)') ? 'selected' : '' ?>>دياكون (شماس كامل)</option>
                                <option value="أرشيدياكون (رئيس الشمامسة)" <?= ($user['deacon_rank'] === 'أرشيدياكون (رئيس الشمامسة)') ? 'selected' : '' ?>>أرشيدياكون (رئيس الشمامسة)</option>
                                <option value="طالب قيد الإعداد" <?= ($user['deacon_rank'] === 'طالب قيد الإعداد') ? 'selected' : '' ?>>طالب قيد الإعداد (بدون رتبة)</option>
                            </select>
                        </div>
                    <?php } ?>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">العنوان السكني بالكامل</label>
                        <input type="text" name="address" class="form-control" value="<?= sanitize($user['address'] ?? '') ?>" placeholder="المنطقة، الشارع، المبنى...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">تاريخ الميلاد</label>
                        <input type="date" name="dob" class="form-control" value="<?= sanitize($user['dob'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">المرحلة والصف</label>
                        <input type="text" class="form-control" value="<?= sanitize($user['stage_name'] ?? 'عام') ?> - <?= sanitize($user['grade_name'] ?? 'غير محدد') ?>" disabled readonly>
                    </div>
                </div>

                <?php if ($user['role'] === 'student') { ?>
                    <div style="background:var(--gold-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0;">
                        <h4 style="color:var(--gold); margin-bottom:1rem;">👨‍👩‍👦 بيانات الأب والأم (للربط التلقائي بحسابات ولي الأمر)</h4>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div class="form-group">
                                <label class="form-label">اسم الأب بالكامل</label>
                                <input type="text" name="father_name" class="form-control" value="<?= sanitize($user['father_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">رقم هاتف الأب</label>
                                <input type="text" name="father_phone" class="form-control" value="<?= sanitize($user['father_phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">اسم الأم بالكامل</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= sanitize($user['mother_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">رقم هاتف الأم</label>
                                <input type="text" name="mother_phone" class="form-control" value="<?= sanitize($user['mother_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <div style="background:var(--royal-blue-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0;">
                    <h4 style="color:var(--royal-blue); margin-bottom:1rem;">تغيير كلمة المرور (اختياري)</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold" style="padding:0.85rem 2rem; font-size:1.05rem;">حفظ التغييرات</button>
            </form>
        </div>
    </main>
</div>

<script>
function toggleProfileRankUI() {
    const genderEl = document.getElementById('profileGender');
    const rankGrp = document.getElementById('profileRankGroup');
    if (genderEl && rankGrp) {
        rankGrp.style.display = (genderEl.value === 'male') ? 'block' : 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleProfileRankUI);
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>

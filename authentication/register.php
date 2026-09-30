<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use Illuminate\Support\Facades\RateLimiter;

$pageTitle = 'تسجيل حساب جديد';
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';

$db = getDB();

// Fetch stages for dynamic dropdown
try {
    $stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();
} catch (Throwable $e) {
    $stages = [];
}

$error = '';
$success = '';

$isLaravelBound = function_exists('app') && app()->bound('request');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || ($isLaravelBound && request()->isMethod('POST'));

$fullName = '';
$phone = '';
$email = '';
$role = 'student';
$gender = 'male';
$dob = '';
$address = '';
$stageId = null;
$gradeId = null;
$classId = null;
$deaconRank = 'إبصالتس (مرتل)';

if ($isPost) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'student');
    $gender = sanitize($_POST['gender'] ?? 'male');
    $dob = sanitize($_POST['dob'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    // Validate allowed roles (student, servant, parent, admin)
    if (! in_array($role, ['student', 'servant', 'parent', 'admin'], true)) {
        $role = 'student';
    }

    // Only set deacon rank if Male & (Student or Servant)
    $deaconRank = (($role === 'student' || $role === 'servant') && $gender === 'male')
        ? sanitize($_POST['deacon_rank'] ?? 'إبصالتس (مرتل)')
        : null;

    // Parent details (only for student accounts)
    $fatherName = ($role === 'student') ? sanitize($_POST['father_name'] ?? '') : null;
    $fatherPhone = ($role === 'student') ? sanitize($_POST['father_phone'] ?? '') : null;
    $motherName = ($role === 'student') ? sanitize($_POST['mother_name'] ?? '') : null;
    $motherPhone = ($role === 'student') ? sanitize($_POST['mother_phone'] ?? '') : null;

    $stageId = ($role === 'student' || $role === 'servant') ? filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT) : null;
    $gradeId = ($role === 'student' || $role === 'servant') ? filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT) : null;
    $classId = ($role === 'student' || $role === 'servant') ? filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT) : null;
    $csrfToken = $_POST['csrf_token'] ?? '';

    $clientIp = $isLaravelBound ? request()->ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $throttleKey = 'register:'.$clientIp;
    $hasLaravelRateLimiter = function_exists('app') && app()->bound('rate_limiter');

    $isThrottled = false;
    $remainingSeconds = 0;

    if ($hasLaravelRateLimiter) {
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $remainingSeconds = RateLimiter::availableIn($throttleKey);
            $isThrottled = true;
        }
    } else {
        $attempts = $_SESSION['register_attempts'][$throttleKey] ?? ['count' => 0, 'last_attempt' => 0];
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) < 900) {
            $remainingSeconds = 900 - (time() - $attempts['last_attempt']);
            $isThrottled = true;
        }
    }

    if ($isThrottled) {
        $minutes = max(1, (int) ceil($remainingSeconds / 60));
        $error = "تم تجاوز الحد الأقصى لمحاولات التسجيل من هذا الجهاز. يرجى الانتظار {$minutes} دقيقة والمحاولة مجدداً.";
    } elseif (! verify_csrf_token($csrfToken)) {
        $error = 'رمز CSRF غير صالح.';
    } elseif (empty($fullName) || empty($phone) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول الأساسية (الاسم، الهاتف، وكلمة المرور).';
    } elseif (strlen($password) < 8) {
        $error = 'كلمة المرور يجب أن لا تقل عن 8 أحرف لضمان حماية وأمان الحساب.';
    } elseif ($password !== $confirmPassword) {
        $error = 'كلمة المرور وتأكيد كلمة المرور غير متطابقين.';
    } elseif ($role === 'student' && (empty($stageId) || empty($gradeId) || empty($classId))) {
        $error = 'يرجى اختيار المرحلة والصف والفصل الخاص بالطالب / الشماس.';
    } else {
        if ($hasLaravelRateLimiter) {
            RateLimiter::hit($throttleKey, 900); // 15 minutes window
        } else {
            $prevCount = $_SESSION['register_attempts'][$throttleKey]['count'] ?? 0;
            $_SESSION['register_attempts'][$throttleKey] = [
                'count' => $prevCount + 1,
                'last_attempt' => time(),
            ];
        }

        // Check duplicate phone or email
        $checkStmt = $db->prepare("SELECT id FROM users WHERE phone = ? OR (email IS NOT NULL AND email = ? AND email != '')");
        $checkStmt->execute([$phone, $email]);
        if ($checkStmt->fetch()) {
            $error = 'رقم الهاتف أو البريد الإلكتروني مسجل بالفعل لدى مستخدم آخر.';
        } else {
            // Handle Profile Pic Upload (MIME, Magic bytes, Extension, Max size 3MB)
            $profilePicName = 'default-avatar.png';
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $tmpFile = $_FILES['profile_pic']['tmp_name'];
                $fileSize = $_FILES['profile_pic']['size'];
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $finfoMime = function_exists('mime_content_type') ? mime_content_type($tmpFile) : 'image/jpeg';

                if ($fileSize > 3 * 1024 * 1024) {
                    $error = 'حجم الصورة الشخصية يجب ألا يتجاوز 3 ميجابايت.';
                } elseif (! in_array($ext, $allowedExts, true) || ! in_array($finfoMime, $allowedMimes, true) || @getimagesize($tmpFile) === false) {
                    $error = 'نوع ملف الصورة غير صالح. يرجى اختيار صورة حقيقية بصيغة JPG أو PNG أو WebP فقط.';
                } else {
                    $profileDir = UPLOAD_PATH.'profile/';
                    if (! is_dir($profileDir)) {
                        mkdir($profileDir, 0777, true);
                    }
                    $profilePicName = 'avatar_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                    move_uploaded_file($tmpFile, $profileDir.$profilePicName);
                }
            }

            if (empty($error)) {
                try {
                    // Generate unique user code (3 letters + 4 digits)
                    $qrToken = generate_unique_user_code($role, $db);
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    try {
                        // Attempt full insert with all extended fields
                        $stmt = $db->prepare("
                            INSERT INTO users (full_name, phone, email, password, role, gender, status, dob, address, deacon_rank, father_name, father_phone, mother_name, mother_phone, stage_id, grade_id, class_id, profile_pic, qr_code_token)
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $fullName, $phone, ! empty($email) ? $email : null, $hashedPassword,
                            $role, $gender, ! empty($dob) ? $dob : null, $address ?: null, $deaconRank,
                            $fatherName ?: null, $fatherPhone ?: null, $motherName ?: null, $motherPhone ?: null,
                            $stageId ?: null, $gradeId ?: null, $classId ?: null,
                            $profilePicName, $qrToken,
                        ]);
                    } catch (Throwable $dbErr) {
                        // Fallback: If legacy users table is missing new columns (e.g. gender, address, deacon_rank)
                        $stmt = $db->prepare("
                            INSERT INTO users (full_name, phone, email, password, role, status, dob, stage_id, grade_id, class_id, profile_pic, qr_code_token)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $fullName, $phone, ! empty($email) ? $email : null, $hashedPassword,
                            $role, ! empty($dob) ? $dob : null,
                            $stageId ?: null, $gradeId ?: null, $classId ?: null,
                            $profilePicName, $qrToken,
                        ]);
                    }

                    $newUserId = (int) $db->lastInsertId();

                    // Auto link Parent and Student accounts if phone numbers match
                    if (function_exists('auto_link_parents')) {
                        auto_link_parents($newUserId);
                    }

                    if (function_exists('log_action')) {
                        log_action($newUserId, 'REGISTERED_PENDING', "New {$role} ({$gender}) registered with pending status");
                    }

                    $_SESSION['flash_success'] = 'تم تقديم طلب التسجيل بنجاح! حسابك الآن في حالة (قيد الانتظار - Pending) لحين اعتماده من إدارة المدرسة.';
                    $redirectUrl = defined('BASE_URL') ? BASE_URL.'authentication/login.php' : 'login.php';
                    header('Location: '.$redirectUrl);
                    exit;
                } catch (Throwable $e) {
                    $error = 'حدث خطأ أثناء معالجة طلب التسجيل: '.$e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.role-select-box {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.role-option {
    border: 2px solid var(--border-color);
    background: var(--bg-surface);
    border-radius: var(--radius-sm);
    padding: 0.85rem 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    user-select: none;
}

.role-option:hover {
    border-color: var(--royal-blue);
    background: var(--royal-blue-glow);
}

.role-option.active {
    border-color: var(--gold);
    background: var(--gold-glow);
    box-shadow: 0 0 12px var(--gold-glow);
}

.role-option input[type="radio"] {
    display: none;
}

.role-option .role-emoji {
    font-size: 1.6rem;
    display: block;
    margin-bottom: 0.25rem;
}

.role-option .role-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-primary);
}

.reg-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

@media (max-width: 640px) {
    .role-select-box {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    .role-option {
        padding: 0.65rem 0.35rem;
    }
    .role-option .role-emoji {
        font-size: 1.35rem;
    }
    .role-option .role-name {
        font-size: 0.8rem;
    }
    .reg-form-grid {
        grid-template-columns: 1fr !important;
        gap: 1rem;
    }
    .reg-form-grid > .form-group {
        grid-column: span 1 !important;
    }
}

@media (max-width: 360px) {
    .role-select-box {
        grid-template-columns: 1fr;
    }
}
</style>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:clamp(1rem, 3vw, 2rem) clamp(0.5rem, 2.5vw, 1rem);">
    <div class="glass-card" style="width:100%; max-width:800px;">
        <div style="text-align:center; margin-bottom:1.75rem;">
            <div style="margin-bottom:0.6rem;">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="شعار مدرسة الشهيد إسطفانوس" style="width:80px; height:80px; object-fit:contain; filter:drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
            </div>
            <h2 style="color:var(--royal-blue); font-weight:800;">تسجيل حساب جديد</h2>
            <p style="color:var(--text-muted); font-size:0.92rem;">مدرسة الشهيد إسطفانوس - اختر نوع الحساب وأدخل بياناتك</p>
        </div>

        <?php if ($error) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center; font-size:0.9rem; border-radius:var(--radius-sm);">
                <?= $error ?>
            </div>
        <?php } ?>

        <form action="" method="POST" enctype="multipart/form-data" id="registerForm">
            <?= csrf_field() ?>

            <!-- Role Selection Bar -->
            <label class="form-label" style="font-weight:800; margin-bottom:0.5rem; display:block;">اختر نوع الحساب / الدور *</label>
            <div class="role-select-box">
                <label class="role-option <?= $role === 'student' ? 'active' : '' ?>" id="role-opt-student" onclick="selectRole('student')">
                    <input type="radio" name="role" value="student" <?= $role === 'student' ? 'checked' : '' ?>>
                    <span class="role-emoji">📜</span>
                    <span class="role-name">شماس / مخدوم</span>
                </label>

                <label class="role-option <?= $role === 'servant' ? 'active' : '' ?>" id="role-opt-servant" onclick="selectRole('servant')">
                    <input type="radio" name="role" value="servant" <?= $role === 'servant' ? 'checked' : '' ?>>
                    <span class="role-emoji">👨‍🏫</span>
                    <span class="role-name">خادم</span>
                </label>

                <label class="role-option <?= $role === 'parent' ? 'active' : '' ?>" id="role-opt-parent" onclick="selectRole('parent')">
                    <input type="radio" name="role" value="parent" <?= $role === 'parent' ? 'checked' : '' ?>>
                    <span class="role-emoji">👨‍👩‍👦</span>
                    <span class="role-name">ولي أمر</span>
                </label>

                <label class="role-option <?= $role === 'admin' ? 'active' : '' ?>" id="role-opt-admin" onclick="selectRole('admin')">
                    <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                    <span class="role-emoji">👑</span>
                    <span class="role-name">مسؤول / إدارة</span>
                </label>
            </div>

            <!-- Parent Role Notice Banner -->
            <div id="parentNoticeBox" style="display:none; background:var(--gold-glow); border:1px solid var(--gold); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem;">
                <div style="font-weight:800; color:var(--gold); margin-bottom:0.25rem;">👨‍👩‍👦 ربط تلقائي بحسابات الأبناء</div>
                <p style="font-size:0.85rem; color:var(--text-secondary); margin:0;">
                    بصفتك ولي أمر، سيقوم النظام تلقائياً بربط حسابك مع جميع أبنائك المسجلين بالمدرسة بمجرد تطابق رقم هاتفك الشخصي مع رقم هاتف الأب أو الأم المسجل في بياناتهم.
                </p>
            </div>

            <!-- Admin Role Notice Banner -->
            <div id="adminNoticeBox" style="display:none; background:var(--royal-blue-glow); border:1px solid var(--royal-blue); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem;">
                <div style="font-weight:800; color:var(--royal-blue); margin-bottom:0.25rem;">👑 طلب حساب إدارة النظام</div>
                <p style="font-size:0.85rem; color:var(--text-secondary); margin:0;">
                    سيتم مراجعة واعتماد طلب حساب المدير من قبل إدارة المدرسة المسؤولة قبل تفعيل الصلاحيات الكاملة.
                </p>
            </div>

            <!-- Basic Info Grid -->
            <div class="reg-form-grid">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="full_name">الاسم بالكامل *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="الاسم الرباعي" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">رقم الهاتف الشخصي *</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">البريد الإلكتروني (اختياري)</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="example@domain.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور *</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group" id="genderGroup">
                    <label class="form-label" for="gender">الجنس (ولد / بنت) *</label>
                    <select id="gender" name="gender" class="form-control" onchange="toggleFormFields()">
                        <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>ذكر (ولد)</option>
                        <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>أنثى (بنت)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dob">تاريخ الميلاد</label>
                    <input type="date" id="dob" name="dob" class="form-control" value="<?= htmlspecialchars($dob, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Deacon Rank (Shown for Male Students and Servants) -->
                <div class="form-group" id="deaconRankGroup" style="grid-column: span 2;">
                    <label class="form-label" for="deacon_rank">الرتبة الشموسية (للذكور فقط)</label>
                    <select id="deacon_rank" name="deacon_rank" class="form-control">
                        <option value="إبصالتس (مرتل)" <?= ($deaconRank === 'إبصالتس (مرتل)') ? 'selected' : '' ?>>إبصالتس (مرتل)</option>
                        <option value="أغنسطس (قارئ)" <?= ($deaconRank === 'أغنسطس (قارئ)') ? 'selected' : '' ?>>أغنسطس (قارئ)</option>
                        <option value="إبديدياكون (معاون)" <?= ($deaconRank === 'إبديدياكون (معاون)') ? 'selected' : '' ?>>إيبودياكون (مساعد شماس)</option>
                        <option value="دياكون (شماس كامل)" <?= ($deaconRank === 'دياكون (شماس كامل)') ? 'selected' : '' ?>>دياكون (شماس)</option>
                        <option value="أرشيدياكون (رئيس الشمامسة)" <?= ($deaconRank === 'أرشيدياكون (رئيس الشمامسة)') ? 'selected' : '' ?>>أرشيدياكون (رئيس الشمامسة)</option>
                        <option value="طالب قيد الإعداد" <?= ($deaconRank === 'طالب قيد الإعداد') ? 'selected' : '' ?>>طالب قيد الإعداد (بدون رتبة)</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="address">العنوان السكني</label>
                    <input type="text" id="address" name="address" class="form-control" placeholder="المنطقة، الشارع، رقم العقار..." value="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="profile_pic">الصورة الشخصية (اختياري)</label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
                </div>
            </div>

            <!-- Parent Information Section (Shown for Student Registration for Auto Linking) -->
            <div id="parentInfoSection" style="background:var(--gold-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0; border:1px solid rgba(217, 119, 6, 0.3);">
                <h4 style="color:var(--gold); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                    <span>👨‍👩‍👦</span>
                    <span>بيانات ولي الأمر (للربط التلقائي مع حساب الأب والأم)</span>
                </h4>
                <div class="reg-form-grid">
                    <div class="form-group">
                        <label class="form-label" for="father_name">اسم الأب بالكامل</label>
                        <input type="text" id="father_name" name="father_name" class="form-control" placeholder="اسم الأب الرباعي" value="<?= htmlspecialchars($_POST['father_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="father_phone">رقم هاتف الأب</label>
                        <input type="text" id="father_phone" name="father_phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['father_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mother_name">اسم الأم بالكامل</label>
                        <input type="text" id="mother_name" name="mother_name" class="form-control" placeholder="اسم الأم الرباعي" value="<?= htmlspecialchars($_POST['mother_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mother_phone">رقم هاتف الأم</label>
                        <input type="text" id="mother_phone" name="mother_phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['mother_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <p style="font-size:0.82rem; color:var(--text-secondary); margin-top:0.5rem; line-height:1.5;">
                    💡 <strong>خاصية الربط الذكي:</strong> عندما يقوم الأب أو الأم بإنشاء حساب بنفس رقم الهاتف المدخل هنا، سيتصل حسابهما بحساب الابن تلقائياً للاطلاع على تقارير القداسات والدرجات.
                </p>
            </div>

            <!-- Stage / Grade / Class Cascading Section -->
            <div id="stageClassSection" style="background:var(--royal-blue-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0; border:1px solid rgba(37, 99, 235, 0.2);">
                <h4 style="color:var(--royal-blue); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                    <span>🏫</span>
                    <span>المرحلة والفصل الدراسي</span>
                </h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="stage_id">المرحلة *</label>
                        <select id="stage_id" name="stage_id" class="form-control">
                            <option value="">اختر المرحلة...</option>
                            <?php foreach ($stages as $stg) { ?>
                                <option value="<?= $stg['id'] ?>" <?= ($stageId == $stg['id']) ? 'selected' : '' ?>><?= sanitize($stg['name_ar']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="grade_id">الصف *</label>
                        <select id="grade_id" name="grade_id" class="form-control">
                            <option value="">اختر الصف...</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="class_id">الفصل *</label>
                        <select id="class_id" name="class_id" class="form-control">
                            <option value="">اختر الفصل...</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-gold" style="width:100%; padding:0.95rem; font-size:1.1rem; font-weight:800; margin-top:1rem;">
                تقديم طلب التسجيل
            </button>
        </form>

        <div style="text-align:center; margin-top:1.5rem; font-size:0.92rem;">
            لديك حساب بالفعل؟ <a href="<?= BASE_URL ?>authentication/login.php" style="color:var(--royal-blue); font-weight:800;">تسجيل الدخول</a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/dynamic-dropdowns.js"></script>
<script>
    let currentRole = '<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>';

    function selectRole(role) {
        currentRole = role;

        // Update radio state
        document.querySelectorAll('.role-option').forEach(el => el.classList.remove('active'));
        const activeOpt = document.getElementById('role-opt-' + role);
        if (activeOpt) {
            activeOpt.classList.add('active');
            const radio = activeOpt.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }

        toggleFormFields();
    }

    function toggleFormFields() {
        const gender = document.getElementById('gender').value;
        const rankGrp = document.getElementById('deaconRankGroup');
        const parentSec = document.getElementById('parentInfoSection');
        const stageSec = document.getElementById('stageClassSection');
        const parentNotice = document.getElementById('parentNoticeBox');
        const adminNotice = document.getElementById('adminNoticeBox');

        // Deacon rank: visible ONLY for male students and servants
        if ((currentRole === 'student' || currentRole === 'servant') && gender === 'male') {
            if (rankGrp) rankGrp.style.display = 'block';
        } else {
            if (rankGrp) rankGrp.style.display = 'none';
        }

        // Parent Information section (for linking): visible for student role
        if (parentSec) {
            parentSec.style.display = (currentRole === 'student') ? 'block' : 'none';
        }

        // Parent notice banner
        if (parentNotice) {
            parentNotice.style.display = (currentRole === 'parent') ? 'block' : 'none';
        }

        // Admin notice banner
        if (adminNotice) {
            adminNotice.style.display = (currentRole === 'admin') ? 'block' : 'none';
        }

        // Stage & Class section: required for student, optional/visible for servant, hidden for parent & admin
        if (stageSec) {
            if (currentRole === 'student' || currentRole === 'servant') {
                stageSec.style.display = 'block';
            } else {
                stageSec.style.display = 'none';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDynamicDropdowns('stage_id', 'grade_id', 'class_id');
        toggleFormFields();
    });
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

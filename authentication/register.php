<?php
$pageTitle = 'تسجيل حساب جديد';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/helpers.php';

$db = getDB();

// Fetch stages for dynamic dropdown
$stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'student');
    $gender = sanitize($_POST['gender'] ?? 'male');
    $dob = sanitize($_POST['dob'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    // Only set deacon rank if Male & Student
    $deaconRank = ($role === 'student' && $gender === 'male') ? sanitize($_POST['deacon_rank'] ?? 'إبصالتس (مرتل)') : null;

    $fatherName = sanitize($_POST['father_name'] ?? '');
    $fatherPhone = sanitize($_POST['father_phone'] ?? '');
    $motherName = sanitize($_POST['mother_name'] ?? '');
    $motherPhone = sanitize($_POST['mother_phone'] ?? '');

    $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
    $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT);
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (! verify_csrf_token($csrfToken)) {
        $error = 'رمز CSRF غير صالح.';
    } elseif (empty($fullName) || empty($phone) || empty($password) || empty($dob) || empty($address)) {
        $error = 'يرجى ملء جميع الحقول الإلزامية (الاسم، الهاتف، التاريخ، العنوان، وكلمة المرور).';
    } elseif ($password !== $confirmPassword) {
        $error = 'كلمة المرور وتأكيد كلمة المرور غير متطابقين.';
    } else {
        // Check duplicate phone or email
        $checkStmt = $db->prepare("SELECT id FROM users WHERE phone = ? OR (email IS NOT NULL AND email = ? AND email != '')");
        $checkStmt->execute([$phone, $email]);
        if ($checkStmt->fetch()) {
            $error = 'رقم الهاتف أو البريد الإلكتروني مسجل بالفعل لدى مستخدم آخر.';
        } else {
            // Handle Profile Pic Upload
            $profilePicName = 'default-avatar.png';
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $profilePicName = 'avatar_'.time().'_'.rand(1000, 9999).'.'.$ext;
                    move_uploaded_file($_FILES['profile_pic']['tmp_name'], UPLOAD_PATH.'profile/'.$profilePicName);
                }
            }

            // Generate unique QR Token
            $prefix = ($role === 'student') ? 'STU-2026-' : (($role === 'servant') ? 'SRV-' : 'PRN-');
            $qrToken = $prefix.strtoupper(bin2hex(random_bytes(3)));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert pending user
            $stmt = $db->prepare("
                INSERT INTO users (full_name, phone, email, password, role, gender, status, dob, address, deacon_rank, father_name, father_phone, mother_name, mother_phone, stage_id, grade_id, class_id, profile_pic, qr_code_token)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fullName, $phone, ! empty($email) ? $email : null, $hashedPassword,
                $role, $gender, ! empty($dob) ? $dob : null, $address, $deaconRank,
                $fatherName ?: null, $fatherPhone ?: null, $motherName ?: null, $motherPhone ?: null,
                $stageId ? $stageId : null, $gradeId ? $gradeId : null, $classId ? $classId : null,
                $profilePicName, $qrToken,
            ]);

            $newUserId = $db->lastInsertId();

            // Auto link Parent and Student accounts if phone numbers match!
            auto_link_parents((int) $newUserId);

            log_action($newUserId, 'REGISTERED_PENDING', "New {$role} ({$gender}) registered with pending status");

            $_SESSION['flash_success'] = 'تم تقديم طلب التسجيل بنجاح! حسابك الآن في حالة (قيد الانتظار - Pending) لحين تفعيله من قبل إدارة المدرسة (Admin).';
            header('Location: '.BASE_URL.'authentication/login.php');
            exit;
        }
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div class="glass-card" style="width:100%; max-width:750px;">
        <div style="text-align:center; margin-bottom:2rem;">
            <div style="font-size:2.5rem; margin-bottom:0.5rem;">📝</div>
            <h2 style="color:var(--royal-blue); font-weight:800;">تسجيل حساب جديد</h2>
            <p style="color:var(--text-muted); font-size:0.9rem;">انضم إلى مدرسة الشهيد إسطفانوس للإلحان و التسبحة</p>
        </div>

        <?php if ($error) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:center; font-size:0.9rem;">
                <?= $error ?>
            </div>
        <?php } ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="full_name">الاسم بالكامل *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="الاسم الرباعي" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">رقم الهاتف الشخصي *</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="01XXXXXXXXX" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">البريد الإلكتروني (اختياري)</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="example@domain.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور *</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gender">الجنس (ولد / بنت) *</label>
                    <select id="gender" name="gender" class="form-control" onchange="toggleDeaconRankUI()" required>
                        <option value="male">ذكر (ولد)</option>
                        <option value="female">أنثى (بنت)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">نوع الحساب *</label>
                    <select id="role" name="role" class="form-control" onchange="toggleDeaconRankUI()" required>
                        <option value="student">طالب / مخدوم (Student)</option>
                        <option value="servant">خادم (Servant)</option>
                        <option value="parent">ولي أمر (Parent)</option>
                    </select>
                </div>

                <!-- Deacon Rank (Shown ONLY if Male & Student) -->
                <div class="form-group" id="deaconRankGroup" style="grid-column: span 2;">
                    <label class="form-label" for="deacon_rank">الرتبة الشموسية (للأولاد فقط)</label>
                    <select id="deacon_rank" name="deacon_rank" class="form-control">
                        <option value="إبصالتس (مرتل)">إبصالتس (مرتل)</option>
                        <option value="أغنسطس (قارئ)">أغنسطس (قارئ)</option>
                        <option value="إبديدياكون (معاون)">إيبودياكون (مساعد شماس)</option>
                        <option value="دياكون (شماس كامل)">دياكون (شماس)</option>
                        <option value="أرشيدياكون (رئيس الشمامسة)">أرشيدياكون (رئيس الشمامسة)</option>
                        <option value="طالب قيد الإعداد">طالب قيد الإعداد (بدون رتبة)</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="address">العنوان *</label>
                    <input type="text" id="address" name="address" class="form-control" placeholder="المنطقة، الشارع، رقم المبنى..." required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dob">تاريخ الميلاد *</label>
                    <input type="date" id="dob" name="dob" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="profile_pic">الصورة الشخصية</label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
                </div>
            </div>

            <!-- Parent Information Section (for Auto Linking) -->
            <div id="parentInfoSection" style="background:var(--gold-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0;">
                <h4 style="color:var(--gold); margin-bottom:1rem;">👨‍👩‍👦 بيانات ولي الأمر (للربط التلقائي مع حساب الأب والأم) *</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label" for="father_name">اسم الأب بالكامل *</label>
                        <input type="text" id="father_name" name="father_name" class="form-control" placeholder="اسم الأب">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="father_phone">رقم هاتف الأب *</label>
                        <input type="text" id="father_phone" name="father_phone" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mother_name">اسم الأم بالكامل *</label>
                        <input type="text" id="mother_name" name="mother_name" class="form-control" placeholder="اسم الأم">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mother_phone">رقم هاتف الأم *</label>
                        <input type="text" id="mother_phone" name="mother_phone" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                </div>
                <p style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem;">💡 عندما يقوم الأب أو الأم بإنشاء حساب بنفس رقم الهاتف المحمول، سيتصل حسابهم بالطفل تلقائياً.</p>
            </div>

            <!-- Dynamic Stage / Grade / Class Cascading Section -->
            <div style="background:var(--royal-blue-glow); padding:1.25rem; border-radius:var(--radius-sm); margin:1.5rem 0;">
                <h4 style="color:var(--royal-blue); margin-bottom:1rem;">بيانات المرحلة والفصل الدراسي *</h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:1rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="stage_id">المرحلة (Stage) *</label>
                        <select id="stage_id" name="stage_id" class="form-control" required>
                            <option value="">اختر المرحلة...</option>
                            <?php foreach ($stages as $stg) { ?>
                                <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="grade_id">الصف (Grade) *</label>
                        <select id="grade_id" name="grade_id" class="form-control" required>
                            <option value="">اختر الصف...</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="class_id">الفصل (Class) *</label>
                        <select id="class_id" name="class_id" class="form-control" required>
                            <option value="">اختر الفصل...</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-gold" style="width:100%; padding:0.9rem; font-size:1.05rem;">
                تقديم طلب التسجيل
            </button>
        </form>

        <div style="text-align:center; margin-top:1.5rem; font-size:0.9rem;">
            لديك حساب بالفعل؟ <a href="<?= BASE_URL ?>authentication/login.php" style="color:var(--royal-blue); font-weight:800;">تسجيل الدخول</a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/dynamic-dropdowns.js"></script>
<script>
    function toggleDeaconRankUI() {
        const gender = document.getElementById('gender').value;
        const role = document.getElementById('role').value;
        const rankGrp = document.getElementById('deaconRankGroup');
        const parentSec = document.getElementById('parentInfoSection');

        // Deacon rank shown ONLY if role === 'student' AND gender === 'male'
        if (role === 'student' && gender === 'male') {
            if (rankGrp) rankGrp.style.display = 'block';
        } else {
            if (rankGrp) rankGrp.style.display = 'none';
        }

        if (parentSec) {
            parentSec.style.display = (role === 'parent') ? 'none' : 'block';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDynamicDropdowns('stage_id', 'grade_id', 'class_id');
        toggleDeaconRankUI();
    });
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

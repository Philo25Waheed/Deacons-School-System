<?php
$pageTitle = 'استيراد الشمامسة بالجملة من Excel';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();

// Handle Template Download
if (isset($_GET['template']) && $_GET['template'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="deacons_import_template.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    $out = fopen('php://output', 'w');
    fputcsv($out, ['الاسم بالكامل', 'رقم الهاتف', 'البريد الإلكتروني', 'الجنس (male/female)', 'الرتبة الشموسية', 'رقم هاتف الأب', 'رقم هاتف الأم']);
    fputcsv($out, ['الشماس بيشوي عادل', '01234567890', 'bishoy@example.com', 'male', 'إبصالتس (مرتل)', '01122334455', '01223344556']);
    fputcsv($out, ['الشماسة مارينا سامي', '01234567891', 'marina@example.com', 'female', '', '01122334455', '01223344556']);
    fclose($out);
    exit;
}

$importedCount = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $userRole = $_SESSION['user']['role'] ?? '';
        $userId = $_SESSION['user']['id'] ?? 0;
        $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
        $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT);
        $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);

        if ($userRole === 'servant' && (! $classId || ! can_servant_access_class($userId, $classId, $userRole))) {
            $errors[] = 'عذراً، بصفتك خادماً يمكنك استيراد الشمامسة للفصول المسندة لخدمتك فقط.';
        } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['csv_file']['tmp_name'];
            $fileSize = $_FILES['csv_file']['size'];
            $origName = $_FILES['csv_file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $finfoMime = function_exists('mime_content_type') ? mime_content_type($tmpFile) : 'text/plain';
            $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];

            if (! in_array($ext, ['csv', 'txt'], true) || ! in_array($finfoMime, $allowedMimes, true)) {
                $errors[] = 'يرجى اختيار ملف CSV أو TXT صالح.';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $errors[] = 'حجم ملف الاستيراد يتجاوز الحد الأقصى (5 ميجابايت).';
            } else {
                $handle = fopen($tmpFile, 'r');
                // Skip BOM and header
                $header = fgetcsv($handle, 1000, ',');
                $defaultPassword = password_hash('Admin@123456', PASSWORD_DEFAULT);

                $db->beginTransaction();
                try {
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        if (empty($data[0]) || empty($data[1])) {
                            continue;
                        }

                        $fullName = sanitize($data[0] ?? '');
                        $phone = sanitize($data[1] ?? '');
                        $email = sanitize($data[2] ?? '');
                        $gender = strtolower(trim($data[3] ?? 'male'));
                        if (! in_array($gender, ['male', 'female'], true)) {
                            $gender = 'male';
                        }
                        $deaconRank = ($gender === 'male') ? sanitize($data[4] ?? 'إبصالتس (مرتل)') : null;
                        $fatherPhone = sanitize($data[5] ?? '');
                        $motherPhone = sanitize($data[6] ?? '');

                        // Check duplicate phone
                        $chk = $db->prepare('SELECT id FROM users WHERE phone = ?');
                        $chk->execute([$phone]);
                        if ($chk->fetch()) {
                            $errors[] = "رقم الهاتف ({$phone}) مسجل بالفعل للشماس ({$fullName}).";

                            continue;
                        }

                        $qrToken = generate_unique_user_code('student', $db);

                        $stmt = $db->prepare("
                            INSERT INTO users (full_name, phone, email, password, role, gender, status, deacon_rank, father_phone, mother_phone, stage_id, grade_id, class_id, qr_code_token)
                            VALUES (?, ?, ?, ?, 'student', ?, 'active', ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $fullName, $phone, ! empty($email) ? $email : null, $defaultPassword,
                            $gender, $deaconRank, $fatherPhone ?: null, $motherPhone ?: null,
                            $stageId ?: null, $gradeId ?: null, $classId ?: null, $qrToken,
                        ]);

                        $newId = $db->lastInsertId();
                        auto_link_parents((int) $newId);
                        $importedCount++;
                    }

                    $db->commit();
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('Student import error: '.$e->getMessage());
                    $errors[] = 'حدث خطأ أثناء استيراد البيانات. يرجى مراجعة محتوى الملف والمحاولة مجدداً.';
                }
                fclose($handle);

                if ($importedCount > 0) {
                    $_SESSION['flash_success'] = "تم استيراد ({$importedCount}) شماس بنجاح وتفعيل حساباتهم وتوليد كروت الـ QR والربط التلقائي بأولياء الأمور! كلمة السر الافتراضية: Admin@123456";
                }
            }
        } else {
            $errors[] = 'يرجى اختيار ملف CSV صالح للرفع.';
        }
    }
}

$stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">
                    📤 استيراد كشوف الشمامسة بالجملة من Excel
                </h1>
                <p style="color:var(--text-muted); font-size:0.95rem;">
                    رفع كشف كامل لشمامسة فصل أو مرحلة دراسية وتوليد حساباتهم وكروت الـ QR تلقائياً
                </p>
            </div>
            <a href="<?= BASE_URL ?>admin/import_students.php?template=1" class="btn btn-secondary" style="font-weight:800;">
                📄 تحميل نموذج Excel / CSV التجريبي
            </a>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (! empty($errors)) { ?>
            <div class="badge badge-danger" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm); text-align:right;">
                <strong>⚠️ تنبيهات الاستيراد:</strong>
                <ul style="margin:0.5rem 1.5rem 0 0;">
                    <?php foreach ($errors as $err) { ?>
                        <li><?= sanitize($err) ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>

        <div class="glass-card" style="max-width:750px; margin:0 auto 2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1.25rem; font-weight:800;">رفع ملف الكشف وتعيين الفصل</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-group" style="background:var(--gold-glow); padding:1rem; border-radius:var(--radius-sm); border:1px solid rgba(217,119,6,0.3); margin-bottom:1.5rem;">
                    <label class="form-label" for="csv_file" style="font-weight:800; color:var(--gold);">اختر ملف Excel / CSV *</label>
                    <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                    <small style="color:var(--text-secondary); display:block; margin-top:0.35rem;">يجب أن يكون الملف بصيغة CSV ومطابقاً للنموذج التجريبي.</small>
                </div>

                <div style="background:var(--royal-blue-glow); padding:1.25rem; border-radius:var(--radius-sm); margin-bottom:1.5rem;">
                    <h4 style="color:var(--royal-blue); margin-bottom:0.75rem;">تسكين الشمامسة المستوردين في الفصل (اختياري)</h4>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="stage_id">المرحلة</label>
                            <select id="stage_id" name="stage_id" class="form-control">
                                <option value="">اختر المرحلة...</option>
                                <?php foreach ($stages as $stg) { ?>
                                    <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="grade_id">الصف</label>
                            <select id="grade_id" name="grade_id" class="form-control">
                                <option value="">اختر الصف...</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="class_id">الفصل</label>
                            <select id="class_id" name="class_id" class="form-control">
                                <option value="">اختر الفصل...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold" style="width:100%; font-weight:800; padding:0.9rem; font-size:1.1rem;">
                    🚀 بدء استيراد وتوليد حسابات الشمامسة
                </button>
            </form>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>assets/js/dynamic-dropdowns.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    initDynamicDropdowns('stage_id', 'grade_id', 'class_id');
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

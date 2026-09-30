<?php
$pageTitle = 'إدارة المناهج والكتب الدراسية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (! verify_csrf_token($csrfToken)) {
        $_SESSION['flash_error'] = 'رمز الأمان غير صالح، يرجى المحاولة مرة أخرى.';
        header('Location: '.BASE_URL.'admin/courses.php');
        exit;
    }

    $action = $_POST['action'] ?? 'upload';

    if ($action === 'delete') {
        $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        if ($courseId) {
            $stmtFind = $db->prepare('SELECT pdf_file FROM courses WHERE id = ?');
            $stmtFind->execute([$courseId]);
            $existingPdf = $stmtFind->fetchColumn();

            if ($existingPdf && file_exists(UPLOAD_PATH.'pdf/'.$existingPdf)) {
                @unlink(UPLOAD_PATH.'pdf/'.$existingPdf);
            }

            $stmtDel = $db->prepare('DELETE FROM courses WHERE id = ?');
            $stmtDel->execute([$courseId]);

            $_SESSION['flash_success'] = 'تم حذف كتاب المنهج بنجاح!';
        }
        header('Location: '.BASE_URL.'admin/courses.php');
        exit;
    }

    if ($action === 'upload') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
        $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT) ?: null;
        $externalLink = trim($_POST['external_link'] ?? '');

        if (empty($title)) {
            $_SESSION['flash_error'] = 'يرجى كتابة عنوان المنهج / الكتاب.';
            header('Location: '.BASE_URL.'admin/courses.php');
            exit;
        }

        if (! $stageId) {
            $_SESSION['flash_error'] = 'يرجى اختيار المرحلة الدراسية المستهدفة لهذا الكتاب.';
            header('Location: '.BASE_URL.'admin/courses.php');
            exit;
        }

        $pdfFilename = null;
        $hasUpload = isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK;

        if (! empty($externalLink)) {
            if (! filter_var($externalLink, FILTER_VALIDATE_URL)) {
                $_SESSION['flash_error'] = 'يرجى إدخال رابط صالح (مثل رابط Google Drive).';
                header('Location: '.BASE_URL.'admin/courses.php');
                exit;
            }
        } elseif (! $hasUpload) {
            $_SESSION['flash_error'] = 'يرجى إما إدخال رابط الكتاب على Google Drive أو رفع ملف PDF.';
            header('Location: '.BASE_URL.'admin/courses.php');
            exit;
        }

        // Process PDF file if uploaded
        if ($hasUpload) {
            $tmpFile = $_FILES['pdf_file']['tmp_name'];
            $fileSize = $_FILES['pdf_file']['size'];
            $origName = $_FILES['pdf_file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $finfoMime = function_exists('mime_content_type') ? mime_content_type($tmpFile) : 'application/pdf';

            // Verify file size limit (50MB)
            if ($fileSize > 50 * 1024 * 1024) {
                $_SESSION['flash_error'] = 'حجم ملف الكتاب يتجاوز الحد الأقصى المسموح به (50 ميجابايت).';
                header('Location: '.BASE_URL.'admin/courses.php');
                exit;
            }

            // Verify extension and MIME
            if ($ext !== 'pdf' || $finfoMime !== 'application/pdf') {
                $_SESSION['flash_error'] = 'عذراً، يُسمح برفع ملفات PDF حقيقية فقط للمناهج الدراسية.';
                header('Location: '.BASE_URL.'admin/courses.php');
                exit;
            }

            // Verify PDF magic bytes (%PDF-)
            $handle = @fopen($tmpFile, 'rb');
            $headerBytes = $handle ? fread($handle, 5) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($headerBytes !== '%PDF-') {
                $_SESSION['flash_error'] = 'الملف المرفوع ليس ملف PDF صالح.';
                header('Location: '.BASE_URL.'admin/courses.php');
                exit;
            }

            $pdfDir = UPLOAD_PATH.'pdf/';
            if (! is_dir($pdfDir)) {
                mkdir($pdfDir, 0777, true);
            }

            $pdfFilename = 'curriculum_pdf_'.time().'_'.bin2hex(random_bytes(6)).'.pdf';
            $destPath = $pdfDir.$pdfFilename;

            if (! move_uploaded_file($tmpFile, $destPath)) {
                $_SESSION['flash_error'] = 'حدث خطأ أثناء حفظ ملف الـ PDF على السيرفر.';
                header('Location: '.BASE_URL.'admin/courses.php');
                exit;
            }
        }

        $stmt = $db->prepare('
            INSERT INTO courses (title, description, stage_id, grade_id, pdf_file, external_link, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            $title,
            $description ?: null,
            $stageId,
            $gradeId,
            $pdfFilename,
            $externalLink ?: null,
            $_SESSION['user']['id'],
        ]);

        $_SESSION['flash_success'] = 'تم حفظ كتاب المنهج الدراسي بنجاح!';
        header('Location: '.BASE_URL.'admin/courses.php');
        exit;
    }
}

// Fetch stages for the dropdown
$stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();

// Fetch all uploaded courses
$courses = $db->query('
    SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
    FROM courses c
    LEFT JOIN stages s ON c.stage_id = s.id
    LEFT JOIN grades g ON c.grade_id = g.id
    ORDER BY s.id ASC, g.id ASC, c.id DESC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;">إدارة المناهج والكتب الدراسية 📚</h1>
        <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:1.5rem;">
            رفع وتحديد كتب المناهج الدراسية بصيغة PDF لكل مرحلة وصف دراسي، لتظهر تلقائياً للمخدومين حسب مرحلتهم.
        </p>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.9rem; margin-bottom:1.5rem; text-align:center; font-size:1rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.9rem; margin-bottom:1.5rem; text-align:center; font-size:1rem;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Upload Curriculum PDF Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1.25rem; font-weight:800; display:flex; align-items:center; gap:0.5rem;">
                <span>📕</span>
                <span>إضافة وتحديد كتاب المنهج الدراسي</span>
            </h3>

            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">

                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">اسم المنهج / الكتاب الدراسي *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: كتاب ألحان وطقوس المرحلة الابتدائية" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">المرحلة الدراسية المستهدفة *</label>
                        <select name="stage_id" id="course_stage_id" class="form-control" required>
                            <option value="">اختر المرحلة...</option>
                            <?php foreach ($stages as $stg) { ?>
                                <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">الصف الدراسي (اختياري)</label>
                        <select name="grade_id" id="course_grade_id" class="form-control">
                            <option value="">جميع صفوف المرحلة</option>
                        </select>
                    </div>
                </div>

                <div style="background:rgba(217, 119, 6, 0.08); border:1px solid rgba(217, 119, 6, 0.25); border-radius:10px; padding:1.2rem; margin-bottom:1.25rem;">
                    <h4 style="color:var(--gold); font-weight:800; margin-bottom:0.75rem; font-size:1rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🔗</span>
                        <span>طريقة توفير الكتاب للمخدومين (رابط Google Drive أو رفع ملف PDF)</span>
                    </h4>

                    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:1rem; align-items:start;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-weight:700; color:var(--text-main);">
                                🌐 رابط الكتاب على Google Drive (موصى به للكتب والمذكرات الكبيرة)
                            </label>
                            <input type="url" name="external_link" class="form-control" placeholder="https://drive.google.com/file/d/.../view?usp=sharing" style="direction:ltr; text-align:left;">
                            <small style="color:var(--text-muted); font-size:0.8rem; display:block; margin-top:0.35rem;">
                                الصق رابط المشاركة من Google Drive وسينتقل المخدوم والخادم للكتاب مباشرة عند الضغط عليه.
                            </small>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-weight:700; color:var(--text-main);">
                                📄 أو رفع ملف PDF مباشرة من جهازك (اختياري)
                            </label>
                            <input type="file" name="pdf_file" id="pdf_file_input" class="form-control" accept=".pdf,application/pdf">
                            <small id="file_size_hint" style="color:var(--text-muted); font-size:0.8rem; display:block; margin-top:0.35rem;">
                                في حال رفع ملف، يُسمح بملفات PDF حتى 100 ميجابايت.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" style="font-weight:700;">وصف أو ملاحظات مختصرة للمخدومين</label>
                    <input type="text" name="description" class="form-control" placeholder="مثال: يحتوي على طقس القداس وألحان التسبحة المقررة">
                </div>

                <div style="text-align:left;">
                    <button type="submit" class="btn btn-primary" style="font-weight:800; padding:0.75rem 2.5rem;">
                        💾 حفظ واعتماد كتاب المنهج
                    </button>
                </div>
            </form>
        </div>

        <!-- Current Uploaded Books Table -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">
                📚 قائمة المناهج والكتب المرفوعة (<?= count($courses) ?> كتب)
            </h3>

            <?php if (empty($courses)) { ?>
                <div style="text-align:center; padding:2.5rem; color:var(--text-muted);">
                    <div style="font-size:3rem; margin-bottom:0.5rem;">📖</div>
                    <p>لم يتم رفع أي كتب مناهج دراسية حتى الآن.</p>
                </div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>اسم الكتاب / المنهج</th>
                                <th>المرحلة والصف</th>
                                <th>مصدر الكتاب / الرابط</th>
                                <th>تاريخ الإضافة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $crs) { ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--royal-blue); font-size:1.05rem;">
                                            <?= sanitize($crs['title']) ?>
                                        </strong>
                                        <?php if (! empty($crs['description'])) { ?>
                                            <div style="font-size:0.82rem; color:var(--text-muted); margin-top:0.25rem;">
                                                <?= sanitize($crs['description']) ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info" style="font-weight:700;">
                                            <?= sanitize($crs['stage_name'] ?? 'عام') ?>
                                        </span>
                                        <span style="font-size:0.85rem; color:var(--text-secondary); margin-right:0.35rem;">
                                            <?= sanitize($crs['grade_name'] ?? 'جميع الصفوف') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (! empty($crs['external_link'])) { ?>
                                            <a href="<?= sanitize($crs['external_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-gold btn-sm" style="font-weight:700; display:inline-flex; align-items:center; gap:0.35rem;">
                                                <span>🌐 Google Drive</span>
                                                <span style="font-size:0.85rem;">↗</span>
                                            </a>
                                        <?php } elseif (! empty($crs['pdf_file'])) { ?>
                                            <a href="<?= BASE_URL ?>uploads/pdf/<?= sanitize($crs['pdf_file']) ?>" target="_blank" class="btn btn-primary btn-sm" style="font-weight:700;">
                                                📄 فتح ملف الـ PDF
                                            </a>
                                        <?php } else { ?>
                                            <span style="color:var(--text-muted);">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?= format_arabic_date(substr($crs['created_at'] ?? date('Y-m-d'), 0, 10)) ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا الكتاب؟');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="course_id" value="<?= (int) $crs['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">🗑️ حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<!-- Dynamic Grade dropdown script based on Stage -->
<script>
document.getElementById('course_stage_id')?.addEventListener('change', function() {
    const stageId = this.value;
    const gradeSelect = document.getElementById('course_grade_id');
    gradeSelect.innerHTML = '<option value="">جميع صفوف المرحلة</option>';

    if (!stageId) return;

    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '../';
    fetch(`${baseUrl}api/get_grades.php?stage_id=${stageId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                res.data.forEach(grade => {
                    const opt = document.createElement('option');
                    opt.value = grade.id;
                    opt.textContent = grade.name_ar;
                    gradeSelect.appendChild(opt);
                });
            }
        })
        .catch(err => console.error('Error fetching grades:', err));
});

// Validate PDF file size on client side (max 100MB)
document.getElementById('pdf_file_input')?.addEventListener('change', function() {
    const file = this.files[0];
    const hint = document.getElementById('file_size_hint');
    if (file) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        if (file.size > 100 * 1024 * 1024) {
            alert(`⚠️ حجم الملف المحدد (${sizeMB} ميجابايت) كبير جداً! الحد الأقصى المسموح به هو 100 ميجابايت.`);
            this.value = '';
            if (hint) {
                hint.textContent = '❌ تم إلغاء اختيار الملف لأنه يتجاوز 100 ميجابايت.';
                hint.style.color = 'var(--danger)';
            }
        } else {
            if (hint) {
                hint.textContent = `✅ تم اختيار: ${file.name} (الحجم: ${sizeMB} ميجابايت)`;
                hint.style.color = 'var(--success)';
            }
        }
    }
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

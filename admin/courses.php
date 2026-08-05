<?php
$pageTitle = 'إدارة المناهج والدروس';
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
        $description = sanitize($_POST['description']);
        $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
        $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_VALIDATE_INT);
        $externalLink = sanitize($_POST['external_link']);

        $pdfFile = null;
        $audioFile = null;

        // PDF File upload
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdfFile = 'course_pdf_'.time().'_'.rand(1000, 9999).'.pdf';
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], UPLOAD_PATH.'pdf/'.$pdfFile);
            }
        }

        // Audio File upload
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg'])) {
                $audioFile = 'course_audio_'.time().'_'.rand(1000, 9999).'.'.$ext;
                move_uploaded_file($_FILES['audio_file']['tmp_name'], UPLOAD_PATH.'audio/'.$audioFile);
            }
        }

        $stmt = $db->prepare('INSERT INTO courses (title, description, stage_id, grade_id, pdf_file, audio_file, external_link, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $stageId ?: null, $gradeId ?: null, $pdfFile, $audioFile, $externalLink ?: null, $_SESSION['user']['id']]);

        $_SESSION['flash_success'] = 'تم رفع المنهج الدراسي بنجاح!';
        header('Location: '.BASE_URL.'admin/courses.php');
        exit;
    }
}

$courses = $db->query('
    SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
    FROM courses c
    LEFT JOIN stages s ON c.stage_id = s.id
    LEFT JOIN grades g ON c.grade_id = g.id
    ORDER BY c.id DESC
')->fetchAll();

$stages = $db->query('SELECT id, name_ar FROM stages')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">إدارة المناهج والدروس التعليمية 📚</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Course Add Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة منهج / درس جديد</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">عنوان الدرس / المنهج *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: طقس القداس الإلهي" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">المرحلة المستهدفة</label>
                        <select name="stage_id" id="course_stage_id" class="form-control">
                            <option value="">كل المراحل</option>
                            <?php foreach ($stages as $stg) { ?>
                                <option value="<?= $stg['id'] ?>"><?= sanitize($stg['name_ar']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الصف المستهدف</label>
                        <select name="grade_id" id="course_grade_id" class="form-control">
                            <option value="">كل الصفوف</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف والملخص</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="وصف محتوى الدرس..."></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">ملف PDF (المذكرة)</label>
                        <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ملف صوتي (تسجيل شائعة/درس)</label>
                        <input type="file" name="audio_file" class="form-control" accept="audio/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رابط فيديو خارجي (YouTube)</label>
                        <input type="url" name="external_link" class="form-control" placeholder="https://youtube.com/...">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">رفع وحفظ المنهج</button>
            </form>
        </div>

        <!-- Courses List -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">المناهج الدراسية المتاحة</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>عنوان المنهج</th>
                            <th>المرحلة والصف</th>
                            <th>المرفقات</th>
                            <th>تاريخ الرفع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $crs) { ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($crs['title']) ?></strong>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= sanitize($crs['description'] ?? '') ?></div>
                                </td>
                                <td><?= sanitize($crs['stage_name'] ?? 'عام') ?> - <?= sanitize($crs['grade_name'] ?? 'جميع الصفوف') ?></td>
                                <td>
                                    <?php if ($crs['pdf_file']) { ?>
                                        <a href="<?= BASE_URL ?>uploads/pdf/<?= $crs['pdf_file'] ?>" target="_blank" class="badge badge-info">📄 PDF</a>
                                    <?php } ?>
                                    <?php if ($crs['audio_file']) { ?>
                                        <a href="<?= BASE_URL ?>uploads/audio/<?= $crs['audio_file'] ?>" target="_blank" class="badge badge-warning">🎧 صوت</a>
                                    <?php } ?>
                                    <?php if ($crs['external_link']) { ?>
                                        <a href="<?= sanitize($crs['external_link']) ?>" target="_blank" class="badge badge-success">🎥 فيديو</a>
                                    <?php } ?>
                                </td>
                                <td><?= format_arabic_date($crs['created_at']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>assets/js/dynamic-dropdowns.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        initDynamicDropdowns('course_stage_id', 'course_grade_id', 'dummy');
    });
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

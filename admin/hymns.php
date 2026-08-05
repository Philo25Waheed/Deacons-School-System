<?php
$pageTitle = 'مكتبة الألحان القبطية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $notes = sanitize($_POST['notes']);
        $videoLink = sanitize($_POST['video_link']);

        $pdfFile = null;
        $audioFile = null;

        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdfFile = 'hymn_pdf_'.time().'_'.rand(1000, 9999).'.pdf';
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], UPLOAD_PATH.'pdf/'.$pdfFile);
            }
        }

        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg'])) {
                $audioFile = 'hymn_audio_'.time().'_'.rand(1000, 9999).'.'.$ext;
                move_uploaded_file($_FILES['audio_file']['tmp_name'], UPLOAD_PATH.'audio/'.$audioFile);
            }
        }

        $stmt = $db->prepare('INSERT INTO hymns (title, description, notes, video_link, pdf_file, audio_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $notes, $videoLink ?: null, $pdfFile, $audioFile, $_SESSION['user']['id']]);

        $_SESSION['flash_success'] = 'تم إضافة اللحن بنجاح لمكتبة الألحان!';
        header('Location: '.BASE_URL.'admin/hymns.php');
        exit;
    }
}

$hymns = $db->query('SELECT * FROM hymns ORDER BY id DESC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">مكتبة الألحان والطقوس القبطية 🎶</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Add Hymn Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة لحن جديد للمكتبة</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">اسم اللحن *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: لحن إكإسماروؤوت الصغير" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف وقسائم اللحن</label>
                        <input type="text" name="description" class="form-control" placeholder="مثال: يقال في القداس الإلهي والأعياد">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الكلمات والملاحظات الطقسية (هزات اللحن والقصة)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="اكتب كلمات اللحن بالقبطية والمعرب..."></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">الملف الصوتي اللحن (MP3)</label>
                        <input type="file" name="audio_file" class="form-control" accept="audio/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">النوتة الموسيقية / الهزات (PDF)</label>
                        <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                    </div>
                    <div class="form-group">
                        <label class="form-label">فيديوتعليمي (YouTube)</label>
                        <input type="url" name="video_link" class="form-control" placeholder="https://youtube.com/...">
                    </div>
                </div>

                <button type="submit" class="btn btn-gold">حفظ اللحن في المكتبة</button>
            </form>
        </div>

        <!-- Hymns Grid -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
            <?php foreach ($hymns as $h) { ?>
                <div class="glass-card">
                    <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;"><?= sanitize($h['title']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;"><?= sanitize($h['description'] ?? '') ?></p>
                    
                    <?php if ($h['notes']) { ?>
                        <div style="background:var(--bg-primary); padding:0.85rem; border-radius:var(--radius-sm); font-size:0.9rem; margin-bottom:1rem; white-space:pre-line;">
                            <?= sanitize($h['notes']) ?>
                        </div>
                    <?php } ?>

                    <?php if ($h['audio_file']) { ?>
                        <audio controls style="width:100%; margin-bottom:1rem;">
                            <source src="<?= BASE_URL ?>uploads/audio/<?= $h['audio_file'] ?>">
                            متصفحك لا يدعم مشغل الصوت.
                        </audio>
                    <?php } ?>

                    <div style="display:flex; gap:0.5rem;">
                        <?php if ($h['pdf_file']) { ?>
                            <a href="<?= BASE_URL ?>uploads/pdf/<?= $h['pdf_file'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="flex:1;">📄 نوتة PDF</a>
                        <?php } ?>
                        <?php if ($h['video_link']) { ?>
                            <a href="<?= sanitize($h['video_link']) ?>" target="_blank" class="btn btn-gold btn-sm" style="flex:1;">🎥 فيديو الشرح</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

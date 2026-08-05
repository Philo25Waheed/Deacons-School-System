<?php
$pageTitle = 'تسجيل الحضور بالماكينة والماسح الضوئي';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();
$servantId = $_SESSION['user']['id'];

// Handle Manual Attendance Checkboxes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_attendance'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $studentIds = $_POST['students'] ?? [];
        $today = date('Y-m-d');

        $insertStmt = $db->prepare("
            INSERT INTO attendance (student_id, servant_id, attendance_date, status)
            VALUES (?, ?, ?, 'present')
            ON DUPLICATE KEY UPDATE status = 'present'
        ");

        foreach ($studentIds as $stuId) {
            $insertStmt->execute([filter_var($stuId, FILTER_VALIDATE_INT), $servantId, $today]);
        }

        $_SESSION['flash_success'] = 'تم حفظ كشف الحضور اليدوي لـ '.count($studentIds).' شماس بنجاح!';
        header('Location: '.BASE_URL.'servant/attendance.php');
        exit;
    }
}

// Fetch students for manual list
$studentsList = $db->query("
    SELECT u.id, u.full_name, u.qr_code_token, s.name_ar as stage, g.name_ar as grade, c.name_ar as class
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.role = 'student' AND u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نظام وتسجيل الحضور 📷</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- QR Camera Scanner Box -->
            <div class="glass-card" style="text-align:center;">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">ماسح الكاميرا الضوئي (QR Code)</h3>
                
                <div style="width:100%; height:260px; background:#000000; border-radius:12px; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; margin-bottom:1rem;">
                    <video id="qrVideo" style="width:100%; height:100%; object-fit:cover;"></video>
                    <div id="scanStatus" style="position:absolute; bottom:10px; background:rgba(0,0,0,0.7); color:#ffffff; padding:0.4rem 1rem; border-radius:20px; font-size:0.85rem;">
                        اضغط بدء تشغيل الكاميرا
                    </div>
                </div>

                <div style="display:flex; gap:0.5rem; justify-content:center; margin-bottom:1.5rem;">
                    <button id="startScanBtn" class="btn btn-primary">📷 تشغيل الكاميرا المسحية</button>
                    <button id="stopScanBtn" class="btn btn-secondary" style="display:none;">🛑 إيقاف</button>
                </div>

                <div style="border-top:1px solid var(--border-color); padding-top:1rem;">
                    <label class="form-label" style="font-size:0.85rem;">إدخال كود الشماس يدويًا</label>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="manualTokenInput" class="form-control" placeholder="STU-2026-XXXX">
                        <button id="manualSubmitBtn" class="btn btn-gold">تسجيل</button>
                    </div>
                </div>
            </div>

            <!-- Manual Checkbox List -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">تسجيل الحضور اليدوي بالفصل</h3>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="manual_attendance" value="1">
                    
                    <div style="max-height:360px; overflow-y:auto; padding-right:0.5rem; margin-bottom:1rem;">
                        <?php foreach ($studentsList as $stu) { ?>
                            <label style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem; background:var(--bg-primary); border-radius:var(--radius-sm); margin-bottom:0.5rem; cursor:pointer;">
                                <div>
                                    <strong><?= sanitize($stu['full_name']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($stu['grade'] ?? '') ?> - <?= sanitize($stu['class'] ?? '') ?></div>
                                </div>
                                <input type="checkbox" name="students[]" value="<?= $stu['id'] ?>" style="width:20px; height:20px;">
                            </label>
                        <?php } ?>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">حفظ الحضور اليدوي</button>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>assets/js/qr-scanner.js"></script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

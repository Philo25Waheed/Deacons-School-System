<?php
$pageTitle = 'مُوَلِّد شهادات التقدير والتفوق';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin', 'servant');

$db = getDB();
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$stageId = filter_input(INPUT_GET, 'stage_id', FILTER_VALIDATE_INT);
$title = sanitize($_GET['title'] ?? 'شهادة تقدير وتفوق');
$reason = sanitize($_GET['reason'] ?? 'لتفوقه المتميز في حفظ ألحان الكنيسة القبطية والالتزام الروحي بخدمة القداسات الإلهية');
$signerPriest = sanitize($_GET['priest'] ?? 'القمص / راعي الكنيسة');
$signerLeader = sanitize($_GET['leader'] ?? 'أمين عام مدرسة الشهيد إسطفانوس');

$students = [];
if ($studentId) {
    $stmt = $db->prepare("
        SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$studentId]);
    $students = $stmt->fetchAll();
} elseif ($stageId) {
    $stmt = $db->prepare("
        SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.stage_id = ? AND u.role = 'student' AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$stageId]);
    $students = $stmt->fetchAll();
} else {
    $students = $db->query("
        SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.role = 'student' AND u.status = 'active'
        ORDER BY u.full_name ASC
        LIMIT 6
    ")->fetchAll();
}

$allStudents = $db->query("SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$stages = $db->query('SELECT id, name_ar FROM stages ORDER BY id ASC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
/* Certificate Sheet Styling */
.cert-sheet {
    background: #ffffff;
    color: #1a202c;
    border: 12px double #d4af37;
    border-radius: 12px;
    padding: 3rem 2.5rem;
    margin: 2rem auto;
    max-width: 850px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    page-break-after: always;
    text-align: center;
    background-image: radial-gradient(circle at center, rgba(212, 175, 55, 0.05) 0%, transparent 70%);
}

.cert-corner {
    position: absolute;
    font-size: 2rem;
    color: #d4af37;
}
.cert-tl { top: 15px; left: 15px; }
.cert-tr { top: 15px; right: 15px; }
.cert-bl { bottom: 15px; left: 15px; }
.cert-br { bottom: 15px; right: 15px; }

.cert-church-header {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e3a8a;
    margin-bottom: 0.5rem;
}

.cert-main-title {
    font-size: 2.4rem;
    font-weight: 900;
    color: #b45309;
    margin: 1.25rem 0 0.75rem;
    text-shadow: 0 1px 3px rgba(180, 83, 9, 0.2);
}

.cert-recipient-name {
    font-size: 2.2rem;
    font-weight: 900;
    color: #1e3a8a;
    border-bottom: 2px dashed #d4af37;
    display: inline-block;
    padding: 0 2rem 0.35rem;
    margin: 1rem 0;
}

.cert-body-text {
    font-size: 1.2rem;
    line-height: 1.9;
    color: #334155;
    max-width: 680px;
    margin: 1rem auto 2.5rem;
}

.cert-sign-grid {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.cert-sign-box {
    text-align: center;
    font-size: 1rem;
    color: #1e3a8a;
    font-weight: 700;
}

.cert-sign-line {
    margin-top: 2.5rem;
    border-top: 1px solid #94a3b8;
    width: 160px;
}

@media print {
    body * {
        visibility: hidden;
    }
    .cert-sheet, .cert-sheet * {
        visibility: visible;
    }
    .cert-sheet {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 2.5rem;
        box-shadow: none;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">مُوَلِّد شهادات التقدير والتفوق 📜🏆</h1>
                <p style="color:var(--text-muted); font-size:0.95rem;">إنشاء وتخصيص شهادات تفوق ملونة للشطار والمتفوقين في الألحان والقداسات</p>
            </div>
            <button onclick="window.print()" class="btn btn-gold" style="font-weight:800; padding:0.75rem 1.5rem;">
                🖨️ طباعة الشهادات الحالية (PDF)
            </button>
        </div>

        <!-- Customizer Form (Hidden when printing) -->
        <div class="glass-card no-print" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">⚙️ تخصيص بيانات الشهادة</h3>
            <form action="" method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                <div class="form-group">
                    <label class="form-label">اختيار شماس محدد</label>
                    <select name="student_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- كل الشمامسة المختارين --</option>
                        <?php foreach ($allStudents as $as) { ?>
                            <option value="<?= $as['id'] ?>" <?= $studentId == $as['id'] ? 'selected' : '' ?>><?= sanitize($as['full_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">أو تصفية حسب المرحلة</label>
                    <select name="stage_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- كل المراحل --</option>
                        <?php foreach ($stages as $stg) { ?>
                            <option value="<?= $stg['id'] ?>" <?= $stageId == $stg['id'] ? 'selected' : '' ?>><?= sanitize($stg['name_ar']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">عنوان الشهادة</label>
                    <input type="text" name="title" class="form-control" value="<?= sanitize($title) ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">نص وسبب التكريم والتقدير</label>
                    <input type="text" name="reason" class="form-control" value="<?= sanitize($reason) ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <button type="submit" class="btn btn-primary" style="font-weight:700;">تحديث عرض الشهادات</button>
                </div>
            </form>
        </div>

        <!-- Rendered Printable Certificates -->
        <div id="certificatesContainer">
            <?php foreach ($students as $stu) { ?>
                <div class="cert-sheet">
                    <div class="cert-corner cert-tl">☦️</div>
                    <div class="cert-corner cert-tr">☦️</div>
                    <div class="cert-corner cert-bl">☦️</div>
                    <div class="cert-corner cert-br">☦️</div>

                    <div class="cert-church-header">
                        <?= CHURCH_NAME ?><br>
                        مدرسة الشهيد إسطفانوس للألحان والتسبحة
                    </div>

                    <div class="cert-main-title"><?= sanitize($title) ?></div>

                    <div style="font-size:1.15rem; color:#475569; margin-top:1rem;">
                        تتشرف إدارة مدرسة الشهيد إسطفانوس بمنح هذه الشهادة للشماس المبارك:
                    </div>

                    <div class="cert-recipient-name">
                        <?= sanitize($stu['full_name']) ?>
                    </div>

                    <div style="font-size:0.95rem; font-weight:700; color:#b45309; margin-bottom:1rem;">
                        <?= sanitize($stu['deacon_rank'] ?? 'شماس') ?> | <?= sanitize($stu['stage_name'] ?? 'مدرسة الشهيد إسطفانوس') ?> (<?= sanitize($stu['class_name'] ?? '') ?>)
                    </div>

                    <div class="cert-body-text">
                        <?= sanitize($reason) ?><br>
                        <span style="font-size:1rem; font-style:italic; color:#64748b;">"كُونُوا أَمَنَاءَ فِي كُلِّ شَيْءٍ" - متمنين له دوام التقدم والنعمة في خدمة هيكل الرب.</span>
                    </div>

                    <div class="cert-sign-grid">
                        <div class="cert-sign-box">
                            <div><?= sanitize($signerPriest) ?></div>
                            <div class="cert-sign-line"></div>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:center;">
                            <img src="<?= BASE_URL ?>assets/images/logo.png" style="width:65px; height:65px; object-fit:contain; filter:drop-shadow(0 2px 5px rgba(212,175,55,0.4));" alt="ختم المدرسة">
                        </div>

                        <div class="cert-sign-box">
                            <div><?= sanitize($signerLeader) ?></div>
                            <div class="cert-sign-line"></div>
                        </div>
                    </div>

                    <div style="margin-top:1.5rem; font-size:0.8rem; color:#94a3b8;">
                        تاريخ التكريم: <?= format_arabic_date(date('Y-m-d')) ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

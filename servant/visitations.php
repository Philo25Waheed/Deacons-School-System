<?php
$pageTitle = 'متابعة الافتقاد الرعوي ورادار الغياب';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();
$servantId = $_SESSION['user']['id'];

// Create table if not exists
$db->exec('
    CREATE TABLE IF NOT EXISTS `pastoral_visitations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `servant_id` INT NOT NULL,
        `type` VARCHAR(50) DEFAULT "phone_call",
        `notes` TEXT DEFAULT NULL,
        `visit_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
');

$accessibleClassIds = get_user_accessible_class_ids();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $type = sanitize($_POST['type'] ?? 'phone_call');
        $notes = sanitize($_POST['notes'] ?? '');
        $visitDate = sanitize($_POST['visit_date'] ?? date('Y-m-d'));

        if ($studentId && can_servant_access_student($servantId, $studentId, $_SESSION['user']['role'])) {
            $stmt = $db->prepare('INSERT INTO pastoral_visitations (student_id, servant_id, type, notes, visit_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$studentId, $servantId, $type, $notes, $visitDate]);

            $_SESSION['flash_success'] = 'تم تسجيل الافتقاد الرعوي للشماس بنجاح!';
        } else {
            $_SESSION['flash_error'] = 'عذراً، لا تملك صلاحية تسجيل افتقاد لشماس خارج فصولك المسندة.';
        }
        header('Location: '.BASE_URL.'servant/visitations.php');
        exit;
    }
}

// Fetch Active Students with Absence Analytics for the Radar (scoped if servant)
$radarQuery = "
    SELECT u.id, u.full_name, u.phone, u.father_phone, u.mother_phone,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'absent') as total_absences,
           (SELECT MAX(a.attendance_date) FROM attendance a WHERE a.student_id = u.id AND a.status = 'present') as last_present_date,
           (SELECT MAX(pv.visit_date) FROM pastoral_visitations pv WHERE pv.student_id = u.id) as last_visit_date
    FROM users u
    WHERE u.role = 'student' AND u.status = 'active'
";
$radarParams = [];

if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $radarQuery .= ' AND 1=0';
    } else {
        $inRadar = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $radarQuery .= " AND u.class_id IN ({$inRadar})";
        $radarParams = $accessibleClassIds;
    }
}

$radarQuery .= ' ORDER BY total_absences DESC, u.full_name ASC';
$stmtRadar = $db->prepare($radarQuery);
$stmtRadar->execute($radarParams);
$radarStudents = $stmtRadar->fetchAll();

$visQuery = '
    SELECT pv.*, u.full_name as student_name, srv.full_name as servant_name
    FROM pastoral_visitations pv
    JOIN users u ON pv.student_id = u.id
    JOIN users srv ON pv.servant_id = srv.id
';
$visParams = [];

if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $visQuery .= ' WHERE pv.servant_id = ?';
        $visParams = [$servantId];
    } else {
        $inVis = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $visQuery .= " WHERE u.class_id IN ({$inVis}) OR pv.servant_id = ?";
        $visParams = array_merge($accessibleClassIds, [$servantId]);
    }
}

$visQuery .= ' ORDER BY pv.id DESC LIMIT 30';
$stmtVis = $db->prepare($visQuery);
$stmtVis->execute($visParams);
$visitations = $stmtVis->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.35rem;">
            🚨 رادار الافتقاد والغياب الذكي ومتابعة الرعاية
        </h1>
        <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:1.5rem;">
            نظام ذكي يرصد الشمامسة الأكثر غياباً واحتياجاً لافتقاد الخادم مع توليد رسائل واتساب تشجيعية فورية للأب والأم.
        </p>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- SMART RADAR SECTION -->
        <div class="glass-card" style="margin-bottom:2rem; border-right:5px solid var(--gold);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.25rem;">
                <div>
                    <h3 style="color:var(--royal-blue); font-weight:800;">🎯 قائمة أولويات الافتقاد (Radar Table)</h3>
                    <span style="font-size:0.85rem; color:var(--text-muted);">مرتبة آلياً حسب معدل الغياب والحاجة للرعاية</span>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <span class="badge badge-danger">🔴 أولوية حرجة (3+ غياب)</span>
                    <span class="badge badge-warning">🟡 أولوية متوسطة (2 غياب)</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الشماس / الطالب</th>
                            <th>عدد مرات الغياب</th>
                            <th>آخر حضور بالقداس</th>
                            <th>آخر افتقاد</th>
                            <th>مستوى الأولوية</th>
                            <th>الإجراء السريع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($radarStudents as $stu) {
                            $abs = (int) $stu['total_absences'];
                            $priority = ($abs >= 3) ? 'danger' : (($abs >= 1) ? 'warning' : 'success');
                            $priorityText = ($abs >= 3) ? '🔴 أولوية حرجة' : (($abs >= 1) ? '🟡 أولوية متوسطة' : '🟢 متابعة دورية');
                            $contactPhone = $stu['father_phone'] ?: ($stu['mother_phone'] ?: $stu['phone']);

                            $waMsg = "سلام ونعمة يا فندم من إدارة مدرسة الشهيد إسطفانوس ☦️\n\nبنطمن على الشماس الحبيب (".sanitize($stu['full_name']).")، بنفتقده جداً في خدمة القداسات والتسبحة وحصص الألحان.\n\nلو في أي ظروف طارئة الكنيسة والخادم في خدمتكم دائماً، ومستنيين حضوره المبارك في القداس القادم بإذن المسيح. دمتم في رعاية الله! 🕊️";
                            $waLink = 'https://wa.me/2'.preg_replace('/[^0-9]/', '', $contactPhone).'?text='.urlencode($waMsg);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($stu['full_name']) ?></strong>
                                    <div style="font-size:0.8rem; color:var(--text-muted);">هاتف: <?= sanitize($stu['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $priority ?>" style="font-weight:800; font-size:0.95rem;">
                                        <?= $abs ?> غياب
                                    </span>
                                </td>
                                <td><?= $stu['last_present_date'] ? format_arabic_date($stu['last_present_date']) : '<span style="color:var(--text-muted);">لم يسجل بعد</span>' ?></td>
                                <td><?= $stu['last_visit_date'] ? format_arabic_date($stu['last_visit_date']) : '<span style="color:#ef4444;">لم يُفتقد بعد</span>' ?></td>
                                <td>
                                    <span class="badge badge-<?= $priority ?>"><?= $priorityText ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.4rem;">
                                        <a href="<?= $waLink ?>" target="_blank" class="btn btn-success btn-sm" style="font-weight:700;" title="إرسال رسالة واتساب تشجيعية للأب/الأم">
                                            💬 واتساب
                                        </a>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="quickLogModal(<?= $stu['id'] ?>, '<?= addslashes(sanitize($stu['full_name'])) ?>')">
                                            📝 تسجيل افتقاد
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Visitation History Table -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">سجل الافتقادات الرعوية السابقة</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الشماس</th>
                            <th>الخادم القائم بالافتقاد</th>
                            <th>نوع الافتقاد</th>
                            <th>تاريخ الافتقاد</th>
                            <th>ملاحظات الافتقاد وتوصيات الخادم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visitations)) { ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">لا توجد افتقادات مسجلة بعد.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($visitations as $vis) { ?>
                                <tr>
                                    <td><strong><?= sanitize($vis['student_name']) ?></strong></td>
                                    <td><?= sanitize($vis['servant_name']) ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?= ($vis['type'] === 'home_visit') ? '🏠 زيارة منزلية' : (($vis['type'] === 'phone_call') ? '📞 اتصال هاتفي' : '⛪ لقاء كنسي') ?>
                                        </span>
                                    </td>
                                    <td><?= format_arabic_date($vis['visit_date']) ?></td>
                                    <td><?= sanitize($vis['notes']) ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Logging Visitation -->
<div id="visModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="width:100%; max-width:480px; margin:1rem; background:var(--bg-surface);">
        <h3 style="color:var(--royal-blue); margin-bottom:0.75rem; font-weight:800;">📝 تسجيل افتقاد رعوي</h3>
        <p id="visModalDesc" style="font-size:0.88rem; color:var(--text-muted); margin-bottom:1rem;"></p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" id="modalStudentId" name="student_id" value="">

            <div class="form-group">
                <label class="form-label" for="type">نوع الافتقاد *</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="phone_call">📞 مكالمة هاتفية مع الشماس / ولي الأمر</option>
                    <option value="home_visit">🏠 زيارة منزلية مع أبونا / الخادم</option>
                    <option value="church_meeting">⛪ مقابلة وافتقاد بالكنيسة</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="visit_date">تاريخ الافتقاد *</label>
                <input type="date" id="visit_date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">ملاحظات الافتقاد وتوصيات المتابعة *</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="اكتب نتائج المكالمة أو الزيارة وتشجيع الشماس..." required></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn btn-secondary" onclick="closeVisModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold" style="font-weight:800;">حفظ الافتقاد في السجل</button>
            </div>
        </form>
    </div>
</div>

<script>
function quickLogModal(studentId, studentName) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('visModalDesc').innerText = `تسجيل افتقاد للشماس: ${studentName}`;
    document.getElementById('visModal').style.display = 'flex';
}

function closeVisModal() {
    document.getElementById('visModal').style.display = 'none';
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

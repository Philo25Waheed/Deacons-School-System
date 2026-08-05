<?php
$pageTitle = 'نظام النقاط والتشجيع';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();
$selectedStudentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

// Fetch all active students for dropdown
$students = $db->query("SELECT id, full_name, qr_code_token FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name ASC")->fetchAll();

// Leaderboard Top 10
$leaderboard = $db->query("
    SELECT u.id, u.full_name, u.profile_pic, s.name_ar as stage, g.name_ar as grade,
           COALESCE(SUM(CASE WHEN p.type = 'positive' THEN p.points ELSE -p.points END), 0) as total_points
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN points p ON u.id = p.student_id
    WHERE u.role = 'student' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY total_points DESC LIMIT 10
")->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نظام تشجيع النقاط ⭐</h1>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- Points Form -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة / خصم نقاط لشماس</h3>
                
                <form id="pointsForm" onsubmit="handlePointsSubmit(event)">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label">اختر الشماس *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">اختر الشماس...</option>
                            <?php foreach ($students as $stu) { ?>
                                <option value="<?= $stu['id'] ?>" <?= $selectedStudentId == $stu['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['qr_code_token']) ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">نوع النقط *</label>
                            <select name="type" class="form-control" required>
                                <option value="positive">إضافة (+) نقاط تشجيعية</option>
                                <option value="negative">خصم (-) نقاط مخصومة</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">عدد النقاط *</label>
                            <input type="number" name="points" class="form-control" value="5" min="1" max="100" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">السبب / الداعي *</label>
                        <input type="text" name="reason" class="form-control" placeholder="مثال: حفظ لحن، تفوق في اختبار، حضور مبكر..." required>
                    </div>

                    <button type="submit" class="btn btn-gold" style="width:100%;">حفظ وإرسال التحديث</button>
                </form>
            </div>

            <!-- Leaderboard -->
            <div class="glass-card">
                <h3 style="color:var(--gold); font-weight:800; margin-bottom:1rem;">🏆 لوحة المتفوقين (Top 10 Leaderboard)</h3>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <?php foreach ($leaderboard as $idx => $lead) { ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem; background:var(--bg-primary); border-radius:var(--radius-sm);">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div style="font-weight:800; font-size:1.2rem; color:var(--royal-blue); width:24px;">#<?= $idx + 1 ?></div>
                                <div>
                                    <strong><?= sanitize($lead['full_name']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($lead['stage'] ?? '') ?> - <?= sanitize($lead['grade'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="badge badge-gold" style="font-size:0.95rem;">⭐ <?= number_format($lead['total_points']) ?> نقطة</div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function handlePointsSubmit(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('pointsForm'));
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '../';

    fetch(baseUrl + 'api/manage_points.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message + ' - رصيد النقاط الجديد: ' + data.new_total);
            window.location.reload();
        } else {
            alert(data.message || 'حدث خطأ في حفظ النقاط');
        }
    })
    .catch(err => alert('خطأ بالاتصال بالسيرفر'));
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

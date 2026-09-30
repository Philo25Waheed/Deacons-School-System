<?php
$pageTitle = 'نظام الطايو والتشجيع';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('servant', 'admin');

$db = getDB();
$selectedStudentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

$accessibleClassIds = get_user_accessible_class_ids();

// Fetch active students for dropdown (scoped if servant)
$studentsQuery = "
    SELECT u.id, u.full_name, u.qr_code_token, c.name_ar as class_name, g.name_ar as grade_name
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.id
    LEFT JOIN grades g ON u.grade_id = g.id
    WHERE u.role = 'student' AND u.status = 'active'
";
$studentsParams = [];

if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $studentsQuery .= ' AND 1=0';
    } else {
        $inStudents = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $studentsQuery .= " AND u.class_id IN ({$inStudents})";
        $studentsParams = $accessibleClassIds;
    }
}
$studentsQuery .= ' ORDER BY u.full_name ASC';
$stmtStudents = $db->prepare($studentsQuery);
$stmtStudents->execute($studentsParams);
$students = $stmtStudents->fetchAll();

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
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">نظام تشجيع الطايو ⭐</h1>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- Points Form -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة / خصم طايو لشماس</h3>
                
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
                            <label class="form-label">نوع العملية *</label>
                            <select name="type" class="form-control" required>
                                <option value="positive">إضافة (+) طايو تشجيعي</option>
                                <option value="negative">خصم (-) طايو مخصوم</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">عدد الطايو *</label>
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
                <h3 style="color:var(--gold); font-weight:800; margin-bottom:1rem;">🏆 لوحة المتفوقين في الطايو (Top 10 Leaderboard)</h3>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <?php foreach ($leaderboard as $idx => $lead) { ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; padding:0.75rem; background:var(--bg-primary); border-radius:var(--radius-sm);">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div style="font-weight:800; font-size:1.2rem; color:var(--royal-blue); width:24px;">#<?= $idx + 1 ?></div>
                                <div>
                                    <strong><?= sanitize($lead['full_name']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($lead['stage'] ?? '') ?> - <?= sanitize($lead['grade'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="badge badge-gold" style="font-size:0.95rem;">⭐ <?= number_format($lead['total_points']) ?> طايو</div>
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
            alert(data.message + ' - رصيد الطايو الجديد: ' + data.new_total);
            window.location.reload();
        } else {
            alert(data.message || 'حدث خطأ في حفظ الطايو');
        }
    })
    .catch(err => alert('خطأ بالاتصال بالسيرفر'));
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

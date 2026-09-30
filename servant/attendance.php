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
$userRole = $_SESSION['user']['role'];

$accessibleClassIds = get_user_accessible_class_ids();
$selectedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$today = date('Y-m-d');

// Fetch accessible classes for filter
if ($accessibleClassIds !== null) {
    if (! empty($accessibleClassIds)) {
        $inClassPlaceholders = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $stmtClasses = $db->prepare("
            SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name 
            FROM classes c 
            JOIN grades g ON c.grade_id = g.id 
            WHERE c.id IN ({$inClassPlaceholders}) 
            ORDER BY g.id, c.id
        ");
        $stmtClasses->execute($accessibleClassIds);
        $filterClasses = $stmtClasses->fetchAll();
    } else {
        $filterClasses = [];
    }
} else {
    $stmtClasses = $db->query('
        SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name 
        FROM classes c 
        JOIN grades g ON c.grade_id = g.id 
        ORDER BY g.id, c.id
    ');
    $filterClasses = $stmtClasses->fetchAll();
}

// Handle Manual Attendance Checkboxes POST (حصة & بامفلت & قداس + طايو)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['manual_attendance'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $classStudentIds = $_POST['class_student_ids'] ?? [];
        $lessonIds = $_POST['att_lesson'] ?? [];
        $pamphletIds = $_POST['att_pamphlet'] ?? [];
        $liturgyIds = $_POST['att_liturgy'] ?? [];

        $checkStmt = $db->prepare('SELECT id, points_awarded FROM attendance WHERE student_id = ? AND attendance_date = ?');
        $insertStmt = $db->prepare('
            INSERT INTO attendance (student_id, servant_id, attendance_date, status, attended_lesson, attended_pamphlet, attended_liturgy, points_awarded, scanned_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ');
        $updateStmt = $db->prepare('
            UPDATE attendance 
            SET status = ?, attended_lesson = ?, attended_pamphlet = ?, attended_liturgy = ?, points_awarded = ?, servant_id = ?
            WHERE student_id = ? AND attendance_date = ?
        ');
        $pointInsertStmt = $db->prepare('
            INSERT INTO points (student_id, servant_id, points, type, reason)
            VALUES (?, ?, ?, ?, ?)
        ');

        $recordedCount = 0;
        $totalPointsAwarded = 0;

        foreach ($classStudentIds as $stuId) {
            $valId = filter_var($stuId, FILTER_VALIDATE_INT);
            if ($valId && can_servant_access_student($servantId, $valId, $userRole)) {
                $hasLesson = in_array($valId, $lessonIds);
                $hasPamphlet = in_array($valId, $pamphletIds);
                $hasLiturgy = in_array($valId, $liturgyIds);

                $newPoints = ($hasLesson ? 1 : 0) + ($hasPamphlet ? 1 : 0) + ($hasLiturgy ? 1 : 0);
                $status = ($newPoints > 0) ? 'present' : 'absent';

                $checkStmt->execute([$valId, $today]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $oldPoints = (int) ($existing['points_awarded'] ?? 0);
                    $diff = $newPoints - $oldPoints;

                    if ($diff > 0) {
                        $pointInsertStmt->execute([
                            $valId,
                            $servantId,
                            $diff,
                            'positive',
                            "تحديث كشف الحضور: إضافة +{$diff} طايو إضافي ({$today})",
                        ]);
                    } elseif ($diff < 0) {
                        $deduct = abs($diff);
                        $pointInsertStmt->execute([
                            $valId,
                            $servantId,
                            $deduct,
                            'negative',
                            "تعديل كشف الحضور: خصم {$deduct} طايو لإلغاء التحديد ({$today})",
                        ]);
                    }

                    $updateStmt->execute([
                        $status,
                        $hasLesson ? 1 : 0,
                        $hasPamphlet ? 1 : 0,
                        $hasLiturgy ? 1 : 0,
                        $newPoints,
                        $servantId,
                        $valId,
                        $today,
                    ]);
                } else {
                    // Only insert if student has at least one box checked or we want to save today's record
                    if ($newPoints > 0) {
                        $reasons = [];
                        if ($hasLesson) {
                            $reasons[] = 'حصة (+1)';
                        }
                        if ($hasPamphlet) {
                            $reasons[] = 'بامفلت (+1)';
                        }
                        if ($hasLiturgy) {
                            $reasons[] = 'قداس (+1)';
                        }
                        $reasonStr = 'حضور مدرسة الشمامسة: '.implode('، ', $reasons);

                        $pointInsertStmt->execute([
                            $valId,
                            $servantId,
                            $newPoints,
                            'positive',
                            $reasonStr,
                        ]);

                        $insertStmt->execute([
                            $valId,
                            $servantId,
                            $today,
                            'present',
                            $hasLesson ? 1 : 0,
                            $hasPamphlet ? 1 : 0,
                            $hasLiturgy ? 1 : 0,
                            $newPoints,
                        ]);
                    }
                }

                if ($newPoints > 0) {
                    $recordedCount++;
                    $totalPointsAwarded += $newPoints;
                }
            }
        }

        $_SESSION['flash_success'] = "تم حفظ كشف الحضور لـ {$recordedCount} شماس وتوزيع {$totalPointsAwarded} طايو بنجاح!";
        header('Location: '.BASE_URL.'servant/attendance.php'.($selectedClassId ? "?class_id={$selectedClassId}" : ''));
        exit;
    }
}

// Build query based on servant assigned classes
$query = "
    SELECT u.id, u.full_name, u.qr_code_token, s.name_ar as stage, g.name_ar as grade, c.name_ar as class
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.role = 'student' AND u.status = 'active'
";
$params = [];

if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $query .= ' AND 1=0'; // No classes assigned
    } else {
        if ($selectedClassId && in_array($selectedClassId, $accessibleClassIds, true)) {
            $query .= ' AND u.class_id = ?';
            $params[] = $selectedClassId;
        } else {
            $inPlaceholders = implode(',', array_fill(0, count($accessibleClassIds), '?'));
            $query .= " AND u.class_id IN ({$inPlaceholders})";
            $params = $accessibleClassIds;
        }
    }
} elseif ($selectedClassId) {
    $query .= ' AND u.class_id = ?';
    $params[] = $selectedClassId;
}

$query .= ' ORDER BY u.full_name ASC';
$stmt = $db->prepare($query);
$stmt->execute($params);
$studentsList = $stmt->fetchAll();

// Preload today's attendance for these students
$todayAttStmt = $db->prepare('SELECT * FROM attendance WHERE attendance_date = ?');
$todayAttStmt->execute([$today]);
$todayAttendance = [];
foreach ($todayAttStmt->fetchAll() as $row) {
    $todayAttendance[(int) $row['student_id']] = $row;
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; margin:0;">نظام وتسجيل الحضور الذكي 📷</h1>
                <p style="color:var(--text-muted); margin:0.35rem 0 0 0;">تسجيل حضور الحصة، البامفلت، والقداس ومنح نقاط الطايو (+1 طايو لكل بند) - تاريخ اليوم: <strong><?= format_arabic_date($today) ?></strong></p>
            </div>
            <?php if (! empty($filterClasses)) { ?>
                <div>
                    <form method="GET" action="" style="display:flex; gap:0.5rem; align-items:center;">
                        <select name="class_id" class="form-control" onchange="this.form.submit()" style="min-width:200px; font-weight:600;">
                            <option value="">جميع الفصول المتاحة</option>
                            <?php foreach ($filterClasses as $fc) { ?>
                                <option value="<?= $fc['id'] ?>" <?= ($selectedClassId == $fc['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($fc['grade_name'].' - '.$fc['class_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </form>
                </div>
            <?php } ?>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.9rem; margin-bottom:1.5rem; font-size:1rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- QR Camera Scanner Box -->
            <div class="glass-card" style="text-align:center; height:fit-content;">
                <h3 style="color:var(--royal-blue); margin-bottom:0.75rem;">ماسح الكاميرا الضوئي (QR Code)</h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">وجّه كود الشماس أمام الكاميرا لتسجيل حضوره فوراً ومنحه الطايو المخصص</p>

                <!-- Scanner Item Checkboxes -->
                <div style="background:var(--bg-primary); padding:0.85rem; border-radius:10px; margin-bottom:1.25rem; border:1px solid var(--border-color); text-align:right;">
                    <div style="font-weight:700; font-size:0.85rem; margin-bottom:0.6rem; color:var(--royal-blue); display:flex; justify-content:space-between;">
                        <span>⚙️ بنود الحضور عند المسح (+1 طايو لكل بند):</span>
                        <span id="scanTayoBadge" class="badge badge-gold" style="font-size:0.8rem;">+1 طايو</span>
                    </div>
                    <div style="display:flex; justify-content:space-around; gap:0.5rem; flex-wrap:wrap;">
                        <label class="att-pill-item att-pill-lesson">
                            <input type="checkbox" id="scanCheckLesson" checked onchange="updateScanTayoBadge()" style="width:17px; height:17px; accent-color:var(--royal-blue);">
                            <span>📖 حصة (+1)</span>
                        </label>
                        <label class="att-pill-item att-pill-pamphlet">
                            <input type="checkbox" id="scanCheckPamphlet" onchange="updateScanTayoBadge()" style="width:17px; height:17px; accent-color:var(--gold);">
                            <span>📜 بامفلت (+1)</span>
                        </label>
                        <label class="att-pill-item att-pill-liturgy">
                            <input type="checkbox" id="scanCheckLiturgy" onchange="updateScanTayoBadge()" style="width:17px; height:17px; accent-color:#10b981;">
                            <span>⛪ قداس (+1)</span>
                        </label>
                    </div>
                </div>
                
                <div style="width:100%; height:min(250px, 60vw); background:#000000; border-radius:12px; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; box-shadow:0 4px 15px rgba(0,0,0,0.15);">
                    <video id="qrVideo" style="width:100%; height:100%; object-fit:cover;"></video>
                    <div id="scanStatus" style="position:absolute; bottom:10px; background:rgba(0,0,0,0.75); color:#ffffff; padding:0.4rem 1rem; border-radius:20px; font-size:0.85rem; backdrop-filter:blur(4px);">
                        اضغط بدء تشغيل الكاميرا
                    </div>
                </div>

                <div style="display:flex; gap:0.5rem; justify-content:center; flex-wrap:wrap; margin-bottom:1.5rem;">
                    <button id="startScanBtn" class="btn btn-primary" style="font-weight:700;">📷 تشغيل الكاميرا المسحية</button>
                    <button id="stopScanBtn" class="btn btn-secondary" style="display:none;">🛑 إيقاف</button>
                </div>

                <div style="border-top:1px solid var(--border-color); padding-top:1.25rem;">
                    <label class="form-label" style="font-size:0.85rem; font-weight:700;">إدخال كود الشماس يدويًا</label>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <input type="text" id="manualTokenInput" class="form-control" placeholder="STU-2026-XXXX" style="direction:ltr; text-align:center; font-weight:bold; letter-spacing:1px; flex:1; min-width:140px;">
                        <button id="manualSubmitBtn" class="btn btn-gold" style="font-weight:700; white-space:nowrap;">تسجيل الكود</button>
                    </div>
                </div>
            </div>

            <!-- Manual Checkbox List (حصة & بامفلت & قداس) -->
            <div class="glass-card">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <h3 style="color:var(--royal-blue); margin:0;">تسجيل الحضور اليدوي بالفصل 📋</h3>
                        <span style="font-size:0.8rem; color:var(--text-muted);">علم على البنود المطلوبة وسيحصل الشماس على 1 طايو لكل بند</span>
                    </div>
                    <span class="badge badge-gold" id="totalClassTayo" style="font-size:0.9rem;">إجمالي الطايو: 0 طايو</span>
                </div>

                <!-- Quick Action Buttons -->
                <div style="display:flex; gap:0.4rem; margin-bottom:1rem; flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllCategory('lesson')">☑️ تحديد كل الحصص</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllCategory('pamphlet')">☑️ تحديد كل البامفلت</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAllCategory('liturgy')">☑️ تحديد كل القداسات</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllAttendance()">✖️ إلغاء الكل</button>
                </div>

                <!-- Search box in list -->
                <div style="margin-bottom:1rem;">
                    <input type="text" id="studentFilterInput" class="form-control" placeholder="🔍 ابحث بالاسم عن شماس..." onkeyup="filterStudentsTable()">
                </div>

                <form action="" method="POST" id="manualAttendanceForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="manual_attendance" value="1">
                    
                    <div style="max-height:460px; overflow-y:auto; padding-right:0.35rem; margin-bottom:1.25rem;" id="studentsListContainer">
                        <?php if (empty($studentsList)) { ?>
                            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                                لا يوجد شمامسة مسجلين في هذا الفصل حالياً.
                            </div>
                        <?php } ?>

                        <?php foreach ($studentsList as $stu) {
                            $stuId = (int) $stu['id'];
                            $att = $todayAttendance[$stuId] ?? null;
                            $hasL = $att ? (bool) ($att['attended_lesson'] ?? true) : false;
                            $hasP = $att ? (bool) ($att['attended_pamphlet'] ?? false) : false;
                            $hasLit = $att ? (bool) ($att['attended_liturgy'] ?? false) : false;
                            $currentPts = ($hasL ? 1 : 0) + ($hasP ? 1 : 0) + ($hasLit ? 1 : 0);
                            ?>
                            <div class="student-att-row" data-name="<?= htmlspecialchars($stu['full_name'], ENT_QUOTES) ?>" style="background:var(--bg-primary); border-radius:var(--radius-sm); padding:0.85rem; margin-bottom:0.65rem; border:1px solid var(--border-color); display:flex; flex-direction:column; gap:0.5rem; transition:background 0.2s;">
                                <input type="hidden" name="class_student_ids[]" value="<?= $stuId ?>">
                                
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="color:var(--text-primary); font-size:0.95rem;"><?= sanitize($stu['full_name']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                            <?= sanitize($stu['grade'] ?? '') ?> - <?= sanitize($stu['class'] ?? '') ?>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span class="badge <?= $currentPts > 0 ? 'badge-success' : 'badge-secondary' ?> stu-tayo-badge" id="badge_<?= $stuId ?>">
                                            🪙 <?= $currentPts ?> طايو
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" style="padding:0.2rem 0.5rem; font-size:0.75rem;" onclick="toggleStudentAll(<?= $stuId ?>)">الكل</button>
                                    </div>
                                </div>

                                <!-- 3 Checkboxes: حصة & بامفلت & قداس -->
                                <div class="att-pill-box">
                                    <label class="att-pill-item att-pill-lesson">
                                        <input type="checkbox" name="att_lesson[]" value="<?= $stuId ?>" class="chk-lesson chk-stu-<?= $stuId ?>" <?= $hasL ? 'checked' : '' ?> onchange="recalculateStudent(<?= $stuId ?>)" style="width:16px; height:16px; accent-color:var(--royal-blue);">
                                        <span>📖 حصة (+1)</span>
                                    </label>
                                    <label class="att-pill-item att-pill-pamphlet">
                                        <input type="checkbox" name="att_pamphlet[]" value="<?= $stuId ?>" class="chk-pamphlet chk-stu-<?= $stuId ?>" <?= $hasP ? 'checked' : '' ?> onchange="recalculateStudent(<?= $stuId ?>)" style="width:16px; height:16px; accent-color:var(--gold);">
                                        <span>📜 بامفلت (+1)</span>
                                    </label>
                                    <label class="att-pill-item att-pill-liturgy">
                                        <input type="checkbox" name="att_liturgy[]" value="<?= $stuId ?>" class="chk-liturgy chk-stu-<?= $stuId ?>" <?= $hasLit ? 'checked' : '' ?> onchange="recalculateStudent(<?= $stuId ?>)" style="width:16px; height:16px; accent-color:#10b981;">
                                        <span>⛪ قداس (+1)</span>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; font-size:1.05rem; padding:0.85rem; font-weight:800; box-shadow:0 4px 15px rgba(30,58,138,0.25);">
                        💾 حفظ كشف الحضور وتوزيع نقاط الطايو
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function updateScanTayoBadge() {
    let pts = 0;
    if (document.getElementById('scanCheckLesson')?.checked) pts++;
    if (document.getElementById('scanCheckPamphlet')?.checked) pts++;
    if (document.getElementById('scanCheckLiturgy')?.checked) pts++;
    const badge = document.getElementById('scanTayoBadge');
    if (badge) badge.innerText = `+${pts} طايو`;
}

function recalculateStudent(stuId) {
    const chks = document.querySelectorAll(`.chk-stu-${stuId}`);
    let pts = 0;
    chks.forEach(c => { if (c.checked) pts++; });
    const badge = document.getElementById(`badge_${stuId}`);
    if (badge) {
        badge.innerText = `🪙 ${pts} طايو`;
        if (pts > 0) {
            badge.className = 'badge badge-success stu-tayo-badge';
        } else {
            badge.className = 'badge badge-secondary stu-tayo-badge';
        }
    }
    recalculateTotalClassTayo();
}

function toggleStudentAll(stuId) {
    const chks = document.querySelectorAll(`.chk-stu-${stuId}`);
    const allChecked = Array.from(chks).every(c => c.checked);
    chks.forEach(c => { c.checked = !allChecked; });
    recalculateStudent(stuId);
}

function toggleAllCategory(cat) {
    const chks = document.querySelectorAll(`.chk-${cat}`);
    const allChecked = Array.from(chks).every(c => c.checked);
    chks.forEach(c => { c.checked = !allChecked; });
    
    // Update all student badges
    document.querySelectorAll('.student-att-row').forEach(row => {
        const idInput = row.querySelector('input[name="class_student_ids[]"]');
        if (idInput) recalculateStudent(idInput.value);
    });
}

function clearAllAttendance() {
    if (!confirm('هل أنت متأكد من رغبتك في إلغاء تحديد كافة بنود الحضور للجميع؟')) return;
    document.querySelectorAll('.chk-lesson, .chk-pamphlet, .chk-liturgy').forEach(c => { c.checked = false; });
    document.querySelectorAll('.student-att-row').forEach(row => {
        const idInput = row.querySelector('input[name="class_student_ids[]"]');
        if (idInput) recalculateStudent(idInput.value);
    });
}

function recalculateTotalClassTayo() {
    let total = 0;
    document.querySelectorAll('.chk-lesson:checked, .chk-pamphlet:checked, .chk-liturgy:checked').forEach(() => { total++; });
    const totalEl = document.getElementById('totalClassTayo');
    if (totalEl) totalEl.innerText = `إجمالي الطايو: ${total} طايو`;
}

function filterStudentsTable() {
    const filter = (document.getElementById('studentFilterInput')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.student-att-row').forEach(row => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        if (name.includes(filter)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

// Initial calculation on page load
document.addEventListener('DOMContentLoaded', () => {
    updateScanTayoBadge();
    recalculateTotalClassTayo();
});
</script>

<script src="<?= BASE_URL ?>assets/js/qr-scanner.js"></script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

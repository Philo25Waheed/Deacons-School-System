<?php
$pageTitle = 'كتاب المنهج الدراسي';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin', 'servant', 'parent');

$db = getDB();
$currentUserId = $_SESSION['user']['id'];
$currentUserRole = $_SESSION['user']['role'];

// Fetch student info and stage
$stuStmt = $db->prepare('
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.id = ?
');
$stuStmt->execute([$currentUserId]);
$student = $stuStmt->fetch();

$stageId = $student['stage_id'] ?? null;
$gradeId = $student['grade_id'] ?? null;

// Query courses specific to the student's stage
$courses = [];
if ($currentUserRole === 'student') {
    if ($stageId) {
        if ($gradeId) {
            $stmt = $db->prepare('
                SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
                FROM courses c
                JOIN stages s ON c.stage_id = s.id
                LEFT JOIN grades g ON c.grade_id = g.id
                WHERE c.stage_id = ? AND (c.grade_id = ? OR c.grade_id IS NULL)
                ORDER BY c.id DESC
            ');
            $stmt->execute([$stageId, $gradeId]);
        } else {
            $stmt = $db->prepare('
                SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
                FROM courses c
                JOIN stages s ON c.stage_id = s.id
                LEFT JOIN grades g ON c.grade_id = g.id
                WHERE c.stage_id = ?
                ORDER BY c.id DESC
            ');
            $stmt->execute([$stageId]);
        }
        $courses = $stmt->fetchAll();
    }
} elseif ($currentUserRole === 'servant') {
    // Servant: get stages and grades associated with servant's assigned classes
    $servantClasses = $db->prepare('
        SELECT DISTINCT g.stage_id, g.id as grade_id
        FROM servant_classes sc
        JOIN classes cl ON sc.class_id = cl.id
        JOIN grades g ON cl.grade_id = g.id
        WHERE sc.servant_id = ?
    ');
    $servantClasses->execute([$currentUserId]);
    $assignedGrades = $servantClasses->fetchAll();

    if (! empty($assignedGrades)) {
        $stageIds = array_unique(array_column($assignedGrades, 'stage_id'));
        $inStages = implode(',', array_map('intval', $stageIds));

        $courses = $db->query("
            SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
            FROM courses c
            JOIN stages s ON c.stage_id = s.id
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE c.stage_id IN ({$inStages})
            ORDER BY s.id ASC, g.id ASC, c.id DESC
        ")->fetchAll();
    } else {
        // Fallback: show all courses
        $courses = $db->query('
            SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
            FROM courses c
            JOIN stages s ON c.stage_id = s.id
            LEFT JOIN grades g ON c.grade_id = g.id
            ORDER BY s.id ASC, g.id ASC, c.id DESC
        ')->fetchAll();
    }
} elseif ($currentUserRole === 'parent') {
    // Parent: get stages of their children
    $parentChildren = $db->prepare('
        SELECT DISTINCT u.stage_id
        FROM parent_student ps
        JOIN users u ON ps.student_id = u.id
        WHERE ps.parent_id = ? AND u.stage_id IS NOT NULL
    ');
    $parentChildren->execute([$currentUserId]);
    $childrenStages = $parentChildren->fetchAll(PDO::FETCH_COLUMN);

    if (! empty($childrenStages)) {
        $inStages = implode(',', array_map('intval', $childrenStages));
        $courses = $db->query("
            SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
            FROM courses c
            JOIN stages s ON c.stage_id = s.id
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE c.stage_id IN ({$inStages})
            ORDER BY s.id ASC, g.id ASC, c.id DESC
        ")->fetchAll();
    } else {
        $courses = $db->query('
            SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
            FROM courses c
            JOIN stages s ON c.stage_id = s.id
            LEFT JOIN grades g ON c.grade_id = g.id
            ORDER BY s.id ASC, g.id ASC, c.id DESC
        ')->fetchAll();
    }
} else {
    // Admin: view all available curriculum books
    $courses = $db->query('
        SELECT c.*, s.name_ar as stage_name, g.name_ar as grade_name
        FROM courses c
        LEFT JOIN stages s ON c.stage_id = s.id
        LEFT JOIN grades g ON c.grade_id = g.id
        ORDER BY s.id ASC, g.id ASC, c.id DESC
    ')->fetchAll();
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.25rem;">كتاب المنهج والدروس المقررة 📖</h1>
                <?php if ($currentUserRole === 'student' && ! empty($student['stage_name'])) { ?>
                    <p style="color:var(--text-muted); font-size:0.95rem;">
                        المرحلة المقيد بها: <strong style="color:var(--royal-blue);"><?= sanitize($student['stage_name']) ?></strong>
                        <?= ! empty($student['grade_name']) ? ' - '.sanitize($student['grade_name']) : '' ?>
                    </p>
                <?php } else { ?>
                    <p style="color:var(--text-muted); font-size:0.95rem;">كتب ومناهج المراحل الدراسية بصيغة PDF</p>
                <?php } ?>
            </div>

            <?php if ($currentUserRole === 'student' && ! empty($student['stage_name'])) { ?>
                <div class="badge badge-gold" style="font-size:0.95rem; padding:0.5rem 1rem;">
                    🏫 مرحلة <?= sanitize($student['stage_name']) ?>
                </div>
            <?php } ?>
        </div>

        <?php if ($currentUserRole === 'student' && ! $stageId) { ?>
            <!-- Student has no stage assigned yet -->
            <div class="glass-card" style="text-align:center; padding:3rem 1.5rem;">
                <div style="font-size:3.5rem; margin-bottom:1rem;">⚠️</div>
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;">لم يتم تحديد مرحلتك الدراسية بعد</h3>
                <p style="color:var(--text-muted); max-width:550px; margin:0 auto 1.5rem auto;">
                    حسابك غير مسند إلى مرحلة دراسية حتى الآن، يرجى التواصل مع الخادم المسؤول أو الإدارة لربط حسابك بالمرحلة والصف الخاص بك حتى تتمكن من تحميل كتاب المنهج.
                </p>
            </div>
        <?php } elseif (empty($courses)) { ?>
            <!-- No books uploaded for this stage yet -->
            <div class="glass-card" style="text-align:center; padding:3.5rem 1.5rem;">
                <div style="font-size:3.5rem; margin-bottom:1rem;">📕</div>
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;">
                    لم يتم رفع كتاب المنهج الخاص بمرحلتك حتى الآن
                </h3>
                <p style="color:var(--text-muted); max-width:550px; margin:0 auto 1.5rem auto;">
                    مرحلة: <strong><?= sanitize($student['stage_name'] ?? 'مرحلتك الدراسية') ?></strong>. سيقوم خادم المرحلة برفع كتاب الـ PDF الخاص بالمنهج قريباً، برجاء المتابعة.
                </p>
            </div>
        <?php } else { ?>
            <!-- Display Books in Modern Cards Grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
                <?php foreach ($courses as $c) { ?>
                    <div class="glass-card" style="display:flex; flex-direction:column; justify-content:space-between; border-top:4px solid var(--royal-blue); transition:transform 0.2s ease;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                                <span class="badge badge-info" style="font-weight:700;">
                                    <?= sanitize($c['stage_name'] ?? 'عام') ?>
                                    <?= ! empty($c['grade_name']) ? ' - '.sanitize($c['grade_name']) : '' ?>
                                </span>
                                <div style="font-size:2rem;">📕</div>
                            </div>

                            <h3 style="color:var(--royal-blue); font-weight:900; margin-bottom:0.75rem; font-size:1.25rem; line-height:1.4;">
                                <?= sanitize($c['title']) ?>
                            </h3>

                            <?php if (! empty($c['description'])) { ?>
                                <p style="color:var(--text-secondary); font-size:0.9rem; line-height:1.6; margin-bottom:1.25rem;">
                                    <?= sanitize($c['description']) ?>
                                </p>
                            <?php } ?>
                        </div>

                        <div style="border-top:1px solid var(--border-color); padding-top:1.25rem; margin-top:1rem;">
                            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                <?php if (! empty($c['external_link'])) { ?>
                                    <a href="<?= sanitize($c['external_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-gold" style="flex:1; text-align:center; font-weight:800; padding:0.75rem 1rem; display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;">
                                        <span>🌐 فتح الكتاب على Google Drive</span>
                                        <span style="font-size:0.9rem;">↗</span>
                                    </a>
                                <?php } elseif (! empty($c['pdf_file'])) { ?>
                                    <a href="<?= BASE_URL ?>uploads/pdf/<?= sanitize($c['pdf_file']) ?>" target="_blank" class="btn btn-primary" style="flex:1; text-align:center; font-weight:800; padding:0.65rem 1rem;">
                                        📖 فتح وقراءة الكتاب
                                    </a>
                                    <a href="<?= BASE_URL ?>uploads/pdf/<?= sanitize($c['pdf_file']) ?>" download="<?= sanitize($c['title']) ?>.pdf" class="btn btn-gold" style="font-weight:800; padding:0.65rem 1rem;" title="تحميل ملف الـ PDF">
                                        ⬇️ تحميل
                                    </a>
                                <?php } else { ?>
                                    <span style="color:var(--text-muted); font-size:0.9rem;">الملف قيد التجهيز...</span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

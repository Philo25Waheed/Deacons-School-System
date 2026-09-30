<?php
$pageTitle = 'نقل وتوزيع الخدام والمخدومين بين الفصول';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (! verify_csrf_token($csrfToken)) {
        $_SESSION['flash_error'] = 'رمز CSRF غير صالح. يرجى إعادة المحاولة.';
        header('Location: '.BASE_URL.'admin/transfer.php');
        exit;
    }

    // 1. Single Transfer
    if ($action === 'single_transfer') {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $targetClassId = filter_input(INPUT_POST, 'target_class_id', FILTER_VALIDATE_INT);

        if ($userId && $targetClassId) {
            $userStmt = $db->prepare('SELECT id, full_name, role, stage_id, grade_id, class_id FROM users WHERE id = ?');
            $userStmt->execute([$userId]);
            $targetUser = $userStmt->fetch();

            $clsStmt = $db->prepare('
                SELECT c.id as class_id, c.name_ar as class_name,
                       g.id as grade_id, g.name_ar as grade_name,
                       s.id as stage_id, s.name_ar as stage_name
                FROM classes c
                JOIN grades g ON c.grade_id = g.id
                JOIN stages s ON g.stage_id = s.id
                WHERE c.id = ?
            ');
            $clsStmt->execute([$targetClassId]);
            $newClass = $clsStmt->fetch();

            if ($targetUser && $newClass && in_array($targetUser['role'], ['student', 'servant'], true)) {
                $oldClassId = $targetUser['class_id'];

                $db->prepare('UPDATE users SET stage_id = ?, grade_id = ?, class_id = ? WHERE id = ?')
                    ->execute([$newClass['stage_id'], $newClass['grade_id'], $newClass['class_id'], $userId]);

                if ($targetUser['role'] === 'servant') {
                    if ($oldClassId) {
                        $db->prepare('DELETE FROM servant_classes WHERE servant_id = ? AND class_id = ?')->execute([$userId, $oldClassId]);
                    }
                    $db->prepare('INSERT IGNORE INTO servant_classes (servant_id, class_id) VALUES (?, ?)')->execute([$userId, $newClass['class_id']]);

                    $msg = "تم نقلك وإسنادك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                    $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل التكليف والفصل 👨‍🏫', ?)")
                        ->execute([$userId, $msg]);

                    if (function_exists('log_action')) {
                        log_action($_SESSION['user']['id'], 'SERVANT_TRANSFERRED', "Transferred servant {$targetUser['full_name']} (ID: {$userId}) to class {$newClass['class_name']}");
                    }
                } else {
                    $msg = "تم نقلك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                    $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل الفصل الدراسي 🏫', ?)")
                        ->execute([$userId, $msg]);

                    $pStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
                    $pStmt->execute([$userId]);
                    $parentIds = $pStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($parentIds as $pId) {
                        $parentMsg = "نود إحاطتكم بأنه تم نقل ابنكم {$targetUser['full_name']} إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}).";
                        $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تحديث فصل الشماس 📢', ?)")
                            ->execute([$pId, $parentMsg]);
                    }

                    if (function_exists('log_action')) {
                        log_action($_SESSION['user']['id'], 'STUDENT_TRANSFERRED', "Transferred student {$targetUser['full_name']} (ID: {$userId}) to class {$newClass['class_name']}");
                    }
                }

                $_SESSION['flash_success'] = "تم نقل {$targetUser['full_name']} إلى فصل ({$newClass['class_name']}) بنجاح!";
            } else {
                $_SESSION['flash_error'] = 'بيانات المستخدم أو الفصل المستهدف غير صحيحة.';
            }
        } else {
            $_SESSION['flash_error'] = 'يرجى اختيار المستخدم والفصل المستهدف.';
        }
        header('Location: '.BASE_URL.'admin/transfer.php');
        exit;
    }

    // 2. Bulk Transfer
    if ($action === 'bulk_transfer') {
        $selectedUserIds = $_POST['user_ids'] ?? [];
        $targetClassId = filter_input(INPUT_POST, 'target_class_id', FILTER_VALIDATE_INT);

        if (empty($selectedUserIds) || ! is_array($selectedUserIds) || ! $targetClassId) {
            $_SESSION['flash_error'] = 'يرجى تحديد طالب أو خادم واحد على الأقل واختيار الفصل الجديد للنقل.';
            header('Location: '.BASE_URL.'admin/transfer.php');
            exit;
        }

        $clsStmt = $db->prepare('
            SELECT c.id as class_id, c.name_ar as class_name,
                   g.id as grade_id, g.name_ar as grade_name,
                   s.id as stage_id, s.name_ar as stage_name
            FROM classes c
            JOIN grades g ON c.grade_id = g.id
            JOIN stages s ON g.stage_id = s.id
            WHERE c.id = ?
        ');
        $clsStmt->execute([$targetClassId]);
        $newClass = $clsStmt->fetch();

        if (! $newClass) {
            $_SESSION['flash_error'] = 'الفصل المستهدف غير موجود.';
            header('Location: '.BASE_URL.'admin/transfer.php');
            exit;
        }

        $transferredCount = 0;
        $db->beginTransaction();

        try {
            foreach ($selectedUserIds as $rawUid) {
                $uid = (int) $rawUid;
                if (! $uid) {
                    continue;
                }

                $uStmt = $db->prepare('SELECT id, full_name, role, class_id FROM users WHERE id = ? AND role IN ("student", "servant")');
                $uStmt->execute([$uid]);
                $user = $uStmt->fetch();

                if (! $user) {
                    continue;
                }

                $oldClassId = $user['class_id'];

                $db->prepare('UPDATE users SET stage_id = ?, grade_id = ?, class_id = ? WHERE id = ?')
                    ->execute([$newClass['stage_id'], $newClass['grade_id'], $newClass['class_id'], $uid]);

                if ($user['role'] === 'servant') {
                    if ($oldClassId) {
                        $db->prepare('DELETE FROM servant_classes WHERE servant_id = ? AND class_id = ?')->execute([$uid, $oldClassId]);
                    }
                    $db->prepare('INSERT IGNORE INTO servant_classes (servant_id, class_id) VALUES (?, ?)')->execute([$uid, $newClass['class_id']]);

                    $msg = "تم نقلك وإسنادك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                    $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل التكليف والفصل 👨‍🏫', ?)")
                        ->execute([$uid, $msg]);
                } else {
                    $msg = "تم نقلك إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}) بنجاح!";
                    $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'نقل الفصل الدراسي 🏫', ?)")
                        ->execute([$uid, $msg]);

                    $pStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
                    $pStmt->execute([$uid]);
                    $parentIds = $pStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($parentIds as $pId) {
                        $parentMsg = "نود إحاطتكم بأنه تم نقل ابنكم {$user['full_name']} إلى فصل {$newClass['class_name']} ({$newClass['grade_name']} - {$newClass['stage_name']}).";
                        $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تحديث فصل الشماس 📢', ?)")
                            ->execute([$pId, $parentMsg]);
                    }
                }

                $transferredCount++;
            }

            $db->commit();

            if (function_exists('log_action')) {
                log_action($_SESSION['user']['id'], 'BULK_CLASS_TRANSFER', "Transferred {$transferredCount} members to class {$newClass['class_name']} (ID: {$targetClassId})");
            }

            $_SESSION['flash_success'] = "تم بنجاح نقل {$transferredCount} عضو/مخدوم إلى فصل ({$newClass['class_name']})!";
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'حدث خطأ أثناء تنفيذ النقل الجماعي: '.$e->getMessage();
        }

        header('Location: '.BASE_URL.'admin/transfer.php');
        exit;
    }
}

// Fetch all classes with counts for UI dropdowns
$allClasses = $db->query('
    SELECT c.id, c.name_ar as class_name, 
           g.name_ar as grade_name, 
           s.name_ar as stage_name,
           (SELECT COUNT(*) FROM users u WHERE u.class_id = c.id AND u.role = "student") as student_count,
           (SELECT COUNT(DISTINCT sc.servant_id) FROM servant_classes sc WHERE sc.class_id = c.id) as servant_count
    FROM classes c
    JOIN grades g ON c.grade_id = g.id
    JOIN stages s ON g.stage_id = s.id
    ORDER BY s.id ASC, g.id ASC, c.id ASC
')->fetchAll();

// Global Stats
$totalStudents = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$studentsInClasses = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND class_id IS NOT NULL")->fetchColumn();
$studentsWithoutClass = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND class_id IS NULL")->fetchColumn();
$totalServants = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'servant'")->fetchColumn();
$totalClasses = count($allClasses);

// Filter source class for bulk transfer if passed via GET
$selectedSourceClassId = filter_input(INPUT_GET, 'from_class', FILTER_VALIDATE_INT) ?: null;
$sourceClassMembers = [];
if ($selectedSourceClassId) {
    $mStmt = $db->prepare('
        SELECT u.id, u.full_name, u.phone, u.role, u.qr_code_token, u.deacon_rank, u.profile_pic,
               c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN stages s ON u.stage_id = s.id
        WHERE (u.class_id = ? AND u.role IN ("student", "servant"))
           OR (u.role = "servant" AND u.id IN (SELECT servant_id FROM servant_classes WHERE class_id = ?))
        ORDER BY u.role DESC, u.full_name ASC
    ');
    $mStmt->execute([$selectedSourceClassId, $selectedSourceClassId]);
    $sourceClassMembers = $mStmt->fetchAll();
}

// Fetch all active students & servants for single transfer selector
$allMembers = $db->query('
    SELECT u.id, u.full_name, u.phone, u.role, u.qr_code_token,
           c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN stages s ON u.stage_id = s.id
    WHERE u.role IN ("student", "servant") AND u.status = "active"
    ORDER BY u.role DESC, u.full_name ASC
')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.6rem; margin-bottom:0.25rem;">
                    نقل وتسكين الخدام والمخدومين بين الفصول 🔄
                </h1>
                <p style="color:var(--text-muted); font-size:0.92rem; margin:0;">
                    إمكانية نقل شماس أو خادم بمفرده أو نقل فصول ومجموعات مخدومين بالكامل بضغطة زر واحدة.
                </p>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary btn-sm">👥 قائمة المستخدمين</a>
                <a href="<?= BASE_URL ?>admin/stages.php" class="btn btn-secondary btn-sm">🏫 إدارة المراحل والفصول</a>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:8px; font-size:0.95rem; text-align:right;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:8px; font-size:0.95rem; text-align:right;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Quick Stats Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.75rem;">
            <div class="glass-card" style="padding:1.15rem; display:flex; align-items:center; gap:1rem;">
                <div style="font-size:2rem; background:rgba(37, 99, 235, 0.1); width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px;">👦</div>
                <div>
                    <div style="font-size:0.85rem; color:var(--text-muted);">شمامسة مسكنين بفصول</div>
                    <div style="font-size:1.4rem; font-weight:800; color:var(--royal-blue);"><?= $studentsInClasses ?> / <?= $totalStudents ?></div>
                </div>
            </div>

            <div class="glass-card" style="padding:1.15rem; display:flex; align-items:center; gap:1rem;">
                <div style="font-size:2rem; background:rgba(217, 119, 6, 0.1); width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px;">👨‍🏫</div>
                <div>
                    <div style="font-size:0.85rem; color:var(--text-muted);">إجمالي الخدام</div>
                    <div style="font-size:1.4rem; font-weight:800; color:var(--gold);"><?= $totalServants ?> خادم</div>
                </div>
            </div>

            <div class="glass-card" style="padding:1.15rem; display:flex; align-items:center; gap:1rem;">
                <div style="font-size:2rem; background:rgba(16, 185, 129, 0.1); width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px;">🏫</div>
                <div>
                    <div style="font-size:0.85rem; color:var(--text-muted);">إجمالي الفصول المتاحة</div>
                    <div style="font-size:1.4rem; font-weight:800; color:#10b981;"><?= $totalClasses ?> فصل</div>
                </div>
            </div>

            <div class="glass-card" style="padding:1.15rem; display:flex; align-items:center; gap:1rem;">
                <div style="font-size:2rem; background:rgba(239, 68, 68, 0.1); width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px;">⚠️</div>
                <div>
                    <div style="font-size:0.85rem; color:var(--text-muted);">بدون فصل (بحاجة لتسكين)</div>
                    <div style="font-size:1.4rem; font-weight:800; color:<?= $studentsWithoutClass > 0 ? '#ef4444' : 'var(--text-primary)' ?>;"><?= $studentsWithoutClass ?></div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr; gap:1.75rem;">

            <!-- SECTION 1: Bulk Transfer Between Classes -->
            <div class="glass-card" style="border-top:4px solid var(--royal-blue);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <h2 style="color:var(--royal-blue); font-size:1.3rem; font-weight:800; margin:0 0 0.25rem 0; display:flex; align-items:center; gap:0.5rem;">
                            <span>📦</span>
                            <span>النقل والترحيل الجماعي (بين الفصول)</span>
                        </h2>
                        <p style="color:var(--text-muted); font-size:0.88rem; margin:0;">
                            اختر الفصل الحالي لعرض كافة مخدوميه وخدامه، ثم حدد الطلاب المطلوبين والفصل الجديد لنقلهم دفعة واحدة.
                        </p>
                    </div>
                </div>

                <!-- Select Source Class Filter -->
                <form action="" method="GET" style="margin-bottom:1.5rem; background:var(--bg-primary); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border-color); display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <label for="from_class" style="font-weight:700; white-space:nowrap; margin:0;">1️⃣ اختر الفصل الحالي المصدر:</label>
                    <select name="from_class" id="from_class" class="form-control" style="flex:1; min-width:240px;" onchange="this.form.submit()">
                        <option value="">-- اضغط لاختيار فصل لعرض طلبته وخدامه --</option>
                        <?php foreach ($allClasses as $cls) { ?>
                            <option value="<?= $cls['id'] ?>" <?= ($selectedSourceClassId == $cls['id']) ? 'selected' : '' ?>>
                                <?= sanitize($cls['stage_name']) ?> ➔ <?= sanitize($cls['grade_name']) ?> ➔ <?= sanitize($cls['class_name']) ?> (<?= $cls['student_count'] ?> مخدوم - <?= $cls['servant_count'] ?> خادم)
                            </option>
                        <?php } ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-primary btn-sm">عرض</button></noscript>
                </form>

                <?php if ($selectedSourceClassId) { ?>
                    <?php if (empty($sourceClassMembers)) { ?>
                        <div style="text-align:center; padding:2.5rem; color:var(--text-muted); background:var(--bg-primary); border-radius:var(--radius-sm);">
                            لا يوجد أي مخدومين أو خدام مسجلين في هذا الفصل حالياً.
                        </div>
                    <?php } else { ?>
                        <form action="" method="POST" id="bulkTransferForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="bulk_transfer">

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:700; cursor:pointer;">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="width:18px; height:18px;">
                                    <span>تحديد الكل (<?= count($sourceClassMembers) ?> أعضاء)</span>
                                </label>
                                <span style="font-size:0.88rem; color:var(--text-muted);" id="selectedCountLabel">تم تحديد: 0</span>
                            </div>

                            <div class="table-responsive" style="max-height:360px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius-sm); margin-bottom:1.5rem;">
                                <table class="custom-table" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px; text-align:center;">اختر</th>
                                            <th style="width:110px;">الكود</th>
                                            <th>الاسم</th>
                                            <th>الدور / الرتبة</th>
                                            <th>الهاتف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sourceClassMembers as $m) { ?>
                                            <tr style="cursor:pointer;" onclick="toggleRowCheckbox(this, event)">
                                                <td style="text-align:center;">
                                                    <input type="checkbox" name="user_ids[]" value="<?= $m['id'] ?>" class="member-checkbox" onchange="updateSelectedCount()" style="width:18px; height:18px;">
                                                </td>
                                                <td><span class="code-badge"><?= sanitize($m['qr_code_token'] ?? '-') ?></span></td>
                                                <td>
                                                    <strong><?= sanitize($m['full_name']) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($m['role'] === 'servant') { ?>
                                                        <span class="badge badge-gold">خادم</span>
                                                    <?php } else { ?>
                                                        <span class="badge badge-info">شماس / مخدوم</span>
                                                        <?php if (! empty($m['deacon_rank'])) { ?>
                                                            <span style="font-size:0.78rem; color:var(--text-muted);">(<?= sanitize($m['deacon_rank']) ?>)</span>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </td>
                                                <td dir="ltr" style="text-align:right; font-family:monospace; font-size:0.88rem;"><?= sanitize($m['phone']) ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Target Class Selection and Execute Button -->
                            <div style="background:var(--royal-blue-glow); padding:1.25rem; border-radius:var(--radius-sm); border:1px solid rgba(37, 99, 235, 0.3);">
                                <h4 style="color:var(--royal-blue); margin-top:0; margin-bottom:0.75rem; font-weight:800;">2️⃣ اختر الفصل الجديد المستهدف للنقل:</h4>
                                <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                                    <div style="flex:1; min-width:280px;">
                                        <select name="target_class_id" class="form-control" required style="font-weight:700;">
                                            <option value="">-- اضغط لاختيار الفصل الجديد المستهدف --</option>
                                            <?php foreach ($allClasses as $cls) { ?>
                                                <?php if ($cls['id'] != $selectedSourceClassId) { ?>
                                                    <option value="<?= $cls['id'] ?>">
                                                        <?= sanitize($cls['stage_name']) ?> ➔ <?= sanitize($cls['grade_name']) ?> ➔ <?= sanitize($cls['class_name']) ?> (حالياً به: <?= $cls['student_count'] ?> مخدوم)
                                                    </option>
                                                <?php } ?>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-gold" style="padding:0.75rem 1.5rem; font-weight:800; font-size:1.05rem;" onclick="return confirmBulkTransfer()">
                                        🚀 نقل جميع المحددين إلى الفصل الجديد
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>

            <!-- SECTION 2: Single Quick Transfer -->
            <div class="glass-card" style="border-top:4px solid var(--gold);">
                <h2 style="color:var(--gold); font-size:1.3rem; font-weight:800; margin:0 0 0.5rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <span>👤</span>
                    <span>النقل الفردي السريع (شماس أو خادم محدد)</span>
                </h2>
                <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:1.25rem;">
                    ابحث عن خادم أو مخدوم محدد وانقله مباشرة إلى أي مرحلة وصف وفصل جديد.
                </p>

                <form action="" method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; align-items:end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="single_transfer">

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-weight:700;">اختر الخادم أو الشماس *</label>
                        <select name="user_id" id="single_user_select" class="form-control" required>
                            <option value="">-- اختر العضو (<?= count($allMembers) ?> عضو متاح) --</option>
                            <?php foreach ($allMembers as $mem) { ?>
                                <option value="<?= $mem['id'] ?>">
                                    <?= ($mem['role'] === 'servant' ? '👨‍🏫 [خادم] ' : '👦 [شماس] ') ?>
                                    <?= sanitize($mem['full_name']) ?> 
                                    - الحالى: (<?= sanitize($mem['stage_name'] ?? 'بدون مرحلة') ?> / <?= sanitize($mem['class_name'] ?? 'بدون فصل') ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-weight:700;">الفصل الجديد المستهدف *</label>
                        <select name="target_class_id" class="form-control" required>
                            <option value="">-- اختر الفصل المستهدف --</option>
                            <?php foreach ($allClasses as $cls) { ?>
                                <option value="<?= $cls['id'] ?>">
                                    <?= sanitize($cls['stage_name']) ?> ➔ <?= sanitize($cls['grade_name']) ?> ➔ <?= sanitize($cls['class_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem; font-weight:800;" onclick="return confirm('هل أنت متأكد من رغبتك بنقل هذا العضو إلى الفصل الجديد؟')">
                            🔄 تنفيذ النقل الفردي
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.member-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    updateSelectedCount();
}

function toggleRowCheckbox(row, event) {
    if (event.target.tagName.toLowerCase() === 'input') return;
    const cb = row.querySelector('.member-checkbox');
    if (cb) {
        cb.checked = !cb.checked;
        updateSelectedCount();
    }
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.member-checkbox:checked').length;
    const total = document.querySelectorAll('.member-checkbox').length;
    const countLabel = document.getElementById('selectedCountLabel');
    if (countLabel) {
        countLabel.textContent = `تم تحديد: ${checked} من ${total}`;
    }
    const selectAll = document.getElementById('selectAllCheckbox');
    if (selectAll) {
        selectAll.checked = (checked === total && total > 0);
    }
}

function confirmBulkTransfer() {
    const checked = document.querySelectorAll('.member-checkbox:checked').length;
    if (checked === 0) {
        alert('يرجى تحديد طالب أو خادم واحد على الأقل للنقل!');
        return false;
    }
    return confirm(`هل أنت متأكد من رغبتك بنقل (${checked}) عضو/مخدوم إلى الفصل المستهدف المحدد؟`);
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

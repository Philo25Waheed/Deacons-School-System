<?php
$pageTitle = 'جدول خدمة القداسات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();

if (! function_exists('notify_roster_assignment')) {
    function notify_roster_assignment(PDO $db, string $title, string $serviceDate, int $sId, array $rolesList, string $adminNotes = ''): void
    {
        $rolesText = implode(' و ', $rolesList);
        $notesPart = ! empty($adminNotes) ? "\n📌 توجيهات وملاحظات الخدمة الخاصة بك: {$adminNotes}" : '';

        $sInfoStmt = $db->prepare('SELECT id, full_name, class_id, father_phone, mother_phone FROM users WHERE id = ?');
        $sInfoStmt->execute([$sId]);
        $studentInfo = $sInfoStmt->fetch();
        $studentName = $studentInfo['full_name'] ?? 'الشماس';

        $notifStmt = $db->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');

        // 1. Notify Student
        $notifStmt->execute([
            $sId,
            'تكليف بخدمة قداس إلهي ⛪',
            "تم إدراج اسمك في جدول خدمة قداس ({$title}) بتاريخ {$serviceDate} لدور: [{$rolesText}].{$notesPart} برجاء تأكيد الحضور من صفحة جدول خدمتي.",
        ]);

        // 2. Notify Parent(s) via parent_student and matched phone numbers
        $parentIds = [];
        $pStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
        $pStmt->execute([$sId]);
        $parentIds = $pStmt->fetchAll(PDO::FETCH_COLUMN);

        $phones = array_values(array_filter([$studentInfo['father_phone'] ?? '', $studentInfo['mother_phone'] ?? '']));
        if (! empty($phones)) {
            $inPhones = implode(',', array_fill(0, count($phones), '?'));
            $pPhoneStmt = $db->prepare("SELECT id FROM users WHERE phone IN ($inPhones) AND role = 'parent'");
            $pPhoneStmt->execute($phones);
            $moreParentIds = $pPhoneStmt->fetchAll(PDO::FETCH_COLUMN);
            $parentIds = array_unique(array_merge($parentIds, $moreParentIds));
        }

        foreach ($parentIds as $pId) {
            $notifStmt->execute([
                $pId,
                'تكليف بخدمة قداس إلهي لابنكم ⛪',
                "تم إدراج ابنكم الشماس ({$studentName}) في جدول خدمة قداس ({$title}) بتاريخ {$serviceDate} لدور: [{$rolesText}].{$notesPart} يرجى تشجيعه ومتابعة حضوره للخدمة.",
            ]);
        }

        // 3. Notify Servant(s) of this student's class
        if (! empty($studentInfo['class_id'])) {
            $srvStmt = $db->prepare('SELECT servant_id FROM servant_classes WHERE class_id = ?');
            $srvStmt->execute([$studentInfo['class_id']]);
            $servantIds = $srvStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($servantIds as $srvId) {
                $notifStmt->execute([
                    $srvId,
                    'تكليف شماس من فصلك بخدمة القداس ⛪',
                    "تم إدراج مخدوم من فصلك ({$studentName}) في جدول خدمة قداس ({$title}) بتاريخ {$serviceDate} لدور: [{$rolesText}].{$notesPart}",
                ]);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $action = sanitize($_POST['form_action'] ?? '');

        if (in_array($action, ['create_roster', 'assign_substitute', 'add_roster_assignment', 'update_student_notes', 'delete_roster_assignment'])) {
            if ($_SESSION['user']['role'] !== 'admin') {
                $_SESSION['flash_error'] = 'عفواً، وضع وتعديل جدول خدمة القداسات والتكليفات متاح لمدير النظام (الآدمن) فقط.';
                header('Location: '.BASE_URL.'admin/roster.php');
                exit;
            }
        }

        if ($action === 'create_roster') {
            $title = sanitize($_POST['title'] ?? '');
            $serviceDate = sanitize($_POST['service_date'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');

            // Collect assignments for readings and service roles
            $assignments = [];
            $readings = [
                'role_matins_gospel' => 'إنجيل باكر',
                'role_paul' => 'بولس',
                'role_catholic' => 'كاثوليكون',
                'role_praxis' => 'إبركسيس',
                'role_liturgy_gospel' => 'إنجيل القداس',
            ];
            $roleNotes = $_POST['role_notes'] ?? [];

            foreach ($readings as $field => $roleName) {
                $sId = filter_input(INPUT_POST, $field, FILTER_VALIDATE_INT);
                if ($sId) {
                    $rNote = sanitize($roleNotes[$field] ?? '');
                    $assignments[] = [
                        'student_id' => $sId,
                        'role_name' => $roleName,
                        'admin_notes' => $rNote,
                    ];
                }
            }

            if (! empty($_POST['role_altar']) && is_array($_POST['role_altar'])) {
                foreach ($_POST['role_altar'] as $sId) {
                    $valId = filter_var($sId, FILTER_VALIDATE_INT);
                    if ($valId) {
                        $assignments[] = [
                            'student_id' => $valId,
                            'role_name' => 'خدمة مذبح',
                            'admin_notes' => '',
                        ];
                    }
                }
            }

            if (! empty($_POST['role_matins']) && is_array($_POST['role_matins'])) {
                foreach ($_POST['role_matins'] as $sId) {
                    $valId = filter_var($sId, FILTER_VALIDATE_INT);
                    if ($valId) {
                        $assignments[] = [
                            'student_id' => $valId,
                            'role_name' => 'خدمة باكر',
                            'admin_notes' => '',
                        ];
                    }
                }
            }

            // Custom dynamic assignments
            if (! empty($_POST['custom_roles']) && is_array($_POST['custom_roles'])) {
                foreach ($_POST['custom_roles'] as $idx => $rawRole) {
                    $cRole = sanitize($rawRole);
                    $cStudent = filter_var($_POST['custom_students'][$idx] ?? 0, FILTER_VALIDATE_INT);
                    $cNote = sanitize($_POST['custom_notes'][$idx] ?? '');
                    if (! empty($cRole) && $cStudent) {
                        $assignments[] = [
                            'student_id' => $cStudent,
                            'role_name' => $cRole,
                            'admin_notes' => $cNote,
                        ];
                    }
                }
            }

            if (empty($title) || empty($serviceDate) || empty($assignments)) {
                $_SESSION['flash_error'] = 'يرجى إدخال عنوان القداس والتاريخ وتعيين شماس واحد على الأقل في القراءات أو خدمة المذبح / باكر أو التكليفات الإضافية.';
                header('Location: '.BASE_URL.'admin/roster.php');
                exit;
            }

            $stmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, notes, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $serviceDate, $notes, $_SESSION['user']['id']]);
            $rosterId = $db->lastInsertId();

            $stmtStu = $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id, role_name, admin_notes, status) VALUES (?, ?, ?, ?, "pending")');
            $studentAssignmentsMap = [];
            foreach ($assignments as $item) {
                $stmtStu->execute([$rosterId, $item['student_id'], $item['role_name'], $item['admin_notes'] ?: null]);
                $studentAssignmentsMap[$item['student_id']]['roles'][] = $item['role_name'];
                if (! empty($item['admin_notes'])) {
                    $studentAssignmentsMap[$item['student_id']]['notes'][] = $item['admin_notes'];
                }
            }

            // Notify assigned students, their parents, and class servants
            foreach ($studentAssignmentsMap as $sId => $info) {
                $combinedNotes = implode(' | ', $info['notes'] ?? []);
                notify_roster_assignment($db, $title, $serviceDate, $sId, $info['roles'], $combinedNotes);
            }

            $_SESSION['flash_success'] = 'تم نشر جدول خدمة القداس وإرسال إشعارات التكليف والتوجيهات للشمامسة وأولياء الأمور بنجاح!';
            header('Location: '.BASE_URL.'admin/roster.php');
            exit;
        } elseif ($action === 'add_roster_assignment') {
            $rosterId = filter_input(INPUT_POST, 'roster_id', FILTER_VALIDATE_INT);
            $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            $roleName = sanitize($_POST['role_name'] ?? '');
            $adminNotes = sanitize($_POST['admin_notes'] ?? '');

            if (! $rosterId || ! $studentId || empty($roleName)) {
                $_SESSION['flash_error'] = 'يرجى اختيار القداس والشماس وتحديد اسم الدور أو التكليف.';
                header('Location: '.BASE_URL.'admin/roster.php');
                exit;
            }

            $rosStmt = $db->prepare('SELECT id, title, service_date FROM liturgy_roster WHERE id = ?');
            $rosStmt->execute([$rosterId]);
            $ros = $rosStmt->fetch();

            if (! $ros) {
                $_SESSION['flash_error'] = 'القداس المحدد غير موجود.';
                header('Location: '.BASE_URL.'admin/roster.php');
                exit;
            }

            $stmtStu = $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id, role_name, admin_notes, status) VALUES (?, ?, ?, ?, "pending")');
            $stmtStu->execute([$rosterId, $studentId, $roleName, $adminNotes ?: null]);

            notify_roster_assignment($db, $ros['title'], $ros['service_date'], $studentId, [$roleName], $adminNotes);

            $_SESSION['flash_success'] = "تمت إضافة تكليف [{$roleName}] للشماس بنجاح وإرسال الإشعارات والتوجيهات الخاصة به!";
            header('Location: '.BASE_URL.'admin/roster.php');
            exit;
        } elseif ($action === 'update_student_notes') {
            $rosterStudentId = filter_input(INPUT_POST, 'roster_student_id', FILTER_VALIDATE_INT);
            $adminNotes = sanitize($_POST['admin_notes'] ?? '');

            if ($rosterStudentId) {
                $db->prepare('UPDATE liturgy_roster_students SET admin_notes = ? WHERE id = ?')
                    ->execute([$adminNotes ?: null, $rosterStudentId]);
                $_SESSION['flash_success'] = 'تم حفظ وتحديث ملاحظات وتوجيهات الشماس بنجاح!';
            }
            header('Location: '.BASE_URL.'admin/roster.php');
            exit;
        } elseif ($action === 'delete_roster_assignment') {
            $rosterStudentId = filter_input(INPUT_POST, 'roster_student_id', FILTER_VALIDATE_INT);

            if ($rosterStudentId) {
                $db->prepare('DELETE FROM liturgy_roster_students WHERE id = ?')
                    ->execute([$rosterStudentId]);
                $_SESSION['flash_success'] = 'تم حذف التكليف من جدول القداس بنجاح!';
            }
            header('Location: '.BASE_URL.'admin/roster.php');
            exit;
        } elseif ($action === 'assign_substitute') {
            $rosterStudentId = filter_input(INPUT_POST, 'roster_student_id', FILTER_VALIDATE_INT);
            $substituteId = filter_input(INPUT_POST, 'substitute_id', FILTER_VALIDATE_INT);

            if ($rosterStudentId && $substituteId) {
                $db->prepare("
                    UPDATE liturgy_roster_students 
                    SET substitute_student_id = ?, status = 'swapped' 
                    WHERE id = ?
                ")->execute([$substituteId, $rosterStudentId]);

                $_SESSION['flash_success'] = 'تم تعيين الشماس البديل بنجاح!';
                header('Location: '.BASE_URL.'admin/roster.php');
                exit;
            }
        } elseif ($action === 'servant_update_status') {
            $rosterStudentId = filter_input(INPUT_POST, 'roster_student_id', FILTER_VALIDATE_INT);
            $newStatus = sanitize($_POST['new_status'] ?? '');
            $statusNote = sanitize($_POST['status_note'] ?? '');

            if ($rosterStudentId && in_array($newStatus, ['confirmed', 'declined'])) {
                $canUpdate = false;
                if ($_SESSION['user']['role'] === 'admin') {
                    $canUpdate = true;
                } else {
                    $chk = $db->prepare('
                        SELECT 1 
                        FROM liturgy_roster_students rs
                        JOIN users u ON rs.student_id = u.id
                        JOIN servant_classes sc ON u.class_id = sc.class_id
                        WHERE rs.id = ? AND sc.servant_id = ?
                    ');
                    $chk->execute([$rosterStudentId, $_SESSION['user']['id']]);
                    $canUpdate = (bool) $chk->fetchColumn();
                }

                if ($canUpdate) {
                    $defaultNote = ($newStatus === 'confirmed') ? 'تم تأكيد الحضور بواسطة خادم الفصل' : 'اعتذار مسجل بواسطة خادم الفصل';
                    $finalNote = $statusNote ?: $defaultNote;

                    $db->prepare('
                        UPDATE liturgy_roster_students 
                        SET status = ?, response_notes = ?, responded_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ')->execute([$newStatus, $finalNote, $rosterStudentId]);

                    if ($newStatus === 'declined') {
                        $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (1, 'تسجيل اعتذار شماس عن خدمة قداس ⚠️', ?)")
                            ->execute(['قام خادم الفصل بتسجيل اعتذار للشماس المكلف. سبب الاعتذار: '.$finalNote]);
                    }

                    $_SESSION['flash_success'] = 'تم تحديث حالة حضور الشماس بنجاح!';
                } else {
                    $_SESSION['flash_error'] = 'غير مصرح لك بتعديل حالة هذا الشماس.';
                }
            }
            header('Location: '.BASE_URL.'admin/roster.php');
            exit;
        }
    }
}

$userRole = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];
$isAdmin = ($userRole === 'admin');
$isServant = ($userRole === 'servant');

$myClassIds = [];
$myClassNames = [];
$myClassAssignments = [];

if ($isServant) {
    $scStmt = $db->prepare('
        SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM servant_classes sc
        JOIN classes c ON sc.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        JOIN stages s ON g.stage_id = s.id
        WHERE sc.servant_id = ?
    ');
    $scStmt->execute([$userId]);
    $servantClasses = $scStmt->fetchAll();
    $myClassIds = array_column($servantClasses, 'id');
    $myClassNames = array_column($servantClasses, 'class_name');

    if (! empty($myClassIds)) {
        $inClasses = implode(',', array_fill(0, count($myClassIds), '?'));
        $stmtMyClass = $db->prepare("
            SELECT rs.id as roster_student_id, rs.role_name, rs.admin_notes, rs.status, rs.response_notes, rs.responded_at,
                   r.id as roster_id, r.title as liturgy_title, r.service_date, r.notes as liturgy_notes,
                   u.id as student_id, u.full_name as student_name, u.phone as student_phone,
                   u.father_phone, u.mother_phone, c.name_ar as class_name
            FROM liturgy_roster_students rs
            JOIN liturgy_roster r ON rs.roster_id = r.id
            JOIN users u ON rs.student_id = u.id
            LEFT JOIN classes c ON u.class_id = c.id
            WHERE u.class_id IN ($inClasses)
            ORDER BY r.service_date DESC, rs.id DESC
        ");
        $stmtMyClass->execute($myClassIds);
        $myClassAssignments = $stmtMyClass->fetchAll();
    }
}

try {
    $students = $db->query("
        SELECT u.id, u.full_name, u.phone, u.deacon_rank, u.class_id, s.name_ar as stage_name, c.name_ar as class_name
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.role = 'student' AND u.status = 'active'
        ORDER BY u.full_name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $students = [];
}

try {
    $classes = $db->query('
        SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        JOIN stages s ON g.stage_id = s.id
        ORDER BY s.id, g.id, c.name_ar ASC
    ')->fetchAll();
} catch (Throwable $e) {
    $classes = [];
}

try {
    $rosters = $db->query('
        SELECT r.*, COUNT(rs.student_id) as student_count
        FROM liturgy_roster r
        LEFT JOIN liturgy_roster_students rs ON r.id = rs.roster_id
        GROUP BY r.id, r.title, r.service_date, r.notes, r.created_by, r.created_at 
        ORDER BY r.service_date DESC
    ')->fetchAll();
} catch (Throwable $e) {
    $rosters = [];
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.deacon-select-card {
    background: var(--bg-surface);
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    transition: var(--transition);
    user-select: none;
}

.deacon-select-card:hover {
    border-color: var(--royal-blue);
    background: var(--royal-blue-glow);
}

.deacon-select-card.selected {
    border-color: var(--gold);
    background: var(--gold-glow);
}

.deacon-select-card input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--royal-blue);
    cursor: pointer;
}

.role-badge-pill {
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.class-student-card:hover {
    border-color: var(--royal-blue);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">جدول خدمة القداسات الإلهية ⛪</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <?php if ($isAdmin) { ?>
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1.25rem; font-weight:800;">➕ تجهيز جدول خدمة قداس جديد</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="create_roster">

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" for="title">عنوان الخدمة / المناسبة *</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="مثال: قداس الأحد - تذكار الشهيد إسطفانوس" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="service_date">تاريخ القداس *</label>
                        <input type="date" id="service_date" name="service_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label class="form-label" for="notes">ملاحظات وتعليمات الخدمة</label>
                    <input type="text" id="notes" name="notes" class="form-control" placeholder="مثال: الحضور بالتونة البيضاء قبل موعد رفع بخور باكر بنصف ساعة">
                </div>

                <!-- Class Filter & Search Bar -->
                <div style="background:var(--royal-blue-glow); border:1.5px solid var(--royal-blue); border-radius:var(--radius-sm); padding:1rem 1.25rem; margin-bottom:1.25rem;">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                        <div style="flex:1; min-width:260px;">
                            <label for="classFilterSelect" style="display:block; font-weight:800; color:var(--royal-blue); margin-bottom:0.4rem;">
                                🏫 فلترة الشمامسة حسب الفصل الدراسي:
                            </label>
                            <select id="classFilterSelect" class="form-control" onchange="filterAllByClass(this.value)" style="border:1.5px solid var(--royal-blue); font-weight:700;">
                                <option value="all">🌟 عرض جميع الفصول والمراحل</option>
                                <?php foreach ($classes as $c) { ?>
                                    <option value="<?= $c['id'] ?>"><?= sanitize($c['stage_name'].' ➔ '.$c['grade_name'].' ➔ '.$c['class_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div style="flex:1; min-width:220px;">
                            <label for="nameFilterInput" style="display:block; font-weight:800; color:var(--royal-blue); margin-bottom:0.4rem;">
                                🔍 بحث سريع باسم الشماس:
                            </label>
                            <input type="text" id="nameFilterInput" class="form-control" placeholder="اكتب اسم الشماس للفلترة..." oninput="filterAllByName(this.value)">
                        </div>
                    </div>
                </div>

                <!-- Selected Class Students Showcase (يظهر أسماء المخدومين في الفصل المختار) -->
                <div id="classStudentsShowcase" style="background:var(--bg-surface); border:1.5px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem; margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                        <div>
                            <h4 id="classShowcaseTitle" style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.5rem;">
                                👥 أسماء مخدومي الفصل المختار (<span id="filteredStudentsCount"><?= count($students) ?></span> شماس)
                            </h4>
                            <span style="font-size:0.83rem; color:var(--text-muted); display:block; margin-top:0.25rem;">
                                قائمة شمامسة الفصل - اضغط على أي زر لتكليف الشماس بالدور مباشرة في الجدول أدناه
                            </span>
                        </div>
                        <span id="activeClassNameBadge" class="badge badge-gold" style="font-size:0.85rem;">عرض جميع الفصول</span>
                    </div>

                    <!-- Container for dynamic student cards of the chosen class -->
                    <div id="classStudentsContainer" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap:0.75rem; max-height:280px; overflow-y:auto; padding:0.35rem 0.15rem;">
                        <!-- Filled dynamically by JavaScript -->
                    </div>
                </div>

                <!-- Section 1: Readings Distribution (توزيع القراءات) -->
                <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem; margin-bottom:1.5rem;">
                    <h4 style="color:var(--royal-blue); font-weight:800; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                        📖 توزيع القراءات الكنسية
                    </h4>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
                        <!-- 1. Matins Gospel -->
                        <div class="form-group">
                            <label class="form-label" for="role_matins_gospel" style="font-weight:700;">
                                🌅 إنجيل باكر
                            </label>
                            <select id="role_matins_gospel" name="role_matins_gospel" class="form-control reading-select">
                                <option value="">-- اختر شماس لإنجيل باكر --</option>
                                <?php foreach ($students as $stu) { ?>
                                    <option value="<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                        <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="text" name="role_notes[role_matins_gospel]" class="form-control" style="margin-top:0.4rem; font-size:0.83rem; padding:0.35rem 0.6rem;" placeholder="📝 ملاحظة خاصة بالشماس (اختياري)...">
                        </div>

                        <!-- 2. Paul -->
                        <div class="form-group">
                            <label class="form-label" for="role_paul" style="font-weight:700;">
                                📜 بولس (رسائل بولس الرسول)
                            </label>
                            <select id="role_paul" name="role_paul" class="form-control reading-select">
                                <option value="">-- اختر شماس للبولس --</option>
                                <?php foreach ($students as $stu) { ?>
                                    <option value="<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                        <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="text" name="role_notes[role_paul]" class="form-control" style="margin-top:0.4rem; font-size:0.83rem; padding:0.35rem 0.6rem;" placeholder="📝 ملاحظة خاصة بالشماس (اختياري)...">
                        </div>

                        <!-- 3. Catholic -->
                        <div class="form-group">
                            <label class="form-label" for="role_catholic" style="font-weight:700;">
                                📜 كاثوليكون (الرسائل الجامعة)
                            </label>
                            <select id="role_catholic" name="role_catholic" class="form-control reading-select">
                                <option value="">-- اختر شماس للكاثوليكون --</option>
                                <?php foreach ($students as $stu) { ?>
                                    <option value="<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                        <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="text" name="role_notes[role_catholic]" class="form-control" style="margin-top:0.4rem; font-size:0.83rem; padding:0.35rem 0.6rem;" placeholder="📝 ملاحظة خاصة بالشماس (اختياري)...">
                        </div>

                        <!-- 4. Praxis -->
                        <div class="form-group">
                            <label class="form-label" for="role_praxis" style="font-weight:700;">
                                📜 ابركسيس (أعمال الرسل)
                            </label>
                            <select id="role_praxis" name="role_praxis" class="form-control reading-select">
                                <option value="">-- اختر شماس للابركسيس --</option>
                                <?php foreach ($students as $stu) { ?>
                                    <option value="<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                        <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="text" name="role_notes[role_praxis]" class="form-control" style="margin-top:0.4rem; font-size:0.83rem; padding:0.35rem 0.6rem;" placeholder="📝 ملاحظة خاصة بالشماس (اختياري)...">
                        </div>

                        <!-- 5. Liturgy Gospel -->
                        <div class="form-group">
                            <label class="form-label" for="role_liturgy_gospel" style="font-weight:700;">
                                ☦️ إنجيل القداس
                            </label>
                            <select id="role_liturgy_gospel" name="role_liturgy_gospel" class="form-control reading-select">
                                <option value="">-- اختر شماس لإنجيل القداس --</option>
                                <?php foreach ($students as $stu) { ?>
                                    <option value="<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                        <?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="text" name="role_notes[role_liturgy_gospel]" class="form-control" style="margin-top:0.4rem; font-size:0.83rem; padding:0.35rem 0.6rem;" placeholder="📝 ملاحظة خاصة بالشماس (اختياري)...">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Ritual Service Roles (خدمة مذبح وخدمة باكر) -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
                    <!-- Altar Service -->
                    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <h4 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.4rem;">
                                🕯️ خدمة مذبح (داخل الهيكل)
                            </h4>
                            <span id="altarCountBadge" class="badge badge-gold">0 شماس</span>
                        </div>
                        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.75rem;">
                            حدد الشمامسة المترتبين لخدمة المذبح المقدس (يمكن اختيار أكثر من شماس).
                        </p>
                        <div id="altarGrid" style="max-height:220px; overflow-y:auto; background:var(--bg-card); padding:0.6rem; border-radius:var(--radius-sm); display:grid; grid-template-columns: 1fr; gap:0.5rem; border:1.5px solid var(--border-color);">
                            <?php foreach ($students as $stu) { ?>
                                <label class="deacon-select-card" id="altar_card_<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                    <input type="checkbox" name="role_altar[]" value="<?= $stu['id'] ?>" onchange="updateRoleCount('altar', this, <?= $stu['id'] ?>)">
                                    <div style="flex:1; overflow:hidden;">
                                        <strong style="color:var(--text-primary); font-size:0.9rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= sanitize($stu['full_name']) ?>
                                        </strong>
                                        <span style="font-size:0.76rem; color:var(--text-muted); display:block;">
                                            <?= sanitize($stu['class_name'] ?: 'بدون فصل') ?> • <?= sanitize($stu['deacon_rank'] ?? 'شماس') ?>
                                        </span>
                                    </div>
                                </label>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Matins Service -->
                    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <h4 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.4rem;">
                                ⛪ خدمة باكر (رفع بخور باكر)
                            </h4>
                            <span id="matinsCountBadge" class="badge badge-gold">0 شماس</span>
                        </div>
                        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.75rem;">
                            حدد الشمامسة المكلفين بخدمة تسبحة ورفع بخور باكر (يمكن اختيار أكثر من شماس).
                        </p>
                        <div id="matinsGrid" style="max-height:220px; overflow-y:auto; background:var(--bg-card); padding:0.6rem; border-radius:var(--radius-sm); display:grid; grid-template-columns: 1fr; gap:0.5rem; border:1.5px solid var(--border-color);">
                            <?php foreach ($students as $stu) { ?>
                                <label class="deacon-select-card" id="matins_card_<?= $stu['id'] ?>" data-class-id="<?= $stu['class_id'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($stu['full_name'] ?? '')) ?>">
                                    <input type="checkbox" name="role_matins[]" value="<?= $stu['id'] ?>" onchange="updateRoleCount('matins', this, <?= $stu['id'] ?>)">
                                    <div style="flex:1; overflow:hidden;">
                                        <strong style="color:var(--text-primary); font-size:0.9rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= sanitize($stu['full_name']) ?>
                                        </strong>
                                        <span style="font-size:0.76rem; color:var(--text-muted); display:block;">
                                            <?= sanitize($stu['class_name'] ?: 'بدون فصل') ?> • <?= sanitize($stu['deacon_rank'] ?? 'شماس') ?>
                                        </span>
                                    </div>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Custom Additional Assignments (تكليفات إضافية مخصصة وملاحظات خاصة) -->
                <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem; margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.85rem;">
                        <div>
                            <h4 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.4rem;">
                                ➕ تكليفات إضافية مخصصة وملاحظات لكل مخدوم (ألحان، دورة، شموع، قراءات...)
                            </h4>
                            <span style="font-size:0.83rem; color:var(--text-muted); display:block; margin-top:0.25rem;">
                                يمكنك تزويد أي تكليف إضافي وتحديد ملاحظات وتوجيهات خاصة بكل مخدوم تصله شخصياً في حسابه
                            </span>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addCustomAssignmentRow()" style="font-weight:800; color:var(--royal-blue); border:1.5px solid var(--royal-blue); padding:0.45rem 0.85rem;">
                            ➕ إضافة تكليف مخصص
                        </button>
                    </div>

                    <div id="customAssignmentsContainer" style="display:flex; flex-direction:column; gap:0.75rem;">
                        <!-- Dynamic custom assignments populated here -->
                    </div>
                    
                    <div id="noCustomAssignmentsNotice" style="text-align:center; padding:1.25rem; background:var(--bg-card); border-radius:var(--radius-sm); border:1px dashed var(--border-color); color:var(--text-muted); font-size:0.88rem;">
                        <span>لا توجد تكليفات إضافية مضافة بعد. اضغط على <strong>"➕ إضافة تكليف مخصص"</strong> لزيادة أي تكليف شماس في هذا القداس وكتابة ملاحظات خاصة به.</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold" style="width:100%; font-weight:800; padding:0.9rem; font-size:1.05rem;">
                    📢 نشر جدول خدمة القداس وإرسال التنبيهات والتوجيهات
                </button>
            </form>
        </div>
        <?php } else { ?>
        <!-- Servant Banner & Dedicated Class Assignments Section -->
        <div style="background:var(--royal-blue-glow); border:1px solid var(--royal-blue); border-radius:var(--radius-sm); padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <span style="font-size:1.75rem;">ℹ️</span>
            <div>
                <strong style="color:var(--royal-blue); display:block; font-size:1rem;">تنبيه صلاحيات:</strong>
                <span style="font-size:0.9rem; color:var(--text-secondary);">وضع وإعداد جداول خدمة القداسات وتوزيع القراءات والأدوار مخصص لمدير النظام (الآدمن) فقط. كخادم مسؤول، يظهر لك أدناه جدول مخصص بأولاد فصلك المكلفين بالخدمة لمتابعتهم والتواصل معهم.</span>
            </div>
        </div>

        <!-- Servant's Class Students Assignments Section -->
        <div class="glass-card" style="margin-bottom:2rem; border-top:4px solid var(--gold);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.25rem;">
                <div>
                    <h3 style="color:var(--royal-blue); font-weight:800; margin:0; display:flex; align-items:center; gap:0.5rem;">
                        <span>⭐</span> مخدومي فصلي المكلفين بخدمة القداسات (<?= count($myClassAssignments) ?> تكليف)
                    </h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.25rem;">
                        هؤلاء هم الشمامسة التابعين لفصولك الذين تم اختيارهم من قِبل إدارة المدرسة للخدمة في القداسات الإلهية
                    </p>
                </div>
                <?php if (! empty($myClassNames)) { ?>
                    <span class="badge badge-gold" style="font-size:0.88rem; padding:0.4rem 0.8rem;">فصولي: <?= htmlspecialchars(implode('، ', $myClassNames)) ?></span>
                <?php } ?>
            </div>

            <?php if (empty($myClassAssignments)) { ?>
                <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                    <div style="font-size:2.5rem; margin-bottom:0.5rem;">⛪</div>
                    <p style="margin:0; font-weight:700;">لا يوجد حالياً أي شمامسة مكلفين بخدمة قداس من فصولك في الجداول الحالية.</p>
                </div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size:0.92rem;">
                        <thead>
                            <tr>
                                <th>الشماس المكلف</th>
                                <th>الفصل</th>
                                <th>القداس والمناسبة</th>
                                <th>تاريخ القداس</th>
                                <th>الدور / التكليف</th>
                                <th>توجيهات وملاحظات الإدارة للشماس 📝</th>
                                <th>حالة تأكيد الحضور / الاعتذار</th>
                                <th>تحديث الحالة (خادم الفصل)</th>
                                <th>التواصل السريع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myClassAssignments as $mca) {
                                $mBadge = 'badge-info';
                                if ($mca['role_name'] === 'خدمة مذبح') {
                                    $mBadge = 'badge-gold';
                                } elseif ($mca['role_name'] === 'خدمة باكر') {
                                    $mBadge = 'badge-primary';
                                } elseif (str_contains($mca['role_name'] ?? '', 'إنجيل')) {
                                    $mBadge = 'badge-success';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($mca['student_name']) ?></strong>
                                        <span class="badge badge-gold" style="font-size:0.75rem; margin-right:0.35rem;">من فصلك ⭐</span>
                                    </td>
                                    <td><span style="font-size:0.85rem; color:var(--text-secondary);"><?= sanitize($mca['class_name'] ?: '-') ?></span></td>
                                    <td><strong style="color:var(--royal-blue);"><?= sanitize($mca['liturgy_title']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= format_arabic_date($mca['service_date']) ?></span></td>
                                    <td>
                                        <span class="badge <?= $mBadge ?>" style="font-weight:800; font-size:0.85rem;">
                                            <?= sanitize($mca['role_name']) ?>
                                        </span>
                                    </td>
                                    <td style="min-width:160px;">
                                        <?php if (! empty($mca['admin_notes'])) { ?>
                                            <div style="background:rgba(217, 119, 6, 0.08); border:1px solid rgba(217, 119, 6, 0.3); border-radius:4px; padding:0.35rem 0.55rem; font-size:0.82rem; color:var(--text-primary);">
                                                📌 <?= sanitize($mca['admin_notes']) ?>
                                            </div>
                                        <?php } else { ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">-</span>
                                        <?php } ?>
                                    </td>
                                    <td style="min-width:180px;">
                                        <?php if (($mca['status'] ?? '') === 'confirmed') { ?>
                                            <span class="badge badge-success" style="font-size:0.85rem; font-weight:700;">حضور مؤكد ✅</span>
                                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                                                <?= sanitize($mca['response_notes'] ?: 'تم تأكيد الحضور') ?>
                                            </div>
                                        <?php } elseif (($mca['status'] ?? '') === 'declined') { ?>
                                            <span class="badge badge-danger" style="font-size:0.85rem; font-weight:700;">معتذر عن الخدمة ⚠️</span>
                                            <div style="font-size:0.8rem; color:#dc2626; font-weight:700; margin-top:0.35rem; background:rgba(220,38,38,0.08); padding:0.3rem 0.5rem; border-radius:4px; border-right:3px solid #dc2626;">
                                                سبب الاعتذار: <?= sanitize($mca['response_notes'] ?: 'لظروف طارئة') ?>
                                            </div>
                                        <?php } elseif (($mca['status'] ?? '') === 'swapped') { ?>
                                            <span class="badge badge-info" style="font-size:0.85rem; font-weight:700;">تم الاستبدال ببديل 🔄</span>
                                        <?php } else { ?>
                                            <span class="badge badge-warning" style="font-size:0.85rem; font-weight:700;">قيد انتظار رد الشماس ⏳</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.35rem; align-items:center; flex-wrap:wrap;">
                                            <?php if (($mca['status'] ?? '') !== 'confirmed') { ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('هل تريد تأكيد حضور الشماس <?= addslashes(sanitize($mca['student_name'])) ?>؟');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="servant_update_status">
                                                    <input type="hidden" name="roster_student_id" value="<?= $mca['roster_student_id'] ?>">
                                                    <input type="hidden" name="new_status" value="confirmed">
                                                    <input type="hidden" name="status_note" value="تم تأكيد الحضور والتواصل من قِبل خادم الفصل">
                                                    <button type="submit" class="btn btn-sm btn-primary" style="font-size:0.75rem; padding:0.25rem 0.5rem;" title="تأكيد حضور الشماس">
                                                        تأكيد ✅
                                                    </button>
                                                </form>
                                            <?php } ?>

                                            <?php if (($mca['status'] ?? '') !== 'declined') { ?>
                                                <button type="button" class="btn btn-sm btn-secondary" style="font-size:0.75rem; padding:0.25rem 0.5rem; color:#dc2626;" onclick="openServantDeclineModal(<?= $mca['roster_student_id'] ?>, '<?= addslashes(sanitize($mca['student_name'])) ?>')">
                                                    تسجيل اعتذار ⚠️
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.4rem; align-items:center;">
                                            <?php
                                                $rawPhone = preg_replace('/[^0-9]/', '', $mca['student_phone'] ?? '');
                                if (! empty($rawPhone)) {
                                    if (! str_starts_with($rawPhone, '20') && strlen($rawPhone) === 11) {
                                        $rawPhone = '2'.$rawPhone;
                                    }
                                    ?>
                                                <a href="https://wa.me/<?= $rawPhone ?>?text=<?= urlencode("سلام ونعمة يا {$mca['student_name']}، نذكرك بخدمتك في قداس ({$mca['liturgy_title']}) بتاريخ {$mca['service_date']} لدور: [{$mca['role_name']}]. برجاء تأكيد الحضور.") ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.8rem; background:#25D366; color:#fff; border:none;" title="تذكير واتساب للشماس">
                                                    📱 شماس
                                                </a>
                                            <?php } ?>
                                            <?php
                                    $parentRaw = preg_replace('/[^0-9]/', '', $mca['father_phone'] ?: ($mca['mother_phone'] ?? ''));
                                if (! empty($parentRaw)) {
                                    if (! str_starts_with($parentRaw, '20') && strlen($parentRaw) === 11) {
                                        $parentRaw = '2'.$parentRaw;
                                    }
                                    ?>
                                                <a href="https://wa.me/<?= $parentRaw ?>?text=<?= urlencode("سلام ونعمة لحضرتك، نود تذكيركم بأن ابنكم الشماس ({$mca['student_name']}) مكلف بخدمة قداس ({$mca['liturgy_title']}) بتاريخ {$mca['service_date']} لدور: [{$mca['role_name']}].") ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.8rem; background:var(--royal-blue); color:#fff; border:none;" title="تذكير واتساب لولي الأمر">
                                                    👨‍👩‍👦 ولي الأمر
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
        <?php } ?>

        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">جداول القداسات وتوزيع الأدوار</h3>
            
            <?php if (empty($rosters)) { ?>
                <p style="color:var(--text-muted); text-align:center; padding:2rem;">لا توجد جداول قداسات مضافة بعد.</p>
            <?php } else { ?>
                <?php foreach ($rosters as $ros) {
                    try {
                        $assignedStmt = $db->prepare('
                            SELECT rs.id as roster_student_id, rs.role_name, rs.admin_notes, rs.status, rs.response_notes, rs.responded_at, 
                                   u.id as student_id, u.full_name, u.phone, u.class_id, c.name_ar as class_name,
                                   sub.full_name as substitute_name
                            FROM liturgy_roster_students rs
                            JOIN users u ON rs.student_id = u.id
                            LEFT JOIN classes c ON u.class_id = c.id
                            LEFT JOIN users sub ON rs.substitute_student_id = sub.id
                            WHERE rs.roster_id = ?
                            ORDER BY 
                                CASE rs.role_name
                                    WHEN "إنجيل باكر" THEN 1
                                    WHEN "بولس" THEN 2
                                    WHEN "كاثوليكون" THEN 3
                                    WHEN "إبركسيس" THEN 4
                                    WHEN "إنجيل القداس" THEN 5
                                    WHEN "خدمة مذبح" THEN 6
                                    WHEN "خدمة باكر" THEN 7
                                    ELSE 8
                                END, u.full_name ASC
                        ');
                        $assignedStmt->execute([$ros['id']]);
                        $assignedStudents = $assignedStmt->fetchAll();
                    } catch (Throwable $e) {
                        $assignedStudents = [];
                    }
                    ?>
                    <div class="glass-card" style="margin-bottom:1.5rem; border:1px solid var(--border-color); padding:1.25rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.85rem;">
                            <div>
                                <h4 style="color:var(--royal-blue); font-size:1.2rem; font-weight:800; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem;">
                                    <span>⛪</span> <?= sanitize($ros['title']) ?>
                                </h4>
                                <div style="font-size:0.85rem; color:var(--text-muted); display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
                                    <span>📅 تاريخ القداس: <strong><?= format_arabic_date($ros['service_date']) ?></strong></span>
                                    <?php if (! empty($ros['notes'])) { ?>
                                        <span>| 📌 <strong>تعليمات عامة:</strong> <?= sanitize($ros['notes']) ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                                <span class="badge badge-gold" style="font-size:0.85rem; padding:0.4rem 0.75rem;"><?= count($assignedStudents) ?> شماس مكلف</span>
                                <?php if ($isAdmin) { ?>
                                    <button type="button" class="btn btn-gold btn-sm" onclick="openAddAssignmentModal(<?= $ros['id'] ?>, '<?= addslashes(sanitize($ros['title'])) ?>')" style="font-weight:800; display:flex; align-items:center; gap:0.35rem; padding:0.4rem 0.85rem;">
                                        ➕ إضافة تكليف لهذا القداس
                                    </button>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="custom-table" style="font-size:0.9rem;">
                                <thead>
                                    <tr>
                                        <th>الشماس المكلف</th>
                                        <th>الدور / التكليف</th>
                                        <th>الفصل</th>
                                        <th>رقم الهاتف</th>
                                        <th>توجيهات وملاحظات خاصة بالمخدوم 📝</th>
                                        <th>حالة التأكيد</th>
                                        <th>رد الشماس / سبب الاعتذار</th>
                                        <th>الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assignedStudents)) { ?>
                                        <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:1.5rem;">لم يتم تعيين شمامسة لهذا القداس بعد. اضغط على "➕ إضافة تكليف لهذا القداس" للبدء.</td></tr>
                                    <?php } else { ?>
                                        <?php foreach ($assignedStudents as $as) {
                                            $roleBadge = 'badge-info';
                                            if ($as['role_name'] === 'خدمة مذبح') {
                                                $roleBadge = 'badge-gold';
                                            } elseif ($as['role_name'] === 'خدمة باكر') {
                                                $roleBadge = 'badge-primary';
                                            } elseif (str_contains($as['role_name'] ?? '', 'إنجيل')) {
                                                $roleBadge = 'badge-success';
                                            }
                                            $isMyStudent = ($isServant && in_array($as['class_id'], $myClassIds));
                                            ?>
                                            <tr style="<?= $isMyStudent ? 'background:rgba(217, 119, 6, 0.05);' : '' ?>">
                                                <td>
                                                    <strong><?= sanitize($as['full_name']) ?></strong>
                                                    <?php if ($isMyStudent) { ?>
                                                        <span class="badge badge-gold" style="font-size:0.75rem; margin-right:0.35rem;">من فصلك ⭐</span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $roleBadge ?>" style="font-size:0.82rem; font-weight:700;">
                                                        <?= sanitize($as['role_name'] ?: 'شماس') ?>
                                                    </span>
                                                </td>
                                                <td><span style="font-size:0.85rem; color:var(--text-secondary);"><?= sanitize($as['class_name'] ?: '-') ?></span></td>
                                                <td><?= sanitize($as['phone']) ?></td>
                                                <td style="min-width:180px;">
                                                    <?php if (! empty($as['admin_notes'])) { ?>
                                                        <div style="background:rgba(217, 119, 6, 0.08); border:1px solid rgba(217, 119, 6, 0.3); border-radius:var(--radius-sm); padding:0.35rem 0.55rem; font-size:0.82rem; color:var(--text-primary); margin-bottom:0.3rem;">
                                                            <strong style="color:var(--gold); display:block; font-size:0.75rem;">توجيهات الإدارة:</strong>
                                                            <?= sanitize($as['admin_notes']) ?>
                                                        </div>
                                                    <?php } else { ?>
                                                        <span style="color:var(--text-muted); font-size:0.8rem; font-style:italic; display:block; margin-bottom:0.25rem;">لا توجد ملاحظات</span>
                                                    <?php } ?>
                                                    <?php if ($isAdmin) { ?>
                                                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:0.15rem 0.45rem; display:inline-flex; align-items:center; gap:0.25rem;" onclick="openEditNotesModal(<?= $as['roster_student_id'] ?>, '<?= addslashes(sanitize($as['full_name'])) ?>', '<?= addslashes(sanitize($as['admin_notes'] ?? '')) ?>')">
                                                            ✏️ <?= empty($as['admin_notes']) ? 'إضافة ملاحظة' : 'تعديل' ?>
                                                        </button>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if (($as['status'] ?? '') === 'confirmed') { ?>
                                                        <span class="badge badge-success">مؤكد ✅</span>
                                                    <?php } elseif (($as['status'] ?? '') === 'declined') { ?>
                                                        <span class="badge badge-danger">اعتذار ⚠️</span>
                                                    <?php } elseif (($as['status'] ?? '') === 'swapped') { ?>
                                                        <span class="badge badge-info">تم الاستبدال بـ: <?= sanitize($as['substitute_name'] ?? 'بديل') ?> 🔄</span>
                                                    <?php } else { ?>
                                                        <span class="badge badge-warning">قيد الانتظار ⏳</span>
                                                    <?php } ?>
                                                </td>
                                                <td><?= sanitize($as['response_notes'] ?? '-') ?></td>
                                                <td>
                                                    <div style="display:flex; gap:0.35rem; align-items:center; flex-wrap:wrap;">
                                                        <?php if ($isAdmin && ($as['status'] ?? '') === 'declined') { ?>
                                                            <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;" onclick="openAssignSubModal(<?= $as['roster_student_id'] ?>, '<?= addslashes(sanitize($as['full_name'])) ?>', '<?= addslashes(sanitize($as['role_name'] ?? '')) ?>')">
                                                                تعيين بديل 🔄
                                                            </button>
                                                        <?php } ?>

                                                        <?php if ($isAdmin) { ?>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف تكليف الشماس <?= addslashes(sanitize($as['full_name'])) ?> (<?= addslashes(sanitize($as['role_name'] ?? '')) ?>) من هذا القداس؟');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="form_action" value="delete_roster_assignment">
                                                                <input type="hidden" name="roster_student_id" value="<?= $as['roster_student_id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-secondary" style="font-size:0.75rem; padding:0.25rem 0.5rem; color:#dc2626;" title="حذف هذا التكليف">
                                                                    🗑️ حذف
                                                                </button>
                                                            </form>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<!-- Assign Substitute Modal -->
<div id="subModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="width:100%; max-width:450px; margin:1rem; background:var(--bg-surface);">
        <h3 style="color:var(--royal-blue); margin-bottom:0.75rem; font-weight:800;">🔄 تعيين شماس بديل</h3>
        <p id="subModalDesc" style="font-size:0.88rem; color:var(--text-muted); margin-bottom:1rem;"></p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="assign_substitute">
            <input type="hidden" id="modalRosterStudentId" name="roster_student_id" value="">

            <div class="form-group">
                <label class="form-label" for="substitute_id">اختر الشماس البديل *</label>
                <select id="substitute_id" name="substitute_id" class="form-control" required>
                    <option value="">اختر من القائمة...</option>
                    <?php foreach ($students as $stu) { ?>
                        <option value="<?= $stu['id'] ?>"><?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn btn-secondary" onclick="closeSubModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary" style="font-weight:800;">حفظ وتعيين البديل</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Assignment to Existing Roster Modal -->
<div id="addAssignmentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="width:100%; max-width:520px; margin:1rem; background:var(--bg-surface); border:1.5px solid var(--gold);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <h3 style="color:var(--royal-blue); margin:0; font-weight:800; display:flex; align-items:center; gap:0.4rem;">
                <span>➕</span> إضافة تكليف شماس للقداس
            </h3>
            <button type="button" onclick="closeAddAssignmentModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">✖</button>
        </div>
        
        <p id="addAssignmentRosterTitle" style="font-size:0.92rem; font-weight:800; color:var(--gold); margin-bottom:1rem;"></p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="add_roster_assignment">
            <input type="hidden" id="addAssignmentRosterId" name="roster_id" value="">

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" for="add_role_name" style="font-weight:700;">اسم الدور أو التكليف *</label>
                <input type="text" id="add_role_name" name="role_name" class="form-control" list="roleSuggestions" placeholder="اكتب أو اختر التكليف (مثال: لحن، مزمور، خدمة مذبح...)" required>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" for="add_student_id" style="font-weight:700;">اختر الشماس المكلف *</label>
                <select id="add_student_id" name="student_id" class="form-control" required>
                    <option value="">-- اختر شماس من القائمة --</option>
                    <?php foreach ($students as $stu) { ?>
                        <option value="<?= $stu['id'] ?>"><?= sanitize($stu['full_name']) ?> (<?= sanitize($stu['class_name'] ?: 'بدون فصل') ?>) - <?= sanitize($stu['deacon_rank'] ?? 'شماس') ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label" for="add_admin_notes" style="font-weight:700;">📝 ملاحظات وتوجيهات خاصة بهذا الشماس (اختياري)</label>
                <textarea id="add_admin_notes" name="admin_notes" class="form-control" rows="3" placeholder="اكتب أي تعليمات وتوجيهات للشماس (أرباع اللحن، موعد الحضور، تنبيهات خاصة)..."></textarea>
                <span style="font-size:0.78rem; color:var(--text-muted); display:block; margin-top:0.25rem;">
                    💡 تظهر هذه الملاحظات في حساب الشماس بصفحة "جدول خدمتي" وسيتم تضمينها في إشعار التكليف.
                </span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAddAssignmentModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold" style="font-weight:800; padding:0.6rem 1.25rem;">
                    ➕ حفظ وتكليف الشماس
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Notes Modal -->
<div id="editNotesModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="width:100%; max-width:480px; margin:1rem; background:var(--bg-surface); border:1.5px solid var(--royal-blue);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
            <h3 style="color:var(--royal-blue); margin:0; font-weight:800; display:flex; align-items:center; gap:0.4rem;">
                <span>📝</span> توجيهات وملاحظات الشماس
            </h3>
            <button type="button" onclick="closeEditNotesModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">✖</button>
        </div>

        <p id="editNotesStudentName" style="font-size:0.92rem; font-weight:800; color:var(--text-primary); margin-bottom:1rem;"></p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="update_student_notes">
            <input type="hidden" id="editNotesRosterStudentId" name="roster_student_id" value="">

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label" for="edit_admin_notes" style="font-weight:700;">الملاحظات والتوجيهات الخاصة بالمخدوم</label>
                <textarea id="edit_admin_notes" name="admin_notes" class="form-control" rows="4" placeholder="اكتب الملاحظات والتوجيهات هنا..."></textarea>
                <span style="font-size:0.78rem; color:var(--text-muted); display:block; margin-top:0.25rem;">
                    💡 تظهر هذه الملاحظات للشماس في جدول خدمته وللخادم المسؤول عن متابعته.
                </span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditNotesModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary" style="font-weight:800;">💾 حفظ الملاحظات</button>
            </div>
        </form>
    </div>
</div>

<!-- Datalist of Role Suggestions -->
<datalist id="roleSuggestions">
    <option value="خدمة مذبح">
    <option value="خدمة باكر">
    <option value="إنجيل باكر">
    <option value="بولس">
    <option value="كاثوليكون">
    <option value="إبركسيس">
    <option value="إنجيل القداس">
    <option value="مزمور القداس">
    <option value="مزمور باكر">
    <option value="لحن إبركسيس">
    <option value="لحن البركة (تين أوؤشت)">
    <option value="لحن بي ماي رومي">
    <option value="لحن أجيوس">
    <option value="دورة البخور">
    <option value="حمل الشموع">
    <option value="مردات الشماس">
    <option value="أبصالية باكر">
    <option value="صلوات الأواشي">
    <option value="حمل البشارة واللفائف">
</datalist>

<script>
const allStudents = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;
const allClasses = <?= json_encode($classes, JSON_UNESCAPED_UNICODE) ?>;

let currentClassFilter = 'all';
let currentNameFilter = '';

function renderClassStudents() {
    const container = document.getElementById('classStudentsContainer');
    const countSpan = document.getElementById('filteredStudentsCount');
    const badge = document.getElementById('activeClassNameBadge');

    if (!container) return;

    let matching = allStudents.filter(stu => {
        const matchClass = (currentClassFilter === 'all' || String(stu.class_id) === String(currentClassFilter));
        const nameLower = (stu.full_name || '').toLowerCase();
        const matchName = (!currentNameFilter || nameLower.includes(currentNameFilter));
        return matchClass && matchName;
    });

    if (countSpan) countSpan.innerText = matching.length;

    if (badge) {
        if (currentClassFilter === 'all') {
            badge.innerText = 'عرض جميع الفصول';
        } else {
            const clsObj = allClasses.find(c => String(c.id) === String(currentClassFilter));
            badge.innerText = clsObj ? `${clsObj.grade_name} ➔ ${clsObj.class_name}` : 'فصل محدد';
        }
    }

    if (matching.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; text-align:center; padding:1.5rem; color:var(--text-muted);">
                <span>🔍 لا يوجد شمامسة مسجلين في هذا الفصل أو مطابقين للبحث.</span>
            </div>
        `;
        return;
    }

    container.innerHTML = matching.map(stu => `
        <div class="class-student-card" id="student_card_${stu.id}" style="background:var(--bg-card); border:1.5px solid var(--border-color); border-radius:var(--radius-sm); padding:0.75rem 0.85rem; display:flex; flex-direction:column; gap:0.45rem; transition:var(--transition);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:0.4rem; overflow:hidden;">
                    <span style="font-size:1.1rem;">👤</span>
                    <strong style="color:var(--text-primary); font-size:0.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(stu.full_name)}">
                        ${escapeHtml(stu.full_name)}
                    </strong>
                </div>
                <span class="badge badge-info" style="font-size:0.74rem;">${escapeHtml(stu.deacon_rank || 'شماس')}</span>
            </div>
            <div style="font-size:0.78rem; color:var(--text-muted); display:flex; justify-content:space-between;">
                <span>🏫 ${escapeHtml(stu.class_name || 'بدون فصل')}</span>
                <span>📞 ${escapeHtml(stu.phone || '-')}</span>
            </div>
            <div style="display:flex; align-items:center; gap:0.3rem; flex-wrap:wrap; margin-top:0.2rem; border-top:1px dashed var(--border-color); padding-top:0.45rem;">
                <span style="font-size:0.72rem; color:var(--text-muted); margin-left:0.2rem;">تكليف سريع:</span>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem;" onclick="quickAssignRole(${stu.id}, 'role_matins_gospel')">إنجيل باكر</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem;" onclick="quickAssignRole(${stu.id}, 'role_paul')">بولس</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem;" onclick="quickAssignRole(${stu.id}, 'role_catholic')">كاثوليكون</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem;" onclick="quickAssignRole(${stu.id}, 'role_praxis')">إبركسيس</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem;" onclick="quickAssignRole(${stu.id}, 'role_liturgy_gospel')">إنجيل القداس</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem; color:var(--gold); border-color:var(--gold);" onclick="quickToggleService('altar', ${stu.id})">+ مذبح</button>
                <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:0.15rem 0.4rem; color:var(--royal-blue); border-color:var(--royal-blue);" onclick="quickToggleService('matins', ${stu.id})">+ باكر</button>
            </div>
        </div>
    `).join('');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function quickAssignRole(studentId, selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;

    let opt = sel.querySelector(`option[value="${studentId}"]`);
    if (!opt) {
        const stu = allStudents.find(s => s.id === studentId);
        if (stu) {
            opt = document.createElement('option');
            opt.value = stu.id;
            opt.textContent = `${stu.full_name} (${stu.class_name || 'بدون فصل'}) - ${stu.deacon_rank || 'شماس'}`;
            sel.appendChild(opt);
        }
    }
    sel.value = studentId;

    sel.style.borderColor = 'var(--gold)';
    sel.style.boxShadow = '0 0 10px var(--gold-glow)';
    setTimeout(() => {
        sel.style.borderColor = '';
        sel.style.boxShadow = '';
    }, 1500);

    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function quickToggleService(type, studentId) {
    const cb = document.querySelector(`#${type}Grid input[value="${studentId}"]`);
    if (cb) {
        cb.checked = !cb.checked;
        updateRoleCount(type, cb, studentId);
        cb.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function filterAllByClass(classId) {
    currentClassFilter = classId;
    applyAllFilters();
}

function filterAllByName(query) {
    currentNameFilter = query.trim().toLowerCase();
    applyAllFilters();
}

function rebuildReadingSelects() {
    const selects = [
        { id: 'role_matins_gospel', placeholder: '-- اختر شماس لإنجيل باكر --' },
        { id: 'role_paul', placeholder: '-- اختر شماس للبولس --' },
        { id: 'role_catholic', placeholder: '-- اختر شماس للكاثوليكون --' },
        { id: 'role_praxis', placeholder: '-- اختر شماس للابركسيس --' },
        { id: 'role_liturgy_gospel', placeholder: '-- اختر شماس لإنجيل القداس --' }
    ];

    selects.forEach(s => {
        const el = document.getElementById(s.id);
        if (!el) return;
        const currentVal = el.value;

        el.innerHTML = `<option value="">${s.placeholder}</option>`;

        allStudents.forEach(stu => {
            const matchClass = (currentClassFilter === 'all' || String(stu.class_id) === String(currentClassFilter));
            const nameLower = (stu.full_name || '').toLowerCase();
            const matchName = (!currentNameFilter || nameLower.includes(currentNameFilter));

            if (matchClass && matchName) {
                const opt = document.createElement('option');
                opt.value = stu.id;
                opt.textContent = `${stu.full_name} (${stu.class_name || 'بدون فصل'}) - ${stu.deacon_rank || 'شماس'}`;
                el.appendChild(opt);
            }
        });

        if (currentVal) {
            el.value = currentVal;
        }
    });
}

function applyAllFilters() {
    renderClassStudents();
    rebuildReadingSelects();

    // Filter altar & matins cards
    const cards = document.querySelectorAll('.deacon-select-card');
    cards.forEach(card => {
        const cardClass = card.getAttribute('data-class-id');
        const cardName = card.getAttribute('data-name') || '';

        const matchClass = (currentClassFilter === 'all' || cardClass === currentClassFilter);
        const matchName = (!currentNameFilter || cardName.includes(currentNameFilter));

        if (matchClass && matchName) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateRoleCount(type, cb, id) {
    const card = document.getElementById(`${type}_card_${id}`);
    if (card) {
        if (cb.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
    const count = document.querySelectorAll(`#${type}Grid input[type="checkbox"]:checked`).length;
    document.getElementById(`${type}CountBadge`).innerText = `${count} شماس`;
}

function openAssignSubModal(rsId, studentName, roleName) {
    document.getElementById('modalRosterStudentId').value = rsId;
    document.getElementById('subModalDesc').innerText = `تعيين بديل للشماس: ${studentName}` + (roleName ? ` (الدور: ${roleName})` : '');
    document.getElementById('subModal').style.display = 'flex';
}

function closeSubModal() {
    document.getElementById('subModal').style.display = 'none';
}

function openServantDeclineModal(rosterStudentId, studentName) {
    const reason = prompt('أدخل سبب اعتذار الشماس (' + studentName + '):', 'ظروف طارئة');
    if (reason !== null && reason.trim() !== '') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="servant_update_status">
            <input type="hidden" name="roster_student_id" value="${rosterStudentId}">
            <input type="hidden" name="new_status" value="declined">
            <input type="hidden" name="status_note" value="${reason.replace(/"/g, '&quot;')}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openAddAssignmentModal(rosterId, rosterTitle) {
    document.getElementById('addAssignmentRosterId').value = rosterId;
    document.getElementById('addAssignmentRosterTitle').innerText = '⛪ القداس: ' + rosterTitle;
    document.getElementById('add_role_name').value = '';
    document.getElementById('add_student_id').value = '';
    document.getElementById('add_admin_notes').value = '';
    document.getElementById('addAssignmentModal').style.display = 'flex';
}

function closeAddAssignmentModal() {
    document.getElementById('addAssignmentModal').style.display = 'none';
}

function openEditNotesModal(rsId, studentName, currentNotes) {
    document.getElementById('editNotesRosterStudentId').value = rsId;
    document.getElementById('editNotesStudentName').innerText = 'الشماس: ' + studentName;
    document.getElementById('edit_admin_notes').value = currentNotes || '';
    document.getElementById('editNotesModal').style.display = 'flex';
}

function closeEditNotesModal() {
    document.getElementById('editNotesModal').style.display = 'none';
}

let customRowCounter = 0;
function addCustomAssignmentRow() {
    const container = document.getElementById('customAssignmentsContainer');
    const notice = document.getElementById('noCustomAssignmentsNotice');
    if (!container) return;
    if (notice) notice.style.display = 'none';

    customRowCounter++;
    const rowId = 'custom_row_' + customRowCounter;

    let studentOptions = '<option value="">-- اختر الشماس المكلف --</option>';
    allStudents.forEach(stu => {
        studentOptions += `<option value="${stu.id}">${escapeHtml(stu.full_name)} (${escapeHtml(stu.class_name || 'بدون فصل')}) - ${escapeHtml(stu.deacon_rank || 'شماس')}</option>`;
    });

    const rowHtml = `
        <div id="${rowId}" class="glass-card" style="background:var(--bg-card); border:1.5px solid var(--royal-blue); border-radius:var(--radius-sm); padding:1rem; position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <strong style="color:var(--royal-blue); font-size:0.95rem; display:flex; align-items:center; gap:0.35rem;">
                    <span>⭐</span> تكليف إضافي #${customRowCounter}
                </strong>
                <button type="button" class="btn btn-sm btn-secondary" style="color:#dc2626; font-size:0.75rem; padding:0.2rem 0.5rem;" onclick="removeCustomAssignmentRow('${rowId}')">
                    ❌ حذف التكليف
                </button>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap:0.85rem; margin-bottom:0.75rem;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem;">الدور / التكليف *</label>
                    <input type="text" name="custom_roles[]" class="form-control" list="roleSuggestions" placeholder="مثال: لحن إبركسيس، مزمور القداس، دورة..." required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem;">الشماس المكلف *</label>
                    <select name="custom_students[]" class="form-control" required>
                        ${studentOptions}
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-weight:700; font-size:0.85rem;">📝 ملاحظات وتوجيهات خاصة بهذا الشماس (اختياري)</label>
                <input type="text" name="custom_notes[]" class="form-control" placeholder="مثال: مراجعة اللحن مع المعلم، الحضور بالتونة 7:30 صباحاً...">
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', rowHtml);
}

function removeCustomAssignmentRow(rowId) {
    const el = document.getElementById(rowId);
    if (el) el.remove();
    const container = document.getElementById('customAssignmentsContainer');
    const notice = document.getElementById('noCustomAssignmentsNotice');
    if (container && container.children.length === 0 && notice) {
        notice.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderClassStudents();
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

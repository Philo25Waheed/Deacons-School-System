<?php

// API: QR Code Attendance Scanner Processor
require_once __DIR__.'/../includes/cors_header.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['status' => 'error', 'message' => 'طريقة الطلب غير مسموح بها'], 405);
}

if (! isset($_SESSION['user']) || ! in_array($_SESSION['user']['role'], ['admin', 'servant'])) {
    send_json(['status' => 'error', 'message' => 'غير مصرح لك بتسجيل الحضور'], 403);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$token = trim($data['qr_token'] ?? '');
$servantId = $_SESSION['user']['id'];

$csrfToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_XSRF_TOKEN'] ?? null));
if (! verify_csrf_token($csrfToken)) {
    send_json(['status' => 'error', 'message' => 'رمز CSRF غير صالح'], 403);
}

if (empty($token)) {
    send_json(['status' => 'error', 'message' => 'كود QR غير مكتمل'], 400);
}

$db = getDB();

try {
    // Find student by QR token or phone/email
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.role, u.profile_pic, u.church_name, u.class_id,
               s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.qr_code_token = ? AND u.role = 'student' AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $student = $stmt->fetch();

    if (! $student) {
        send_json(['status' => 'error', 'message' => 'رمز الشماس غير صحيح أو غير مفعل'], 444);
    }

    // Permission check: Servant can only take attendance for students in their assigned classes
    if (! can_servant_access_student($servantId, (int) $student['id'], $_SESSION['user']['role'])) {
        $className = ($student['grade_name'] ? $student['grade_name'].' - ' : '').($student['class_name'] ?? 'غير محدد');
        send_json([
            'status' => 'error',
            'message' => 'عذراً، هذا الشماس ('.$className.') ليس ضمن الفصول المسندة لخدمتك.',
        ], 403);
    }

    $today = date('Y-m-d');

    $attendedLesson = isset($data['attended_lesson']) ? (bool) $data['attended_lesson'] : true;
    $attendedPamphlet = isset($data['attended_pamphlet']) ? (bool) $data['attended_pamphlet'] : false;
    $attendedLiturgy = isset($data['attended_liturgy']) ? (bool) $data['attended_liturgy'] : false;
    $pointsToAward = ($attendedLesson ? 1 : 0) + ($attendedPamphlet ? 1 : 0) + ($attendedLiturgy ? 1 : 0);

    $db->beginTransaction();

    // Check for duplicate attendance today
    $checkStmt = $db->prepare('SELECT id, points_awarded FROM attendance WHERE student_id = ? AND attendance_date = ?');
    $checkStmt->execute([$student['id'], $today]);
    $existingAtt = $checkStmt->fetch();

    if ($existingAtt) {
        $db->rollBack();
        send_json([
            'status' => 'warning',
            'message' => 'تم تسجيل حضور الشماس اليوم مسبقاً (رصيد مسجل: '.($existingAtt['points_awarded'] ?? 0).' طايو)',
            'student' => $student,
            'scanned_at' => date('H:i:s'),
            'points_awarded' => (int) ($existingAtt['points_awarded'] ?? 0),
        ]);
    }

    // Insert attendance with detailed components
    $insertStmt = $db->prepare("
        INSERT INTO attendance (student_id, servant_id, attendance_date, status, attended_lesson, attended_pamphlet, attended_liturgy, points_awarded, scanned_at)
        VALUES (?, ?, ?, 'present', ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $insertStmt->execute([
        $student['id'],
        $servantId,
        $today,
        $attendedLesson ? 1 : 0,
        $attendedPamphlet ? 1 : 0,
        $attendedLiturgy ? 1 : 0,
        $pointsToAward,
    ]);

    // Award Tayo points
    if ($pointsToAward > 0) {
        $reasons = [];
        if ($attendedLesson) {
            $reasons[] = 'حصة (+1)';
        }
        if ($attendedPamphlet) {
            $reasons[] = 'بامفلت (+1)';
        }
        if ($attendedLiturgy) {
            $reasons[] = 'قداس (+1)';
        }
        $reasonStr = 'تسجيل حضور بالماسح: '.implode('، ', $reasons);

        $pointStmt = $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, 'positive', ?)");
        $pointStmt->execute([$student['id'], $servantId, $pointsToAward, $reasonStr]);
    }

    // Send notification to student & linked parents
    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تسجيل الحضور والنقاط', ?)");
    $notifMsg = 'تم تسجيل حضورك اليوم بنجاح في '.date('H:i');
    if ($pointsToAward > 0) {
        $notifMsg .= " وحصلت على +{$pointsToAward} طايو 🎉";
    }
    $notifStmt->execute([$student['id'], $notifMsg]);

    // Parent notification
    $parentStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
    $parentStmt->execute([$student['id']]);
    $parents = $parentStmt->fetchAll();
    foreach ($parents as $p) {
        $pMsg = "تم تسجيل حضور ابنكم {$student['full_name']} بنجاح في المدرسة اليوم";
        if ($pointsToAward > 0) {
            $pMsg .= " وتم إضافة {$pointsToAward} طايو إلى رصيده.";
        }
        $notifStmt->execute([$p['parent_id'], 'تسجيل حضور الشماس', $pMsg]);
    }

    $db->commit();

    if (function_exists('log_action')) {
        log_action($servantId, 'ATTENDANCE_SCANNED', "Recorded attendance for student ID {$student['id']} with {$pointsToAward} tayo points");
    }

    $detailsSummary = [];
    if ($attendedLesson) {
        $detailsSummary[] = 'حصة ✔';
    }
    if ($attendedPamphlet) {
        $detailsSummary[] = 'بامفلت ✔';
    }
    if ($attendedLiturgy) {
        $detailsSummary[] = 'قداس ✔';
    }
    $summaryText = ! empty($detailsSummary) ? ' ('.implode(' - ', $detailsSummary).')' : '';

    send_json([
        'status' => 'success',
        'message' => "تم تسجيل الحضور بنجاح ومنح +{$pointsToAward} طايو{$summaryText}!",
        'student' => $student,
        'scanned_at' => date('H:i:s'),
        'points_awarded' => $pointsToAward,
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Attendance scan error: '.$e->getMessage());

    if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate')) {
        send_json([
            'status' => 'warning',
            'message' => 'تم تسجيل حضور الشماس اليوم مسبقاً',
            'student' => $student ?? null,
        ]);
    }

    send_json(['status' => 'error', 'message' => 'حدث خطأ أثناء تسجيل الحضور. يرجى المحاولة مرة أخرى.'], 500);
}

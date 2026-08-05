<?php

// API: QR Code Attendance Scanner Processor
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

$data = json_decode(file_get_contents('php://input'), true);
$token = trim($data['qr_token'] ?? '');
$servantId = $_SESSION['user']['id'];

if (empty($token)) {
    send_json(['status' => 'error', 'message' => 'كود QR غير مكتمل'], 400);
}

try {
    $db = getDB();

    // Find student by QR token or phone/email
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.role, u.profile_pic, u.church_name,
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

    $today = date('Y-m-d');

    // Check for duplicate attendance today
    $checkStmt = $db->prepare('SELECT id FROM attendance WHERE student_id = ? AND attendance_date = ?');
    $checkStmt->execute([$student['id'], $today]);
    if ($checkStmt->fetch()) {
        send_json([
            'status' => 'warning',
            'message' => 'تم تسجيل حضور الشماس اليوم بالفعل!',
            'student' => $student,
            'scanned_at' => date('H:i:s'),
        ]);
    }

    // Insert attendance
    $insertStmt = $db->prepare("
        INSERT INTO attendance (student_id, servant_id, attendance_date, status, scanned_at)
        VALUES (?, ?, ?, 'present', NOW())
    ");
    $insertStmt->execute([$student['id'], $servantId]);

    // Send notification to student & linked parents
    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تسجيل الحضور', ?)");
    $notifStmt->execute([$student['id'], 'تم تسجيل حضورك بنجاح في '.date('H:i')]);

    // Parent notification
    $parentStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
    $parentStmt->execute([$student['id']]);
    $parents = $parentStmt->fetchAll();
    foreach ($parents as $p) {
        $notifStmt->execute([$p['parent_id'], 'تسجيل حضور الشماس', "تم تسجيل حضور ابنائكم {$student['full_name']} بنجاح في المدرسة اليوم."]);
    }

    log_action($servantId, 'ATTENDANCE_SCANNED', "Recorded attendance for student ID {$student['id']}");

    send_json([
        'status' => 'success',
        'message' => 'تم تسجيل الحضور بنجاح!',
        'student' => $student,
        'scanned_at' => date('H:i:s'),
    ]);

} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => 'حدث خطأ في النظام: '.$e->getMessage()], 500);
}

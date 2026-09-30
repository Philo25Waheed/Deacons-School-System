<?php

// API: Points system processor (Add / Subtract points with reason)
require_once __DIR__.'/../includes/cors_header.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['status' => 'error', 'message' => 'طلب غير مسموح'], 405);
}

if (! isset($_SESSION['user']) || ! in_array($_SESSION['user']['role'], ['admin', 'servant'])) {
    send_json(['status' => 'error', 'message' => 'غير مصرح لك بإدارة الطايو'], 403);
}

$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);
$type = sanitize($_POST['type'] ?? 'positive');
$reason = sanitize($_POST['reason'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';

if (! verify_csrf_token($csrfToken)) {
    send_json(['status' => 'error', 'message' => 'رمز CSRF غير صالح'], 403);
}

if (! $studentId || ! $points || empty($reason)) {
    send_json(['status' => 'error', 'message' => 'يرجى ملء جميع الحقول المطلوبة (الشماس، الطايو، والسبب)'], 400);
}

if (! can_servant_access_student($_SESSION['user']['id'], $studentId, $_SESSION['user']['role'])) {
    send_json(['status' => 'error', 'message' => 'عذراً، لا تملك صلاحية إدارة طايو هذا الشماس لأنه ليس ضمن الفصول المسندة لخدمتك.'], 403);
}

try {
    $db = getDB();
    $servantId = $_SESSION['user']['id'];

    $stmt = $db->prepare('INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$studentId, $servantId, $points, $type, $reason]);

    // Send Notification to Student
    $typeLabel = ($type === 'positive') ? 'إضافة طايو تشجيعي' : 'خصم طايو';
    $sign = ($type === 'positive') ? '+' : '-';
    $notifMsg = "تم {$typeLabel} ({$sign}{$points} طايو) - السبب: {$reason}";

    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تحديث رصيد الطايو', ?)");
    $notifStmt->execute([$studentId, $notifMsg]);

    if (function_exists('log_action')) {
        log_action($servantId, 'POINTS_UPDATED', "{$typeLabel} ({$points}) for student ID {$studentId}");
    }

    // Fetch new total points
    $totalStmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) as total_points
        FROM points WHERE student_id = ?
    ");
    $totalStmt->execute([$studentId]);
    $total = $totalStmt->fetchColumn();

    send_json([
        'status' => 'success',
        'message' => 'تم حفظ الطايو بنجاح!',
        'new_total' => $total,
    ]);

} catch (Exception $e) {
    error_log('Points management error: '.$e->getMessage());
    send_json(['status' => 'error', 'message' => 'حدث خطأ أثناء معالجة العملية. يرجى المحاولة مرة أخرى.'], 500);
}

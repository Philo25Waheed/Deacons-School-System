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
    send_json(['status' => 'error', 'message' => 'غير مصرح لك بإضافة النقاط'], 403);
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
    send_json(['status' => 'error', 'message' => 'يرجى ملء جميع الحقول المطلوبة (الطالب، النقاط، والسبب)'], 400);
}

try {
    $db = getDB();
    $servantId = $_SESSION['user']['id'];

    $stmt = $db->prepare('INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$studentId, $servantId, $points, $type, $reason]);

    // Send Notification to Student
    $typeLabel = ($type === 'positive') ? 'إضافة نقاط تشجيعية' : 'خصم نقاط';
    $sign = ($type === 'positive') ? '+' : '-';
    $notifMsg = "تم {$typeLabel} ({$sign}{$points} نقطة) - السبب: {$reason}";

    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'تحديث النقاط', ?)");
    $notifStmt->execute([$studentId, $notifMsg]);

    log_action($servantId, 'POINTS_UPDATED', "{$typeLabel} ({$points}) for student ID {$studentId}");

    // Fetch new total points
    $totalStmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'positive' THEN points ELSE -points END), 0) as total_points
        FROM points WHERE student_id = ?
    ");
    $totalStmt->execute([$studentId]);
    $total = $totalStmt->fetchColumn();

    send_json([
        'status' => 'success',
        'message' => 'تم حفظ النقاط بنجاح!',
        'new_total' => $total,
    ]);

} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => 'خطأ أثناء تنفيذ العملية: '.$e->getMessage()], 500);
}

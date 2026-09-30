<?php

// API: Notifications endpoint
require_once __DIR__.'/../includes/cors_header.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (! isset($_SESSION['user']['id'])) {
    send_json(['status' => 'error', 'message' => 'غير مسجل الدخول'], 401);
}

$userId = $_SESSION['user']['id'];
$action = sanitize($_GET['action'] ?? 'list');

try {
    $db = getDB();
    if (function_exists('trigger_daily_birthday_notifications')) {
        trigger_daily_birthday_notifications($db);
    }

    if ($action === 'mark_read') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            send_json(['status' => 'error', 'message' => 'طريقة الطلب غير صحيحة، يتطلب POST'], 405);
        }
        $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (! verify_csrf_token($csrfToken)) {
            send_json(['status' => 'error', 'message' => 'رمز CSRF غير صالح'], 403);
        }
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
        send_json(['status' => 'success', 'message' => 'تم تحديث الإشعارات']);
    }

    $stmt = $db->prepare('SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20');
    $stmt->execute([$userId]);
    $list = $stmt->fetchAll();

    $unreadCount = 0;
    foreach ($list as $item) {
        if (! $item['is_read']) {
            $unreadCount++;
        }
    }

    send_json([
        'status' => 'success',
        'unread_count' => $unreadCount,
        'notifications' => $list,
    ]);

} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => 'خطأ أثناء استرجاع الإشعارات'], 500);
}

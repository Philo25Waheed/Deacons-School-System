<?php

// API: Get classes by grade_id
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$gradeId = filter_input(INPUT_GET, 'grade_id', FILTER_VALIDATE_INT);
if (! $gradeId) {
    send_json(['status' => 'error', 'message' => 'grade_id غير صحيح'], 400);
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name_ar FROM classes WHERE grade_id = ? ORDER BY id ASC');
    $stmt->execute([$gradeId]);
    $classes = $stmt->fetchAll();

    send_json(['status' => 'success', 'data' => $classes]);
} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => 'حدث خطأ في استرجاع الفصول'], 500);
}

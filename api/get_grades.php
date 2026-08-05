<?php

// API: Get grades by stage_id
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$stageId = filter_input(INPUT_GET, 'stage_id', FILTER_VALIDATE_INT);
if (! $stageId) {
    send_json(['status' => 'error', 'message' => 'stage_id غير صحيح'], 400);
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name_ar FROM grades WHERE stage_id = ? ORDER BY id ASC');
    $stmt->execute([$stageId]);
    $grades = $stmt->fetchAll();

    send_json(['status' => 'success', 'data' => $grades]);
} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => 'حدث خطأ في استرجاع المراحل'], 500);
}

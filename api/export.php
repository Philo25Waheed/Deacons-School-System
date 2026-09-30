<?php

// Export Students / Attendance to CSV (Excel readable)
require_once __DIR__.'/../includes/cors_header.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin', 'servant');

$type = sanitize($_GET['type'] ?? 'students');

$db = getDB();

$userRole = $_SESSION['user']['role'] ?? '';
$accessibleClasses = get_user_accessible_class_ids();

if ($type === 'students') {
    $filename = 'students_export_'.date('Y-m-d').'.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // UTF-8 BOM for Excel UTF-8 Arabic support
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, ['رقم الكود', 'الاسم بالكامل', 'رقم الهاتف', 'البريد الإلكتروني', 'المرحلة', 'الصف', 'الفصل', 'الحالة']);

    $whereClause = "WHERE u.role = 'student'";
    $params = [];
    if ($userRole === 'servant') {
        if (! empty($accessibleClasses)) {
            $inPlaceholders = implode(',', array_fill(0, count($accessibleClasses), '?'));
            $whereClause .= " AND u.class_id IN ({$inPlaceholders})";
            $params = $accessibleClasses;
        } else {
            $whereClause .= ' AND 1 = 0';
        }
    }

    $stmt = $db->prepare("
        SELECT u.qr_code_token, u.full_name, u.phone, u.email,
               s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
               u.status
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        {$whereClause}
        ORDER BY u.id DESC
    ");
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        $statusAr = ($row['status'] === 'active') ? 'مفعل' : (($row['status'] === 'pending') ? 'قيد الانتظار' : 'معطل');
        fputcsv($output, sanitize_csv_row([
            $row['qr_code_token'],
            $row['full_name'],
            $row['phone'],
            $row['email'],
            $row['stage'],
            $row['grade'],
            $row['class'],
            $statusAr,
        ]));
    }
    fclose($output);
    exit;
} elseif ($type === 'attendance') {
    $filename = 'attendance_export_'.date('Y-m-d').'.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, ['اسم الشماس', 'المرحلة', 'الصف', 'الفصل', 'تاريخ الحضور', 'الوقت', 'الخادم المسجل']);

    $whereClause = '';
    $params = [];
    if ($userRole === 'servant') {
        if (! empty($accessibleClasses)) {
            $inPlaceholders = implode(',', array_fill(0, count($accessibleClasses), '?'));
            $whereClause = "WHERE u.class_id IN ({$inPlaceholders})";
            $params = $accessibleClasses;
        } else {
            $whereClause = 'WHERE 1 = 0';
        }
    }

    $stmt = $db->prepare("
        SELECT u.full_name as student_name, s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
               a.attendance_date, a.scanned_at, srv.full_name as servant_name
        FROM attendance a
        JOIN users u ON a.student_id = u.id
        JOIN users srv ON a.servant_id = srv.id
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        {$whereClause}
        ORDER BY a.id DESC
    ");
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, sanitize_csv_row([
            $row['student_name'],
            $row['stage'],
            $row['grade'],
            $row['class'],
            $row['attendance_date'],
            $row['scanned_at'],
            $row['servant_name'],
        ]));
    }
    fclose($output);
    exit;
}

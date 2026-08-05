<?php

// Export Students / Attendance to CSV (Excel readable)
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin', 'servant');

$type = sanitize($_GET['type'] ?? 'students');

$db = getDB();

if ($type === 'students') {
    $filename = 'students_export_'.date('Y-m-d').'.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // UTF-8 BOM for Excel UTF-8 Arabic support
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, ['رقم الكود', 'الاسم بالكامل', 'رقم الهاتف', 'البريد الإلكتروني', 'المرحلة', 'الصف', 'الفصل', 'الكنيسة', 'الحالة']);

    $stmt = $db->query("
        SELECT u.qr_code_token, u.full_name, u.phone, u.email,
               s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
               u.church_name, u.status
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.role = 'student'
        ORDER BY u.id DESC
    ");

    while ($row = $stmt->fetch()) {
        $statusAr = ($row['status'] === 'active') ? 'مفعل' : (($row['status'] === 'pending') ? 'قيد الانتظار' : 'معطل');
        fputcsv($output, [
            $row['qr_code_token'],
            $row['full_name'],
            $row['phone'],
            $row['email'],
            $row['stage'],
            $row['grade'],
            $row['class'],
            $row['church_name'],
            $statusAr,
        ]);
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

    $stmt = $db->query('
        SELECT u.full_name as student_name, s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
               a.attendance_date, a.scanned_at, srv.full_name as servant_name
        FROM attendance a
        JOIN users u ON a.student_id = u.id
        JOIN users srv ON a.servant_id = srv.id
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN classes c ON u.class_id = c.id
        ORDER BY a.id DESC
    ');

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['student_name'],
            $row['stage'],
            $row['grade'],
            $row['class'],
            $row['attendance_date'],
            $row['scanned_at'],
            $row['servant_name'],
        ]);
    }
    fclose($output);
    exit;
}

<?php

// Add 5 Children (3 Siblings in Family A, 2 Children in Family B) and Link Parents
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();
    $pwdHash = '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2'; // Admin@123456

    // 1. Insert Parents (Father & Mother)
    $parents = [
        ['سامح مينا عادل (الأب)', '01355667788', 'sameh@deacons.school', 'parent', 'PRN-000010'],
        ['مريم عاطف فؤاد (الأم)', '01366778899', 'mary@deacons.school', 'parent', 'PRN-000011'],
    ];

    $parentStmt = $db->prepare("
        INSERT INTO users (full_name, phone, email, password, role, status, qr_code_token, church_name)
        VALUES (?, ?, ?, ?, ?, 'active', ?, 'كنيسة مارجرجس')
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)
    ");

    foreach ($parents as $p) {
        $parentStmt->execute([$p[0], $p[1], $p[2], $pwdHash, $p[3], $p[4]]);
    }

    $fatherId = $db->query("SELECT id FROM users WHERE phone = '01355667788'")->fetchColumn();
    $motherId = $db->query("SELECT id FROM users WHERE phone = '01366778899'")->fetchColumn();

    // 2. Insert 5 Children Students (3 siblings for Father, 2 for Mother)
    $children = [
        // Sibling 1 (Family A - Father Sameh)
        ['الشماس أبانوب سامح مينا', '01266778899', 'abanoub@deacons.school', 'student', 2, 4, 7, 'STU-2026-0015'],
        // Sibling 2 (Family A - Father Sameh)
        ['الشماسة يستينا سامح مينا', '01277889900', 'yustina@deacons.school', 'student', 1, 2, 3, 'STU-2026-0016'],
        // Sibling 3 (Family A - Father Sameh)
        ['الشماس مكسيموس سامح مينا', '01288990011', 'maximus@deacons.school', 'student', 4, 10, 13, 'STU-2026-0017'],
        // Child 4 (Family B - Mother Mary)
        ['الشماس توماس هاني سعد', '01299001122', 'thomas@deacons.school', 'student', 3, 7, 10, 'STU-2026-0018'],
        // Child 5 (Family B - Mother Mary)
        ['الشماسة سارة هاني سعد', '01200112233', 'sara@deacons.school', 'student', 2, 6, 9, 'STU-2026-0019'],
    ];

    $childStmt = $db->prepare("
        INSERT INTO users (full_name, phone, email, password, role, status, stage_id, grade_id, class_id, qr_code_token, church_name)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, 'كنيسة مارجرجس')
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)
    ");

    $childIds = [];
    foreach ($children as $ch) {
        $childStmt->execute([$ch[0], $ch[1], $ch[2], $pwdHash, $ch[3], $ch[4], $ch[5], $ch[6], $ch[7]]);
        $childIds[] = $db->lastInsertId() ?: $db->query("SELECT id FROM users WHERE phone = '{$ch[1]}'")->fetchColumn();
    }

    // 3. Link Parents to Children in parent_student table
    $mapStmt = $db->prepare('INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (?, ?, ?)');

    // Father linked to 3 Sibling Children
    $mapStmt->execute([$fatherId, $childIds[0], 'والد (أب)']);
    $mapStmt->execute([$fatherId, $childIds[1], 'والد (أب)']);
    $mapStmt->execute([$fatherId, $childIds[2], 'والد (أب)']);

    // Mother linked to 2 Children
    $mapStmt->execute([$motherId, $childIds[3], 'والدة (أم)']);
    $mapStmt->execute([$motherId, $childIds[4], 'والدة (أم)']);

    // 4. Seed Attendance, Points, and Evaluations for the new 5 children
    $attStmt = $db->prepare("INSERT IGNORE INTO attendance (student_id, servant_id, attendance_date, status, notes) VALUES (?, 2, ?, 'present', 'حضور ممتاز بالقداس')");
    $ptsStmt = $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, 2, ?, 'positive', ?)");

    $dates = [date('Y-m-d'), date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-14 days'))];

    foreach ($childIds as $cId) {
        foreach ($dates as $d) {
            $attStmt->execute([$cId, $d]);
        }
        $ptsStmt->execute([$cId, 15, 'حفظ ألحان القداس الإلهي والالتزام بالمواعيد']);
    }

    echo "5 New Children (including 3 siblings) & 2 Parents added & mapped successfully!\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

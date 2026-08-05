<?php

// Comprehensive Seed Script for All Test Data
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();
    $pwdHash = '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2'; // Admin@123456

    // 1. Seed Additional Users
    $users = [
        ['الخادم بيشوي مجدي', '01122334455', 'bishoy@deacons.school', 'servant', 2, 3, 5, 'SRV-000005'],
        ['الشماس كيرلس بيشوي', '01233445566', 'kyrollos@deacons.school', 'student', 2, 3, 5, 'STU-2026-0006'],
        ['الشماس مارك إبراهيم', '01244556677', 'mark@deacons.school', 'student', 3, 7, 10, 'STU-2026-0007'],
        ['الشماس دانيال سامح', '01255667788', 'daniel@deacons.school', 'student', 4, 10, 13, 'STU-2026-0008'],
        ['ولي الأمر إبراهيم جرجس', '01344556677', 'ibrahim@deacons.school', 'parent', null, null, null, 'PRN-000009'],
    ];

    $userStmt = $db->prepare("
        INSERT INTO users (full_name, phone, email, password, role, status, stage_id, grade_id, class_id, qr_code_token, church_name)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, 'كنيسة مارجرجس')
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)
    ");

    foreach ($users as $u) {
        $userStmt->execute([$u[0], $u[1], $u[2], $pwdHash, $u[3], $u[4], $u[5], $u[6], $u[7]]);
    }

    // Map parent to student Mark
    $db->exec("INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (5, 7, 'والد');");
    $db->exec("INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (4, 6, 'عم/ولي أمر');");

    // 2. Seed Attendance Records
    $attStmt = $db->prepare("INSERT IGNORE INTO attendance (student_id, servant_id, attendance_date, status, notes) VALUES (?, ?, ?, 'present', ?)");
    $dates = [date('Y-m-d'), date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('-21 days'))];

    foreach ([3, 6, 7, 8] as $sId) {
        foreach ($dates as $d) {
            $attStmt->execute([$sId, 2, $d, 'حضور بالقداس والمدارس']);
        }
    }

    // 3. Seed Points & Achievements
    $ptsStmt = $db->prepare('INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, ?, ?)');
    $ptsData = [
        [3, 2, 15, 'positive', 'تفوق في حفظ ألحان أسبوع الآلام'],
        [6, 2, 20, 'positive', 'المواظبة على صلاة التسبحة بمبكرة الأحد'],
        [7, 2, 10, 'positive', 'مساعدة الخدام في ترتيب الخورس'],
        [8, 2, 25, 'positive', 'الدرجة النهائية في اختبار الطقس'],
        [6, 2, 5, 'negative', 'تأخير 15 دقيقة عن بدء القداس'],
    ];
    foreach ($ptsData as $p) {
        $ptsStmt->execute($p);
    }

    // 4. Seed Evaluations
    $evalStmt = $db->prepare('INSERT INTO evaluations (student_id, servant_id, behavior_score, hymn_memorization, church_attending, notes, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $evalData = [
        [3, 2, 10, 9, 10, 'شماس ممتاز ومواظب جداً', date('Y-m-d')],
        [6, 2, 9, 10, 8, 'حفظ الألحان ممتاز مع التزام رائع', date('Y-m-d', strtotime('-5 days'))],
        [7, 2, 8, 8, 9, 'هدوء والتزام داخل الهيكل', date('Y-m-d', strtotime('-10 days'))],
    ];
    foreach ($evalData as $ev) {
        $evalStmt->execute($ev);
    }

    // 5. Seed Courses
    $crsStmt = $db->prepare('INSERT INTO courses (title, description, stage_id, grade_id, external_link, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $crsData = [
        ['طقس أسبوع الآلام والجمعة العظيمة', 'شرح تفصيلي لطقوس وصلوات أسبوع الآلام وألحانه الحزايني', 2, 3, 'https://youtube.com', 1],
        ['دراسات في سفر أعمال الرسل (الأبركسيس)', 'تفسير وتأملات في سفر أعمال الرسل القديسين', 3, 7, 'https://youtube.com', 1],
        ['طقس القداس الإلهي (رفع بخور باكر وعشية)', 'خطوات رفع بخور عشية وباكر وأسرار القداس', 2, null, 'https://youtube.com', 1],
    ];
    foreach ($crsData as $c) {
        $crsStmt->execute($c);
    }

    // 6. Seed Hymns
    $hymnStmt = $db->prepare('INSERT INTO hymns (title, description, notes, video_link, created_by) VALUES (?, ?, ?, ?, ?)');
    $hymnData = [
        ['لحن أريبسالين (Aripsalin)', 'لحن يقال في الهوس الثالث بتسبحة نصف الليل', 'سبحوا الرب لأنه صالح وهللويا لأن إلى الأبد رحمته...', 'https://youtube.com', 1],
        ['لحن بيك ثورونوس (Pekthronos)', 'لحن سادوم يقال في الجمعة الكبيرة وساعات البصخة', 'كرسيك يا الله إلى دهر الدهور، قضيب الاستقامة هو قضيب ملكك...', 'https://youtube.com', 1],
    ];
    foreach ($hymnData as $h) {
        $hymnStmt->execute($h);
    }

    // 7. Seed Announcements
    $annStmt = $db->prepare('INSERT INTO announcements (title, content, target_type, created_by) VALUES (?, ?, ?, ?)');
    $annData = [
        ['مواعيد اختبارات الألحان النصف سنوية', 'تنويه لجميع الشمامسة: تبدأ اختبارات حفظ الألحان اعتباراً من الأسبوع القادم.', 'students', 1],
        ['اجتماع أولياء الأمور القادم', 'يدعو مجلس مدرسة الشمامسة أولياء الأمور لحضور الاجتماع الدوري لمتابعة الأبناء.', 'parents', 1],
    ];
    foreach ($annData as $a) {
        $annStmt->execute($a);
    }

    // 8. Seed Liturgy Rosters
    $rosStmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, hymn_required, notes, created_by) VALUES (?, ?, ?, ?, ?)');
    $rosStmt->execute(['قداس الأحد القادم - خورس المرحلة الإبتدائية', date('Y-m-d', strtotime('+3 days')), 'لحن أريبسالين + الهيتنيات', 'حضور الخورس بالتونة 6:30 صباحاً', 1]);
    $rosterId = $db->lastInsertId();

    $db->exec("INSERT IGNORE INTO liturgy_roster_students (roster_id, student_id) VALUES ($rosterId, 3), ($rosterId, 6), ($rosterId, 7);");

    // 9. Seed Reward Orders
    $db->exec("INSERT INTO reward_orders (reward_id, student_id, points_spent, status) VALUES (1, 3, 15, 'pending'), (2, 6, 10, 'fulfilled');");

    // 10. Seed Pastoral Visitations
    $visStmt = $db->prepare('INSERT INTO pastoral_visitations (student_id, servant_id, type, notes, visit_date) VALUES (?, ?, ?, ?, ?)');
    $visStmt->execute([6, 2, 'home_visit', 'تم زيارة الشماس بالمنزل للافتقاد وتوصيل كتاب الهدايا والتفوق.', date('Y-m-d', strtotime('-2 days'))]);
    $visStmt->execute([7, 2, 'phone', 'مكالمة هاتفية للاطمئنان عليه بعد العكوك المرضي.', date('Y-m-d', strtotime('-4 days'))]);

    // 11. Seed Exam Results
    $db->exec('INSERT INTO exam_results (exam_id, student_id, score, total_marks) VALUES (1, 3, 4, 4), (1, 6, 4, 4);');

    echo "Rich Test Seed Data Inserted Successfully!\n";

} catch (Exception $e) {
    echo 'Error Seeding: '.$e->getMessage()."\n";
}

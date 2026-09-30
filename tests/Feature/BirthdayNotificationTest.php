<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class BirthdayNotificationTest extends TestCase
{
    public function test_birthday_notifications_for_student_classmates_and_servant()
    {
        require_once __DIR__.'/../../config/database.php';
        require_once __DIR__.'/../../includes/helpers.php';

        $db = getDB();

        // 1. Pick an active student or create a temporary one for testing
        $student = $db->query("SELECT id, full_name, class_id FROM users WHERE role = 'student' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $tempStudent = false;
        if (! $student) {
            $db->prepare("INSERT INTO users (full_name, phone, password, role, status) VALUES ('طالب تجريبي للاختبار', '01000000099', 'password123', 'student', 'active')")->execute();
            $studentId = (int) $db->lastInsertId();
            $student = ['id' => $studentId, 'full_name' => 'طالب تجريبي للاختبار', 'class_id' => 1];
            $tempStudent = true;
        } else {
            $studentId = (int) $student['id'];
            $classId = (int) $student['class_id'];
        }
        $this->assertNotEmpty($student, 'Student should exist in the database');

        // 2. Set student's birthday to today
        $todayMD = date('m-d');
        $testDob = '2015-'.$todayMD;
        $db->prepare('UPDATE users SET dob = ? WHERE id = ?')->execute([$testDob, $studentId]);

        // 3. Clear today's test birthday notifications
        $db->exec("DELETE FROM notifications WHERE title LIKE '%عيد ميلاد%' OR title LIKE '%سنة حلوة%'");

        // 4. Trigger notifications
        trigger_daily_birthday_notifications($db, true);

        // 5. Verify student got birthday wish
        $studentNotifs = $db->query("SELECT COUNT(*) FROM notifications WHERE user_id = $studentId AND title LIKE '%سنة حلوة%'")->fetchColumn();
        $this->assertGreaterThanOrEqual(1, (int) $studentNotifs);

        // 6. Verify idempotency (no duplicates on second run)
        trigger_daily_birthday_notifications($db, true);
        $studentNotifsAfter = $db->query("SELECT COUNT(*) FROM notifications WHERE user_id = $studentId AND title LIKE '%سنة حلوة%'")->fetchColumn();
        $this->assertEquals((int) $studentNotifs, (int) $studentNotifsAfter);

        if ($tempStudent) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
            $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$studentId]);
        }
    }
}

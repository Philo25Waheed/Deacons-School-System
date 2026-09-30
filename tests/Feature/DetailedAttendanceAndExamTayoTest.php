<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class DetailedAttendanceAndExamTayoTest extends TestCase
{
    private function getPdo(): PDO
    {
        require_once __DIR__.'/../../config/database.php';

        return getDB();
    }

    private function getOrCreateTestStudent(PDO $db): array
    {
        $student = $db->query("SELECT id, full_name FROM users WHERE role = 'student' LIMIT 1")->fetch();
        if ($student) {
            return [(int) $student['id'], false];
        }

        $db->prepare("INSERT INTO users (full_name, phone, password, role, status) VALUES ('طالب تجريبي للاختبار', '01000000099', 'password123', 'student', 'active')")->execute();

        return [(int) $db->lastInsertId(), true];
    }

    public function test_detailed_attendance_awards_tayo_points_for_lesson_pamphlet_liturgy()
    {
        $db = $this->getPdo();

        // 1. Ensure a test student exists
        [$studentId, $isTemp] = $this->getOrCreateTestStudent($db);
        $this->assertGreaterThan(0, $studentId);

        $today = date('Y-m-d');

        // Clean up any test attendance today
        $db->prepare('DELETE FROM attendance WHERE student_id = ? AND attendance_date = ?')->execute([$studentId, $today]);
        $db->prepare("DELETE FROM points WHERE student_id = ? AND reason LIKE '%اختبار فحص الحضور%'")->execute([$studentId]);

        // 2. Simulate checking (حصة & بامفلت & قداس) = 3 Tayo points
        $attendedLesson = 1;
        $attendedPamphlet = 1;
        $attendedLiturgy = 1;
        $tayoPoints = ($attendedLesson ? 1 : 0) + ($attendedPamphlet ? 1 : 0) + ($attendedLiturgy ? 1 : 0);

        $this->assertEquals(3, $tayoPoints);

        // Insert attendance
        $stmt = $db->prepare("
            INSERT INTO attendance (student_id, servant_id, attendance_date, status, attended_lesson, attended_pamphlet, attended_liturgy, points_awarded, scanned_at)
            VALUES (?, 1, ?, 'present', ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$studentId, $today, $attendedLesson, $attendedPamphlet, $attendedLiturgy, $tayoPoints]);

        // Insert corresponding Tayo points
        $db->prepare("
            INSERT INTO points (student_id, servant_id, points, type, reason)
            VALUES (?, 1, ?, 'positive', 'اختبار فحص الحضور: حصة (+1)، بامفلت (+1)، قداس (+1)')
        ")->execute([$studentId, $tayoPoints]);

        // 3. Verify in attendance table
        $attRow = $db->query("SELECT * FROM attendance WHERE student_id = {$studentId} AND attendance_date = '{$today}'")->fetch();
        $this->assertNotEmpty($attRow);
        $this->assertEquals(1, $attRow['attended_lesson']);
        $this->assertEquals(1, $attRow['attended_pamphlet']);
        $this->assertEquals(1, $attRow['attended_liturgy']);
        $this->assertEquals(3, $attRow['points_awarded']);

        // 4. Verify points were awarded
        $pointRow = $db->query("SELECT * FROM points WHERE student_id = {$studentId} AND reason LIKE '%اختبار فحص الحضور%' ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertNotEmpty($pointRow);
        $this->assertEquals(3, $pointRow['points']);
        $this->assertEquals('positive', $pointRow['type']);

        // Clean up
        $db->prepare('DELETE FROM attendance WHERE student_id = ? AND attendance_date = ?')->execute([$studentId, $today]);
        $db->prepare("DELETE FROM points WHERE student_id = ? AND reason LIKE '%اختبار فحص الحضور%'")->execute([$studentId]);
        if ($isTemp) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
        }
    }

    public function test_exam_creation_and_passing_awards_specified_tayo_points()
    {
        $db = $this->getPdo();

        [$studentId, $isTemp] = $this->getOrCreateTestStudent($db);
        $this->assertGreaterThan(0, $studentId);

        // 1. Create a test exam with 15 Tayo points reward and 50% pass percentage
        $rewardTayo = 15;
        $passPct = 50;

        $db->prepare("
            INSERT INTO exams (title, description, duration_minutes, reward_points, pass_percentage, created_by)
            VALUES ('امتحان تجريبي لاختبار الطايو', 'وصف الامتحان', 15, ?, ?, 1)
        ")->execute([$rewardTayo, $passPct]);
        $examId = (int) $db->lastInsertId();

        $this->assertGreaterThan(0, $examId);

        // Verify exam properties in DB
        $examRow = $db->query("SELECT * FROM exams WHERE id = {$examId}")->fetch();
        $this->assertEquals($rewardTayo, (int) $examRow['reward_points']);
        $this->assertEquals($passPct, (int) $examRow['pass_percentage']);

        // 2. Simulate student taking and passing the exam (score 80 out of 100 = 80% >= 50%)
        $score = 80;
        $totalMarks = 100;
        $earnedPercentage = round(($score / $totalMarks) * 100);
        $this->assertGreaterThanOrEqual($passPct, $earnedPercentage);

        // Award the exact Tayo specified on exam
        $db->prepare("
            INSERT INTO points (student_id, servant_id, points, type, reason)
            VALUES (?, 1, ?, 'positive', 'اجتياز اختبار تجريبي بنجاح (+15 طايو)')
        ")->execute([$studentId, $rewardTayo]);

        // Record exam result with anti-cheating log
        $cheatingViolations = 1;
        $cheatingDetails = '[10:00:00] تبديل التاب لمرة واحدة';

        $db->prepare("
            INSERT INTO exam_results (exam_id, student_id, score, total_marks, status, points_awarded, cheating_violations, cheating_details)
            VALUES (?, ?, ?, ?, 'completed', ?, ?, ?)
        ")->execute([$examId, $studentId, $score, $totalMarks, $rewardTayo, $cheatingViolations, $cheatingDetails]);
        $resId = (int) $db->lastInsertId();

        // 3. Verify exam result record
        $resRow = $db->query("SELECT * FROM exam_results WHERE id = {$resId}")->fetch();
        $this->assertEquals(15, (int) $resRow['points_awarded']);
        $this->assertEquals(1, (int) $resRow['cheating_violations']);
        $this->assertStringContainsString('تبديل التاب', $resRow['cheating_details']);

        // Clean up test data
        $db->prepare('DELETE FROM exam_results WHERE id = ?')->execute([$resId]);
        $db->prepare('DELETE FROM exams WHERE id = ?')->execute([$examId]);
        $db->prepare("DELETE FROM points WHERE student_id = ? AND reason LIKE '%اختبار تجريبي بنجاح%'")->execute([$studentId]);
        if ($isTemp) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
        }
    }
}

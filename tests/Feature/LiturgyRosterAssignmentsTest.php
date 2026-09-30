<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class LiturgyRosterAssignmentsTest extends TestCase
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

    public function test_create_roster_with_custom_assignments_and_individual_notes()
    {
        $db = $this->getPdo();

        // 1. Get student
        [$studentId, $isTemp] = $this->getOrCreateTestStudent($db);
        $this->assertGreaterThan(0, $studentId);

        $title = 'قداس تجريبي لاختبار التكليفات المخصصة والملاحظات';
        $serviceDate = date('Y-m-d', strtotime('+3 days'));
        $adminNotesReading = 'يرجى قراءة إنجيل باكر بصوت واضح وهادئ';
        $adminNotesCustom = 'حفظ ربع لحن إبركسيس مع المعلم والحضور مبكراً';

        // 2. Insert test roster
        $stmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, notes, created_by) VALUES (?, ?, ?, 1)');
        $stmt->execute([$title, $serviceDate, 'تعليمات عامة للقداس']);
        $rosterId = (int) $db->lastInsertId();

        // 3. Insert assignment with reading + admin notes
        $stmtStu = $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id, role_name, admin_notes, status) VALUES (?, ?, ?, ?, "pending")');
        $stmtStu->execute([$rosterId, $studentId, 'إنجيل باكر', $adminNotesReading]);
        $readingAssignmentId = (int) $db->lastInsertId();

        // 4. Insert custom assignment + admin notes
        $stmtStu->execute([$rosterId, $studentId, 'لحن إبركسيس', $adminNotesCustom]);
        $customAssignmentId = (int) $db->lastInsertId();

        // 5. Verify database storage
        $readingRow = $db->query("SELECT * FROM liturgy_roster_students WHERE id = {$readingAssignmentId}")->fetch();
        $this->assertNotEmpty($readingRow);
        $this->assertEquals('إنجيل باكر', $readingRow['role_name']);
        $this->assertEquals($adminNotesReading, $readingRow['admin_notes']);

        $customRow = $db->query("SELECT * FROM liturgy_roster_students WHERE id = {$customAssignmentId}")->fetch();
        $this->assertNotEmpty($customRow);
        $this->assertEquals('لحن إبركسيس', $customRow['role_name']);
        $this->assertEquals($adminNotesCustom, $customRow['admin_notes']);

        // Clean up
        $db->prepare('DELETE FROM liturgy_roster WHERE id = ?')->execute([$rosterId]);
        if ($isTemp) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
        }
    }

    public function test_add_roster_assignment_to_existing_roster_and_update_notes()
    {
        $db = $this->getPdo();

        [$studentId, $isTemp] = $this->getOrCreateTestStudent($db);
        $this->assertGreaterThan(0, $studentId);

        $title = 'قداس تجريبي لإضافة تكليف على قداس قائم';
        $serviceDate = date('Y-m-d', strtotime('+5 days'));

        $stmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, notes, created_by) VALUES (?, ?, ?, 1)');
        $stmt->execute([$title, $serviceDate, 'قداس تجريبي']);
        $rosterId = (int) $db->lastInsertId();

        // Add assignment (action: add_roster_assignment)
        $roleName = 'دورة البخور وحمل الشموع';
        $initialNotes = 'التواجد بالهيكل قبل بدء التسبحة';

        $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id, role_name, admin_notes, status) VALUES (?, ?, ?, ?, "pending")')
            ->execute([$rosterId, $studentId, $roleName, $initialNotes]);
        $assignmentId = (int) $db->lastInsertId();

        // Verify assignment added
        $assigned = $db->query("SELECT * FROM liturgy_roster_students WHERE id = {$assignmentId}")->fetch();
        $this->assertEquals($roleName, $assigned['role_name']);
        $this->assertEquals($initialNotes, $assigned['admin_notes']);

        // Update student notes (action: update_student_notes)
        $updatedNotes = 'تم تعديل الملاحظة: الحضور الساعة 7:15 صباحاً واستلام الشمعدان';
        $db->prepare('UPDATE liturgy_roster_students SET admin_notes = ? WHERE id = ?')
            ->execute([$updatedNotes, $assignmentId]);

        $afterUpdate = $db->query("SELECT * FROM liturgy_roster_students WHERE id = {$assignmentId}")->fetch();
        $this->assertEquals($updatedNotes, $afterUpdate['admin_notes']);

        // Test delete assignment (action: delete_roster_assignment)
        $db->prepare('DELETE FROM liturgy_roster_students WHERE id = ?')->execute([$assignmentId]);
        $deleted = $db->query("SELECT * FROM liturgy_roster_students WHERE id = {$assignmentId}")->fetch();
        $this->assertFalse($deleted);

        // Clean up
        $db->prepare('DELETE FROM liturgy_roster WHERE id = ?')->execute([$rosterId]);
        if ($isTemp) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
        }
    }

    public function test_student_can_fetch_roster_with_admin_notes()
    {
        $db = $this->getPdo();

        [$studentId, $isTemp] = $this->getOrCreateTestStudent($db);
        $this->assertGreaterThan(0, $studentId);

        $title = 'قداس تجريبي لعرض الملاحظات في حساب الشماس';
        $serviceDate = date('Y-m-d', strtotime('+2 days'));

        $stmt = $db->prepare('INSERT INTO liturgy_roster (title, service_date, notes, created_by) VALUES (?, ?, ?, 1)');
        $stmt->execute([$title, $serviceDate, 'ملاحظة عامة']);
        $rosterId = (int) $db->lastInsertId();

        $specialNote = 'يرجى مراجعة لحن التوزيع مع أستاذك';
        $db->prepare('INSERT INTO liturgy_roster_students (roster_id, student_id, role_name, admin_notes, status) VALUES (?, ?, ?, ?, "pending")')
            ->execute([$rosterId, $studentId, 'مزمور القداس', $specialNote]);

        // Run the query used in student/roster.php
        $rosters = $db->prepare('
            SELECT r.*, rs.role_name, rs.admin_notes, rs.status as my_status, rs.response_notes, rs.responded_at
            FROM liturgy_roster r
            JOIN liturgy_roster_students rs ON r.id = rs.roster_id
            WHERE rs.student_id = ? OR rs.substitute_student_id = ?
            ORDER BY r.service_date DESC
        ');
        $rosters->execute([$studentId, $studentId]);
        $myRosters = $rosters->fetchAll();

        $found = null;
        foreach ($myRosters as $r) {
            if ((int) $r['id'] === $rosterId) {
                $found = $r;
                break;
            }
        }

        $this->assertNotNull($found, 'Student should find their assigned roster');
        $this->assertEquals('مزمور القداس', $found['role_name']);
        $this->assertEquals($specialNote, $found['admin_notes']);

        // Clean up
        $db->prepare('DELETE FROM liturgy_roster WHERE id = ?')->execute([$rosterId]);
        if ($isTemp) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$studentId]);
        }
    }
}

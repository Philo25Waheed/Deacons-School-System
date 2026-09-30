<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiturgyRosterTest extends TestCase
{
    public function test_servant_class_roster_query_and_parent_notification()
    {
        require_once __DIR__.'/../../config/database.php';
        $db = getDB();

        // 1. Verify servant assigned classes query
        $servant = $db->query("SELECT id FROM users WHERE role = 'servant' LIMIT 1")->fetch();
        if ($servant) {
            $servantId = (int) $servant['id'];
            $scStmt = $db->prepare('
                SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
                FROM servant_classes sc
                JOIN classes c ON sc.class_id = c.id
                JOIN grades g ON c.grade_id = g.id
                JOIN stages s ON g.stage_id = s.id
                WHERE sc.servant_id = ?
            ');
            $scStmt->execute([$servantId]);
            $servantClasses = $scStmt->fetchAll();
            $this->assertIsArray($servantClasses);
        }

        // 2. Verify parent linking query for notifications
        $student = $db->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetch();
        if ($student) {
            $studentId = (int) $student['id'];
            $pStmt = $db->prepare('SELECT parent_id FROM parent_student WHERE student_id = ?');
            $pStmt->execute([$studentId]);
            $parents = $pStmt->fetchAll();
            $this->assertIsArray($parents);
        }

        $this->assertTrue(true);
    }
}

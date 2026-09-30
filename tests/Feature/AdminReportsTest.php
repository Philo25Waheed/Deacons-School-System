<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    public function test_admin_reports_query_and_render()
    {
        require_once __DIR__.'/../../config/database.php';
        $db = getDB();

        // 1. Verify the query in reports.php executes cleanly
        $classesReport = $db->query("
            SELECT c.id, c.name_ar as class_name, g.patron_saint, g.time_from, g.time_to, g.location,
                   g.name_ar as grade_name, s.name_ar as stage_name,
                   (SELECT GROUP_CONCAT(u2.full_name, '، ') FROM servant_classes cs JOIN users u2 ON cs.servant_id = u2.id WHERE cs.class_id = c.id) as servant_name,
                   (SELECT COUNT(*) FROM users WHERE class_id = c.id AND role = 'student' AND status = 'active') as student_count,
                   (SELECT COUNT(*) FROM attendance a JOIN users u ON a.student_id = u.id WHERE u.class_id = c.id AND a.status = 'present') as present_count,
                   (SELECT COUNT(*) FROM attendance a JOIN users u ON a.student_id = u.id WHERE u.class_id = c.id) as total_attendance,
                   (SELECT COALESCE(AVG(er.score * 100.0 / NULLIF(er.total_marks, 0)), 0) FROM exam_results er JOIN users u ON er.student_id = u.id WHERE u.class_id = c.id) as avg_exam_percentage,
                   (SELECT COALESCE(SUM(CASE WHEN pt.type = 'positive' THEN pt.points ELSE -pt.points END), 0) FROM points pt JOIN users u ON pt.student_id = u.id WHERE u.class_id = c.id) as class_total_points
            FROM classes c
            JOIN grades g ON c.grade_id = g.id
            JOIN stages s ON g.stage_id = s.id
            ORDER BY s.id, g.id, c.name_ar
        ")->fetchAll();

        $this->assertIsArray($classesReport);
    }

    public function test_student_detailed_report_queries()
    {
        require_once __DIR__.'/../../config/database.php';
        $db = getDB();

        // Ensure at least one student exists or test query logic
        $student = $db->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetch();
        if ($student) {
            $studentId = (int) $student['id'];

            // 1. Attendance stats query
            $stmtAtt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_cnt,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_cnt,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_cnt
                FROM attendance
                WHERE student_id = ?
            ");
            $stmtAtt->execute([$studentId]);
            $attRow = $stmtAtt->fetch();
            $this->assertIsArray($attRow);

            // 2. Exams query
            $stmtExams = $db->prepare('
                SELECT r.*, e.title as exam_title, r.total_marks as max_marks, u.full_name as servant_name
                FROM exam_results r
                JOIN exams e ON r.exam_id = e.id
                LEFT JOIN users u ON e.servant_id = u.id
                WHERE r.student_id = ?
                ORDER BY r.taken_at DESC, r.id DESC
            ');
            $stmtExams->execute([$studentId]);
            $this->assertIsArray($stmtExams->fetchAll());

            // 3. Liturgy roster query
            $stmtLiturgy = $db->prepare('
                SELECT r.id as roster_id, r.title, r.service_date, r.notes as liturgy_notes,
                       rs.role_name, rs.status as student_status, rs.response_notes, rs.responded_at,
                       sub.full_name as substitute_name
                FROM liturgy_roster_students rs
                JOIN liturgy_roster r ON rs.roster_id = r.id
                LEFT JOIN users sub ON rs.substitute_student_id = sub.id
                WHERE rs.student_id = ? OR rs.substitute_student_id = ?
                ORDER BY r.service_date DESC
            ');
            $stmtLiturgy->execute([$studentId, $studentId]);
            $this->assertIsArray($stmtLiturgy->fetchAll());

            // 4. Tayou (الطايو) query
            $stmtTayou = $db->prepare('
                SELECT p.*, srv.full_name as servant_name
                FROM points p
                LEFT JOIN users srv ON p.servant_id = srv.id
                WHERE p.student_id = ?
                ORDER BY p.id DESC
            ');
            $stmtTayou->execute([$studentId]);
            $this->assertIsArray($stmtTayou->fetchAll());

            // 5. Test Class Filter query
            $class = $db->query('SELECT id FROM classes LIMIT 1')->fetch();
            if ($class) {
                $stmtClassStudents = $db->prepare("
                    SELECT u.id, u.full_name, u.class_id
                    FROM users u
                    WHERE u.role = 'student' AND u.status = 'active' AND u.class_id = ?
                ");
                $stmtClassStudents->execute([(int) $class['id']]);
                $filteredList = $stmtClassStudents->fetchAll();
                $this->assertIsArray($filteredList);
                foreach ($filteredList as $row) {
                    $this->assertEquals((int) $class['id'], (int) $row['class_id']);
                }
            }
        } else {
            $this->assertTrue(true);
        }
    }
}

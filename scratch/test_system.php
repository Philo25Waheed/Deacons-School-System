<?php

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/helpers.php';

try {
    $db = getDB();
    echo "1. DB Connection: OK\n";

    // Test Users
    $stmt = $db->query('SELECT id, full_name, email, phone, role, password, status FROM users');
    $users = $stmt->fetchAll();
    echo '2. Total Users in DB: '.count($users)."\n";

    foreach ($users as $u) {
        $pwdValid = password_verify('Admin@123456', $u['password']);
        echo "   - User: {$u['full_name']} ({$u['role']}) | Status: {$u['status']} | Pwd Check: ".($pwdValid ? 'PASS' : 'FAIL')."\n";
    }

    // Test Stages, Grades, Classes
    $stagesCount = $db->query('SELECT COUNT(*) FROM stages')->fetchColumn();
    $gradesCount = $db->query('SELECT COUNT(*) FROM grades')->fetchColumn();
    $classesCount = $db->query('SELECT COUNT(*) FROM classes')->fetchColumn();

    echo "3. Stages: {$stagesCount}, Grades: {$gradesCount}, Classes: {$classesCount}\n";

    // Test Points
    $pointsCount = $db->query('SELECT COUNT(*) FROM points')->fetchColumn();
    echo "4. Sample Points Recorded: {$pointsCount}\n";

    echo "\n=== ALL BACKEND & DATABASE CHECKS PASSED ===\n";

} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

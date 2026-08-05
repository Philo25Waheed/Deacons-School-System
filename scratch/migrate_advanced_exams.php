<?php

// Migration for Advanced Exam Engine Expansion
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();

    $queries = [
        'ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `class_id` INT DEFAULT NULL;',
        'ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `servant_id` INT DEFAULT NULL;',
        'ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `is_published` TINYINT(1) DEFAULT 1;',

        "ALTER TABLE `exam_questions` ADD COLUMN IF NOT EXISTS `question_type` ENUM('mcq', 'true_false', 'matching', 'essay') DEFAULT 'mcq';",
        'ALTER TABLE `exam_questions` ADD COLUMN IF NOT EXISTS `matching_pairs` TEXT DEFAULT NULL;',

        "ALTER TABLE `exam_results` ADD COLUMN IF NOT EXISTS `status` ENUM('completed', 'needs_grading') DEFAULT 'completed';",
        'ALTER TABLE `exam_results` ADD COLUMN IF NOT EXISTS `answers_json` TEXT DEFAULT NULL;',
        'ALTER TABLE `exam_results` ADD COLUMN IF NOT EXISTS `essay_scores_json` TEXT DEFAULT NULL;',
        'ALTER TABLE `exam_results` ADD COLUMN IF NOT EXISTS `servant_feedback` TEXT DEFAULT NULL;',
    ];

    foreach ($queries as $q) {
        try {
            $db->exec($q);
        } catch (Exception $e) {
            // Ignore if exists
        }
    }

    echo "Exam schema expansion completed successfully!\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

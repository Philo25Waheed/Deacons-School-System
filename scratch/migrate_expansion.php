<?php

// Expansion Migration Script for Deacons School System
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();

    $queries = [
        'CREATE TABLE IF NOT EXISTS `exams` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `stage_id` INT DEFAULT NULL,
            `grade_id` INT DEFAULT NULL,
            `duration_minutes` INT DEFAULT 30,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

        "CREATE TABLE IF NOT EXISTS `exam_questions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `exam_id` INT NOT NULL,
            `question_text` TEXT NOT NULL,
            `option_a` VARCHAR(255) NOT NULL,
            `option_b` VARCHAR(255) NOT NULL,
            `option_c` VARCHAR(255) DEFAULT NULL,
            `option_d` VARCHAR(255) DEFAULT NULL,
            `correct_option` ENUM('a', 'b', 'c', 'd') NOT NULL,
            `points` INT DEFAULT 1,
            FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'CREATE TABLE IF NOT EXISTS `exam_results` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `exam_id` INT NOT NULL,
            `student_id` INT NOT NULL,
            `score` INT NOT NULL,
            `total_marks` INT NOT NULL,
            `taken_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

        'CREATE TABLE IF NOT EXISTS `liturgy_roster` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `service_date` DATE NOT NULL,
            `class_id` INT DEFAULT NULL,
            `hymn_required` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

        'CREATE TABLE IF NOT EXISTS `liturgy_roster_students` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `roster_id` INT NOT NULL,
            `student_id` INT NOT NULL,
            FOREIGN KEY (`roster_id`) REFERENCES `liturgy_roster`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

        "CREATE TABLE IF NOT EXISTS `rewards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `points_cost` INT NOT NULL,
            `image_url` VARCHAR(255) DEFAULT 'default-reward.png',
            `stock_quantity` INT DEFAULT 10,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `reward_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reward_id` INT NOT NULL,
            `student_id` INT NOT NULL,
            `points_spent` INT NOT NULL,
            `status` ENUM('pending', 'fulfilled', 'cancelled') DEFAULT 'pending',
            `ordered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`reward_id`) REFERENCES `rewards`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `pastoral_visitations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `servant_id` INT NOT NULL,
            `type` ENUM('phone', 'home_visit', 'church_chat') NOT NULL,
            `notes` TEXT NOT NULL,
            `visit_date` DATE NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`servant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ];

    foreach ($queries as $q) {
        $db->exec($q);
    }

    // Seed sample rewards
    $rewardCount = $db->query('SELECT COUNT(*) FROM rewards')->fetchColumn();
    if ($rewardCount == 0) {
        $db->exec("INSERT INTO rewards (title, description, points_cost, stock_quantity) VALUES
            ('كتاب الخولاجي الملحن', 'كتاب شامل لألحان وطقوس القداس الإلهي', 15, 10),
            ('أجبية الصلاة باللغتين', 'كتاب الأجبية القبطي العربي المزخرف', 10, 15),
            ('وسام الشماس المثالي', 'وسام معدني تذكاري نادراً ما يُمنح', 25, 5);");
    }

    // Seed sample exam
    $examCount = $db->query('SELECT COUNT(*) FROM exams')->fetchColumn();
    if ($examCount == 0) {
        $db->exec("INSERT INTO exams (title, description, duration_minutes, created_by) VALUES
            ('اختبار ألحان القداس الإلهي', 'اختبار عام في ألحان الهيتنيات وإكإسماروؤوت', 15, 1);");
        $examId = $db->lastInsertId();

        $db->exec("INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, points) VALUES
            ($examId, 'ما معنى كلمة إكإسماروؤوت؟', 'مبارك أنت', 'قدوس أنت', 'عظيم أنت', 'صالح أنت', 'a', 2),
            ($examId, 'متى يُقال لحن إكإسماروؤوت؟', 'في الأعياد والقداس الإلهي', 'في التسبحة فقط', 'في الجمعة الكبيرة فقط', 'في الجنازات', 'a', 2);");
    }

    echo 'Migration completed successfully!';

} catch (Exception $e) {
    echo 'Migration Error: '.$e->getMessage();
}

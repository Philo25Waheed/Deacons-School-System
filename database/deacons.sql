-- Deacons School Management System (مدرسة الشمامسة)
-- Complete Database Schema

CREATE DATABASE IF NOT EXISTS `deacons_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `deacons_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `hymns`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `evaluations`;
DROP TABLE IF EXISTS `points`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `servant_classes`;
DROP TABLE IF EXISTS `parent_student`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `stages`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Stages Table
CREATE TABLE `stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_ar` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Grades Table
CREATE TABLE `grades` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stage_id` INT NOT NULL,
  `name_ar` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`stage_id`) REFERENCES `stages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Classes Table
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `grade_id` INT NOT NULL,
  `name_ar` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`grade_id`) REFERENCES `grades`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(150) DEFAULT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'servant', 'student', 'parent') NOT NULL,
  `status` ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
  `dob` DATE DEFAULT NULL,
  `church_name` VARCHAR(150) DEFAULT 'كنيسة مارجرجس',
  `stage_id` INT DEFAULT NULL,
  `grade_id` INT DEFAULT NULL,
  `class_id` INT DEFAULT NULL,
  `profile_pic` VARCHAR(255) DEFAULT 'default-avatar.png',
  `qr_code_token` VARCHAR(64) UNIQUE DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`stage_id`) REFERENCES `stages`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`grade_id`) REFERENCES `grades`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Parent Student Mapping Table
CREATE TABLE `parent_student` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `relationship` VARCHAR(50) DEFAULT 'والد / والدة',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `parent_student_unique` (`parent_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Servant Classes Mapping Table
CREATE TABLE `servant_classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `servant_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`servant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `servant_class_unique` (`servant_id`, `class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Attendance Table
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `servant_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
  `scanned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `notes` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`servant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_daily_attendance` (`student_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Points Table
CREATE TABLE `points` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `servant_id` INT NOT NULL,
  `points` INT NOT NULL,
  `type` ENUM('positive', 'negative') NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`servant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Evaluations Table
CREATE TABLE `evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `servant_id` INT NOT NULL,
  `behavior_score` INT NOT NULL DEFAULT 5,
  `hymn_memorization` INT NOT NULL DEFAULT 5,
  `church_attending` INT NOT NULL DEFAULT 5,
  `notes` TEXT DEFAULT NULL,
  `evaluation_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`servant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Courses Table
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `stage_id` INT DEFAULT NULL,
  `grade_id` INT DEFAULT NULL,
  `pdf_file` VARCHAR(255) DEFAULT NULL,
  `audio_file` VARCHAR(255) DEFAULT NULL,
  `video_file` VARCHAR(255) DEFAULT NULL,
  `external_link` VARCHAR(255) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`stage_id`) REFERENCES `stages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`grade_id`) REFERENCES `grades`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Hymns Table (مكتبة الألحان)
CREATE TABLE `hymns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `audio_file` VARCHAR(255) DEFAULT NULL,
  `pdf_file` VARCHAR(255) DEFAULT NULL,
  `video_link` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Announcements Table
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `target_type` ENUM('everyone', 'students', 'parents', 'servants', 'stage', 'grade', 'class') DEFAULT 'everyone',
  `target_id` INT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Notifications Table
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Audit Logs Table
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED DATA

-- Insert Stages
INSERT INTO `stages` (`id`, `name_ar`) VALUES
(1, 'مرحلة الروضة (Kindergarten)'),
(2, 'المرحلة الإبتدائية (Primary)'),
(3, 'المرحلة الإعدادية (Preparatory)'),
(4, 'المرحلة الثانوية (Secondary)'),
(5, 'مرحلة الجامعة والشباب (University)');

-- Insert Grades
INSERT INTO `grades` (`id`, `stage_id`, `name_ar`) VALUES
(1, 1, 'KG1'),
(2, 1, 'KG2'),
(3, 2, 'الصف الأول الإبتدائي'),
(4, 2, 'الصف الثاني الإبتدائي'),
(5, 2, 'الصف الثالث الإبتدائي'),
(6, 2, 'الصف الرابع الإبتدائي'),
(7, 3, 'الصف الأول الإعدادي'),
(8, 3, 'الصف الثاني الإعدادي'),
(9, 3, 'الصف الثالث الإعدادي'),
(10, 4, 'الصف الأول الثانوي'),
(11, 4, 'الصف الثاني الثانوي'),
(12, 4, 'الصف الثالث الثانوي'),
(13, 5, 'جامعة وشباب');

-- Insert Classes
INSERT INTO `classes` (`id`, `grade_id`, `name_ar`) VALUES
(1, 1, 'فصل أ'),
(2, 1, 'فصل ب'),
(3, 2, 'فصل أ'),
(4, 2, 'فصل ب'),
(5, 3, 'فصل 1'),
(6, 3, 'فصل 2'),
(7, 4, 'فصل 1'),
(8, 5, 'فصل 1'),
(9, 6, 'فصل 1'),
(10, 7, 'فصل 1'),
(11, 8, 'فصل 1'),
(12, 9, 'فصل 1'),
(13, 10, 'فصل 1'),
(14, 11, 'فصل 1'),
(15, 12, 'فصل 1'),
(16, 13, 'شباب 1'),
(17, 13, 'شباب 2');

-- Password for all seed users is 'Admin@123456'
INSERT INTO `users` (`id`, `full_name`, `phone`, `email`, `password`, `role`, `status`, `church_name`, `stage_id`, `grade_id`, `class_id`, `qr_code_token`) VALUES
(1, 'المدير المسؤول', '01000000000', 'admin@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'admin', 'active', 'كنيسة العذراء مريم', NULL, NULL, NULL, 'ADM-000001'),
(2, 'الخادم مينا عادل', '01111111111', 'mina@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', 'كنيسة مارجرجس', 2, 3, 5, 'SRV-000002'),
(3, 'الشماس يوسف مينا', '01222222222', 'youssef@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', 'كنيسة مارجرجس', 2, 3, 5, 'STU-2026-0003'),
(4, 'ولي الأمر مينا سامي', '01333333333', 'parent@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', 'كنيسة مارجرجس', NULL, NULL, NULL, 'PRN-000004');

INSERT INTO `servant_classes` (`servant_id`, `class_id`) VALUES (2, 5);

INSERT INTO `parent_student` (`parent_id`, `student_id`, `relationship`) VALUES (4, 3, 'والد');

INSERT INTO `points` (`student_id`, `servant_id`, `points`, `type`, `reason`) VALUES
(3, 2, 10, 'positive', 'حفظ لحن إكإسماروؤوت ممتاز'),
(3, 2, 5, 'positive', 'الحضور مبكراً للقداس');

INSERT INTO `announcements` (`title`, `content`, `target_type`, `created_by`) VALUES
('أهلاً بكم في مدرسة الشمامسة', 'نرحب بجميع الشمامسة والخدام وأولياء الأمور في العام الدراسي الجديد. برجاء الالتزام بالمواعيد.', 'everyone', 1);

INSERT INTO `hymns` (`title`, `description`, `notes`, `created_by`) VALUES
('لحن إكإسماروؤوت (Ek-Smaro-out)', 'لحن يقال في الأعياد والمناسبات والقداس الإلهي', 'مبارك أنت أيها المسيح إلهنا مع أبيك الصالح والروح القدس لأنك أتيت وخلاصنا.', 1);

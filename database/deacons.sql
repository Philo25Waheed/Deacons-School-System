-- ========================================================
-- Deacons School Management System (مدرسة الشهيد إسطفانوس)
-- Complete Production Database (Schema + 100+ Fake Accounts + Rich Seed Data)
-- Optimized for InfinityFree MySQL / MariaDB
-- Password for all accounts: Admin@123456
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+02:00';

DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `deacon_books`;
DROP TABLE IF EXISTS `calendar_events`;
DROP TABLE IF EXISTS `event_registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `visitations`;
DROP TABLE IF EXISTS `pastoral_visitations`;
DROP TABLE IF EXISTS `reward_orders`;
DROP TABLE IF EXISTS `rewards`;
DROP TABLE IF EXISTS `liturgy_roster_students`;
DROP TABLE IF EXISTS `liturgy_roster`;
DROP TABLE IF EXISTS `exam_results`;
DROP TABLE IF EXISTS `exam_questions`;
DROP TABLE IF EXISTS `exams`;
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

-- --------------------------------------------------------
-- Table structure for `stages`
-- --------------------------------------------------------
CREATE TABLE `stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `grades`
-- --------------------------------------------------------
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `patron_saint` varchar(255) DEFAULT NULL,
  `time_from` varchar(50) DEFAULT NULL,
  `time_to` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `stage_id` (`stage_id`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `classes`
-- --------------------------------------------------------
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `grade_id` (`grade_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','servant','student','parent') NOT NULL,
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `dob` date DEFAULT NULL,
  `church_name` varchar(150) DEFAULT 'كنيسة مارجرجس',
  `stage_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default-avatar.png',
  `qr_code_token` varchar(64) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address` varchar(255) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `mother_phone` varchar(20) DEFAULT NULL,
  `deacon_rank` varchar(100) DEFAULT 'إبصالتس (مرتل)',
  `gender` enum('male','female') DEFAULT 'male',
  `points_balance` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `qr_code_token` (`qr_code_token`),
  KEY `stage_id` (`stage_id`),
  KEY `grade_id` (`grade_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `parent_student`
-- --------------------------------------------------------
CREATE TABLE `parent_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT 'والد / والدة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_student_unique` (`parent_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `servant_classes`
-- --------------------------------------------------------
CREATE TABLE `servant_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `servant_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `servant_class_unique` (`servant_id`,`class_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `servant_classes_ibfk_1` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `servant_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `attendance`
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `servant_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') DEFAULT 'present',
  `attended_lesson` tinyint(1) NOT NULL DEFAULT 1,
  `attended_pamphlet` tinyint(1) NOT NULL DEFAULT 0,
  `attended_liturgy` tinyint(1) NOT NULL DEFAULT 0,
  `points_awarded` int(11) NOT NULL DEFAULT 0,
  `scanned_at` datetime DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_daily_attendance` (`student_id`,`attendance_date`),
  KEY `servant_id` (`servant_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `points`
-- --------------------------------------------------------
CREATE TABLE `points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `servant_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `type` enum('positive','negative') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `servant_id` (`servant_id`),
  CONSTRAINT `points_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `points_ibfk_2` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `evaluations`
-- --------------------------------------------------------
CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `servant_id` int(11) NOT NULL,
  `behavior_score` int(11) NOT NULL DEFAULT 5,
  `hymn_memorization` int(11) NOT NULL DEFAULT 5,
  `church_attending` int(11) NOT NULL DEFAULT 5,
  `notes` text DEFAULT NULL,
  `evaluation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `servant_id` (`servant_id`),
  CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `courses`
-- --------------------------------------------------------
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `audio_file` varchar(255) DEFAULT NULL,
  `video_file` varchar(255) DEFAULT NULL,
  `external_link` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `stage_id` (`stage_id`),
  KEY `grade_id` (`grade_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `hymns`
-- --------------------------------------------------------
CREATE TABLE `hymns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `audio_file` varchar(255) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `video_link` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `hymns_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `announcements`
-- --------------------------------------------------------
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_type` enum('everyone','students','parents','servants','stage','grade','class') DEFAULT 'everyone',
  `target_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `audit_logs`
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `exams`
-- --------------------------------------------------------
CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `servant_id` int(11) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `reward_points` int(11) NOT NULL DEFAULT 5,
  `pass_percentage` int(11) NOT NULL DEFAULT 50,
  `deadline` datetime DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `exam_questions`
-- --------------------------------------------------------
CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_option` enum('a','b','c','d') NOT NULL,
  `points` int(11) DEFAULT 1,
  `question_type` enum('mcq','true_false','matching','essay') DEFAULT 'mcq',
  `matching_pairs` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `exam_results`
-- --------------------------------------------------------
CREATE TABLE `exam_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_marks` int(11) NOT NULL,
  `points_awarded` int(11) NOT NULL DEFAULT 0,
  `cheating_violations` int(11) NOT NULL DEFAULT 0,
  `cheating_details` text DEFAULT NULL,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('completed','needs_grading') DEFAULT 'completed',
  `answers_json` text DEFAULT NULL,
  `essay_scores_json` text DEFAULT NULL,
  `servant_feedback` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `liturgy_roster`
-- --------------------------------------------------------
CREATE TABLE `liturgy_roster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `service_date` date NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `hymn_required` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `liturgy_roster_students`
-- --------------------------------------------------------
CREATE TABLE `liturgy_roster_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `roster_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `role_name` varchar(255) DEFAULT 'خدمة مذبح',
  `admin_notes` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `response_notes` varchar(255) DEFAULT NULL,
  `substitute_student_id` int(11) DEFAULT NULL,
  `responded_at` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `roster_id` (`roster_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `liturgy_roster_students_ibfk_1` FOREIGN KEY (`roster_id`) REFERENCES `liturgy_roster` (`id`) ON DELETE CASCADE,
  CONSTRAINT `liturgy_roster_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `rewards`
-- --------------------------------------------------------
CREATE TABLE `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points_cost` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT 'default-reward.png',
  `stock_quantity` int(11) DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `reward_orders`
-- --------------------------------------------------------
CREATE TABLE `reward_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reward_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `status` enum('pending','fulfilled','cancelled') DEFAULT 'pending',
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reward_id` (`reward_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `reward_orders_ibfk_1` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_orders_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `pastoral_visitations`
-- --------------------------------------------------------
CREATE TABLE `pastoral_visitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `servant_id` int(11) NOT NULL,
  `type` enum('phone','home_visit','church_chat') NOT NULL,
  `notes` text NOT NULL,
  `visit_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `servant_id` (`servant_id`),
  CONSTRAINT `pastoral_visitations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pastoral_visitations_ibfk_2` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `visitations`
-- --------------------------------------------------------
CREATE TABLE `visitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `servant_id` int(11) NOT NULL,
  `visit_type` enum('home_visit','phone_call','church_meeting') DEFAULT 'phone_call',
  `visit_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('completed','scheduled','cancelled') DEFAULT 'completed',
  `next_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `servant_id` (`servant_id`),
  CONSTRAINT `visitations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visitations_ibfk_2` FOREIGN KEY (`servant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `events`
-- --------------------------------------------------------
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('trip','retreat','spiritual_day','meeting','celebration') DEFAULT 'trip',
  `event_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `max_capacity` int(11) DEFAULT 50,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `event_registrations`
-- --------------------------------------------------------
CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `seats_count` int(11) DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `status` enum('registered','confirmed','cancelled') DEFAULT 'registered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_event` (`event_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `calendar_events`
-- --------------------------------------------------------
CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('class','exam','event','meeting') DEFAULT 'class',
  `class_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `deacon_books`
-- --------------------------------------------------------
CREATE TABLE `deacon_books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'خدمة الشماس والطقس',
  `description` text DEFAULT NULL,
  `drive_url` text DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `downloads_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `deacon_books_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA (البيانات الأساسية والتجريبية)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Stages (المراحل الدراسية)
INSERT INTO `stages` (`id`, `name_ar`) VALUES
(1, 'مرحلة الروضة (Kindergarten)'),
(2, 'المرحلة الإبتدائية (Primary)'),
(3, 'المرحلة الإعدادية (Preparatory)'),
(4, 'المرحلة الثانوية (Secondary)'),
(5, 'مرحلة الجامعة والشباب (University)');

-- 2. Grades (الصفوف الدراسية)
INSERT INTO `grades` (`id`, `stage_id`, `name_ar`, `patron_saint`, `time_from`, `time_to`, `location`) VALUES
(1, 1, 'KG1', 'الشهيد مارمينا العجائبي', '10:00 ص', '12:00 م', 'قاعة الروضة - الدور الأرضي'),
(2, 1, 'KG2', 'الشهيد أبانوب النهيسي', '10:00 ص', '12:00 م', 'قاعة الروضة - الدور الأرضي'),
(3, 2, 'الصف الأول الإبتدائي', 'الشهيد مارجرجس الروماني', '10:00 ص', '12:00 م', 'مبنى الخدمات - قاعة 1'),
(4, 2, 'الصف الثاني الإبتدائي', 'القديس فيلوباتير مرقوريوس', '10:00 ص', '12:00 م', 'مبنى الخدمات - قاعة 2'),
(5, 2, 'الصف الثالث الإبتدائي', 'القديس الأنبا بيشوي', '10:00 ص', '12:00 م', 'مبنى الخدمات - قاعة 3'),
(6, 2, 'الصف الرابع الإبتدائي', 'القديس يوحنا الحبيب', '10:00 ص', '12:00 م', 'مبنى الخدمات - قاعة 4'),
(7, 3, 'الصف الأول الإعدادي', 'الشهيد إسطفانوس رئيس الشمامسة', '10:00 ص', '12:30 م', 'مبنى الخدمات - قاعة 5'),
(8, 3, 'الصف الثاني الإعدادي', 'القديس أثناسيوس الرسولي', '10:00 ص', '12:30 م', 'مبنى الخدمات - قاعة 6'),
(9, 3, 'الصف الثالث الإعدادي', 'القديس كيرلس عمود الدين', '10:00 ص', '12:30 م', 'مبنى الخدمات - قاعة 7'),
(10, 4, 'الصف الأول الثانوي', 'القديس حبيب جرجس', '11:00 ص', '01:00 م', 'المسرح الكبير'),
(11, 4, 'الصف الثاني الثانوي', 'القديس بولس الرسول', '11:00 ص', '01:00 م', 'المسرح الكبير'),
(12, 4, 'الصف الثالث الثانوي', 'القديس مرقس الرسول', '11:00 ص', '01:00 م', 'المسرح الكبير'),
(13, 5, 'جامعة وشباب', 'الشهيد مارمرقس كاروز الديار المصرية', '11:30 ص', '01:30 م', 'قاعة الشباب المركزية');

-- 3. Classes (الفصول الدراسية)
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

-- 4. Users (المستخدمين: 100+ حساب تجريبي يشمل الآدمن والخدام والشمامسة وأولياء الأمور)
INSERT INTO `users` (`id`, `full_name`, `phone`, `email`, `password`, `role`, `status`, `dob`, `church_name`, `stage_id`, `grade_id`, `class_id`, `profile_pic`, `qr_code_token`, `address`, `father_name`, `father_phone`, `mother_name`, `mother_phone`, `deacon_rank`, `gender`) VALUES
(1, 'المدير المسؤول', '01000000000', 'admin@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'admin', 'active', '1985-01-01', 'كنيسة السيدة العذراء والأنبا رويس', NULL, NULL, NULL, 'default-avatar.png', 'ADM-000001', 'حدائق الأهرام - الجيزة', NULL, NULL, NULL, NULL, 'دياكون (شماس كامل)', 'male'),
(2, 'فيلوباتير وحيد', '01000000099', 'Philo.admin@deacon.com', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'admin', 'active', '2004-11-25', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'ADM-000002', 'حدائق الأهرام - البوابة الأولى', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(3, 'الخادم مينا عادل', '01111111111', 'mina@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'SRV-000002', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(4, 'الشماس يوسف مينا', '01222222222', 'youssef@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'STU-2026-0003', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(5, 'ولي الأمر مينا سامي', '01333333333', 'parent@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '2010-05-15', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-000004', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(6, 'الخادم بيشوي مجدي', '01122334455', 'bishoy@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'SRV-000005', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(7, 'الطالب كيرلس بيشوي', '01233445566', 'kyrollos@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'STU-2026-0006', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(8, 'الطالبة ماريا إبراهيم', '01244556677', 'mark@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'STU-2026-0007', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'female'),
(9, 'الطالب دانيال سامح', '01255667788', 'daniel@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'STU-2026-0008', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'أغنسطس (قارئ)', 'male'),
(10, 'ولي الأمر إبراهيم جرجس', '01344556677', 'ibrahim@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '2010-05-15', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-000009', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(11, 'سامح مينا عادل (الأب)', '01355667788', 'sameh@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '2010-05-15', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-000010', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(12, 'مريم عاطف فؤاد (الأم)', '01366778899', 'mary@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '2010-05-15', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-000011', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'female'),
(13, 'الشماس أبانوب سامح مينا', '01266778899', 'abanoub@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 4, 7, 'default-avatar.png', 'STU-2026-0015', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(14, 'الشماسة يستينا سامح مينا', '01277889900', 'yustina@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 1, 2, 3, 'default-avatar.png', 'STU-2026-0016', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'female'),
(15, 'مكسيموس سامح مينا', '01288990011', 'maximus@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'STU-2026-0017', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'أغنسطس (قارئ)', 'male'),
(16, 'توماس هاني سعد', '01299001122', 'thomas@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'STU-2026-0018', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'أغنسطس (قارئ)', 'male'),
(17, 'سارة هاني سعد', '01200112233', 'sara@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2010-05-15', 'كنيسة مارجرجس', 2, 6, 9, 'default-avatar.png', 'STU-2026-0019', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'female'),
(18, 'فيلوباتير وحيد ثابت', '01203031800', 'filowaheed28@gmail.com', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '2010-05-15', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'SRV-01BE35', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(21, 'الخادم مينا عادل فؤاد', '01110010001', 'servant.c3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 1, 2, 3, 'default-avatar.png', 'SRV-0021', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(22, 'الخادم بيشوي ميلاد رزق', '01110010002', 'servant.c4@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 1, 2, 4, 'default-avatar.png', 'SRV-0022', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(23, 'الخادم شنودة شكري عوض', '01110010003', 'servant.c5@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'SRV-0023', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(24, 'الخادم كيرلس نسيم عزيز', '01110010004', 'servant.c6@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 2, 3, 6, 'default-avatar.png', 'SRV-0024', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(25, 'الخادم فادي يوسف جرجس', '01110010005', 'servant.c7@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 2, 4, 7, 'default-avatar.png', 'SRV-0025', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(26, 'الخادم مارك إيهاب صليب', '01110010006', 'servant.c9@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 2, 6, 9, 'default-avatar.png', 'SRV-0026', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(27, 'الخادم جورج عاطف غالي', '01110010007', 'servant.c10@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'SRV-0027', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(28, 'الخادم توني نبيل لمعي', '01110010008', 'servant.c11@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 3, 8, 11, 'default-avatar.png', 'SRV-0028', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(29, 'الخادم رامي صبحي شفيق', '01110010009', 'servant.c12@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 3, 9, 12, 'default-avatar.png', 'SRV-0029', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(30, 'الخادم ريمون مدحت حبيب', '01110010010', 'servant.c13@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'SRV-0030', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(31, 'الخادم بولا سامي نصيف', '01110010011', 'servant.c14@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 4, 11, 14, 'default-avatar.png', 'SRV-0031', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(32, 'الخادم أمير شريف كامل', '01110010012', 'servant.c15@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 4, 12, 15, 'default-avatar.png', 'SRV-0032', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(33, 'الخادم بيتر ماهر رمزي', '01110010013', 'servant.c16@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 5, 13, 16, 'default-avatar.png', 'SRV-0033', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(34, 'الخادم أندرو سمير فهمي', '01110010014', 'servant.c17@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', '1992-03-20', 'كنيسة مارجرجس', 5, 13, 17, 'default-avatar.png', 'SRV-0034', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبديدياكون (مساعد شماس)', 'male'),
(41, 'الشماس كيرلس مينا عادل', '01210010001', 'student.c3.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 3, 'default-avatar.png', 'STU-2026-0041', 'حدائق الأهرام', 'مينا عادل فؤاد', '01010010001', NULL, NULL, 'إبصالتس', 'male'),
(42, 'الشماس ديفيد بيشوي ميلاد', '01210010002', 'student.c3.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 3, 'default-avatar.png', 'STU-2026-0042', 'حدائق الأهرام', 'بيشوي ميلاد رزق', '01010010002', NULL, NULL, 'إبصالتس', 'male'),
(43, 'الشماس توماس سامح منير', '01210010003', 'student.c3.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 3, 'default-avatar.png', 'STU-2026-0043', 'حدائق الأهرام', 'سامح منير غالي', '01010010003', NULL, NULL, 'إبصالتس', 'male'),
(44, 'الشماس يوحنا شنودة شكري', '01210010004', 'student.c4.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 4, 'default-avatar.png', 'STU-2026-0044', 'حدائق الأهرام', 'شنودة شكري عوض', '01010010004', NULL, NULL, 'إبصالتس', 'male'),
(45, 'الشماس مرقس فادي يوسف', '01210010005', 'student.c4.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 4, 'default-avatar.png', 'STU-2026-0045', 'حدائق الأهرام', 'فادي يوسف جرجس', '01010010005', NULL, NULL, 'إبصالتس', 'male'),
(46, 'الشماس أنطوني جورج عاطف', '01210010006', 'student.c4.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 1, 2, 4, 'default-avatar.png', 'STU-2026-0046', 'حدائق الأهرام', 'جورج عاطف غالي', '01010010006', NULL, NULL, 'إبصالتس', 'male'),
(47, 'الشماس أبانوب سامح مينا', '01210010007', 'student.c5.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'STU-2026-0047', 'حدائق الأهرام', 'سامح مينا عادل', '01010010007', NULL, NULL, 'إبصالتس', 'male'),
(48, 'الشماس يوسف مينا كمال', '01210010008', 'student.c5.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'STU-2026-0048', 'حدائق الأهرام', 'مينا كمال عزيز', '01010010008', NULL, NULL, 'إبصالتس', 'male'),
(49, 'الشماس بولا ريمون مدحت', '01210010009', 'student.c5.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 5, 'default-avatar.png', 'STU-2026-0049', 'حدائق الأهرام', 'ريمون مدحت حبيب', '01010010009', NULL, NULL, 'إبصالتس', 'male'),
(50, 'الشماس مكسيموس عاطف غالي', '01210010010', 'student.c6.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 6, 'default-avatar.png', 'STU-2026-0050', 'حدائق الأهرام', 'عاطف غالي خليل', '01010010010', NULL, NULL, 'إبصالتس', 'male'),
(51, 'الشماس روفائيل مدحت نسيم', '01210010011', 'student.c6.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 6, 'default-avatar.png', 'STU-2026-0051', 'حدائق الأهرام', 'مدحت نسيم تادرس', '01010010011', NULL, NULL, 'إبصالتس', 'male'),
(52, 'الشماس فيلوباتير صبحي شفيق', '01210010012', 'student.c6.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 3, 6, 'default-avatar.png', 'STU-2026-0052', 'حدائق الأهرام', 'صبحي شفيق عوض', '01010010012', NULL, NULL, 'إبصالتس', 'male'),
(53, 'الشماس ميخائيل وجدي لمعي', '01210010013', 'student.c7.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 4, 7, 'default-avatar.png', 'STU-2026-0053', 'حدائق الأهرام', 'وجدي لمعي كامل', '01010010013', NULL, NULL, 'إبصالتس', 'male'),
(54, 'الشماس تادرس كمال عزيز', '01210010014', 'student.c7.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 4, 7, 'default-avatar.png', 'STU-2026-0054', 'حدائق الأهرام', 'كمال عزيز مرقس', '01010010014', NULL, NULL, 'إبصالتس', 'male'),
(55, 'الشماس سوريال فايز صليب', '01210010015', 'student.c7.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 4, 7, 'default-avatar.png', 'STU-2026-0055', 'حدائق الأهرام', 'فايز صليب جرجس', '01010010015', NULL, NULL, 'إبصالتس', 'male'),
(56, 'الشماس غبريال لمعي حبيب', '01210010016', 'student.c9.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 6, 9, 'default-avatar.png', 'STU-2026-0056', 'حدائق الأهرام', 'لمعي حبيب نصيف', '01010010016', NULL, NULL, 'إبصالتس', 'male'),
(57, 'الشماس أرسانيوس عادل فهمي', '01210010017', 'student.c9.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 6, 9, 'default-avatar.png', 'STU-2026-0057', 'حدائق الأهرام', 'عادل فهمي بطرس', '01010010017', NULL, NULL, 'إبصالتس', 'male'),
(58, 'الشماس بيشوي ميلاد شريف', '01210010018', 'student.c9.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 2, 6, 9, 'default-avatar.png', 'STU-2026-0058', 'حدائق الأهرام', 'ميلاد شريف زكي', '01010010018', NULL, NULL, 'إبصالتس', 'male'),
(59, 'الشماس بافلي ناصر صليب', '01210010019', 'student.c10.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'STU-2026-0059', 'حدائق الأهرام', 'ناصر صليب ميخائيل', '01010010019', NULL, NULL, 'أغنسطس', 'male'),
(60, 'الشماس سلوانس رمزي غطاس', '01210010020', 'student.c10.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'STU-2026-0060', 'حدائق الأهرام', 'رمزي غطاس فهيم', '01010010020', NULL, NULL, 'أغنسطس', 'male'),
(61, 'الشماس كيرلس هاني سعد', '01210010021', 'student.c10.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 7, 10, 'default-avatar.png', 'STU-2026-0061', 'حدائق الأهرام', 'هاني سعد بخيت', '01010010021', NULL, NULL, 'أغنسطس', 'male'),
(62, 'الشماس بيمن ناجي فؤاد', '01210010022', 'student.c11.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 8, 11, 'default-avatar.png', 'STU-2026-0062', 'حدائق الأهرام', 'ناجي فؤاد غالي', '01010010022', NULL, NULL, 'أغنسطس', 'male'),
(63, 'الشماس بيشوي عماد شفيق', '01210010023', 'student.c11.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 8, 11, 'default-avatar.png', 'STU-2026-0063', 'حدائق الأهرام', 'عماد شفيق صبحي', '01010010023', NULL, NULL, 'أغنسطس', 'male'),
(64, 'الشماس مارك مجدي نظمي', '01210010024', 'student.c11.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 8, 11, 'default-avatar.png', 'STU-2026-0064', 'حدائق الأهرام', 'مجدي نظمي خليل', '01010010024', NULL, NULL, 'أغنسطس', 'male'),
(65, 'الشماس ميرون نادر لمعي', '01210010025', 'student.c12.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 9, 12, 'default-avatar.png', 'STU-2026-0065', 'حدائق الأهرام', 'نادر لمعي عزيز', '01010010025', NULL, NULL, 'أغنسطس', 'male'),
(66, 'الشماس أبرآم شفيق كمال', '01210010026', 'student.c12.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 9, 12, 'default-avatar.png', 'STU-2026-0066', 'حدائق الأهرام', 'شفيق كمال مرقس', '01010010026', NULL, NULL, 'أغنسطس', 'male'),
(67, 'الشماس تيموثاوس رضا فايز', '01210010027', 'student.c12.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 3, 9, 12, 'default-avatar.png', 'STU-2026-0067', 'حدائق الأهرام', 'رضا فايز نصيف', '01010010027', NULL, NULL, 'أغنسطس', 'male'),
(68, 'الشماس أثناسيوس إبراهيم كامل', '01210010028', 'student.c13.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'STU-2026-0068', 'حدائق الأهرام', 'إبراهيم كامل عوض', '01010010028', NULL, NULL, 'أغنسطس', 'male'),
(69, 'الشماس كيرلس نسيم عزيز', '01210010029', 'student.c13.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'STU-2026-0069', 'حدائق الأهرام', 'نسيم عزيز حبيب', '01010010029', NULL, NULL, 'أغنسطس', 'male'),
(70, 'الشماس دانيال شريف فهمي', '01210010030', 'student.c13.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 10, 13, 'default-avatar.png', 'STU-2026-0070', 'حدائق الأهرام', 'شريف فهمي بطرس', '01010010030', NULL, NULL, 'أغنسطس', 'male'),
(71, 'الشماس دوماديوس يوحنا غالي', '01210010031', 'student.c14.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 11, 14, 'default-avatar.png', 'STU-2026-0071', 'حدائق الأهرام', 'يوحنا غالي صليب', '01010010031', NULL, NULL, 'أغنسطس', 'male'),
(72, 'الشماس إيسيذوروس وليم زكي', '01210010032', 'student.c14.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 11, 14, 'default-avatar.png', 'STU-2026-0072', 'حدائق الأهرام', 'وليم زكي ميخائيل', '01010010032', NULL, NULL, 'أغنسطس', 'male'),
(73, 'الشماس سمعان زكي فهيم', '01210010033', 'student.c14.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 11, 14, 'default-avatar.png', 'STU-2026-0073', 'حدائق الأهرام', 'زكي فهيم رمزي', '01010010033', NULL, NULL, 'أغنسطس', 'male'),
(74, 'الشماس أغسطينوس رفعت عوض', '01210010034', 'student.c15.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 12, 15, 'default-avatar.png', 'STU-2026-0074', 'حدائق الأهرام', 'رفعت عوض تادرس', '01010010034', NULL, NULL, 'إبديدياكون', 'male'),
(75, 'الشماس بنيامين فرج بطرس', '01210010035', 'student.c15.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 12, 15, 'default-avatar.png', 'STU-2026-0075', 'حدائق الأهرام', 'فرج بطرس جرجس', '01010010035', NULL, NULL, 'إبديدياكون', 'male'),
(76, 'الشماس يوسف مكرم لمعي', '01210010036', 'student.c15.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 4, 12, 15, 'default-avatar.png', 'STU-2026-0076', 'حدائق الأهرام', 'مكرم لمعي كامل', '01010010036', NULL, NULL, 'إبديدياكون', 'male'),
(77, 'الشماس يوحنا فؤاد خليل', '01210010037', 'student.c16.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 16, 'default-avatar.png', 'STU-2026-0077', 'حدائق الأهرام', 'فؤاد خليل غالي', '01010010037', NULL, NULL, 'إبديدياكون', 'male'),
(78, 'الشماس بطرس منير عزيز', '01210010038', 'student.c16.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 16, 'default-avatar.png', 'STU-2026-0078', 'حدائق الأهرام', 'منير عزيز نصيف', '01010010038', NULL, NULL, 'إبديدياكون', 'male'),
(79, 'الشماس ستيفن ماجد صليب', '01210010039', 'student.c16.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 16, 'default-avatar.png', 'STU-2026-0079', 'حدائق الأهرام', 'ماجد صليب عوض', '01010010039', NULL, NULL, 'إبديدياكون', 'male'),
(80, 'الشماس جورج أسعد فهيم', '01210010040', 'student.c17.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 17, 'default-avatar.png', 'STU-2026-0080', 'حدائق الأهرام', 'أسعد فهيم رمزي', '01010010040', NULL, NULL, 'إبديدياكون', 'male'),
(81, 'الشماس يعقوب رأفت نظمي', '01210010041', 'student.c17.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 17, 'default-avatar.png', 'STU-2026-0081', 'حدائق الأهرام', 'رأفت نظمي بطرس', '01010010041', NULL, NULL, 'إبديدياكون', 'male'),
(82, 'الشماس لوقا طلعت حبيب', '01210010042', 'student.c17.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', '2012-08-10', 'كنيسة مارجرجس', 5, 13, 17, 'default-avatar.png', 'STU-2026-0082', 'حدائق الأهرام', 'طلعت حبيب لمعي', '01010010042', NULL, NULL, 'إبديدياكون', 'male'),
(91, 'مينا عادل فؤاد', '01010010001', 'parent.c3.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0091', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(92, 'بيشوي ميلاد رزق', '01010010002', 'parent.c3.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0092', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(93, 'سامح منير غالي', '01010010003', 'parent.c3.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0093', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(94, 'شنودة شكري عوض', '01010010004', 'parent.c4.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0094', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(95, 'فادي يوسف جرجس', '01010010005', 'parent.c4.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0095', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(96, 'جورج عاطف غالي', '01010010006', 'parent.c4.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0096', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(97, 'سامح مينا عادل', '01010010007', 'parent.c5.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0097', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(98, 'مينا كمال عزيز', '01010010008', 'parent.c5.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0098', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(99, 'ريمون مدحت حبيب', '01010010009', 'parent.c5.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-0099', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(100, 'عاطف غالي خليل', '01010010010', 'parent.c6.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00100', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(101, 'مدحت نسيم تادرس', '01010010011', 'parent.c6.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00101', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(102, 'صبحي شفيق عوض', '01010010012', 'parent.c6.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00102', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(103, 'وجدي لمعي كامل', '01010010013', 'parent.c7.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00103', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(104, 'كمال عزيز مرقس', '01010010014', 'parent.c7.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00104', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(105, 'فايز صليب جرجس', '01010010015', 'parent.c7.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00105', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(106, 'لمعي حبيب نصيف', '01010010016', 'parent.c9.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00106', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(107, 'عادل فهمي بطرس', '01010010017', 'parent.c9.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00107', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(108, 'ميلاد شريف زكي', '01010010018', 'parent.c9.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00108', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(109, 'ناصر صليب ميخائيل', '01010010019', 'parent.c10.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00109', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(110, 'رمزي غطاس فهيم', '01010010020', 'parent.c10.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00110', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(111, 'هاني سعد بخيت', '01010010021', 'parent.c10.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00111', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(112, 'ناجي فؤاد غالي', '01010010022', 'parent.c11.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00112', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(113, 'عماد شفيق صبحي', '01010010023', 'parent.c11.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00113', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(114, 'مجدي نظمي خليل', '01010010024', 'parent.c11.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00114', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(115, 'نادر لمعي عزيز', '01010010025', 'parent.c12.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00115', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(116, 'شفيق كمال مرقس', '01010010026', 'parent.c12.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00116', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(117, 'رضا فايز نصيف', '01010010027', 'parent.c12.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00117', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(118, 'إبراهيم كامل عوض', '01010010028', 'parent.c13.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00118', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(119, 'نسيم عزيز حبيب', '01010010029', 'parent.c13.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00119', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(120, 'شريف فهمي بطرس', '01010010030', 'parent.c13.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00120', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(121, 'يوحنا غالي صليب', '01010010031', 'parent.c14.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00121', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(122, 'وليم زكي ميخائيل', '01010010032', 'parent.c14.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00122', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(123, 'زكي فهيم رمزي', '01010010033', 'parent.c14.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00123', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(124, 'رفعت عوض تادرس', '01010010034', 'parent.c15.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00124', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(125, 'فرج بطرس جرجس', '01010010035', 'parent.c15.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00125', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(126, 'مكرم لمعي كامل', '01010010036', 'parent.c15.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00126', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(127, 'فؤاد خليل غالي', '01010010037', 'parent.c16.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00127', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(128, 'منير عزيز نصيف', '01010010038', 'parent.c16.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00128', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(129, 'ماجد صليب عوض', '01010010039', 'parent.c16.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00129', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(130, 'أسعد فهيم رمزي', '01010010040', 'parent.c17.1@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00130', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(131, 'رأفت نظمي بطرس', '01010010041', 'parent.c17.2@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00131', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male'),
(132, 'طلعت حبيب لمعي', '01010010042', 'parent.c17.3@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', '1980-06-12', 'كنيسة مارجرجس', NULL, NULL, NULL, 'default-avatar.png', 'PRN-00132', 'حدائق الأهرام', NULL, NULL, NULL, NULL, 'إبصالتس (مرتل)', 'male');

-- 5. Servant Classes (ربط الخدام بالفصول المسندة إليهم)
INSERT IGNORE INTO `servant_classes` (`servant_id`, `class_id`) VALUES
(3, 5),
(6, 5),
(18, 10),
(21, 3),
(22, 4),
(23, 5),
(24, 6),
(25, 7),
(26, 9),
(27, 10),
(28, 11),
(29, 12),
(30, 13),
(31, 14),
(32, 15),
(33, 16),
(34, 17);

-- 6. Parent Student (ربط أولياء الأمور بأبنائهم الشمامسة)
INSERT IGNORE INTO `parent_student` (`parent_id`, `student_id`, `relationship`) VALUES
(5, 4, 'والد'),
(10, 8, 'والد'),
(11, 13, 'والد (أب)'),
(11, 14, 'والد (أب)'),
(11, 15, 'والد (أب)'),
(12, 16, 'والدة (أم)'),
(12, 17, 'والدة (أم)'),
(91, 41, 'والد (أب)'),
(92, 42, 'والد (أب)'),
(93, 43, 'والد (أب)'),
(94, 44, 'والد (أب)'),
(95, 45, 'والد (أب)'),
(96, 46, 'والد (أب)'),
(97, 47, 'والد (أب)'),
(98, 48, 'والد (أب)'),
(99, 49, 'والد (أب)'),
(100, 50, 'والد (أب)'),
(101, 51, 'والد (أب)'),
(102, 52, 'والد (أب)'),
(103, 53, 'والد (أب)'),
(104, 54, 'والد (أب)'),
(105, 55, 'والد (أب)'),
(106, 56, 'والد (أب)'),
(107, 57, 'والد (أب)'),
(108, 58, 'والد (أب)'),
(109, 59, 'والد (أب)'),
(110, 60, 'والد (أب)'),
(111, 61, 'والد (أب)'),
(112, 62, 'والد (أب)'),
(113, 63, 'والد (أب)'),
(114, 64, 'والد (أب)'),
(115, 65, 'والد (أب)'),
(116, 66, 'والد (أب)'),
(117, 67, 'والد (أب)'),
(118, 68, 'والد (أب)'),
(119, 69, 'والد (أب)'),
(120, 70, 'والد (أب)'),
(121, 71, 'والد (أب)'),
(122, 72, 'والد (أب)'),
(123, 73, 'والد (أب)'),
(124, 74, 'والد (أب)'),
(125, 75, 'والد (أب)'),
(126, 76, 'والد (أب)'),
(127, 77, 'والد (أب)'),
(128, 78, 'والد (أب)'),
(129, 79, 'والد (أب)'),
(130, 80, 'والد (أب)'),
(131, 81, 'والد (أب)'),
(132, 82, 'والد (أب)');

-- 7. Attendance (سجلات الحضور والغياب للقداس وفصول المدارس)
INSERT IGNORE INTO `attendance` (`student_id`, `servant_id`, `attendance_date`, `status`, `notes`) VALUES
(4, 3, '2026-09-06', 'present', 'حضور بالقداس والمدارس'),
(4, 3, '2026-09-13', 'present', 'حضور بالقداس والمدارس'),
(7, 3, '2026-09-06', 'present', 'حضور بالقداس والمدارس'),
(7, 3, '2026-09-13', 'present', 'حضور بالقداس والمدارس'),
(41, 21, '2026-09-06', 'present', 'حضور ممتاز بالقداس وخورس الشمامسة'),
(41, 21, '2026-09-13', 'present', 'حضور بالقداس'),
(42, 21, '2026-09-13', 'present', 'حضور خورس الروضة'),
(44, 22, '2026-09-13', 'present', 'التزام ممتاز بالتونة'),
(47, 23, '2026-09-13', 'present', 'حضور مبكر 06:30 ص'),
(50, 24, '2026-09-13', 'present', 'حضور باكر والتزام بالقداس');

-- 8. Points (نقاط التحفيز والسلوك والمواظبة)
INSERT INTO `points` (`student_id`, `servant_id`, `points`, `type`, `reason`) VALUES
(4, 3, 15, 'positive', 'حفظ لحن إكإسماروؤوت ممتاز'),
(4, 3, 10, 'positive', 'حضور مبكر للقداس الإلهي'),
(7, 3, 20, 'positive', 'المواظبة على تسبحة نصف الليل'),
(8, 3, 15, 'positive', 'الدرجة النهائية في اختبار الطقس'),
(41, 21, 25, 'positive', 'حفظ أرباع الناقوس كاملة بإتقان'),
(42, 21, 15, 'positive', 'هدوء والتزام داخل الهيكل'),
(44, 22, 20, 'positive', 'التفوق في مسابقة الألحان الكبرى'),
(47, 23, 30, 'positive', 'حفظ مرد الإنجيل والمزمور القبطي'),
(50, 24, 15, 'positive', 'مساعدة الخدام في ترتيب الخورس');

-- 9. Evaluations (التقييمات الروحية والألحان والسلوك)
INSERT INTO `evaluations` (`student_id`, `servant_id`, `behavior_score`, `hymn_memorization`, `church_attending`, `notes`, `evaluation_date`) VALUES
(4, 3, 10, 9, 10, 'شماس ممتاز ومواظب جداً على صلوات الكنيسة', '2026-09-13'),
(7, 3, 9, 10, 9, 'حفظ الألحان ممتاز مع صوت منضبط ورائع', '2026-09-13'),
(41, 21, 10, 10, 10, 'مستواه ممتاز جداً في حفظ مردات القداس والتزامه بالمواعيد', '2026-09-13'),
(44, 22, 9, 9, 10, 'التزام رائع بالهيكل وروح الصلاة', '2026-09-13'),
(47, 23, 10, 8, 9, 'يجتهد جداً في حفظ الألحان القبطية', '2026-09-13');

-- 10. Courses (المناهج والدروس الشماسية والطقسية)
INSERT INTO `courses` (`id`, `title`, `description`, `stage_id`, `grade_id`, `external_link`, `created_by`) VALUES
(1, 'طقس أسبوع الآلام والجمعة العظيمة', 'شرح تفصيلي لطقوس وصلوات أسبوع البصخة المقدسة وألحانها الحزايني', 2, 3, 'https://youtube.com', 1),
(2, 'دراسات في سفر أعمال الرسل (الإبركسيس)', 'تفسير وتأملات في خدمة الرسل وبدايات الكنيسة الأولى', 3, 7, 'https://youtube.com', 1),
(3, 'طقس القداس الإلهي (رفع بخور عشية وباكر)', 'خطوات رفع بخور عشية وباكر وحركات الشماس داخل وخارج الهيكل', 4, 10, 'https://youtube.com', 1),
(4, 'مقدمة في قواعد اللغة القبطية والحروف', 'دراسة الأبجدية القبطية ونطق الحروف والقواعد الصوتية', 1, 2, 'https://youtube.com', 1);

-- 11. Hymns (مكتبة الألحان القبطية والتسجيلات)
INSERT INTO `hymns` (`id`, `title`, `description`, `notes`, `video_link`, `created_by`) VALUES
(1, 'لحن إكإسماروؤوت (Ek-Smaro-out)', 'لحن يقال في الأعياد والمناسبات والقداس الإلهي', 'مبارك أنت أيها المسيح إلهنا مع أبيك الصالح والروح القدس لأنك أتيت وخلصتنا.', 'https://youtube.com', 1),
(2, 'لحن أريبسالين (Aripsalin)', 'لحن يقال في الهوس الثالث بتسبحة نصف الليل الكيهكية والسنوية', 'سبحوا الرب لأنه صالح وهللويا لأن إلى الأبد رحمته...', 'https://youtube.com', 1),
(3, 'لحن بيك ثورونوس (Pekthronos)', 'لحن سادوم حزايني يقال في ساعات البصخة والجمعة الكبيرة', 'كرسيك يا الله إلى دهر الدهور، قضيب الاستقامة هو قضيب ملكك...', 'https://youtube.com', 1),
(4, 'لحن هيتنيات القداس الباسيلي (Hiten)', 'مردات شفاعات القديسين في رفع بخور باكر والقداس', 'بشفاعات والدة الإله القديسة مريم يا رب أنعم لنا بمغفرة خطايانا...', 'https://youtube.com', 1);

-- 12. Announcements (الإعلانات والتنبيهات العامة والخاصة)
INSERT INTO `announcements` (`id`, `title`, `content`, `target_type`, `created_by`) VALUES
(1, 'أهلاً بكم في مدرسة الشهيد إسطفانوس للشمامسة ☦️', 'نرحب بجميع الشمامسة والخدام وأولياء الأمور في العام الدراسي الجديد. برجاء الالتزام بالمواعيد والزي الكنسي (التونة).', 'everyone', 1),
(2, 'مواعيد اختبارات حفظ الألحان الشهرية 📅', 'تنويه لجميع الشمامسة بجميع المراحل: تبدأ اختبارات تقييم الألحان اعتباراً من يوم الجمعة القادم بعد القداس مباشرة.', 'students', 1),
(3, 'اجتماع أولياء الأمور الدوري 👨‍👩‍👧‍👦', 'يدعو مجلس إدارة مدرسة الشمامسة السادة أولياء الأمور لحضور اللقاء التشاوري لمتابعة تقدم الأبناء ومستواهم الروحي والطقسي.', 'parents', 1);

-- 13. Notifications (الإشعارات)
INSERT INTO `notifications` (`user_id`, `title`, `message`, `is_read`) VALUES
(4, 'نقاط جديدة!', 'تمت إضافة 15 نقطة لتفوقك في حفظ لحن إكإسماروؤوت.', 0),
(41, 'أهلاً بك في مدرسة الشمامسة', 'تم تفعيل حسابك بنجاح في مدرسة الشمامسة. نتمنى لك عاماً مباركاً.', 1);

-- 14. Audit Logs (سجلات الأمان)
INSERT INTO `audit_logs` (`user_id`, `action`, `details`, `ip_address`) VALUES
(1, 'SYSTEM_INIT', 'تهيئة قاعدة البيانات الشاملة بنجاح مع 100+ حساب تجريبي', '127.0.0.1');

-- 15. Exams & Questions (بنك الامتحانات والاختبارات الأسبوعية)
INSERT INTO `exams` (`id`, `title`, `description`, `stage_id`, `grade_id`, `class_id`, `duration_minutes`, `is_published`, `created_by`) VALUES
(1, 'اختبار طقس وألحان القداس الإلهي', 'اختبار عام لتقييم حفظ مردات الشماس وطقوس رفع بخور عشية وباكر', 2, 3, 5, 20, 1, 1),
(2, 'مسابقة سفر أعمال الرسل والألحان', 'مسابقة كبرى للشماس المتميز في حفظ الألحان القبطية ودراسة الكتاب', 3, 7, 10, 30, 1, 1);

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `points`) VALUES
(1, 1, 'ما معنى كلمة إكإسماروؤوت (Ek-Smaro-out) باللغة القبطية؟', 'mcq', 'مبارك أنت', 'قدوس أنت', 'عظيم أنت', 'صالح أنت', 'a', 5),
(2, 1, 'متى يُقال لحن إكإسماروؤوت في الكنيسة القبطية؟', 'mcq', 'في الأعياد والقداس الإلهي', 'في صلوات التجنيز فقط', 'في الجمعة الكبيرة فقط', 'في أسبوع البصخة فقط', 'a', 5),
(3, 2, 'من هو كاتب سفر أعمال الرسل (الإبركسيس)؟', 'mcq', 'القديس لوقا الإنجيلي', 'القديس بطرس الرسول', 'القديس بولس الرسول', 'القديس يوحنا الحبيب', 'a', 5),
(4, 2, 'ما معنى رتبة إبصالتس (Psaltis) في الكنيسة القبطية؟', 'mcq', 'مرتل', 'قارئ', 'مساعد شماس', 'شماس كامل', 'a', 5);

INSERT INTO `exam_results` (`exam_id`, `student_id`, `score`, `total_marks`, `status`, `taken_at`) VALUES
(1, 4, 10, 10, 'completed', '2026-09-12 10:30:00'),
(1, 41, 10, 10, 'completed', '2026-09-12 11:00:00');

-- 16. Liturgy Roster (جداول خدمة القداسات الإلهية)
INSERT INTO `liturgy_roster` (`id`, `title`, `service_date`, `class_id`, `hymn_required`, `notes`, `created_by`) VALUES
(1, 'قداس الأحد - تذكار الشهيد مارجرجس (خورس ابتدائي)', '2026-09-20', 5, 'لحن أريبسالين + الهيتنيات', 'الحضور بالتواليت والتونة في تمام 06:30 ص والتواجد بالهيكل بخشوع', 1),
(2, 'قداس الجمعة - صلوات خورس الإعدادي والثانوي', '2026-09-25', 10, 'مرد الإبركسيس ومزمور القداس', 'قراءة النبوات والبولس والكاثوليكون باللغة القبطية والعربية', 1);

INSERT INTO `liturgy_roster_students` (`roster_id`, `student_id`, `role_name`, `admin_notes`, `status`) VALUES
(1, 4, 'إنجيل القداس', 'قراءة إنجيل القداس بالقبطي والعربي', 'confirmed'),
(1, 41, 'خدمة مذبح', 'التواجد بالهيكل مبكراً بالتونة', 'confirmed'),
(1, 42, 'خدمة باكر', 'رفع بخور باكر ومردات الشماس', 'confirmed'),
(2, 50, 'بولس', 'قراءة فصل البولس بالقبطي والعربي', 'confirmed'),
(2, 51, 'كاثوليكون', 'قراءة الكاثوليكون بخشوع', 'confirmed');

-- 17. Rewards & Orders (متجر الجوائز والهدايا الشماسية)
INSERT INTO `rewards` (`id`, `title`, `description`, `points_cost`, `stock_quantity`, `image_url`) VALUES
(1, 'كتاب الخولاجي المقدس الملحن', 'كتاب فاخر يتضمن صلوات القداسات الثلاثة (الباسيلي، الغريغوري، الكيرلسي) بالألحان الكاملة', 25, 15, 'default-reward.png'),
(2, 'أجبية الصلاة بالألحان (عربي - قبطي)', 'كتاب السبع صلوات النهارية والليلية مزخرف بتصميم قبطي أصيل', 15, 20, 'default-reward.png'),
(3, 'وسام الشماس المثالي الفضي', 'وسام تقديري معدني يُمنح للشماس الأكثر مواظبة وحفظاً للألحان', 40, 10, 'default-reward.png'),
(4, 'صليب شماسي خشبي من حفر القدس', 'صليب يد شماسي مبارك مصنوع من خشب الزيتون العريق', 20, 25, 'default-reward.png');

INSERT INTO `reward_orders` (`reward_id`, `student_id`, `points_spent`, `status`) VALUES
(1, 4, 25, 'fulfilled'),
(2, 41, 15, 'pending');

-- 18. Pastoral Visitations (سجل الافتقاد الرعوي للخدام)
INSERT INTO `pastoral_visitations` (`student_id`, `servant_id`, `type`, `notes`, `visit_date`) VALUES
(4, 3, 'home_visit', 'تمت زيارة الشماس بالمنزل والاطمئنان على دراسته ومواظبته على الصلاة ومراجعة الألحان معه', '2026-09-10'),
(41, 21, 'phone', 'اتصال هاتفي بولي الأمر للاطمئنان على صحة الشماس وتشجيعه على الحضور باكر', '2026-09-12');

INSERT INTO `visitations` (`student_id`, `servant_id`, `visit_type`, `notes`, `visit_date`, `status`) VALUES
(4, 3, 'home_visit', 'زيارة منزلية رعوية دورية', '2026-09-10', 'completed'),
(41, 21, 'phone_call', 'متابعة هاتفية للاطمئنان والتنسيق', '2026-09-12', 'completed');

-- 19. Events & Registrations (الأنشطة والرحلات الكنسية)
INSERT INTO `events` (`id`, `title`, `description`, `event_type`, `event_date`, `location`, `price`, `max_capacity`, `created_by`) VALUES
(1, 'رحلة دير القديس العظيم مارمينا العجائبي وكينج مريوط 🚌', 'رحلة روحية وترفيهية ليوم كامل تشمل زيارة مزار القديس مارمينا والبابا كيرلس السادس مع فقرات ألحان ومسابقات', 'trip', '2026-10-06', 'دير الشهيد مارمينا بمريوط', 150.00, 50, 1),
(2, 'الخلوة الروحية السنوية لشمامسة المرحلة الإعدادية والثانوية ⛺', 'خلوة لمدة يومين تتضمن دراسة طقس التسبحة، حفظ ألحان أسبوع الآلام، وورش عمل روحية وشماسية متقدمة', 'retreat', '2026-10-23', 'بيت ماريوحنا الحبيب - وادي النطرون', 250.00, 40, 1);

INSERT INTO `event_registrations` (`event_id`, `user_id`, `seats_count`, `notes`, `status`) VALUES
(1, 4, 1, 'حجز مؤكد مع مشرف الباص', 'confirmed'),
(1, 41, 1, 'حجز مخدوم فصل KG2', 'registered'),
(2, 50, 1, 'مشترك في ورشة الألحان المتقدمة', 'confirmed');

INSERT INTO `calendar_events` (`title`, `event_date`, `event_type`, `description`) VALUES
('عيد استشهاد القديس إسطفانوس رئيس الشمامسة', '2026-10-01', 'church_feast', 'تذكار رئيس الشمامسة وأول الشهداء القديس إسطفانوس شفيع المدرسة'),
('بدء صوم الميلاد المجيد', '2026-11-25', 'fasting', 'بدء الصوم المقدس وصلوات التسبحة الكيهكية المباركة');

-- 20. Deacon Books (مكتبة الكتب والمراجع الشماسية)
INSERT INTO `deacon_books` (`id`, `title`, `author`, `category`, `description`, `pdf_file`, `cover_image`, `file_size`, `downloads_count`, `created_by`) VALUES
(1, 'كتاب خدمة الشماس في القداسات الإلهية', 'مطرانية بني سويف ومدرسة الشمامسة', 'طقس وخدمة الشماس', 'دليل شامل لجميع مردات الشماس القبطية والمعربة في القداس الباسيلي والغريغوري والكيرلسي مع شرح طقس الحركات داخل الهيكل.', 'deacon_service_book.pdf', 'deacon_service_cover.jpg', '4.5 MB', 12, 1),
(2, 'الإبصلمودية السنوية المقدسة (التسبحة)', 'جمعية نهضة الكنائس القبطية الأرثوذكسية', 'التسبحة والألحان', 'كتاب التسبحة اليومية الشامل لجميع الهوسات والثيؤطوكيات والذكصولوجيات والإبصاليات السنوية للمرتلين والشمامسة.', 'annual_psalmodia.pdf', 'psalmodia_cover.jpg', '8.2 MB', 25, 1),
(3, 'كتاب الخولاجي المقدس (الصلوات والقداسات الثلاثة)', 'دير القديس أنبا مقار الكبير', 'نصوص القداسات', 'النصوص الليتورجية الكاملة لقداسات الكنيسة القبطية الأرثوذكسية (صلاة رفع بخور عشية وباكر، القداس الباسيلي، الغريغوري، والكيرلسي).', 'holy_euchologion.pdf', 'euchologion_cover.jpg', '6.1 MB', 40, 1),
(4, 'دلال أسبوع الآلام وطقس البصخة المقدسة', 'بطريركية الأقباط الأرثوذكس', 'مناسبات وأعياد', 'الترتيب الطقسي لصلوات البصخة المقدسة نهاراً وليلاً وألحان ومردات خورس الشمامسة خلال أسبوع الآلام وحتى سبت الفرح وأحد القيامة.', 'pascha_week_book.pdf', 'pascha_cover.jpg', '12.0 MB', 33, 1);

-- 21. System Settings (إعدادات النظام العامة)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('store_enabled', '1');

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- End of deacons.sql Database Dump (Successfully Generated)
-- ========================================================

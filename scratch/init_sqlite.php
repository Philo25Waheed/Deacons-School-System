<?php

$sqliteFile = __DIR__.'/../database/database.sqlite';
$db = new PDO('sqlite:'.$sqliteFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS stages (id INTEGER PRIMARY KEY AUTOINCREMENT, name_ar TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS grades (id INTEGER PRIMARY KEY AUTOINCREMENT, stage_id INTEGER NOT NULL, name_ar TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS classes (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_id INTEGER NOT NULL, name_ar TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');

$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    email TEXT UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL,
    status TEXT DEFAULT "pending",
    dob DATE,
    church_name TEXT DEFAULT "كنيسة مارجرجس",
    stage_id INTEGER,
    grade_id INTEGER,
    class_id INTEGER,
    profile_pic TEXT DEFAULT "default-avatar.png",
    qr_code_token TEXT UNIQUE,
    remember_token TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);');

$db->exec('CREATE TABLE IF NOT EXISTS parent_student (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER NOT NULL, student_id INTEGER NOT NULL, relationship TEXT DEFAULT "والد / والدة", created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS servant_classes (id INTEGER PRIMARY KEY AUTOINCREMENT, servant_id INTEGER NOT NULL, class_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER NOT NULL, servant_id INTEGER NOT NULL, attendance_date DATE NOT NULL, status TEXT DEFAULT "present", scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP, notes TEXT);');
$db->exec('CREATE TABLE IF NOT EXISTS points (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER NOT NULL, servant_id INTEGER NOT NULL, points INTEGER NOT NULL, type TEXT NOT NULL, reason TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS evaluations (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER NOT NULL, servant_id INTEGER NOT NULL, behavior_score INTEGER DEFAULT 5, hymn_memorization INTEGER DEFAULT 5, church_attending INTEGER DEFAULT 5, notes TEXT, evaluation_date DATE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS courses (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT, stage_id INTEGER, grade_id INTEGER, pdf_file TEXT, audio_file TEXT, video_file TEXT, external_link TEXT, created_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS hymns (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT, audio_file TEXT, pdf_file TEXT, video_link TEXT, notes TEXT, created_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS announcements (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, content TEXT NOT NULL, target_type TEXT DEFAULT "everyone", target_id INTEGER, created_by INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, is_read INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');
$db->exec('CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT NOT NULL, details TEXT, ip_address TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);');

// Seed stages
$db->exec("INSERT OR IGNORE INTO stages (id, name_ar) VALUES
(1, 'مرحلة الروضة (Kindergarten)'),
(2, 'المرحلة الإبتدائية (Primary)'),
(3, 'المرحلة الإعدادية (Preparatory)'),
(4, 'المرحلة الثانوية (Secondary)'),
(5, 'مرحلة الجامعة والشباب (University)');");

// Seed default users (password: Admin@123456)
$stmt = $db->prepare('INSERT OR IGNORE INTO users (id, full_name, phone, email, password, role, status, church_name, qr_code_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([1, 'المدير المسؤول', '01000000000', 'admin@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'admin', 'active', 'كنيسة العذراء مريم', 'ADM-000001']);
$stmt->execute([2, 'الخادم مينا عادل', '01111111111', 'mina@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'servant', 'active', 'كنيسة مارجرجس', 'SRV-000002']);
$stmt->execute([3, 'الشماس يوسف مينا', '01222222222', 'youssef@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'student', 'active', 'كنيسة مارجرجس', 'STU-2026-0003']);
$stmt->execute([4, 'ولي الأمر مينا سامي', '01333333333', 'parent@deacons.school', '$2y$12$YU5dwy5TDoDzFyvC3r6ksO7dVgc/7N7l4RxgklhILJwl6jAL426D2', 'parent', 'active', 'كنيسة مارجرجس', 'PRN-000004']);

echo 'SQLite database created successfully!';

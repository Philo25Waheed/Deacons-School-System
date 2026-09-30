<?php

// Helper Functions

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';

/**
 * XSS Sanitization helper
 */
if (! function_exists('sanitize')) {
    function sanitize(?string $data): string
    {
        if ($data === null) {
            return '';
        }

        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSV Formula Injection (DDE) Neutralization helper
 * Prevents execution of formulas starting with =, +, -, @, \t, \r in Microsoft Excel / LibreOffice
 */
if (! function_exists('sanitize_csv_cell')) {
    function sanitize_csv_cell($value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;
        $trimmed = ltrim($str);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}

if (! function_exists('sanitize_csv_row')) {
    function sanitize_csv_row(array $row): array
    {
        return array_map('sanitize_csv_cell', $row);
    }
}

if (! function_exists('log_action')) {
    function log_action(?int $userId, string $action, string $details = ''): void
    {
        try {
            if (function_exists('getDB')) {
                $db = getDB();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stmt = $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $action, $details, $ip]);
            }
        } catch (Throwable $e) {
            // Silently fail logging if DB issues occur to avoid breaking execution
        }
    }
}

if (! function_exists('auto_link_parents')) {
    function auto_link_parents(int $userId): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT id, role, phone, father_phone, mother_phone FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (! $user) {
                return;
            }

            if ($user['role'] === 'student') {
                // Check Father Phone
                if (! empty($user['father_phone'])) {
                    $fStmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND role = 'parent'");
                    $fStmt->execute([$user['father_phone']]);
                    $fatherId = $fStmt->fetchColumn();
                    if ($fatherId) {
                        $db->prepare("INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (?, ?, 'والد (أب)')")
                            ->execute([$fatherId, $user['id']]);
                    }
                }

                // Check Mother Phone
                if (! empty($user['mother_phone'])) {
                    $mStmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND role = 'parent'");
                    $mStmt->execute([$user['mother_phone']]);
                    $motherId = $mStmt->fetchColumn();
                    if ($motherId) {
                        $db->prepare("INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (?, ?, 'والدة (أم)')")
                            ->execute([$motherId, $user['id']]);
                    }
                }
            } elseif ($user['role'] === 'parent') {
                // Check if any student listed this parent's phone as father or mother
                $sStmt = $db->prepare("SELECT id, father_phone, mother_phone FROM users WHERE role = 'student' AND (father_phone = ? OR mother_phone = ?)");
                $sStmt->execute([$user['phone'], $user['phone']]);
                $matchedStudents = $sStmt->fetchAll();

                foreach ($matchedStudents as $stu) {
                    $rel = ($stu['father_phone'] === $user['phone']) ? 'والد (أب)' : 'والدة (أم)';
                    $db->prepare('INSERT IGNORE INTO parent_student (parent_id, student_id, relationship) VALUES (?, ?, ?)')
                        ->execute([$user['id'], $stu['id'], $rel]);
                }
            }
        } catch (Throwable $e) {
            // Silently catch mapping error
        }
    }
}

if (! function_exists('send_json')) {
    function send_json(array $response, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (! function_exists('build_whatsapp_link')) {
    function build_whatsapp_link(string $phone, string $message): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (! str_starts_with($cleanPhone, '20') && strlen($cleanPhone) === 11) {
            $cleanPhone = '2'.$cleanPhone;
        }

        return 'https://api.whatsapp.com/send?phone='.urlencode($cleanPhone).'&text='.urlencode($message);
    }
}

if (! function_exists('format_arabic_date')) {
    function format_arabic_date(string $dateStr): string
    {
        $time = strtotime($dateStr);
        $months = [
            'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل',
            'May' => 'مايو', 'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس',
            'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر',
        ];
        $monthEn = date('M', $time);
        $monthAr = $months[$monthEn] ?? $monthEn;

        return date('d', $time).' '.$monthAr.' '.date('Y', $time);
    }
}

/**
 * Get assigned class IDs for a servant
 *
 * @return int[]
 */
if (! function_exists('get_servant_assigned_class_ids')) {
    function get_servant_assigned_class_ids(int $servantId): array
    {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT class_id FROM servant_classes WHERE servant_id = ?');
            $stmt->execute([$servantId]);
            $assigned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

            // Also include direct class_id from users table if assigned
            $uStmt = $db->prepare('SELECT class_id FROM users WHERE id = ?');
            $uStmt->execute([$servantId]);
            $directClass = $uStmt->fetchColumn();
            if ($directClass && ! in_array((int) $directClass, $assigned, true)) {
                $assigned[] = (int) $directClass;
            }

            return $assigned;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Get accessible class IDs for current or specified user
 * Returns null for admin (unrestricted full access), or array of class IDs for servant
 *
 * @return int[]|null
 */
if (! function_exists('get_user_accessible_class_ids')) {
    function get_user_accessible_class_ids(?array $user = null): ?array
    {
        $user = $user ?? ($_SESSION['user'] ?? null);
        if (! $user) {
            return [];
        }

        if (($user['role'] ?? '') === 'admin') {
            return null; // Full unrestricted access
        }

        return get_servant_assigned_class_ids((int) $user['id']);
    }
}

/**
 * Check if a servant/admin has access to a specific class
 */
if (! function_exists('can_servant_access_class')) {
    function can_servant_access_class(int $userId, ?int $classId, string $role = 'servant'): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if (! $classId) {
            return false;
        }

        $assignedClassIds = get_servant_assigned_class_ids($userId);

        return in_array($classId, $assignedClassIds, true);
    }
}

/**
 * Check if a servant/admin has access to a specific student
 */
if (! function_exists('can_servant_access_student')) {
    function can_servant_access_student(int $userId, int $studentId, string $role = 'servant'): bool
    {
        if ($role === 'admin') {
            return true;
        }

        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT class_id FROM users WHERE id = ? AND role = "student"');
            $stmt->execute([$studentId]);
            $studentClassId = $stmt->fetchColumn();

            if (! $studentClassId) {
                return false;
            }

            return can_servant_access_class($userId, (int) $studentClassId, $role);
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Generate a unique user code (3 letters + 4 digits: e.g. STU-1024, SRV-2015, PRN-3012, ADM-1001)
 */
if (! function_exists('generate_unique_user_code')) {
    function generate_unique_user_code(string $role = 'student', ?PDO $db = null): string
    {
        $db = $db ?? getDB();
        $prefix = match ($role) {
            'student' => 'STU',
            'servant' => 'SRV',
            'parent' => 'PRN',
            'admin' => 'ADM',
            default => 'USR',
        };

        for ($i = 0; $i < 100; $i++) {
            $digits = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$digits}";
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE qr_code_token = ?');
            $stmt->execute([$code]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
        }

        $stmtMax = $db->prepare('SELECT qr_code_token FROM users WHERE qr_code_token LIKE ? ORDER BY id DESC LIMIT 1');
        $stmtMax->execute(["{$prefix}-%"]);
        $lastCode = $stmtMax->fetchColumn();
        $lastNum = $lastCode ? (int) substr($lastCode, 4) : 1000;

        return "{$prefix}-".($lastNum + 1);
    }
}

/**
 * Trigger daily birthday notifications for students whose birthday is today:
 * 1. Personal congratulation notification to the birthday student.
 * 2. Notification to classmates in the same class to congratulate their friend.
 * 3. Notification to the servant(s) responsible for that class.
 */
if (! function_exists('trigger_daily_birthday_notifications')) {
    function trigger_daily_birthday_notifications(?PDO $db = null, bool $force = false): void
    {
        static $alreadyRunInRequest = false;
        if (! $force && $alreadyRunInRequest) {
            return;
        }
        $alreadyRunInRequest = true;

        try {
            $db = $db ?? getDB();
            $todayMD = date('m-d');
            $todayYear = date('Y');

            // For cross-database compatibility (MySQL vs SQLite date formatting)
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $dateCondition = ($driver === 'mysql')
                ? "DATE_FORMAT(u.dob, '%m-%d') = ?"
                : "strftime('%m-%d', u.dob) = ?";

            $stmt = $db->prepare("
                SELECT u.id, u.full_name, u.class_id, u.dob, c.name_ar as class_name, g.name_ar as grade_name
                FROM users u
                LEFT JOIN classes c ON u.class_id = c.id
                LEFT JOIN grades g ON u.grade_id = g.id
                WHERE u.role = 'student' 
                  AND u.status = 'active'
                  AND u.dob IS NOT NULL 
                  AND {$dateCondition}
            ");
            $stmt->execute([$todayMD]);
            $birthdayStudents = $stmt->fetchAll();

            if (empty($birthdayStudents)) {
                return;
            }

            // Prepared check and insert statements
            $checkNotifStmt = $db->prepare('
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND title = ? AND created_at >= ?
            ');
            $insertNotifStmt = $db->prepare('
                INSERT INTO notifications (user_id, title, message) 
                VALUES (?, ?, ?)
            ');

            $todayStart = date('Y-m-d 00:00:00');

            foreach ($birthdayStudents as $student) {
                $studentId = (int) $student['id'];
                $studentName = $student['full_name'];
                $classId = (int) ($student['class_id'] ?? 0);
                $className = ($student['grade_name'] ? $student['grade_name'].' - ' : '').($student['class_name'] ?? 'الفصل');

                // 1. Notification to the Birthday Student
                $titleToStudent = '🎂 سنة حلوة مع بابا يسوع! 🎉';
                $checkNotifStmt->execute([$studentId, $titleToStudent, $todayStart]);
                if ((int) $checkNotifStmt->fetchColumn() === 0) {
                    $msgToStudent = "كل سنة وأنت طيب وبخير يا شماسنا المبارك {$studentName}! 🌟 أسرة مدرسة الشهيد إسطفانوس تتمنى لك عاماً سعيداً ملئ بالبركة والنعمة والنمو الروحي في خدمة كنيستنا المقدسة. 🎈";
                    $insertNotifStmt->execute([$studentId, $titleToStudent, $msgToStudent]);
                }

                if ($classId > 0) {
                    // 2. Notification to Classmates in the same class (excluding birthday student)
                    $cmStmt = $db->prepare("
                        SELECT id FROM users 
                        WHERE role = 'student' 
                          AND status = 'active' 
                          AND class_id = ? 
                          AND id != ?
                    ");
                    $cmStmt->execute([$classId, $studentId]);
                    $classmateIds = $cmStmt->fetchAll(PDO::FETCH_COLUMN);

                    $titleToClassmates = "🎂 عيد ميلاد زميلكم {$studentName}! 🎈";
                    $msgToClassmates = "النهارده عيد ميلاد صديقكم وزميلكم في الفصل الشماس {$studentName} ({$className})! 🎉 متنسوش تباركوا له وتقولوا له كل سنة وأنت طيب وبخير! 🌟";

                    foreach ($classmateIds as $cId) {
                        $checkNotifStmt->execute([(int) $cId, $titleToClassmates, $todayStart]);
                        if ((int) $checkNotifStmt->fetchColumn() === 0) {
                            $insertNotifStmt->execute([(int) $cId, $titleToClassmates, $msgToClassmates]);
                        }
                    }

                    // 3. Notification to Servant(s) responsible for this class
                    $srvStmt = $db->prepare("
                        SELECT DISTINCT u.id 
                        FROM users u
                        LEFT JOIN servant_classes sc ON u.id = sc.servant_id
                        WHERE u.role = 'servant' 
                          AND u.status = 'active'
                          AND (sc.class_id = ? OR u.class_id = ?)
                    ");
                    $srvStmt->execute([$classId, $classId]);
                    $servantIds = $srvStmt->fetchAll(PDO::FETCH_COLUMN);

                    $titleToServant = "🎂 عيد ميلاد مخدومك: {$studentName} 🎉";
                    $msgToServant = "تنبيه خادم الفصل: اليوم يوافق عيد ميلاد الشماس المبارك {$studentName} في فصل ({$className}). 🎈 يرجى الافتقاد وتهنئته تليفونياً أو في الكنيسة. 🕊️";

                    foreach ($servantIds as $sId) {
                        $checkNotifStmt->execute([(int) $sId, $titleToServant, $todayStart]);
                        if ((int) $checkNotifStmt->fetchColumn() === 0) {
                            $insertNotifStmt->execute([(int) $sId, $titleToServant, $msgToServant]);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently fail to avoid breaking page render
        }
    }
}

/**
 * Check if Tayo Exhibition / Store is visible & enabled for students
 */
if (! function_exists('is_store_enabled')) {
    function is_store_enabled(?PDO $db = null): bool
    {
        try {
            $db = $db ?? getDB();
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "store_enabled"');
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null) {
                return true; // Default is enabled (visible)
            }

            return (string) $val === '1';
        } catch (Throwable $e) {
            return true; // Fallback to enabled
        }
    }
}

/**
 * Set Tayo Exhibition / Store visibility (Admin toggle)
 */
if (! function_exists('set_store_enabled')) {
    function set_store_enabled(bool $enabled, ?PDO $db = null): bool
    {
        try {
            $db = $db ?? getDB();
            $db->exec('CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NULL
            )');
            $stmt = $db->prepare('REPLACE INTO system_settings (setting_key, setting_value) VALUES ("store_enabled", ?)');

            return $stmt->execute([$enabled ? '1' : '0']);
        } catch (Throwable $e) {
            return false;
        }
    }
}

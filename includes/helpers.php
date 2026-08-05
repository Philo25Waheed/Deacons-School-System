<?php

// Helper Functions

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

if (! function_exists('log_action')) {
    function log_action(?int $userId, string $action, string $details = ''): void
    {
        try {
            $db = getDB();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $action, $details, $ip]);
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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


<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('admin');

$db = getDB();
$type = sanitize($_GET['type'] ?? 'students');

if (isset($_GET['download']) && $_GET['download'] === '1') {
    $filename = "deacons_export_{$type}_".date('Y-m-d').'.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // Add UTF-8 BOM for Excel Arabic compatibility
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    if ($type === 'students') {
        fputcsv($out, ['المعرف ID', 'الاسم بالكامل', 'رقم الهاتف', 'البريد الإلكتروني', 'الجنس', 'الرتبة الشموسية', 'المرحلة', 'الصف', 'الفصل', 'هاتف الأب', 'هاتف الأم', 'كود QR', 'الحالة']);
        $stmt = $db->query("
            SELECT u.id, u.full_name, u.phone, u.email, u.gender, u.deacon_rank, 
                   s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
                   u.father_phone, u.mother_phone, u.qr_code_token, u.status
            FROM users u
            LEFT JOIN stages s ON u.stage_id = s.id
            LEFT JOIN grades g ON u.grade_id = g.id
            LEFT JOIN classes c ON u.class_id = c.id
            WHERE u.role = 'student'
            ORDER BY u.full_name ASC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, sanitize_csv_row($row));
        }
    } elseif ($type === 'attendance') {
        fputcsv($out, ['المعرف ID', 'اسم الشماس', 'رقم الهاتف', 'المرحلة', 'الفصل', 'تاريخ الحضور', 'الحالة', 'الخادم القائم بالمسح']);
        $stmt = $db->query('
            SELECT a.id, u.full_name, u.phone, s.name_ar as stage, c.name_ar as class, a.attendance_date, a.status, srv.full_name as servant_name
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            LEFT JOIN stages s ON u.stage_id = s.id
            LEFT JOIN classes c ON u.class_id = c.id
            LEFT JOIN users srv ON a.servant_id = srv.id
            ORDER BY a.attendance_date DESC
        ');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, sanitize_csv_row($row));
        }
    } elseif ($type === 'exams') {
        fputcsv($out, ['المعرف ID', 'اسم الشماس', 'عنوان الاختبار', 'الدرجة المحصلة', 'الدرجة الكلية', 'تاريخ الاختبار']);
        $stmt = $db->query('
            SELECT r.id, u.full_name, e.title as exam_title, r.score, r.total_marks, r.taken_at
            FROM exam_results r
            JOIN users u ON r.student_id = u.id
            JOIN exams e ON r.exam_id = e.id
            ORDER BY r.id DESC
        ');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, sanitize_csv_row($row));
        }
    } elseif ($type === 'points') {
        fputcsv($out, ['المعرف ID', 'اسم الشماس', 'الطايو', 'النوع', 'سبب التشجيع', 'الخادم', 'التاريخ']);
        $stmt = $db->query('
            SELECT p.id, u.full_name, p.points, p.type, p.reason, srv.full_name as servant_name, p.created_at
            FROM points p
            JOIN users u ON p.student_id = u.id
            LEFT JOIN users srv ON p.servant_id = srv.id
            ORDER BY p.id DESC
        ');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, sanitize_csv_row($row));
        }
    }

    fclose($out);
    exit;
}

$pageTitle = 'تصدير البيانات إلى Excel';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.35rem;">
            📥 تصدير البيانات إلى Excel و CSV
        </h1>
        <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:2rem;">
            تصدير كشوف وقواعد بيانات المدرسة بضغطة زر واحدة متوافقة مع برنامج Microsoft Excel باللغة العربية.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem;">
            <div class="glass-card" style="text-align:center; padding:2rem 1.5rem; border-right:5px solid var(--royal-blue);">
                <div style="font-size:3rem; margin-bottom:1rem;">👥</div>
                <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">دليل الشمامسة والطلاب</h3>
                <p style="font-size:0.88rem; color:var(--text-secondary); margin-bottom:1.5rem;">
                    تصدير جميع بيانات الشمامسة، الرتب، المراحل، وأرقام هواتف الآباء والأمهات.
                </p>
                <a href="<?= BASE_URL ?>admin/export.php?type=students&download=1" class="btn btn-primary" style="width:100%; font-weight:800;">
                    📥 تحميل Excel (الشمامسة)
                </a>
            </div>

            <div class="glass-card" style="text-align:center; padding:2rem 1.5rem; border-right:5px solid var(--gold);">
                <div style="font-size:3rem; margin-bottom:1rem;">📅</div>
                <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">سجل الحضور والغياب</h3>
                <p style="font-size:0.88rem; color:var(--text-secondary); margin-bottom:1.5rem;">
                    تصدير سجل الحضور الشامل لجميع القداسات وتواريخ المسح الذكي.
                </p>
                <a href="<?= BASE_URL ?>admin/export.php?type=attendance&download=1" class="btn btn-gold" style="width:100%; font-weight:800;">
                    📥 تحميل Excel (الحضور)
                </a>
            </div>

            <div class="glass-card" style="text-align:center; padding:2rem 1.5rem; border-right:5px solid #10b981;">
                <div style="font-size:3rem; margin-bottom:1rem;">📝</div>
                <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">كشوف درجات الاختبارات</h3>
                <p style="font-size:0.88rem; color:var(--text-secondary); margin-bottom:1.5rem;">
                    تصدير نتائج وتفوق الشمامسة في اختبارات الألحان والطقوس والتقييمات.
                </p>
                <a href="<?= BASE_URL ?>admin/export.php?type=exams&download=1" class="btn btn-success" style="width:100%; font-weight:800;">
                    📥 تحميل Excel (الدرجات)
                </a>
            </div>

            <div class="glass-card" style="text-align:center; padding:2rem 1.5rem; border-right:5px solid #8b5cf6;">
                <div style="font-size:3rem; margin-bottom:1rem;">⭐</div>
                <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">سجل الطايو والأوسمة</h3>
                <p style="font-size:0.88rem; color:var(--text-secondary); margin-bottom:1.5rem;">
                    تصدير الطايو الإيجابي والتشجيعي وأسباب الحصول عليه.
                </p>
                <a href="<?= BASE_URL ?>admin/export.php?type=points&download=1" class="btn btn-secondary" style="width:100%; font-weight:800; background:#8b5cf6; color:#fff;">
                    📥 تحميل Excel (الطايو)
                </a>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

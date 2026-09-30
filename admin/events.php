<?php
$pageTitle = 'إدارة الأنشطة والرحلات والمناسبات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $action = sanitize($_POST['form_action'] ?? '');

        if ($action === 'create_event') {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description'] ?? '');
            $eventType = sanitize($_POST['event_type'] ?? 'trip');
            $eventDate = sanitize($_POST['event_date']);
            $location = sanitize($_POST['location'] ?? '');
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT) ?: 0.00;
            $maxCapacity = filter_input(INPUT_POST, 'max_capacity', FILTER_VALIDATE_INT) ?: 50;

            $stmt = $db->prepare('
                INSERT INTO events (title, description, event_type, event_date, location, price, max_capacity, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$title, $description, $eventType, $eventDate, $location, $price, $maxCapacity, $_SESSION['user']['id']]);

            // Broadcast announcement notification to all users
            $eventTypeName = match ($eventType) {
                'trip' => 'رحلة كنسية',
                'retreat' => 'خلوة روحية',
                'spiritual_day' => 'يوم روحي',
                default => 'فعالية كنسية',
            };

            $db->prepare("
                INSERT INTO announcements (title, content, target_type, created_by)
                VALUES (?, ?, 'everyone', ?)
            ")->execute([
                "تم الإعلان عن {$eventTypeName}: {$title} 🚌",
                "تاريخ الفعالية: {$eventDate} في {$location}. يمكنكم الاشتراك الآن من صفحة جدول الأنشطة.",
                $_SESSION['user']['id'],
            ]);

            $_SESSION['flash_success'] = 'تم إنشاء الفعالية ونشر الإعلان بنجاح!';
            header('Location: '.BASE_URL.'admin/events.php');
            exit;
        } elseif ($action === 'update_status') {
            $regId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT);
            $status = sanitize($_POST['status'] ?? 'confirmed');
            $db->prepare('UPDATE event_registrations SET status = ? WHERE id = ?')->execute([$status, $regId]);
            $_SESSION['flash_success'] = 'تم تحديث حالة حجز الشماس بنجاح!';
            header('Location: '.BASE_URL.'admin/events.php');
            exit;
        }
    }
}

try {
    $events = $db->query('
        SELECT e.*, COUNT(er.id) as registered_count, COALESCE(SUM(er.seats_count), 0) as total_seats
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.status != "cancelled"
        GROUP BY e.id, e.title, e.description, e.event_type, e.event_date, e.location, e.price, e.max_capacity, e.created_by, e.created_at
        ORDER BY e.event_date DESC
    ')->fetchAll();
} catch (Throwable $e) {
    $events = [];
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">
            📅 جدول الأنشطة والرحلات الكنسية والخلوات
        </h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Create Event Card -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">➕ إضافة فعالية / رحلة كنسية جديدة</h3>
            <form action="" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="create_event">

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="title">عنوان الفعالية / الرحلة *</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="مثال: رحلة أديرة وادي النطرون والأنبا بيشوي" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="event_type">نوع النشاط *</label>
                        <select id="event_type" name="event_type" class="form-control" required>
                            <option value="trip">🚌 رحلة كنسية ترفيهية وزيارة أديرة</option>
                            <option value="retreat">🕊️ خلوة روحية وتسبحة</option>
                            <option value="spiritual_day">☀️ يوم روحي ولقاء دراسي</option>
                            <option value="celebration">🎉 حفل تكريم ومهرجان</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="event_date">تاريخ الموعد *</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="location">مكان التجمع / الوجهة *</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="مثال: أديرة وادي النطرون" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="price">قيمة الاشتراك (ج.م) [0 = مجاناً]</label>
                        <input type="number" step="0.5" id="price" name="price" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="max_capacity">الحد الأقصى للأفراد (السعة)</label>
                        <input type="number" id="max_capacity" name="max_capacity" class="form-control" value="50">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">التفاصيل وبرنامج اليوم</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="اكتب تفاصيل ومواعيد التحرك والبرنامج الروحي..."></textarea>
                </div>

                <button type="submit" class="btn btn-gold" style="width:100%; font-weight:800; padding:0.85rem;">
                    📢 نشر الفعالية وإرسال إشعار للجميع
                </button>
            </form>
        </div>

        <!-- Events List -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">الفعاليات الحالية وقوائم المشتركين</h3>
            
            <?php if (empty($events)) { ?>
                <p style="color:var(--text-muted); text-align:center; padding:2rem;">لا توجد فعاليات مضافة بعد.</p>
            <?php } else { ?>
                <?php foreach ($events as $ev) {
                    $eventRegs = $db->prepare('
                        SELECT er.*, u.full_name, u.phone, u.role
                        FROM event_registrations er
                        JOIN users u ON er.user_id = u.id
                        WHERE er.event_id = ?
                        ORDER BY er.id DESC
                    ');
                    $eventRegs->execute([$ev['id']]);
                    $regList = $eventRegs->fetchAll();
                    ?>
                    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:1.25rem; margin-bottom:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                            <div>
                                <h4 style="color:var(--royal-blue); font-size:1.2rem; font-weight:800; margin-bottom:0.25rem;"><?= sanitize($ev['title']) ?></h4>
                                <span style="font-size:0.88rem; color:var(--text-muted);">
                                    📍 <?= sanitize($ev['location']) ?> | 📅 <?= format_arabic_date($ev['event_date']) ?> | 💰 <?= ($ev['price'] > 0) ? $ev['price'].' ج.م' : 'مجاناً' ?>
                                </span>
                            </div>
                            <div>
                                <span class="badge badge-gold">الحجوزات: <?= $ev['total_seats'] ?: 0 ?> / <?= $ev['max_capacity'] ?></span>
                            </div>
                        </div>

                        <?php if ($ev['description']) { ?>
                            <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:1rem; line-height:1.6;">
                                <?= nl2br(sanitize($ev['description'])) ?>
                            </p>
                        <?php } ?>

                        <!-- Participants Table -->
                        <h5 style="color:var(--royal-blue); margin-top:1rem; margin-bottom:0.5rem; font-size:0.95rem;">👥 قائمة المشتركين المسجلين:</h5>
                        <?php if (empty($regList)) { ?>
                            <p style="font-size:0.85rem; color:var(--text-muted);">لا يوجد مشتركون بعد في هذه الفعالية.</p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="custom-table" style="font-size:0.88rem;">
                                    <thead>
                                        <tr>
                                            <th>الاسم</th>
                                            <th>رقم الهاتف</th>
                                            <th>عدد المقاعد</th>
                                            <th>تاريخ الحجز</th>
                                            <th>حالة التأكيد</th>
                                            <th>إجراء</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($regList as $reg) { ?>
                                            <tr>
                                                <td><strong><?= sanitize($reg['full_name']) ?></strong></td>
                                                <td><?= sanitize($reg['phone']) ?></td>
                                                <td><span class="badge badge-info"><?= $reg['seats_count'] ?></span></td>
                                                <td><?= format_arabic_date(substr($reg['created_at'], 0, 10)) ?></td>
                                                <td>
                                                    <span class="badge <?= ($reg['status'] === 'confirmed') ? 'badge-success' : (($reg['status'] === 'cancelled') ? 'badge-danger' : 'badge-warning') ?>">
                                                        <?= ($reg['status'] === 'confirmed') ? 'مؤكد ✅' : (($reg['status'] === 'cancelled') ? 'ملغي ❌' : 'قيد المراجعة ⏳') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display:inline-flex; gap:0.25rem;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="form_action" value="update_status">
                                                        <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">
                                                        <?php if ($reg['status'] !== 'confirmed') { ?>
                                                            <button type="submit" name="status" value="confirmed" class="btn btn-success btn-sm">تأكيد</button>
                                                        <?php } ?>
                                                        <?php if ($reg['status'] !== 'cancelled') { ?>
                                                            <button type="submit" name="status" value="cancelled" class="btn btn-danger btn-sm">إلغاء</button>
                                                        <?php } ?>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

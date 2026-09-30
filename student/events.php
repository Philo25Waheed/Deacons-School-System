<?php
$pageTitle = 'جدول الأنشطة والرحلات والمناسبات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'parent', 'servant', 'admin');

$db = getDB();
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];

// Fetch linked children if parent
$children = [];
$childrenIds = [];
if ($userRole === 'parent') {
    $chStmt = $db->prepare('
        SELECT u.id, u.full_name, u.qr_code_token
        FROM parent_student ps
        JOIN users u ON ps.student_id = u.id
        WHERE ps.parent_id = ?
    ');
    $chStmt->execute([$userId]);
    $children = $chStmt->fetchAll();
    $childrenIds = array_column($children, 'id');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        // Parents cannot book trips for themselves
        if ($userRole === 'parent') {
            $_SESSION['flash_error'] = 'عذراً، نظام حجز الرحلات مخصص للشمامسة والطلاب فقط، ولا يمكن لولي الأمر حجز رحلة لنفسه.';
            header('Location: '.BASE_URL.'student/events.php');
            exit;
        }

        $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
        $seatsCount = filter_input(INPUT_POST, 'seats_count', FILTER_VALIDATE_INT) ?: 1;
        $notes = sanitize($_POST['notes'] ?? '');

        // Check duplicate
        $chk = $db->prepare('SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?');
        $chk->execute([$eventId, $userId]);
        if ($chk->fetch()) {
            $_SESSION['flash_error'] = 'أنت مسجل بالفعل في هذه الفعالية مسبقاً.';
        } else {
            $stmt = $db->prepare('
                INSERT INTO event_registrations (event_id, user_id, seats_count, notes, status)
                VALUES (?, ?, ?, ?, "registered")
            ');
            $stmt->execute([$eventId, $userId, $seatsCount, $notes]);
            $_SESSION['flash_success'] = 'تم تسجيل اشتراكك في الفعالية بنجاح! سيتم مراجعة الحجز وتأكيده من قبل إدارة المدرسة.';
        }
        header('Location: '.BASE_URL.'student/events.php');
        exit;
    }
}

// Fetch all events
try {
    $events = $db->query('
        SELECT e.*, 
               (SELECT COUNT(er.id) FROM event_registrations er WHERE er.event_id = e.id AND er.status != "cancelled") as registered_count,
               (SELECT er.status FROM event_registrations er WHERE er.event_id = e.id AND er.user_id = '.(int) $userId.') as my_reg_status
        FROM events e
        ORDER BY e.event_date DESC
    ')->fetchAll();
} catch (Throwable $e) {
    $events = [];
}

// If parent, fetch each child's registration status for events
$childrenRegistrations = [];
if ($userRole === 'parent' && ! empty($childrenIds)) {
    $inCh = implode(',', array_fill(0, count($childrenIds), '?'));
    $chRegStmt = $db->prepare("
        SELECT er.event_id, er.user_id as student_id, er.status, er.seats_count, er.created_at, u.full_name as student_name
        FROM event_registrations er
        JOIN users u ON er.user_id = u.id
        WHERE er.user_id IN ({$inCh}) AND er.status != 'cancelled'
    ");
    $chRegStmt->execute($childrenIds);
    foreach ($chRegStmt->fetchAll() as $cr) {
        $childrenRegistrations[$cr['event_id']][] = $cr;
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.35rem;">
            📅 جدول الأنشطة والرحلات الكنسية والخلوات
        </h1>
        <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:1.5rem;">
            تصفح الرحلات والفعاليات والأيام الروحية القادمة واحجز مكانك أنت وأسرتك.
        </p>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
            <?php if (empty($events)) { ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:3rem;">
                    <span style="font-size:3rem; display:block; margin-bottom:1rem;">🚌</span>
                    <p style="color:var(--text-muted); font-size:1.1rem;">لا توجد رحلات أو فعاليات معلنة في الوقت الحالي.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($events as $ev) {
                    $isRegistered = ! empty($ev['my_reg_status']);
                    $typeIcon = match ($ev['event_type']) {
                        'trip' => '🚌 رحلة كنسية',
                        'retreat' => '🕊️ خلوة روحية',
                        'spiritual_day' => '☀️ يوم روحي',
                        default => '🎉 مناسبة خاصة',
                    };
                    ?>
                    <div class="glass-card" style="display:flex; flex-direction:column; justify-content:space-between; border-right:5px solid var(--royal-blue);">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                                <span class="badge badge-info"><?= $typeIcon ?></span>
                                <span class="badge badge-gold"><?= format_arabic_date($ev['event_date']) ?></span>
                            </div>

                            <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem; font-size:1.25rem;">
                                <?= sanitize($ev['title']) ?>
                            </h3>

                            <div style="font-size:0.88rem; color:var(--text-muted); margin-bottom:0.75rem;">
                                📍 <strong>المكان:</strong> <?= sanitize($ev['location']) ?><br>
                                💰 <strong>الاشتراك:</strong> <?= ($ev['price'] > 0) ? $ev['price'].' ج.م' : 'مجاناً 🎁' ?>
                            </div>

                            <?php if ($ev['description']) { ?>
                                <p style="font-size:0.9rem; color:var(--text-secondary); line-height:1.6; margin-bottom:1rem;">
                                    <?= nl2br(sanitize($ev['description'])) ?>
                                </p>
                            <?php } ?>
                        </div>

                        <div style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:1rem;">
                            <?php if ($userRole === 'parent') { ?>
                                <!-- Parent View: Show child booking status, no self booking -->
                                <?php
                                $eventChildRegs = $childrenRegistrations[$ev['id']] ?? [];
                                ?>
                                <?php if (! empty($eventChildRegs)) { ?>
                                    <div style="background:rgba(22, 163, 74, 0.08); border:1px solid rgba(22, 163, 74, 0.25); border-radius:var(--radius-sm); padding:0.75rem;">
                                        <span style="font-size:0.8rem; font-weight:700; color:#15803d; display:block; margin-bottom:0.4rem;">
                                            👦 موقف حجز الأبناء في هذه الرحلة:
                                        </span>
                                        <?php foreach ($eventChildRegs as $cr) { ?>
                                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.88rem; margin-top:0.25rem;">
                                                <strong><?= sanitize($cr['student_name']) ?>:</strong>
                                                <span class="badge <?= ($cr['status'] === 'confirmed') ? 'badge-success' : 'badge-warning' ?>" style="font-size:0.8rem;">
                                                    <?= ($cr['status'] === 'confirmed') ? 'حجز مؤكد ✅' : 'قيد المراجعة ⏳' ?>
                                                    (<?= $cr['seats_count'] ?> مقعد)
                                                </span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div style="text-align:center; padding:0.6rem; background:var(--bg-primary); border-radius:var(--radius-sm); border:1px dashed var(--border-color);">
                                        <span style="font-size:0.85rem; color:var(--text-muted);">
                                            لم يقم أي من أبنائك بالحجز في هذه الرحلة بعد.
                                        </span>
                                    </div>
                                <?php } ?>
                                <div style="text-align:center; margin-top:0.6rem;">
                                    <span style="font-size:0.78rem; color:var(--text-muted);">
                                        ℹ️ الحجز يتم مباشرة من حساب الشماس.
                                    </span>
                                </div>
                            <?php } else { ?>
                                <!-- Student/Servant View -->
                                <?php if ($isRegistered) { ?>
                                    <div style="text-align:center;">
                                        <span class="badge badge-success" style="padding:0.5rem 1rem; font-size:0.95rem; font-weight:800; display:block;">
                                            ✅ أنت مسجل في هذه الفعالية (<?= ($ev['my_reg_status'] === 'confirmed') ? 'حجز مؤكد' : 'قيد المراجعة' ?>)
                                        </span>
                                    </div>
                                <?php } else { ?>
                                    <form method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                        <div style="width:90px;">
                                            <input type="number" name="seats_count" class="form-control" value="1" min="1" max="10" title="عدد الأفراد" required>
                                        </div>
                                        <button type="submit" class="btn btn-gold" style="flex:1; font-weight:800;">
                                            ✍️ حجز واشتراك
                                        </button>
                                    </form>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

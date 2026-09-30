<?php
$pageTitle = 'جدول خدمتي في القداسات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'admin', 'servant');

$db = getDB();
$studentId = $_SESSION['user']['id'];
$msg = '';

// Handle Confirmation or Swap Request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $rosterId = filter_input(INPUT_POST, 'roster_id', FILTER_VALIDATE_INT);
        $action = sanitize($_POST['action'] ?? '');
        $notes = sanitize($_POST['response_notes'] ?? '');

        if ($rosterId && $action === 'confirm') {
            $stmt = $db->prepare("
                UPDATE liturgy_roster_students 
                SET status = 'confirmed', responded_at = CURRENT_TIMESTAMP, response_notes = 'تم تأكيد الحضور من قبل الشماس' 
                WHERE roster_id = ? AND student_id = ?
            ");
            $stmt->execute([$rosterId, $studentId]);
            $_SESSION['flash_success'] = 'تم تأكيد حضورك لخدمة القداس بنجاح! بركة القداس معك ☦️';
            header('Location: '.BASE_URL.'student/roster.php');
            exit;
        } elseif ($rosterId && $action === 'swap') {
            $stmt = $db->prepare("
                UPDATE liturgy_roster_students 
                SET status = 'declined', responded_at = CURRENT_TIMESTAMP, response_notes = ? 
                WHERE roster_id = ? AND student_id = ?
            ");
            $stmt->execute([$notes ?: 'اعتذار عن الحضور لظروف طارئة', $rosterId, $studentId]);

            // Notify Admin / Servant
            $db->prepare("
                INSERT INTO notifications (user_id, title, message)
                VALUES (1, 'طلب اعتذار عن خدمة قداس ⚠️', ?)
            ")->execute(["قدم الشماس {$_SESSION['user']['full_name']} اعتذاراً عن خدمة القداس. سبب الاعتذار: {$notes}"]);

            $_SESSION['flash_success'] = 'تم إرسال طلب الاعتذار لإدارة الخدمة بنجاح.';
            header('Location: '.BASE_URL.'student/roster.php');
            exit;
        }
    }
}

$rosters = $db->prepare('
    SELECT r.*, rs.role_name, rs.admin_notes, rs.status as my_status, rs.response_notes, rs.responded_at
    FROM liturgy_roster r
    JOIN liturgy_roster_students rs ON r.id = rs.roster_id
    WHERE rs.student_id = ? OR rs.substitute_student_id = ?
    ORDER BY r.service_date DESC
');
$rosters->execute([$studentId, $studentId]);
$myRosters = $rosters->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;">جدول خدمتي في القداسات الإلهية ⛪</h1>
        <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:1.5rem;">
            القداسات المجدولة لخدمتك وتوزيع الأدوار المكلف بها. برجاء تأكيد الحضور أو تقديم اعتذار مبكر لترتيب البديل.
        </p>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; border-radius:var(--radius-sm);">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
            <?php if (empty($myRosters)) { ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:3rem;">
                    <span style="font-size:3rem; display:block; margin-bottom:1rem;">🕊️</span>
                    <p style="color:var(--text-muted); font-size:1.1rem;">لا توجد تكليفات خدمة حالية بالقداسات القادمة.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($myRosters as $r) {
                    $isPending = ($r['my_status'] === 'pending' || empty($r['my_status']));
                    $isConfirmed = ($r['my_status'] === 'confirmed');
                    $isDeclined = ($r['my_status'] === 'declined' || $r['my_status'] === 'swapped');
                    ?>
                    <div class="glass-card" style="border-right:5px solid <?= $isConfirmed ? 'var(--royal-blue)' : ($isDeclined ? '#ef4444' : 'var(--gold)') ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <h3 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($r['title']) ?></h3>
                            <span class="badge badge-gold"><?= format_arabic_date($r['service_date']) ?></span>
                        </div>

                        <!-- Role Display Card -->
                        <div style="background:var(--royal-blue-glow); border:1.5px solid var(--royal-blue); border-radius:var(--radius-sm); padding:0.85rem 1rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.75rem;">
                            <span style="font-size:1.6rem;">
                                <?= in_array($r['role_name'] ?? '', ['خدمة مذبح', 'خدمة باكر']) ? '🕯️' : '📖' ?>
                            </span>
                            <div>
                                <small style="color:var(--royal-blue); font-weight:800; display:block;">دورك وتكليفك في هذا القداس:</small>
                                <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">
                                    <?= sanitize($r['role_name'] ?: 'خدمة شماس') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Admin Custom Notes for Student -->
                        <?php if (! empty($r['admin_notes'])) { ?>
                            <div style="background:rgba(217, 119, 6, 0.08); border:1.5px solid var(--gold); border-radius:var(--radius-sm); padding:0.75rem 1rem; margin-bottom:1rem;">
                                <strong style="color:var(--gold); display:flex; align-items:center; gap:0.4rem; font-size:0.92rem; margin-bottom:0.25rem;">
                                    <span>📝</span> توجيهات وملاحظات خاصة بك من إدارة الخدمة:
                                </strong>
                                <p style="margin:0; color:var(--text-primary); font-size:0.95rem; font-weight:600; line-height:1.5;">
                                    <?= sanitize($r['admin_notes']) ?>
                                </p>
                            </div>
                        <?php } ?>

                        <!-- Status Badge -->
                        <div style="margin-bottom:1rem;">
                            <?php if ($isConfirmed) { ?>
                                <span class="badge badge-success" style="font-size:0.85rem; padding:0.4rem 0.85rem;">
                                    ✅ تم تأكيد الحضور
                                </span>
                            <?php } elseif ($isDeclined) { ?>
                                <span class="badge badge-danger" style="font-size:0.85rem; padding:0.4rem 0.85rem;">
                                    ⚠️ تم تقديم طلب اعتذار / استبدال
                                </span>
                                <?php if ($r['response_notes']) { ?>
                                    <p style="font-size:0.82rem; color:var(--text-muted); margin-top:0.35rem;">سبب الاعتذار: <?= sanitize($r['response_notes']) ?></p>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="badge badge-warning" style="font-size:0.85rem; padding:0.4rem 0.85rem;">
                                    ⏳ في انتظار تأكيد الحضور
                                </span>
                            <?php } ?>
                        </div>

                        <?php if ($r['notes']) { ?>
                            <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1rem;">
                                📌 <strong>ملاحظات عامة للقداس:</strong> <?= sanitize($r['notes']) ?>
                            </p>
                        <?php } ?>

                        <!-- Action Buttons -->
                        <?php if ($isPending) { ?>
                            <div style="display:flex; gap:0.75rem; margin-top:1.25rem; border-top:1px solid var(--border-color); padding-top:1rem;">
                                <form method="POST" style="flex:1;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" class="btn btn-primary" style="width:100%; font-weight:800;">
                                        ✅ تأكيد الحضور
                                    </button>
                                </form>

                                <button type="button" class="btn btn-secondary" onclick="openSwapModal(<?= $r['id'] ?>)" style="flex:1; color:#ef4444; font-weight:700;">
                                    🔄 طلب اعتذار
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<!-- Modal for Swap/Decline Request -->
<div id="swapModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="width:100%; max-width:450px; margin:1rem; background:var(--bg-surface);">
        <h3 style="color:#ef4444; margin-bottom:1rem; font-weight:800;">🔄 طلب اعتذار عن خدمة القداس</h3>
        <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:1rem;">
            يرجى توضيح سبب الاعتذار حتى تتمكن إدارة المدرسة من ترتيب شماس بديل في الوقت المناسب.
        </p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" id="modalRosterId" name="roster_id" value="">
            <input type="hidden" name="action" value="swap">

            <div class="form-group">
                <label class="form-label" for="response_notes">سبب الاعتذار أو طلب الاستبدال *</label>
                <textarea id="response_notes" name="response_notes" class="form-control" rows="3" placeholder="اكتب سبب الاعتذار هنا..." required></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn btn-secondary" onclick="closeSwapModal()">إلغاء</button>
                <button type="submit" class="btn btn-danger" style="font-weight:800;">تأكيد إرسال الاعتذار</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSwapModal(rosterId) {
    document.getElementById('modalRosterId').value = rosterId;
    const modal = document.getElementById('swapModal');
    modal.style.display = 'flex';
}

function closeSwapModal() {
    document.getElementById('swapModal').style.display = 'none';
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

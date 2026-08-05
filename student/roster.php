<?php
$pageTitle = 'جدول خدمتي في القداسات';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];

$rosters = $db->prepare('
    SELECT r.*
    FROM liturgy_roster r
    JOIN liturgy_roster_students rs ON r.id = rs.roster_id
    WHERE rs.student_id = ?
    ORDER BY r.service_date DESC
');
$rosters->execute([$studentId]);
$myRosters = $rosters->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">جدول خدمتي في خورس القداسات ⛪</h1>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
            <?php if (empty($myRosters)) { ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:3rem;">
                    <p style="color:var(--text-muted);">لا توجد تكليفات خدمة حالية بالقداسات المقبولة.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($myRosters as $r) { ?>
                    <div class="glass-card" style="border-right:5px solid var(--gold);">
                        <span class="badge badge-gold" style="float:left;"><?= format_arabic_date($r['service_date']) ?></span>
                        <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.75rem;"><?= sanitize($r['title']) ?></h3>
                        
                        <?php if ($r['hymn_required']) { ?>
                            <div style="background:var(--royal-blue-glow); padding:0.85rem; border-radius:var(--radius-sm); margin-bottom:1rem;">
                                <strong style="color:var(--royal-blue);">🎶 اللحن المطلوب تحضيره:</strong>
                                <p style="margin-top:0.25rem; font-weight:700; color:var(--text-primary);"><?= sanitize($r['hymn_required']) ?></p>
                            </div>
                        <?php } ?>

                        <p style="font-size:0.85rem; color:var(--text-muted);">يرجى التواجد بالخورس قبل بدء القداس بـ 15 دقيقة بالتونة واللفافة.</p>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

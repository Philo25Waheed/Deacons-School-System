<?php
$pageTitle = 'لوحة متابعة ولي الأمر';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('parent', 'admin');

$db = getDB();
$parentId = $_SESSION['user']['id'];

// Fetch linked children
$childrenStmt = $db->prepare("
    SELECT u.id, u.full_name, u.qr_code_token, u.profile_pic, s.name_ar as stage, g.name_ar as grade, c.name_ar as class,
           COALESCE(SUM(CASE WHEN p.type = 'positive' THEN p.points ELSE -p.points END), 0) as total_points,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'present') as present_count
    FROM parent_student ps
    JOIN users u ON ps.student_id = u.id
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    LEFT JOIN points p ON u.id = p.student_id
    WHERE ps.parent_id = ?
    GROUP BY u.id
");
$childrenStmt->execute([$parentId]);
$children = $childrenStmt->fetchAll();

// Fetch upcoming liturgy services for children
$childrenIds = array_column($children, 'id');
$upcomingChildrenServices = [];
if (! empty($childrenIds)) {
    $inCh = implode(',', array_fill(0, count($childrenIds), '?'));
    $todayDate = date('Y-m-d');
    $chRosterStmt = $db->prepare("
        SELECT rs.*, r.title as liturgy_title, r.service_date, u.full_name as student_name
        FROM liturgy_roster_students rs
        JOIN liturgy_roster r ON rs.roster_id = r.id
        JOIN users u ON rs.student_id = u.id
        WHERE rs.student_id IN ($inCh) AND r.service_date >= ?
        ORDER BY r.service_date ASC
    ");
    $chRosterStmt->execute(array_merge($childrenIds, [$todayDate]));
    $upcomingChildrenServices = $chRosterStmt->fetchAll();
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;">لوحة متابعة أولياء الأمور 👨‍👩‍👦</h1>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">مرحباً بك يا <?= sanitize($_SESSION['user']['full_name']) ?>. يمكنك هنا متابعة حضور وطايو وتكليفات خدمة أبنائك.</p>

        <?php if (! empty($upcomingChildrenServices)) { ?>
            <div class="glass-card" style="margin-bottom:2rem; border-top:4px solid var(--gold); background:rgba(217, 119, 6, 0.04);">
                <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
                    <span>⛪</span> تكليفات خدمة القداسات القادمة لأبنائك
                </h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                    <?php foreach ($upcomingChildrenServices as $ucs) { ?>
                        <div style="background:var(--bg-surface); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                                <strong style="color:var(--royal-blue); font-size:1rem;"><?= sanitize($ucs['student_name']) ?></strong>
                                <span class="badge badge-gold" style="font-weight:800;"><?= sanitize($ucs['role_name']) ?></span>
                            </div>
                            <div style="font-size:0.85rem; font-weight:700; color:var(--text-primary); margin-bottom:0.25rem;">
                                <?= sanitize($ucs['liturgy_title']) ?>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem;">
                                <span>📅 <?= format_arabic_date($ucs['service_date']) ?></span>
                                <?php if (($ucs['status'] ?? '') === 'confirmed') { ?>
                                    <span class="badge badge-success">حضور مؤكد ✅</span>
                                <?php } elseif (($ucs['status'] ?? '') === 'declined') { ?>
                                    <span class="badge badge-danger">اعتذار ⚠️</span>
                                <?php } else { ?>
                                    <span class="badge badge-warning">قيد الانتظار ⏳</span>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
            <?php foreach ($children as $child) { ?>
                <div class="glass-card">
                    <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
                        <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($child['profile_pic'] ?? 'default-avatar.png') ?>" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" alt="صورة الشماس">
                        <div>
                            <h3 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($child['full_name']) ?></h3>
                            <p style="color:var(--text-muted); font-size:0.85rem;"><?= sanitize($child['stage'] ?? '') ?> - <?= sanitize($child['grade'] ?? '') ?></p>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin:1rem 0; padding:0.85rem; background:var(--bg-primary); border-radius:var(--radius-sm);">
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-muted); display:block;">مرات الحضور</span>
                            <strong style="color:var(--royal-blue); font-size:1.1rem;"><?= $child['present_count'] ?> يوم</strong>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-muted); display:block;">رصيد الطايو</span>
                            <strong style="color:var(--gold); font-size:1.1rem;">⭐ <?= number_format($child['total_points']) ?> طايو</strong>
                        </div>
                    </div>

                    <div style="display:flex; gap:0.5rem;">
                        <a href="<?= BASE_URL ?>parent/child_details.php?id=<?= $child['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;">📊 التقرير التفصيلي</a>
                        <a href="<?= BASE_URL ?>student/card.php?id=<?= $child['id'] ?>" class="btn btn-gold btn-sm" style="flex:1;">🪪 كارت الشماس</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

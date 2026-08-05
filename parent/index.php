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

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">لوحة متابعة أياء الأمور 👨‍👩‍👦</h1>
        <p style="color:var(--text-muted); margin-bottom:2rem;">مرحباً بك يا <?= sanitize($_SESSION['user']['full_name']) ?>. يمكنك هنا متابعة حضور ونقاط وسلوك أبنائك.</p>

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
                            <span style="font-size:0.75rem; color:var(--text-muted); display:block;">رصيد النقاط</span>
                            <strong style="color:var(--gold); font-size:1.1rem;">⭐ <?= number_format($child['total_points']) ?></strong>
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

<?php
$pageTitle = 'أبنائي المسجلين';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('parent', 'admin');

$db = getDB();
$parentId = $_SESSION['user']['id'];

$childrenStmt = $db->prepare('
    SELECT u.*, s.name_ar as stage, g.name_ar as grade, c.name_ar as class, ps.relationship
    FROM parent_student ps
    JOIN users u ON ps.student_id = u.id
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE ps.parent_id = ?
');
$childrenStmt->execute([$parentId]);
$children = $childrenStmt->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">قائمة الأبناء المسجلين 👨‍👩‍👦</h1>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الكود</th>
                            <th>اسم الشماس</th>
                            <th>صلة القرابة</th>
                            <th>المرحلة والصف</th>
                            <th>الكنيسة</th>
                            <th>التقرير والبطاقة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $ch) { ?>
                            <tr>
                                <td>
                                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($ch['profile_pic'] ?? 'default-avatar.png') ?>" 
                                         style="width:45px; height:45px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" 
                                         alt="الصورة" 
                                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                                </td>
                                <td><code><?= sanitize($ch['qr_code_token']) ?></code></td>
                                <td><strong><?= sanitize($ch['full_name']) ?></strong></td>
                                <td><span class="badge badge-gold"><?= sanitize($ch['relationship'] ?? 'والد / أم') ?></span></td>
                                <td><?= sanitize($ch['stage'] ?? '') ?> - <?= sanitize($ch['grade'] ?? '') ?></td>
                                <td><?= sanitize($ch['church_name']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>parent/child_details.php?id=<?= $ch['id'] ?>" class="btn btn-primary btn-sm">التقرير الشامل</a>
                                    <a href="<?= BASE_URL ?>student/card.php?id=<?= $ch['id'] ?>" class="btn btn-gold btn-sm">طباعة الكارت</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

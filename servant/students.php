<?php
$pageTitle = 'دليل الشمامسة';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('servant', 'admin');

$db = getDB();
$search = sanitize($_GET['search'] ?? '');

$query = "
    SELECT u.*, s.name_ar as stage_name, g.name_ar as grade_name, c.name_ar as class_name,
           COALESCE(SUM(CASE WHEN p.type = 'positive' THEN p.points ELSE -p.points END), 0) as total_points
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN grades g ON u.grade_id = g.id
    LEFT JOIN classes c ON u.class_id = c.id
    LEFT JOIN points p ON u.id = p.student_id
    WHERE u.role = 'student'
";
$params = [];

if ($search) {
    $query .= ' AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.qr_code_token LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$query .= ' GROUP BY u.id ORDER BY u.full_name ASC';
$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">دليل الشمامسة المخدومين 👦</h1>

        <div class="glass-card" style="margin-bottom:1.5rem;">
            <form action="" method="GET" style="display:flex; gap:1rem;">
                <input type="text" name="search" class="form-control" placeholder="بحث باسم الشماس، الهاتف، أو الكود..." value="<?= sanitize($search) ?>">
                <button type="submit" class="btn btn-primary">بحث</button>
            </form>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>المرحلة والصف</th>
                            <th>الكنيسة</th>
                            <th>النقاط</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu) { ?>
                            <tr>
                                <td>
                                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($stu['profile_pic'] ?? 'default-avatar.png') ?>" 
                                         style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" 
                                         alt="الصورة" 
                                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                                </td>
                                <td><code><?= sanitize($stu['qr_code_token']) ?></code></td>
                                <td><strong><?= sanitize($stu['full_name']) ?></strong></td>
                                <td><?= sanitize($stu['stage_name'] ?? '') ?> - <?= sanitize($stu['grade_name'] ?? '') ?></td>
                                <td><?= sanitize($stu['church_name']) ?></td>
                                <td><span class="badge badge-gold">⭐ <?= $stu['total_points'] ?></span></td>
                                <td>
                                    <a href="<?= BASE_URL ?>servant/points.php?student_id=<?= $stu['id'] ?>" class="btn btn-gold btn-sm">إضافة نقاط</a>
                                    <a href="<?= BASE_URL ?>servant/evaluations.php?student_id=<?= $stu['id'] ?>" class="btn btn-secondary btn-sm">تقييم</a>
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

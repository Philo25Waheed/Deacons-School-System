<?php
$pageTitle = 'المناهج والدروس التعليمية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$stageId = $_SESSION['user']['stage_id'] ?? null;

$query = 'SELECT * FROM courses WHERE 1=1';
if ($stageId) {
    $query .= " AND (stage_id = $stageId OR stage_id IS NULL)";
}
$query .= ' ORDER BY id DESC';
$courses = $db->query($query)->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">المناهج والدروس التعليمية 📖</h1>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
            <?php foreach ($courses as $c) { ?>
                <div class="glass-card">
                    <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;"><?= sanitize($c['title']) ?></h3>
                    <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1rem;"><?= sanitize($c['description'] ?? '') ?></p>

                    <?php if ($c['audio_file']) { ?>
                        <audio controls style="width:100%; margin-bottom:1rem;">
                            <source src="<?= BASE_URL ?>uploads/audio/<?= $c['audio_file'] ?>">
                        </audio>
                    <?php } ?>

                    <div style="display:flex; gap:0.5rem;">
                        <?php if ($c['pdf_file']) { ?>
                            <a href="<?= BASE_URL ?>uploads/pdf/<?= $c['pdf_file'] ?>" target="_blank" class="btn btn-primary btn-sm" style="flex:1;">📄 تحميل المذكرة PDF</a>
                        <?php } ?>
                        <?php if ($c['external_link']) { ?>
                            <a href="<?= sanitize($c['external_link']) ?>" target="_blank" class="btn btn-gold btn-sm" style="flex:1;">🎥 مشاهدة الشرح</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'مكتبة الألحان';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'admin');

$db = getDB();
$hymns = $db->query('SELECT * FROM hymns ORDER BY id DESC')->fetchAll();

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">مكتبة الألحان للشمامسة 🎶</h1>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
            <?php foreach ($hymns as $h) { ?>
                <div class="glass-card">
                    <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.4rem;"><?= sanitize($h['title']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.75rem;"><?= sanitize($h['description'] ?? '') ?></p>

                    <?php if ($h['notes']) { ?>
                        <div style="background:var(--bg-primary); padding:0.85rem; border-radius:var(--radius-sm); font-size:0.9rem; margin-bottom:1rem; white-space:pre-line;">
                            <strong>كلمات اللحن:</strong><br>
                            <?= sanitize($h['notes']) ?>
                        </div>
                    <?php } ?>

                    <?php if ($h['audio_file']) { ?>
                        <audio controls style="width:100%; margin-bottom:1rem;">
                            <source src="<?= BASE_URL ?>uploads/audio/<?= $h['audio_file'] ?>">
                        </audio>
                    <?php } ?>

                    <div style="display:flex; gap:0.5rem;">
                        <?php if ($h['pdf_file']) { ?>
                            <a href="<?= BASE_URL ?>uploads/pdf/<?= $h['pdf_file'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="flex:1;">📄 النوتة PDF</a>
                        <?php } ?>
                        <?php if ($h['video_link']) { ?>
                            <a href="<?= sanitize($h['video_link']) ?>" target="_blank" class="btn btn-gold btn-sm" style="flex:1;">🎥 فيديو التعليم</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

<?php
$pageTitle = 'مكتبة الكتب والمراجع الشماسية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'servant', 'parent', 'admin');

$db = getDB();
$isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');

// Search and Category Filtering
$search = sanitize($_GET['q'] ?? '');
$category = sanitize($_GET['cat'] ?? 'all');

$query = 'SELECT * FROM deacon_books WHERE 1=1';
$params = [];

if ($search !== '') {
    $query .= ' AND (title LIKE ? OR author LIKE ? OR description LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category !== 'all' && $category !== '') {
    $query .= ' AND category = ?';
    $params[] = $category;
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
} catch (Throwable $e) {
    $books = [];
}

$categories = [
    'all' => 'الكل (جميع الكتب)',
    'طقس وخدمة الشماس' => 'طقس وخدمة الشماس ⛪',
    'التسبحة والألحان' => 'التسبحة والإبصلمودية 🎶',
    'نصوص القداسات' => 'الخولاجي والقداسات 📖',
    'مناسبات وأعياد' => 'أسبوع الآلام والمناسبات ✝️',
    'قواعد اللغة القبطية' => 'اللغة القبطية 🔤',
];

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.books-hero-banner {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--royal-blue-glow) 100%);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.book-card {
    background: var(--bg-card);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.book-card:hover {
    transform: translateY(-5px);
    border-color: var(--gold);
    box-shadow: 0 10px 25px var(--gold-glow);
}

.book-cover-wrap {
    height: 180px;
    background: linear-gradient(145deg, var(--bg-surface) 0%, rgba(30,58,138,0.15) 100%);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.book-cover-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.book-cover-placeholder {
    font-size: 4rem;
    color: var(--royal-blue);
}

.cat-pill {
    padding: 0.45rem 1rem;
    border-radius: 50px;
    font-size: 0.88rem;
    font-weight: 700;
    text-decoration: none;
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 1.5px solid var(--border-color);
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.cat-pill:hover, .cat-pill.active {
    background: var(--royal-blue);
    color: #fff;
    border-color: var(--royal-blue);
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Hero Banner -->
        <div class="books-hero-banner">
            <div>
                <span class="badge badge-gold" style="margin-bottom:0.5rem; font-size:0.85rem;">المكتبة السحابية ☁️</span>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.8rem; margin-bottom:0.5rem;">
                    مكتبة الكتب والمراجع الشماسية
                </h1>
                <p style="color:var(--text-secondary); max-width:600px; font-size:0.95rem; line-height:1.6;">
                    تصفح وحمّل كتب الطقوس، الخولاجيات، الإبصلموديات، وقواعد اللغة القبطية مباشرة عبر Google Drive بجودة عالية وسرعة فائقة.
                </p>
                <?php if ($isAdmin) { ?>
                    <div style="margin-top:1rem;">
                        <a href="<?= BASE_URL ?>admin/books.php" class="btn btn-gold" style="font-weight:800; font-size:0.9rem;">
                            ➕ إدارة وإضافة وحذف الكتب
                        </a>
                    </div>
                <?php } ?>
            </div>
            <div style="text-align:left;">
                <div style="font-size:2.5rem; font-weight:900; color:var(--royal-blue); line-height:1;"><?= count($books) ?></div>
                <span style="font-size:0.85rem; color:var(--text-muted); font-weight:700;">كتاب ومرجع متاح</span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="glass-card" style="margin-bottom:2rem; padding:1.25rem;">
            <form method="GET" action="" style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem;">
                <div style="flex:1; min-width:250px;">
                    <input type="text" name="q" class="form-control" placeholder="🔍 ابحث عن اسم كتاب، مؤلف، أو موضوع..." value="<?= sanitize($search) ?>">
                </div>
                <input type="hidden" name="cat" value="<?= sanitize($category) ?>">
                <button type="submit" class="btn btn-gold" style="font-weight:800; padding:0 1.75rem;">بحث بالمكتبة</button>
            </form>

            <!-- Category Pills -->
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <?php foreach ($categories as $catKey => $catLabel) { ?>
                    <a href="<?= BASE_URL ?>student/books.php?cat=<?= urlencode($catKey) ?>&q=<?= urlencode($search) ?>" 
                       class="cat-pill <?= ($category === $catKey) ? 'active' : '' ?>">
                        <?= $catLabel ?>
                    </a>
                <?php } ?>
            </div>
        </div>

        <!-- Books Grid -->
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:1.5rem;">
            <?php if (empty($books)) { ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:3.5rem;">
                    <span style="font-size:3.5rem; display:block; margin-bottom:1rem;">📖</span>
                    <h3 style="color:var(--royal-blue); margin-bottom:0.5rem;">لا توجد كتب مطابقة لبحثك حالياً</h3>
                    <p style="color:var(--text-muted);">جرب تغيير تصنيف البحث أو مسح الكلمات المفتاحية.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($books as $b) {
                    $bookLink = $b['drive_url'] ?: $b['pdf_file'];
                    ?>
                    <div class="book-card">
                        <div>
                            <div class="book-cover-wrap">
                                <?php if (! empty($b['cover_image']) && file_exists(__DIR__.'/../uploads/books/covers/'.$b['cover_image'])) { ?>
                                    <img src="<?= BASE_URL ?>uploads/books/covers/<?= sanitize($b['cover_image']) ?>" class="book-cover-img" alt="غلاف الكتاب">
                                <?php } else { ?>
                                    <div class="book-cover-placeholder">📖</div>
                                <?php } ?>
                                <span class="badge badge-gold" style="position:absolute; top:10px; right:10px; font-size:0.75rem;">
                                    <?= sanitize($b['category']) ?>
                                </span>
                            </div>

                            <h3 style="color:var(--royal-blue); font-size:1.15rem; font-weight:800; margin-bottom:0.35rem; line-height:1.4;">
                                <?= sanitize($b['title']) ?>
                            </h3>

                            <?php if ($b['author']) { ?>
                                <div style="font-size:0.85rem; color:var(--gold); font-weight:700; margin-bottom:0.6rem;">
                                    ✍️ <?= sanitize($b['author']) ?>
                                </div>
                            <?php } ?>

                            <?php if ($b['description']) { ?>
                                <p style="font-size:0.88rem; color:var(--text-secondary); line-height:1.6; margin-bottom:1rem;">
                                    <?= sanitize($b['description']) ?>
                                </p>
                            <?php } ?>
                        </div>

                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; color:var(--text-muted); border-top:1px solid var(--border-color); padding-top:0.75rem; margin-bottom:1rem;">
                                <span>المصدر: <strong style="color:var(--royal-blue);">Google Drive ☁️</strong></span>
                                <span>تصفح وتحميل فوري ⚡</span>
                            </div>

                            <div style="display:flex; gap:0.5rem; align-items:center;">
                                <a href="<?= htmlspecialchars($bookLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="flex:1; font-weight:800; font-size:0.92rem; display:flex; align-items:center; justify-content:center; gap:0.4rem; padding:0.7rem; border-radius:var(--radius-sm);">
                                    <span>📖 فتح على Google Drive ↗️</span>
                                </a>
                                <?php if ($isAdmin) { ?>
                                    <form method="POST" action="<?= BASE_URL ?>admin/books.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا الكتاب من المكتبة؟');" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_book">
                                        <input type="hidden" name="book_id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:0.7rem 0.9rem;" title="حذف الكتاب">🗑️</button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

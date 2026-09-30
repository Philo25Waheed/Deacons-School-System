<?php
$pageTitle = 'إدارة مكتبة الكتب الشماسية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin');

$db = getDB();

// Handle Delete Book via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_book') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $delId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        if ($delId) {
            $stmt = $db->prepare('SELECT pdf_file, cover_image FROM deacon_books WHERE id = ?');
            $stmt->execute([$delId]);
            $bk = $stmt->fetch();
            if ($bk) {
                if ($bk['cover_image'] && file_exists(__DIR__.'/../uploads/books/covers/'.$bk['cover_image'])) {
                    @unlink(__DIR__.'/../uploads/books/covers/'.$bk['cover_image']);
                }
                $db->prepare('DELETE FROM deacon_books WHERE id = ?')->execute([$delId]);
                $_SESSION['flash_success'] = 'تم حذف الكتاب بنجاح من المكتبة!';
            }
        }
    } else {
        $_SESSION['flash_error'] = 'رمز CSRF غير صالح.';
    }
    header('Location: '.BASE_URL.'admin/books.php');
    exit;
}

// Handle Add New Book via Google Drive Link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $title = sanitize($_POST['title'] ?? '');
        $author = sanitize($_POST['author'] ?? '');
        $category = sanitize($_POST['category'] ?? 'طقس وخدمة الشماس');
        $description = sanitize($_POST['description'] ?? '');
        $driveUrl = trim($_POST['drive_url'] ?? '');

        if (empty($title) || empty($driveUrl)) {
            $_SESSION['flash_error'] = 'يرجى إدخال عنوان الكتاب ورابط Google Drive.';
            header('Location: '.BASE_URL.'admin/books.php');
            exit;
        }

        // Process Cover Image Upload (Optional)
        $coverFileName = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $imgTmp = $_FILES['cover_image']['tmp_name'];
            $imgExt = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfoMime = function_exists('mime_content_type') ? mime_content_type($imgTmp) : 'image/jpeg';

            if (in_array($imgExt, $allowedExts, true) && in_array($finfoMime, $allowedMimes, true) && @getimagesize($imgTmp) !== false) {
                $coversDir = __DIR__.'/../uploads/books/covers';
                if (! is_dir($coversDir)) {
                    mkdir($coversDir, 0777, true);
                }
                $coverFileName = 'cover_'.time().'_'.bin2hex(random_bytes(3)).'.'.$imgExt;
                move_uploaded_file($imgTmp, $coversDir.'/'.$coverFileName);
            }
        }

        $stmt = $db->prepare('
            INSERT INTO deacon_books (title, author, category, description, drive_url, pdf_file, cover_image, file_size, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, "Google Drive", ?)
        ');
        $stmt->execute([$title, $author, $category, $description, $driveUrl, $driveUrl, $coverFileName, $_SESSION['user']['id']]);

        // Broadcast announcement
        $db->prepare("
            INSERT INTO announcements (title, content, target_type, created_by)
            VALUES (?, ?, 'everyone', ?)
        ")->execute([
            "تمت إضافة كتاب جديد بالمكتبة الشماسية: {$title} 📖",
            'الكتاب متاح الآن للاطلاع والتحميل عبر Google Drive لجميع الشمامسة والخدام وأولياء الأمور.',
            $_SESSION['user']['id'],
        ]);

        $_SESSION['flash_success'] = 'تمت إضافة رابط الكتاب بنجاح وإتاحته للجميع على Google Drive!';
        header('Location: '.BASE_URL.'admin/books.php');
        exit;
    }
}

try {
    $books = $db->query('
        SELECT b.*, u.full_name as uploader_name
        FROM deacon_books b
        LEFT JOIN users u ON b.created_by = u.id
        ORDER BY b.id DESC
    ')->fetchAll();
} catch (Throwable $e) {
    $books = [];
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1 style="color:var(--royal-blue); font-weight:800;">
                    📚 إدارة مكتبة الكتب والمراجع الشماسية
                </h1>
                <p style="color:var(--text-muted); font-size:0.95rem;">
                    إضافة روابط كتب الخدمة والطقس عبر Google Drive لتوفير مساحة الاستضافة وتسهيل التصفح السريع
                </p>
            </div>
            <a href="<?= BASE_URL ?>student/books.php" class="btn btn-gold" style="font-weight:800;">
                👁️ استعراض واجهة المكتبة للمخدومين
            </a>
        </div>

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

        <!-- Add Book via Google Drive Link Form -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1.25rem; font-weight:800;">➕ إضافة كتاب جديد (رابط Google Drive)</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="title">عنوان أو اسم الكتاب *</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="مثال: كتاب خدمة الشماس في القداسات الإلهية" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="author">المؤلف / المطرانية / الدير</label>
                        <input type="text" id="author" name="author" class="form-control" placeholder="مثال: دير القديس أنبا مقار">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="category">تصنيف الكتاب *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="طقس وخدمة الشماس">طقس وخدمة الشماس ⛪</option>
                            <option value="التسبحة والألحان">التسبحة والإبصلمودية 🎶</option>
                            <option value="نصوص القداسات">نصوص القداسات والخولاجي 📖</option>
                            <option value="مناسبات وأعياد">أسبوع الآلام ومناسبات كنسية ✝️</option>
                            <option value="قواعد اللغة القبطية">اللغة القبطية وقواعدها 🔤</option>
                            <option value="عقيدة وتاريخ كنسي">عقيدة وتاريخ كنسي 📜</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="drive_url" style="color:var(--royal-blue); font-weight:800;">
                            🔗 رابط الكتاب على Google Drive *
                        </label>
                        <input type="url" id="drive_url" name="drive_url" class="form-control" placeholder="https://drive.google.com/file/d/... أو رابط المشاركة" required>
                        <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                            💡 ضع رابط ملف الـ PDF من Google Drive بعد تفعيل المشاركة (Anyone with the link can view).
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="cover_image">صورة الغلاف (اختياري)</label>
                        <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-group" style="margin-top:0.5rem;">
                    <label class="form-label" for="description">نبذة مختصرة عن محتوى الكتاب</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="اكتب نبذة عن أبواب الكتاب والمردات والألحان التي يحتويها..."></textarea>
                </div>

                <button type="submit" class="btn btn-gold" style="width:100%; font-weight:800; padding:0.85rem; font-size:1.05rem;">
                    🚀 إضافة الكتاب للمكتبة وتوفير المساحة
                </button>
            </form>
        </div>

        <!-- Books List Table -->
        <div class="glass-card">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem; font-weight:800;">قائمة الكتب المتوفرة بالمكتبة</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>الكتاب</th>
                            <th>المؤلف / الناشر</th>
                            <th>التصنيف</th>
                            <th>نوع التخزين</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)) { ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">لا توجد كتب مضافة بعد.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($books as $bk) {
                                $bookLink = $bk['drive_url'] ?: $bk['pdf_file'];
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <div style="font-size:1.8rem; color:var(--royal-blue);">📖</div>
                                            <div>
                                                <strong><?= sanitize($bk['title']) ?></strong>
                                                <?php if ($bk['description']) { ?>
                                                    <div style="font-size:0.8rem; color:var(--text-muted); max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                        <?= sanitize($bk['description']) ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= sanitize($bk['author'] ?? 'غير محدد') ?></td>
                                    <td><span class="badge badge-info"><?= sanitize($bk['category']) ?></span></td>
                                    <td><span class="badge badge-gold">Google Drive ☁️</span></td>
                                    <td><?= format_arabic_date(substr($bk['created_at'], 0, 10)) ?></td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem; align-items:center;">
                                            <a href="<?= htmlspecialchars($bookLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm" title="فتح الرابط على Google Drive">
                                                ↗️ فتح في Drive
                                            </a>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا الكتاب؟');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_book">
                                                <input type="hidden" name="book_id" value="<?= (int) $bk['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="حذف الكتاب">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

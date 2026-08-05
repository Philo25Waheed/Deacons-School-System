<?php
$pageTitle = 'إدارة الامتحانات ومتابعة أمناء الخدمة';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];

// Filter by class for supervisors
$selectedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$selectedExamId = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);

// Handle Exam Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'create_exam') {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $duration = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT) ?: 15;
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);

            $stmt = $db->prepare('INSERT INTO exams (title, description, duration_minutes, class_id, servant_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $duration, $classId ?: null, $userId, $userId]);

            $_SESSION['flash_success'] = 'تم إنشاء الامتحان بنجاح! يمكنك الآن إضافة الأسئلة (اختياري، صح/غلط، توصيل، مقالي).';
        } elseif ($action === 'add_question') {
            $examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
            $qType = sanitize($_POST['question_type']);
            $questionText = sanitize($_POST['question_text']);
            $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT) ?: 2;

            $optA = sanitize($_POST['option_a'] ?? '');
            $optB = sanitize($_POST['option_b'] ?? '');
            $optC = sanitize($_POST['option_c'] ?? '');
            $optD = sanitize($_POST['option_d'] ?? '');
            $correct = sanitize($_POST['correct_option'] ?? 'a');

            $matchingPairs = null;
            if ($qType === 'matching') {
                $leftItems = $_POST['matching_left'] ?? [];
                $rightItems = $_POST['matching_right'] ?? [];
                $pairs = [];
                for ($i = 0; $i < count($leftItems); $i++) {
                    if (! empty($leftItems[$i]) && ! empty($rightItems[$i])) {
                        $pairs[] = ['left' => sanitize($leftItems[$i]), 'right' => sanitize($rightItems[$i])];
                    }
                }
                $matchingPairs = json_encode($pairs, JSON_UNESCAPED_UNICODE);
            }

            $stmt = $db->prepare('INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, matching_pairs, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$examId, $questionText, $qType, $optA, $optB, $optC ?: null, $optD ?: null, $correct, $matchingPairs, $points]);

            $_SESSION['flash_success'] = 'تم إضافة السؤال بنجاح!';
        } elseif ($action === 'grade_essay') {
            $resultId = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);
            $essayScore = filter_input(INPUT_POST, 'essay_score', FILTER_VALIDATE_INT) ?: 0;
            $feedback = sanitize($_POST['servant_feedback']);

            // Fetch result record
            $resStmt = $db->prepare('SELECT * FROM exam_results WHERE id = ?');
            $resStmt->execute([$resultId]);
            $res = $resStmt->fetch();

            if ($res) {
                $newScore = $res['score'] + $essayScore;
                $db->prepare("UPDATE exam_results SET score = ?, status = 'completed', servant_feedback = ? WHERE id = ?")
                    ->execute([$newScore, $feedback, $resultId]);

                $_SESSION['flash_success'] = "تم رخص وتصحيح السؤال المقالي واعتتماد الدرجة النهائية ({$newScore} من {$res['total_marks']}) بنجاح!";
            }
        }
        header('Location: '.BASE_URL.'admin/exams.php'.($selectedExamId ? "?exam_id={$selectedExamId}" : ''));
        exit;
    }
}

$classes = $db->query('SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name FROM classes c JOIN grades g ON c.grade_id = g.id')->fetchAll();
$exams = $db->query('
    SELECT e.*, u.full_name as servant_name, c.name_ar as class_name, g.name_ar as grade_name,
           (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) as total_questions,
           (SELECT COUNT(*) FROM exam_results r WHERE r.exam_id = e.id) as total_submissions
    FROM exams e
    LEFT JOIN users u ON e.servant_id = u.id
    LEFT JOIN classes c ON e.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    ORDER BY e.id DESC
')->fetchAll();

// Supervisor Inspection View for an Exam
$examDetails = null;
$questionsList = [];
$studentResults = [];
if ($selectedExamId) {
    $stmtE = $db->prepare('
        SELECT e.*, c.name_ar as class_name, g.name_ar as grade_name, u.full_name as servant_name
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        LEFT JOIN users u ON e.servant_id = u.id
        WHERE e.id = ?
    ');
    $stmtE->execute([$selectedExamId]);
    $examDetails = $stmtE->fetch();

    if ($examDetails) {
        $questionsList = $db->query("SELECT * FROM exam_questions WHERE exam_id = {$selectedExamId} ORDER BY id ASC")->fetchAll();

        // Fetch class students and their results
        $classFilter = $examDetails['class_id'] ? "AND u.class_id = {$examDetails['class_id']}" : '';
        $studentResults = $db->query("
            SELECT u.id as student_id, u.full_name, u.qr_code_token, r.id as result_id, r.score, r.total_marks, r.status, r.taken_at, r.answers_json, r.servant_feedback
            FROM users u
            LEFT JOIN exam_results r ON u.id = r.student_id AND r.exam_id = {$selectedExamId}
            WHERE u.role = 'student' AND u.status = 'active' {$classFilter}
            ORDER BY u.full_name ASC
        ")->fetchAll();
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">منظومة الامتحانات ومتابعة الخدام وأمناء الخدمة 📝</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <!-- Exam Builder & Question Creator -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- Create Exam Form -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إنشاء امتحان للفصل</h3>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_exam">
                    <div class="form-group">
                        <label class="form-label">عنوان الامتحان *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: اختبار الألحان والطقس الأسبوعي" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف والتعليمات</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="تعليمات الشماس..."></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">الفصل المستهدف *</label>
                            <select name="class_id" class="form-control" required>
                                <option value="">اختر الفصل...</option>
                                <?php foreach ($classes as $cls) { ?>
                                    <option value="<?= $cls['id'] ?>"><?= sanitize($cls['grade_name']) ?> - <?= sanitize($cls['class_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المدة بالدقائق</label>
                            <input type="number" name="duration_minutes" class="form-control" value="20" min="5">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">حفظ وإنشاء الامتحان</button>
                </form>
            </div>

            <!-- Advanced Question Creator (MCQ, True/False, Matching, Essay) -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">إضافة سؤال (اختيار / صح وغلط / توصيل / مقالي)</h3>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_question">
                    <div class="form-group">
                        <label class="form-label">اختر الامتحان *</label>
                        <select name="exam_id" class="form-control" required>
                            <?php foreach ($exams as $ex) { ?>
                                <option value="<?= $ex['id'] ?>"><?= sanitize($ex['title']) ?> (<?= sanitize($ex['class_name'] ?? 'عام') ?>)</option>
                            <?php } ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">نوع السؤال *</label>
                            <select name="question_type" id="questionTypeSelect" class="form-control" onchange="switchQuestionTypeUI(this.value)" required>
                                <option value="mcq">اختيار من متعدد (MCQ)</option>
                                <option value="true_false">صح أو خطأ (True / False)</option>
                                <option value="matching">توصيل كلمات/عبارات (Matching)</option>
                                <option value="essay">سؤال مقالي (يتطلب تصحيح الخادم)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الدرجة *</label>
                            <input type="number" name="points" class="form-control" value="2" min="1" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نص السؤال *</label>
                        <textarea name="question_text" class="form-control" rows="2" placeholder="اكتب نص السؤال هنا..." required></textarea>
                    </div>

                    <!-- MCQ Options Section -->
                    <div id="mcqSection" style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                        <input type="text" name="option_a" class="form-control" placeholder="خيار (أ)">
                        <input type="text" name="option_b" class="form-control" placeholder="خيار (ب)">
                        <input type="text" name="option_c" class="form-control" placeholder="خيار (جـ)">
                        <input type="text" name="option_d" class="form-control" placeholder="خيار (د)">
                        <div style="grid-column: span 2;">
                            <label class="form-label">الإجابة الصحيحة</label>
                            <select name="correct_option" class="form-control">
                                <option value="a">(أ)</option> <option value="b">(ب)</option> <option value="c">(جـ)</option> <option value="d">(د)</option>
                            </select>
                        </div>
                    </div>

                    <!-- True False Section -->
                    <div id="tfSection" style="display:none; margin-bottom:1rem;">
                        <label class="form-label">الإجابة الصحيحة</label>
                        <select name="correct_tf" class="form-control" onchange="document.getElementsByName('correct_option')[0].value = this.value">
                            <option value="a">صح (True)</option>
                            <option value="b">خطأ (False)</option>
                        </select>
                    </div>

                    <!-- Matching Section -->
                    <div id="matchingSection" style="display:none; background:var(--royal-blue-glow); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1rem;">
                        <label class="form-label">أزواج التوصيل (العمود الأول = ما يقابله في العمود الثاني)</label>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; margin-bottom:0.5rem;">
                            <input type="text" name="matching_left[]" class="form-control" placeholder="الكلمة / الطرف الأول (مثال: آدم)">
                            <input type="text" name="matching_right[]" class="form-control" placeholder="ما يقابلها (مثال: أول البشر)">
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                            <input type="text" name="matching_left[]" class="form-control" placeholder="الطرف الأول (مثال: نوح)">
                            <input type="text" name="matching_right[]" class="form-control" placeholder="ما يقابلها (مثال: الفلك)">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold" style="width:100%;">حفظ السؤال في الامتحان</button>
                </form>
            </div>
        </div>

        <!-- Exams List & Supervisor Inspection Portal -->
        <div class="glass-card" style="margin-bottom:2rem;">
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">قائمة كافة امتحانات الخدام والفصول (لوحة أمين الخدمة والمدير)</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>اسم الامتحان</th>
                            <th>الخادم واضع الامتحان</th>
                            <th>الفصل المستهدف</th>
                            <th>عدد الأسئلة</th>
                            <th>عدد الذين اختبروا</th>
                            <th>معاينة ومتابعة الدرجات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $ex) { ?>
                            <tr>
                                <td><strong><?= sanitize($ex['title']) ?></strong></td>
                                <td><?= sanitize($ex['servant_name'] ?? 'المدير') ?></td>
                                <td><?= sanitize($ex['grade_name'] ?? '') ?> - <?= sanitize($ex['class_name'] ?? 'جميع الفصول') ?></td>
                                <td><span class="badge badge-info"><?= $ex['total_questions'] ?> أسئلة</span></td>
                                <td><span class="badge badge-gold"><?= $ex['total_submissions'] ?> شماس</span></td>
                                <td>
                                    <a href="?exam_id=<?= $ex['id'] ?>" class="btn btn-primary btn-sm">👁️ متابعة أسئلة ودرجات الفصل</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Inspection & Manual Essay Grading Section -->
        <?php if ($examDetails) { ?>
            <div class="glass-card" style="border:2px solid var(--royal-blue);">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1.5rem;">
                    <div>
                        <h2 style="color:var(--royal-blue); font-weight:800;">تقرير متابعة الامتحان: <?= sanitize($examDetails['title']) ?></h2>
                        <p style="color:var(--text-muted);">الخادم واضع الامتحان: <?= sanitize($examDetails['servant_name']) ?> | الفصل: <?= sanitize($examDetails['grade_name']) ?> (<?= sanitize($examDetails['class_name']) ?>)</p>
                    </div>
                    <a href="<?= BASE_URL ?>admin/exams.php" class="btn btn-secondary btn-sm">إغلاق المتابعة</a>
                </div>

                <!-- Questions Inspection -->
                <h4 style="color:var(--gold); margin-bottom:0.75rem;">1. الأسئلة المدرجة بهذا الامتحان (<?= count($questionsList) ?> سؤال):</h4>
                <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:2rem;">
                    <?php foreach ($questionsList as $idx => $ql) { ?>
                        <div style="background:var(--bg-primary); padding:0.75rem; border-radius:var(--radius-sm); font-size:0.9rem;">
                            <strong>س<?= $idx + 1 ?>: <?= sanitize($ql['question_text']) ?></strong>
                            <span class="badge badge-info" style="margin-right:0.5rem;"><?= $ql['question_type'] ?></span>
                            <span style="float:left; color:var(--gold); font-weight:700;"><?= $ql['points'] ?> درجات</span>
                        </div>
                    <?php } ?>
                </div>

                <!-- Roster & Manual Essay Grading Table -->
                <h4 style="color:var(--gold); margin-bottom:0.75rem;">2. درجات وتصحيح الشمامسة بهذا الفصل:</h4>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>اسم الشماس</th>
                                <th>الكود</th>
                                <th>تاريخ الاختبار</th>
                                <th>حالة التصحيح</th>
                                <th>الدرجة النهائية</th>
                                <th>تصحيح مقالي / ملاحظات الخادم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentResults as $sr) { ?>
                                <tr>
                                    <td><strong><?= sanitize($sr['full_name']) ?></strong></td>
                                    <td><code><?= sanitize($sr['qr_code_token']) ?></code></td>
                                    <td><?= $sr['taken_at'] ? format_arabic_date($sr['taken_at']) : 'لم يختبر بعد' ?></td>
                                    <td>
                                        <?php if (! $sr['taken_at']) { ?>
                                            <span class="badge badge-secondary">لم يدخل الامتحان</span>
                                        <?php } elseif ($sr['status'] === 'needs_grading') { ?>
                                            <span class="badge badge-warning">يتطلب تصحيح السؤال المقالي ⏳</span>
                                        <?php } else { ?>
                                            <span class="badge badge-success">مكتمل ومصمم ✅</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($sr['taken_at']) { ?>
                                            <strong style="color:var(--royal-blue); font-size:1.1rem;"><?= $sr['score'] ?> / <?= $sr['total_marks'] ?></strong>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($sr['taken_at'] && $sr['status'] === 'needs_grading') { ?>
                                            <!-- Manual Essay Grading Form -->
                                            <form action="" method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="grade_essay">
                                                <input type="hidden" name="result_id" value="<?= $sr['result_id'] ?>">
                                                <input type="number" name="essay_score" class="form-control" style="width:90px;" placeholder="درجة المقالي" required>
                                                <input type="text" name="servant_feedback" class="form-control" placeholder="ملاحظات الخادم...">
                                                <button type="submit" class="btn btn-gold btn-sm">اعتماد الدرجة</button>
                                            </form>
                                        <?php } else { ?>
                                            <?= sanitize($sr['servant_feedback'] ?? '-') ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </main>
</div>

<script>
function switchQuestionTypeUI(type) {
    const mcq = document.getElementById('mcqSection');
    const tf = document.getElementById('tfSection');
    const matching = document.getElementById('matchingSection');

    mcq.style.display = (type === 'mcq') ? 'grid' : 'none';
    tf.style.display = (type === 'true_false') ? 'block' : 'none';
    matching.style.display = (type === 'matching') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

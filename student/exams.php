<?php
$pageTitle = 'الامتحانات والاختبارات الأونلاين';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('student', 'admin');

$db = getDB();
$studentId = $_SESSION['user']['id'];
$examId = filter_input(INPUT_GET, 'take_id', FILTER_VALIDATE_INT);

// Handle Exam Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
        $userAnswers = $_POST['answers'] ?? [];
        $matchingAnswers = $_POST['matching_answers'] ?? [];
        $essayAnswers = $_POST['essay_answers'] ?? [];

        // Fetch questions
        $stmtQ = $db->prepare('SELECT * FROM exam_questions WHERE exam_id = ?');
        $stmtQ->execute([$examId]);
        $questions = $stmtQ->fetchAll();

        $score = 0;
        $totalMarks = 0;
        $hasEssay = false;

        foreach ($questions as $q) {
            $totalMarks += $q['points'];
            $qId = $q['id'];

            if ($q['question_type'] === 'mcq' || $q['question_type'] === 'true_false') {
                $ans = $userAnswers[$qId] ?? '';
                if ($ans === $q['correct_option']) {
                    $score += $q['points'];
                }
            } elseif ($q['question_type'] === 'matching') {
                $mPairs = json_decode($q['matching_pairs'] ?? '[]', true);
                $mUserAns = $matchingAnswers[$qId] ?? [];
                $correctCount = 0;
                $totalPairs = count($mPairs);

                if ($totalPairs > 0) {
                    foreach ($mPairs as $idx => $pair) {
                        if (isset($mUserAns[$idx]) && $mUserAns[$idx] === $pair['right']) {
                            $correctCount++;
                        }
                    }
                    $score += round(($correctCount / $totalPairs) * $q['points']);
                }
            } elseif ($q['question_type'] === 'essay') {
                $hasEssay = true;
            }
        }

        $status = $hasEssay ? 'needs_grading' : 'completed';
        $fullAnswersPayload = json_encode([
            'objective' => $userAnswers,
            'matching' => $matchingAnswers,
            'essay' => $essayAnswers,
        ], JSON_UNESCAPED_UNICODE);

        // Save Result
        $stmtRes = $db->prepare('INSERT INTO exam_results (exam_id, student_id, score, total_marks, status, answers_json) VALUES (?, ?, ?, ?, ?, ?)');
        $stmtRes->execute([$examId, $studentId, $score, $totalMarks, $status, $fullAnswersPayload]);

        // Award points if pure objective & score >= 70%
        if (! $hasEssay && $totalMarks > 0 && ($score / $totalMarks) >= 0.7) {
            $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, 1, 5, 'positive', 'اجتياز اختبار أونلاين بنجاح')")
                ->execute([$studentId]);
        }

        if ($hasEssay) {
            $_SESSION['flash_success'] = "تم تسليم الامتحان بنجاح! درجتك الجزئية للأسئلة الموضوعية هي ({$score} من {$totalMarks})، والحالة قيد التصحيح للسؤال المقالي من الخادم.";
        } else {
            $_SESSION['flash_success'] = "تم إنهاء الامتحان بنجاح! درجتك النهائية هي: ({$score} من {$totalMarks}).";
        }
        header('Location: '.BASE_URL.'student/exams.php');
        exit;
    }
}

// Available Exams
$exams = $db->query("
    SELECT e.*, (SELECT score FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = $studentId ORDER BY id DESC LIMIT 1) as my_score,
                (SELECT total_marks FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = $studentId ORDER BY id DESC LIMIT 1) as my_total,
                (SELECT status FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = $studentId ORDER BY id DESC LIMIT 1) as my_status,
                (SELECT servant_feedback FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = $studentId ORDER BY id DESC LIMIT 1) as my_feedback
    FROM exams e ORDER BY e.id DESC
")->fetchAll();

$currentExam = null;
$questions = [];
if ($examId) {
    $stmtE = $db->prepare('SELECT * FROM exams WHERE id = ?');
    $stmtE->execute([$examId]);
    $currentExam = $stmtE->fetch();

    if ($currentExam) {
        $stmtQ = $db->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC');
        $stmtQ->execute([$examId]);
        $questions = $stmtQ->fetchAll();
    }
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:1.5rem;">الامتحانات والاختبارات الأونلاين 📝</h1>

        <?php if (isset($_SESSION['flash_success'])) { ?>
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:1rem; margin-bottom:1.5rem; font-size:1.05rem; text-align:center; line-height:1.6;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if ($currentExam && ! empty($questions)) { ?>
            <!-- Active Exam Taking Mode -->
            <div class="glass-card">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1.5rem;">
                    <div>
                        <h2 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($currentExam['title']) ?></h2>
                        <p style="color:var(--text-muted);"><?= sanitize($currentExam['description'] ?? '') ?></p>
                    </div>
                    <span class="badge badge-gold" style="font-size:1.1rem;">⏱️ المدة: <?= $currentExam['duration_minutes'] ?> دقيقة</span>
                </div>

                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submit_exam" value="1">
                    <input type="hidden" name="exam_id" value="<?= $currentExam['id'] ?>">

                    <?php foreach ($questions as $idx => $q) { ?>
                        <div style="background:var(--bg-primary); padding:1.25rem; border-radius:var(--radius-sm); margin-bottom:1.25rem;">
                            <h4 style="color:var(--text-primary); font-weight:700; margin-bottom:1rem;">
                                س<?= $idx + 1 ?>: <?= sanitize($q['question_text']) ?>
                                <span class="badge badge-info" style="margin-right:0.5rem;">
                                    <?php
                                $typeLabels = ['mcq' => 'اختيار من متعدد', 'true_false' => 'صح أم خطأ', 'matching' => 'توصيل', 'essay' => 'سؤال مقالي'];
                        echo $typeLabels[$q['question_type']] ?? 'سؤال';
                        ?>
                                </span>
                                <span style="float:left; color:var(--gold); font-weight:700;"><?= $q['points'] ?> درجات</span>
                            </h4>
                            
                            <?php if ($q['question_type'] === 'mcq') { ?>
                                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="a" required> (أ) <?= sanitize($q['option_a']) ?>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="b"> (ب) <?= sanitize($q['option_b']) ?>
                                    </label>
                                    <?php if ($q['option_c']) { ?>
                                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="c"> (جـ) <?= sanitize($q['option_c']) ?>
                                        </label>
                                    <?php } ?>
                                    <?php if ($q['option_d']) { ?>
                                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="d"> (د) <?= sanitize($q['option_d']) ?>
                                        </label>
                                    <?php } ?>
                                </div>

                            <?php } elseif ($q['question_type'] === 'true_false') { ?>
                                <div style="display:flex; gap:1.5rem;">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="a" required> (✔️) صح (True)
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="b"> (❌) خطأ (False)
                                    </label>
                                </div>

                            <?php } elseif ($q['question_type'] === 'matching') { ?>
                                <?php
                                $pairs = json_decode($q['matching_pairs'] ?? '[]', true);
                                $rightOptions = array_column($pairs, 'right');
                                shuffle($rightOptions); // Shuffle right options for student matching challenge
                                ?>
                                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.75rem;">اختر ما يناسب كل عنصر في العمود الأول:</p>
                                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                    <?php foreach ($pairs as $pIdx => $pair) { ?>
                                        <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                                            <strong><?= sanitize($pair['left']) ?></strong>
                                            <span>⬅️ يصل بـ ⬅️</span>
                                            <select name="matching_answers[<?= $q['id'] ?>][<?= $pIdx ?>]" class="form-control" style="width:250px;" required>
                                                <option value="">اختر الإجابة المقابلة...</option>
                                                <?php foreach ($rightOptions as $rOpt) { ?>
                                                    <option value="<?= sanitize($rOpt) ?>"><?= sanitize($rOpt) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                </div>

                            <?php } elseif ($q['question_type'] === 'essay') { ?>
                                <textarea name="essay_answers[<?= $q['id'] ?>]" class="form-control" rows="3" placeholder="اكتب إجابتك الشاملة هنا..." required></textarea>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-gold" style="width:100%; padding:1rem; font-size:1.1rem;">تسليم الإجابات وإنهاء الامتحان</button>
                </form>
            </div>
        <?php } else { ?>
            <!-- Exams List -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
                <?php foreach ($exams as $ex) { ?>
                    <div class="glass-card">
                        <h3 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.5rem;"><?= sanitize($ex['title']) ?></h3>
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;"><?= sanitize($ex['description'] ?? '') ?></p>

                        <?php if ($ex['my_score'] !== null) { ?>
                            <div style="background:var(--bg-primary); padding:0.85rem; border-radius:var(--radius-sm); margin-bottom:1rem; text-align:center;">
                                <span style="font-size:0.85rem; color:var(--text-muted); display:block;">النتيجة التي حصلت عليها:</span>
                                <strong style="color:var(--gold); font-size:1.2rem;"><?= $ex['my_score'] ?> / <?= $ex['my_total'] ?></strong>
                                <?php if ($ex['my_status'] === 'needs_grading') { ?>
                                    <div class="badge badge-warning" style="margin-top:0.4rem; display:block;">جاري تصحيح السؤال المقالي ⏳</div>
                                <?php } ?>
                                <?php if (! empty($ex['my_feedback'])) { ?>
                                    <div style="font-size:0.8rem; color:var(--royal-blue); margin-top:0.4rem;">ملاحظات الخادم: <?= sanitize($ex['my_feedback']) ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <a href="?take_id=<?= $ex['id'] ?>" class="btn btn-primary" style="width:100%;">دخول الاختبار الآن ✏️</a>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

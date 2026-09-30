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

        // Fetch exam info
        $stmtExam = $db->prepare('SELECT * FROM exams WHERE id = ?');
        $stmtExam->execute([$examId]);
        $examData = $stmtExam->fetch();

        if (! $examData) {
            $_SESSION['flash_error'] = 'عذراً، الامتحان المطلوب غير موجود.';
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

        // 1. CHECK DEADLINE
        if (! empty($examData['deadline']) && strtotime($examData['deadline']) < time()) {
            $_SESSION['flash_error'] = 'عذراً، لقد انتهى الموعد النهائي المحدد لهذا الامتحان (Deadline) وتم إغلاقه تلقائياً.';
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

        // 2. CHECK SINGLE ATTEMPT (Cannot take exam more than once)
        $stmtCheck = $db->prepare('SELECT id, score, total_marks FROM exam_results WHERE exam_id = ? AND student_id = ?');
        $stmtCheck->execute([$examId, $studentId]);
        $existingRes = $stmtCheck->fetch();

        if ($existingRes) {
            $_SESSION['flash_error'] = "لقد قمت بأداء هذا الامتحان مسبقاً! درجتك المسجلة هي ({$existingRes['score']} من {$existingRes['total_marks']})، ولا يُسمح بإعادة الاختبار سوى مرة واحدة فقط.";
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

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

        $cheatingViolations = filter_input(INPUT_POST, 'cheating_violations', FILTER_VALIDATE_INT) ?: 0;
        $cheatingDetails = sanitize($_POST['cheating_details'] ?? '');
        $isAutoSubmittedCheating = ! empty($_POST['auto_submitted_cheating']);

        $passPercentage = (int) ($examData['pass_percentage'] ?? 50);
        $rewardPoints = (int) ($examData['reward_points'] ?? 5);

        $status = $hasEssay ? 'needs_grading' : 'completed';
        $fullAnswersPayload = json_encode([
            'objective' => $userAnswers,
            'matching' => $matchingAnswers,
            'essay' => $essayAnswers,
        ], JSON_UNESCAPED_UNICODE);

        $earnedPoints = 0;
        $pct = ($totalMarks > 0) ? round(($score / $totalMarks) * 100) : 0;
        $isPassed = ($pct >= $passPercentage);

        // Award points if pure objective & student passed
        if (! $hasEssay && $isPassed && $rewardPoints > 0) {
            $earnedPoints = $rewardPoints;
            $servantCreator = $examData['created_by'] ?: 1;
            $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, 'positive', ?)")
                ->execute([
                    $studentId,
                    $servantCreator,
                    $rewardPoints,
                    "اجتياز اختبار ({$examData['title']}) بنجاح (+{$rewardPoints} طايو)",
                ]);
        }

        // Save Result
        $stmtRes = $db->prepare('
            INSERT INTO exam_results (exam_id, student_id, score, total_marks, status, points_awarded, cheating_violations, cheating_details, answers_json) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtRes->execute([
            $examId,
            $studentId,
            $score,
            $totalMarks,
            $status,
            $earnedPoints,
            $cheatingViolations,
            $cheatingDetails ?: ($isAutoSubmittedCheating ? 'تسليم تلقائي لتكرار محاولات مغادرة الشاشة' : null),
            $fullAnswersPayload,
        ]);

        // Clean up session timer on submission
        $sessionKey = 'exam_start_'.$studentId.'_'.$examId;
        unset($_SESSION[$sessionKey]);

        if ($isAutoSubmittedCheating) {
            $_SESSION['flash_error'] = "⚠️ تم سحب الامتحان وتسليمه تلقائياً لتكرار محاولات مغادرة صفحة الاختبار (تم تسجيل {$cheatingViolations} مخالفات). درجتك هي ({$score} من {$totalMarks}) بنسبة {$pct}%.";
        } elseif ($hasEssay) {
            $_SESSION['flash_success'] = "تم تسليم الامتحان بنجاح! درجتك المبدئية للأسئلة الموضوعية هي ({$score} من {$totalMarks})، والحالة قيد التصحيح للسؤال المقالي من الخادم. سيتم منح نقاط الطايو المحددة ({$rewardPoints} طايو) فور اعتماد النتيجة عند النجاح.";
        } else {
            $tayoNote = ($earnedPoints > 0)
                ? " 🎉 مبروك! لقد نجحت بنسبة {$pct}% وحصلت على {$earnedPoints} طايو كهدية نجاح!"
                : " بنسبة {$pct}%. (نسبة النجاح المطلوبة للحصول على الطايو هي {$passPercentage}% ولم تحققها هذه المرة).";
            $_SESSION['flash_success'] = "تم تسليم الامتحان بنجاح! درجتك النهائية هي ({$score} من {$totalMarks}){$tayoNote}";
        }
        header('Location: '.BASE_URL.'student/exams.php');
        exit;
    }
}

// Student's class ID for scoped exam visibility
$stmtStu = $db->prepare('SELECT class_id FROM users WHERE id = ?');
$stmtStu->execute([$studentId]);
$studentClassId = $stmtStu->fetchColumn();

// Fetch exams for this student's class or general exams
$stmtExams = $db->prepare('
    SELECT e.*,
           c.name_ar as class_name,
           g.name_ar as grade_name,
           u.full_name as servant_name,
           (SELECT score FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_score,
           (SELECT total_marks FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_total,
           (SELECT status FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_status,
           (SELECT points_awarded FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_points_awarded,
           (SELECT cheating_violations FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_cheating_violations,
           (SELECT servant_feedback FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_feedback,
           (SELECT taken_at FROM exam_results r WHERE r.exam_id = e.id AND r.student_id = ? ORDER BY id DESC LIMIT 1) as my_taken_at,
           (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) as total_questions
    FROM exams e
    LEFT JOIN classes c ON e.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    LEFT JOIN users u ON e.servant_id = u.id
    WHERE (e.class_id IS NULL OR e.class_id = ?)
    ORDER BY e.id DESC
');
$stmtExams->execute([$studentId, $studentId, $studentId, $studentId, $studentId, $studentId, $studentId, $studentClassId ?: 0]);
$exams = $stmtExams->fetchAll();

$currentExam = null;
$questions = [];
$remainingSeconds = 0;
$durationMinutes = 20;

if ($examId) {
    $stmtE = $db->prepare('SELECT * FROM exams WHERE id = ?');
    $stmtE->execute([$examId]);
    $currentExam = $stmtE->fetch();

    if ($currentExam) {
        // 1. Verify student class restriction
        if (! empty($currentExam['class_id']) && $studentClassId && $currentExam['class_id'] != $studentClassId && $_SESSION['user']['role'] === 'student') {
            $_SESSION['flash_error'] = 'عذراً، هذا الامتحان مخصص لفصل دراسي آخر.';
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

        // 2. CHECK DEADLINE: Has deadline passed?
        if (! empty($currentExam['deadline']) && strtotime($currentExam['deadline']) < time()) {
            $_SESSION['flash_error'] = 'عذراً، لقد انتهى الموعد النهائي المحدد لهذا الامتحان (Deadline) وتم إغلاقه.';
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

        // 3. CHECK SINGLE ATTEMPT: Already submitted?
        $chkRes = $db->prepare('SELECT id, score, total_marks FROM exam_results WHERE exam_id = ? AND student_id = ?');
        $chkRes->execute([$examId, $studentId]);
        $alreadyRes = $chkRes->fetch();

        if ($alreadyRes) {
            $_SESSION['flash_error'] = "لقد قمت بأداء هذا الامتحان مسبقاً! درجتك المسجلة هي ({$alreadyRes['score']} من {$alreadyRes['total_marks']}). يُسمح بالمحاولة مرة واحدة فقط.";
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

        // 4. CHECK DURATION TIMER: Track individual session timer
        $durationMinutes = (int) ($currentExam['duration_minutes'] ?: 20);
        $totalAllowedSeconds = $durationMinutes * 60;
        $sessionKey = 'exam_start_'.$studentId.'_'.$examId;

        if (! isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = time();
        }

        $elapsedSeconds = time() - $_SESSION[$sessionKey];
        $remainingSeconds = max(0, $totalAllowedSeconds - $elapsedSeconds);

        if ($remainingSeconds <= 0) {
            unset($_SESSION[$sessionKey]);
            $_SESSION['flash_error'] = "عذراً، لقد انتهى الوقت المخصص للامتحان ({$durationMinutes} دقيقة) بالكامل وتم إغلاقه تلقائياً.";
            header('Location: '.BASE_URL.'student/exams.php');
            exit;
        }

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

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="alert-box alert-box-danger alert-dismissible" style="width:100%; justify-content:center; text-align:center;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <?php if ($currentExam && ! empty($questions)) {
            $hasD = ! empty($currentExam['deadline']);
            ?>
            <!-- Active Exam Taking Mode -->
            <style>
                .exam-secure-area {
                    user-select: none;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                }
                .exam-secure-area textarea, .exam-secure-area input[type="text"] {
                    user-select: text;
                    -webkit-user-select: text;
                }
                #antiCheatWarningModal {
                    display: none;
                    position: fixed;
                    top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.75);
                    z-index: 99999;
                    backdrop-filter: blur(5px);
                    justify-content: center;
                    align-items: center;
                }
            </style>

            <!-- Anti-Cheating Warning Modal -->
            <div id="antiCheatWarningModal">
                <div class="glass-card" style="background:var(--bg-surface); color:var(--text-primary); max-width:480px; width:90%; text-align:center; padding:2rem; border-radius:16px; border:3px solid #ef4444; box-shadow:0 25px 50px rgba(220,38,38,0.3);">
                    <div style="font-size:3rem; margin-bottom:0.5rem;">⚠️🚨</div>
                    <h3 style="color:#ef4444; font-weight:900; margin-bottom:0.75rem;">تنبيه أمني: رصد محاولة خروج أو غش!</h3>
                    <p id="antiCheatModalText" style="color:var(--text-secondary); font-size:1rem; line-height:1.6; margin-bottom:1.5rem;">
                        تم رصد مغادرة صفحة الاختبار أو تبديل النافذة. يُحظر تماماً فتح أي نوافذ أو تطبيقات أخرى أثناء أداء الامتحان.
                    </p>
                    <div class="alert-box alert-box-danger" style="justify-content:center; margin-bottom:1.5rem; font-weight:700;">
                        المخالفة رقم: <span id="modalViolationNum" style="font-size:1.3rem; margin:0 0.35rem;">1</span> من أصل 3 مخالفات مسموحة
                    </div>
                    <button type="button" class="btn btn-danger" style="width:100%; font-weight:800; font-size:1.05rem;" onclick="dismissAntiCheatModal()">
                        أوافق وأتعهد بالبقاء في الامتحان 🛡️
                    </button>
                </div>
            </div>

            <div class="glass-card exam-secure-area" id="examContainerCard" style="position:relative;">
                <!-- Live Sticky Countdown Timer & Security Indicator -->
                <div style="position:sticky; top:12px; z-index:100; background:linear-gradient(135deg, #1e3a8a, #0f172a); color:#ffffff; padding:0.9rem 1.5rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 6px 20px rgba(0,0,0,0.25); border:1px solid rgba(212,175,55,0.4); flex-wrap:wrap; gap:0.75rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:700; font-size:1.05rem;">
                        <span>⏱️</span>
                        <span>الوقت المتبقي لانتهاء وخروج الامتحان:</span>
                    </div>
                    <div id="countdownTimer" style="font-size:1.6rem; font-weight:900; color:var(--gold); font-family:monospace; direction:ltr; letter-spacing:1px; background:rgba(0,0,0,0.3); padding:0.2rem 0.8rem; border-radius:6px;">
                        --:--
                    </div>
                </div>

                <!-- Anti-Cheating Active Security Bar -->
                <div class="alert-box alert-box-danger" style="justify-content:space-between; flex-wrap:wrap; margin-bottom:1.5rem;">
                    <div style="display:flex; align-items:center; gap:0.6rem; font-weight:700; font-size:0.95rem;">
                        <span>🛡️ نظام المراقبة ومنع الغش:</span>
                        <span id="violationCountBadge" class="badge badge-success" style="font-size:0.85rem;">0 مخالفات مرصودة</span>
                        <span style="font-size:0.8rem; opacity:0.9;">(يُمنع تبديل التاب أو فتح نافذة أخرى، الإغلاق التلقائي عند 3 مخالفات)</span>
                    </div>
                    <button type="button" id="fullscreenToggleBtn" class="btn btn-sm btn-secondary" onclick="toggleExamFullscreen()" style="font-size:0.85rem; font-weight:700;">
                        ⛶ وضع ملء الشاشة
                    </button>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($currentExam['title']) ?></h2>
                        <p style="color:var(--text-muted); margin:0.35rem 0;"><?= sanitize($currentExam['description'] ?? '') ?></p>
                        <?php if ($hasD) { ?>
                            <div style="margin-top:0.4rem; color:#dc2626; font-weight:700; font-size:0.9rem;">
                                📅 الموعد النهائي العام للنظام: <?= date('d/m/Y h:i A', strtotime($currentExam['deadline'])) ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.4rem;">
                        <span class="badge badge-gold" style="font-size:1.05rem; font-weight:800;">
                            🪙 مكافأة النجاح: <?= (int) ($currentExam['reward_points'] ?? 5) ?> طايو (نسبة <?= (int) ($currentExam['pass_percentage'] ?? 50) ?>%)
                        </span>
                        <div style="display:flex; gap:0.4rem;">
                            <span class="badge badge-info" style="font-size:0.85rem;">⏱️ المدة: <?= $durationMinutes ?> دقيقة</span>
                            <span class="badge badge-warning" style="font-size:0.85rem;">⚠️ محاولة واحدة فقط</span>
                        </div>
                    </div>
                </div>

                <div class="badge badge-info" style="display:block; padding:0.85rem; margin-bottom:1.5rem; text-align:right; font-size:0.95rem; line-height:1.6;">
                    📌 <strong>تنبيهات الاختبار:</strong> عند انتهاء الوقت أو رصد 3 محاولات خروج، يقوم النظام تلقائياً بتسليم إجاباتك وإغلاق الامتحان. يُرجى التركيز في صفحة الامتحان فقط.
                </div>

                <form id="examActiveForm" action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submit_exam" value="1">
                    <input type="hidden" name="exam_id" value="<?= $currentExam['id'] ?>">
                    <!-- Anti-Cheating Hidden Tracking -->
                    <input type="hidden" name="cheating_violations" id="cheatingViolationsInput" value="0">
                    <input type="hidden" name="cheating_details" id="cheatingDetailsInput" value="">
                    <input type="hidden" name="auto_submitted_cheating" id="autoSubmittedCheatingInput" value="0">

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
                                if (! empty($pairs)) {
                                    $rightOptions = array_column($pairs, 'right');
                                    shuffle($rightOptions);
                                    ?>
                                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                        <?php foreach ($pairs as $pIdx => $p) { ?>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; align-items:center;">
                                                <div><strong><?= $pIdx + 1 ?>. <?= sanitize($p['left']) ?></strong></div>
                                                <div>
                                                    <select name="matching_answers[<?= $q['id'] ?>][<?= $pIdx ?>]" class="form-control" required>
                                                        <option value="">-- اختر المطابق --</option>
                                                        <?php foreach ($rightOptions as $rOpt) { ?>
                                                            <option value="<?= sanitize($rOpt) ?>"><?= sanitize($rOpt) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>

                            <?php } elseif ($q['question_type'] === 'essay') { ?>
                                <textarea name="essay_answers[<?= $q['id'] ?>]" class="form-control" rows="3" placeholder="اكتب إجابتك الشاملة هنا..." required></textarea>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-gold" style="width:100%; padding:1rem; font-size:1.1rem; font-weight:800;">تسليم الإجابات وإنهاء الامتحان</button>
                </form>
            </div>
        <?php } else { ?>
            <!-- Exams List -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">
                <?php if (empty($exams)) { ?>
                    <div class="glass-card" style="grid-column: 1 / -1; text-align:center; padding:2rem; color:var(--text-muted);">
                        لا توجد امتحانات متاحة لفصلك حالياً.
                    </div>
                <?php } ?>
                <?php foreach ($exams as $ex) {
                    $hasDeadline = ! empty($ex['deadline']);
                    $isExpired = $hasDeadline && (strtotime($ex['deadline']) < time());
                    $isCompleted = ($ex['my_score'] !== null);
                    $pct = ($isCompleted && $ex['my_total'] > 0) ? round(($ex['my_score'] / $ex['my_total']) * 100) : 0;
                    $rewPts = (int) ($ex['reward_points'] ?? 5);
                    $passPct = (int) ($ex['pass_percentage'] ?? 50);
                    $ptsEarned = (int) ($ex['my_points_awarded'] ?? 0);
                    $cheatingCount = (int) ($ex['my_cheating_violations'] ?? 0);
                    ?>
                    <div class="glass-card" style="display:flex; flex-direction:column; justify-content:space-between; position:relative;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem; gap:0.5rem;">
                                <h3 style="color:var(--royal-blue); font-weight:800; font-size:1.2rem;"><?= sanitize($ex['title']) ?></h3>
                                <?php if ($isCompleted) { ?>
                                    <span class="badge badge-success">تم الأداء ✅</span>
                                <?php } elseif ($isExpired) { ?>
                                    <span class="badge badge-danger">مغلق 🔒</span>
                                <?php } else { ?>
                                    <span class="badge badge-gold">متاح ✏️</span>
                                <?php } ?>
                            </div>

                            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;"><?= sanitize($ex['description'] ?? '') ?></p>

                            <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:1rem; font-size:0.8rem;">
                                <span class="badge badge-gold" style="font-weight:700;">🪙 جائزة النجاح: <?= $rewPts ?> طايو</span>
                                <span class="badge badge-info">🎯 نسبة النجاح: <?= $passPct ?>%</span>
                                <span class="badge badge-info">⏱️ <?= $ex['duration_minutes'] ?> دقيقة</span>
                                <span class="badge badge-info">❓ <?= $ex['total_questions'] ?> أسئلة</span>
                                <?php if ($ex['class_name']) { ?>
                                    <span class="badge badge-secondary"><?= sanitize($ex['grade_name'] ?? '') ?> - <?= sanitize($ex['class_name']) ?></span>
                                <?php } ?>
                            </div>

                            <!-- Deadline Notice -->
                            <div style="margin-bottom:1rem; font-size:0.85rem; padding:0.5rem 0.75rem; border-radius:var(--radius-sm); background:var(--bg-primary);">
                                <?php if ($hasDeadline) { ?>
                                    <?php if ($isExpired) { ?>
                                        <span style="color:#dc2626; font-weight:700;">🔒 انتهى موعد الامتحان:</span>
                                        <div style="color:var(--text-muted); font-size:0.8rem; margin-top:0.2rem;"><?= date('d/m/Y h:i A', strtotime($ex['deadline'])) ?></div>
                                    <?php } else { ?>
                                        <span style="color:#d97706; font-weight:700;">⏱️ آخر موعد للتسليم:</span>
                                        <div style="color:var(--text-primary); font-size:0.8rem; margin-top:0.2rem; font-weight:600;"><?= date('d/m/Y h:i A', strtotime($ex['deadline'])) ?></div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span style="color:var(--text-muted);">متاح بدون موعد انتهاء محدد</span>
                                <?php } ?>
                            </div>

                            <!-- Student Result Card (if completed) -->
                            <?php if ($isCompleted) { ?>
                                <div style="background:linear-gradient(135deg, rgba(30,58,138,0.06), rgba(212,175,55,0.08)); border:1px solid var(--border-color); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; text-align:center;">
                                    <span style="font-size:0.85rem; color:var(--text-muted); display:block;">الدرجة المحصلة:</span>
                                    <strong style="color:var(--royal-blue); font-size:1.4rem; display:block; margin:0.3rem 0;">
                                        <?= $ex['my_score'] ?> / <?= $ex['my_total'] ?>
                                    </strong>
                                    <span class="badge <?= $pct >= $passPct ? 'badge-success' : 'badge-danger' ?>" style="font-size:0.9rem;">
                                        <?= $pct ?>% (<?= $pct >= $passPct ? 'ناجح 🎉' : 'راسب' ?>)
                                    </span>

                                    <!-- Tayo Reward Status Badge -->
                                    <?php if ($ptsEarned > 0) { ?>
                                        <div class="badge badge-gold" style="margin-top:0.6rem; display:block; font-size:0.95rem; font-weight:800; padding:0.4rem;">
                                            🪙 تم الحصول على +<?= $ptsEarned ?> طايو بنجاح! 🎉
                                        </div>
                                    <?php } elseif ($pct >= $passPct && $ex['my_status'] === 'needs_grading') { ?>
                                        <div class="badge badge-warning" style="margin-top:0.6rem; display:block;">سيتم منح الـ <?= $rewPts ?> طايو فور اعتماد المقالي ⏳</div>
                                    <?php } else { ?>
                                        <div class="badge badge-secondary" style="margin-top:0.6rem; display:block; font-size:0.8rem;">لم تتحقق نسبة النجاح المحددة للطايو (<?= $passPct ?>%)</div>
                                    <?php } ?>

                                    <?php if ($cheatingCount > 0) { ?>
                                        <div class="badge badge-danger" style="margin-top:0.5rem; display:block; font-size:0.8rem;">
                                            ⚠️ تم رصد <?= $cheatingCount ?> محاولة خروج أثناء الامتحان
                                        </div>
                                    <?php } ?>

                                    <?php if ($ex['my_status'] === 'needs_grading') { ?>
                                        <div class="badge badge-warning" style="margin-top:0.6rem; display:block;">جاري مراجعة وتصحيح السؤال المقالي ⏳</div>
                                    <?php } else { ?>
                                        <div class="badge badge-success" style="margin-top:0.6rem; display:block;">تم اعتماد النتيجة ✅</div>
                                    <?php } ?>

                                    <?php if (! empty($ex['my_feedback'])) { ?>
                                        <div style="font-size:0.85rem; color:var(--text-primary); margin-top:0.6rem; background:var(--bg-primary); border:1px solid var(--border-color); padding:0.6rem 0.75rem; border-radius:6px; text-align:right;">
                                            <strong style="color:var(--royal-blue);">ملاحظات الخادم:</strong> <?= sanitize($ex['my_feedback']) ?>
                                        </div>
                                    <?php } ?>
                                    <?php if ($ex['my_taken_at']) { ?>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.5rem;">
                                            تاريخ الأداء: <?= format_arabic_date($ex['my_taken_at']) ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>

                        <div>
                            <?php if ($isCompleted) { ?>
                                <button class="btn btn-secondary" style="width:100%; cursor:not-allowed; opacity:0.8;" disabled>
                                    ✅ تم أداء الامتحان (محاولة واحدة فقط)
                                </button>
                            <?php } elseif ($isExpired) { ?>
                                <button class="btn btn-secondary" style="width:100%; cursor:not-allowed; opacity:0.7;" disabled>
                                    🔒 انتهى موعد الاختبار (مغلق)
                                </button>
                            <?php } else { ?>
                                <a href="?take_id=<?= $ex['id'] ?>" class="btn btn-primary" style="width:100%;">
                                    دخول الاختبار الآن ✏️
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</div>

<?php if ($currentExam && ! empty($questions)) { ?>
<script>
// --- Timer & Anti-Cheating Suite ---
(function() {
    let timeLeft = <?= (int) $remainingSeconds ?>;
    const timerElem = document.getElementById('countdownTimer');
    const examForm = document.getElementById('examActiveForm');
    const violationsInput = document.getElementById('cheatingViolationsInput');
    const detailsInput = document.getElementById('cheatingDetailsInput');
    const autoSubmitInput = document.getElementById('autoSubmittedCheatingInput');
    const badgeEl = document.getElementById('violationCountBadge');
    const modalEl = document.getElementById('antiCheatWarningModal');
    const modalText = document.getElementById('antiCheatModalText');
    const modalNum = document.getElementById('modalViolationNum');

    let violations = 0;
    const maxViolations = 3;
    let logRecords = [];
    let lastViolationTime = 0;

    function updateDisplay() {
        const m = Math.floor(timeLeft / 60);
        const s = timeLeft % 60;
        if (timerElem) {
            timerElem.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            if (timeLeft <= 120) {
                timerElem.style.color = '#ef4444';
                timerElem.style.background = 'rgba(239, 68, 68, 0.2)';
            }
        }
    }
    updateDisplay();

    const timerInterval = setInterval(function() {
        timeLeft--;
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (timerElem) {
                timerElem.textContent = '00:00';
            }
            alert('انتهت مدة الـ (<?= $durationMinutes ?> دقيقة) المحددة للامتحان تماماً! سيتم تسليم إجاباتك وخروجك من الامتحان تلقائياً.');
            if (examForm) {
                examForm.submit();
            }
        } else {
            updateDisplay();
        }
    }, 1000);

    function triggerCheatingViolation(reason) {
        const now = Date.now();
        // Debounce violation triggers (minimum 1.5 seconds between triggers)
        if (now - lastViolationTime < 1500) return;
        lastViolationTime = now;

        violations++;
        const timeStr = new Date().toLocaleTimeString('ar-EG');
        logRecords.push(`[${timeStr}] ${reason}`);

        if (violationsInput) violationsInput.value = violations;
        if (detailsInput) detailsInput.value = logRecords.join(' | ');

        if (badgeEl) {
            badgeEl.innerText = `${violations} مخالفات مرصودة`;
            badgeEl.className = violations >= 2 ? 'badge badge-danger' : 'badge badge-warning';
        }

        if (violations >= maxViolations) {
            if (autoSubmitInput) autoSubmitInput.value = "1";
            if (modalEl) modalEl.style.display = 'none';
            alert('⚠️ تنبيه نهائي: لقد تجاوزت الحد الأقصى للمخالفات (3 مخالفات) بالخروج من صفحة الامتحان! تم سحب الامتحان وتسليمه تلقائياً الآن.');
            if (examForm) examForm.submit();
        } else {
            if (modalNum) modalNum.innerText = violations;
            if (modalText) {
                modalText.innerHTML = `<strong>تم رصد:</strong> ${reason}.<br>تبقى لك (${maxViolations - violations}) محاولة قبل سحب الامتحان وتسليمه فورياً!`;
            }
            if (modalEl) modalEl.style.display = 'flex';
        }
    }

    window.dismissAntiCheatModal = function() {
        if (modalEl) modalEl.style.display = 'none';
    };

    // 1. Tab Switching & Window Blur Detection
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            triggerCheatingViolation('مغادرة نافذة الامتحان أو التبديل إلى تبويب آخر');
        }
    });

    window.addEventListener('blur', function() {
        triggerCheatingViolation('فقدان التركيز على صفحة الاختبار (فتح تطبيق أو نافذة أخرى)');
    });

    // 2. Prevent Context Menu (Right Click)
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        triggerCheatingViolation('محاولة فتح القائمة المنسدلة للزر الأيمن (Right-Click)');
        return false;
    });

    // 3. Prevent Copy, Cut, Paste
    document.addEventListener('copy', function(e) {
        e.preventDefault();
        triggerCheatingViolation('محاولة نسخ نص من الامتحان (Copy)');
        return false;
    });
    document.addEventListener('cut', function(e) {
        e.preventDefault();
        return false;
    });

    // 4. Block Developer Tools & Shortcut Keys
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            triggerCheatingViolation('محاولة فتح أدوات المطور (F12)');
            return false;
        }
        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
            e.preventDefault();
            triggerCheatingViolation('محاولة فحص عناصر الصفحة (Inspect)');
            return false;
        }
        // Ctrl+U (View Source)
        if (e.ctrlKey && (e.key === 'u' || e.key === 'U')) {
            e.preventDefault();
            triggerCheatingViolation('محاولة عرض مصدر الصفحة (View Source)');
            return false;
        }
        // Ctrl+C outside textareas
        if (e.ctrlKey && (e.key === 'c' || e.key === 'C') && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            triggerCheatingViolation('محاولة نسخ أسئلة الامتحان');
            return false;
        }
        // Ctrl+P (Print)
        if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            return false;
        }
    });

    // 5. Fullscreen Toggle
    window.toggleExamFullscreen = function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Fullscreen request error:', err);
            });
            const btn = document.getElementById('fullscreenToggleBtn');
            if (btn) btn.innerText = '⛶ الخروج من ملء الشاشة';
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
            const btn = document.getElementById('fullscreenToggleBtn');
            if (btn) btn.innerText = '⛶ وضع ملء الشاشة';
        }
    };
})();
</script>
<?php } ?>

<?php require_once __DIR__.'/../includes/footer.php'; ?>



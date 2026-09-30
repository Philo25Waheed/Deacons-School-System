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
$accessibleClassIds = get_user_accessible_class_ids();

// Handle Exam Creation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrfToken)) {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'create_exam') {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $duration = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT) ?: 15;
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $deadlineInput = sanitize($_POST['deadline'] ?? '');
            $deadline = null;

            if (! empty($deadlineInput)) {
                $deadlineTimestamp = strtotime($deadlineInput);
                if ($deadlineTimestamp !== false) {
                    $deadline = date('Y-m-d H:i:s', $deadlineTimestamp);
                }
            }

            if ($userRole === 'servant') {
                if (empty($classId) || ! can_servant_access_class($userId, $classId, $userRole)) {
                    $_SESSION['flash_error'] = 'عذراً، بصفتك خادماً يمكنك فقط إنشاء امتحانات للفصول المسندة لخدمتك حصراً.';
                    $redirectUrl = BASE_URL.'servant/exams.php';
                    header('Location: '.$redirectUrl);
                    exit;
                }
            }

            $rewardPoints = filter_input(INPUT_POST, 'reward_points', FILTER_VALIDATE_INT);
            if ($rewardPoints === null || $rewardPoints === false || $rewardPoints < 0) {
                $rewardPoints = 5;
            }
            $passPercentage = filter_input(INPUT_POST, 'pass_percentage', FILTER_VALIDATE_INT);
            if ($passPercentage === null || $passPercentage === false || $passPercentage <= 0 || $passPercentage > 100) {
                $passPercentage = 50;
            }

            // Derive stage_id and grade_id from class if provided
            $stageId = null;
            $gradeId = null;
            if ($classId) {
                $clsStmt = $db->prepare('SELECT c.grade_id, g.stage_id FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?');
                $clsStmt->execute([$classId]);
                $clsRow = $clsStmt->fetch();
                if ($clsRow) {
                    $gradeId = $clsRow['grade_id'];
                    $stageId = $clsRow['stage_id'];
                }
            }

            $stmt = $db->prepare('INSERT INTO exams (title, description, duration_minutes, reward_points, pass_percentage, deadline, stage_id, grade_id, class_id, servant_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $duration, $rewardPoints, $passPercentage, $deadline, $stageId, $gradeId, $classId ?: null, $userId, $userId]);

            $_SESSION['flash_success'] = "تم إنشاء الامتحان بنجاح وتحديد مكافأة ({$rewardPoints} طايو) عند النجاح بنسبة ({$passPercentage}%)! يمكنك الآن إضافة الأسئلة.";
        } elseif ($action === 'delete_exam') {
            $examIdToDelete = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
            if ($examIdToDelete) {
                $chkExam = $db->prepare('SELECT class_id, servant_id, created_by FROM exams WHERE id = ?');
                $chkExam->execute([$examIdToDelete]);
                $examInfo = $chkExam->fetch();

                if (! $examInfo) {
                    $_SESSION['flash_error'] = 'الامتحان غير موجود.';
                } elseif ($userRole === 'servant' && ! can_servant_access_class($userId, $examInfo['class_id'], $userRole) && $examInfo['created_by'] != $userId && $examInfo['servant_id'] != $userId) {
                    $_SESSION['flash_error'] = 'ليس لديك صلاحية لحذف هذا الامتحان.';
                } else {
                    $db->prepare('DELETE FROM exams WHERE id = ?')->execute([$examIdToDelete]);
                    $_SESSION['flash_success'] = 'تم حذف الامتحان وكافة أسئلته ونتائجه بنجاح.';
                    if ($selectedExamId == $examIdToDelete) {
                        $selectedExamId = null;
                    }
                }
            }
        } elseif ($action === 'add_question') {
            $examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
            $qType = sanitize($_POST['question_type']);
            $questionText = sanitize($_POST['question_text']);
            $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT) ?: 2;

            // Verify exam permission
            $chkExam = $db->prepare('SELECT class_id, servant_id, created_by FROM exams WHERE id = ?');
            $chkExam->execute([$examId]);
            $examInfo = $chkExam->fetch();

            if ($userRole === 'servant' && $examInfo && ! can_servant_access_class($userId, $examInfo['class_id'], $userRole) && $examInfo['servant_id'] != $userId && $examInfo['created_by'] != $userId) {
                $_SESSION['flash_error'] = 'ليس لديك صلاحية لإضافة أسئلة لهذا الامتحان.';
            } else {
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
            }
        } elseif ($action === 'grade_essay') {
            $resultId = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);
            $essayScore = filter_input(INPUT_POST, 'essay_score', FILTER_VALIDATE_INT) ?: 0;
            $feedback = sanitize($_POST['servant_feedback']);

            // Fetch result record
            $resStmt = $db->prepare('SELECT * FROM exam_results WHERE id = ?');
            $resStmt->execute([$resultId]);
            $res = $resStmt->fetch();

            if ($res && can_servant_access_student($userId, (int) $res['student_id'], $userRole)) {
                $newScore = $res['score'] + $essayScore;

                // Fetch exam details for pass percentage and reward points
                $stmtEx = $db->prepare('SELECT * FROM exams WHERE id = ?');
                $stmtEx->execute([$res['exam_id']]);
                $examInfo = $stmtEx->fetch();

                $passPct = (int) ($examInfo['pass_percentage'] ?? 50);
                $rewardPoints = (int) ($examInfo['reward_points'] ?? 5);
                $totalMarks = (int) $res['total_marks'];
                $alreadyAwarded = (int) ($res['points_awarded'] ?? 0);
                $awardedPointsNow = $alreadyAwarded;

                // If student now passes and points weren't awarded yet
                if ($totalMarks > 0 && (($newScore / $totalMarks) * 100) >= $passPct && $alreadyAwarded == 0 && $rewardPoints > 0) {
                    $db->prepare("INSERT INTO points (student_id, servant_id, points, type, reason) VALUES (?, ?, ?, 'positive', ?)")
                        ->execute([
                            $res['student_id'],
                            $userId,
                            $rewardPoints,
                            "اجتياز امتحان ({$examInfo['title']}) بنجاح بعد اعتماد السؤال المقالي (+{$rewardPoints} طايو)",
                        ]);
                    $awardedPointsNow = $rewardPoints;
                }

                $db->prepare("UPDATE exam_results SET score = ?, status = 'completed', points_awarded = ?, servant_feedback = ? WHERE id = ?")
                    ->execute([$newScore, $awardedPointsNow, $feedback, $resultId]);

                $tayoMsg = ($awardedPointsNow > 0 && $alreadyAwarded == 0) ? " وتم منح الشماس ({$awardedPointsNow} طايو) لاجتياز الاختبار بنجاح 🎉" : '';
                $_SESSION['flash_success'] = "تم رصد وتصحيح السؤال المقالي واعتماد الدرجة النهائية ({$newScore} من {$res['total_marks']}) بنجاح!{$tayoMsg}";
            } else {
                $_SESSION['flash_error'] = 'لا تملك صلاحية تصحيح هذا الطالب.';
            }
        }
        $redirectUrl = ($userRole === 'servant') ? BASE_URL.'servant/exams.php' : BASE_URL.'admin/exams.php';
        header('Location: '.$redirectUrl.($selectedExamId ? "?exam_id={$selectedExamId}" : ''));
        exit;
    }
}

// Fetch classes (scoped if servant)
if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $classes = [];
    } else {
        $inClassPlaceholders = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $stmtClasses = $db->prepare("
            SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
            FROM classes c
            JOIN grades g ON c.grade_id = g.id
            JOIN stages s ON g.stage_id = s.id
            WHERE c.id IN ({$inClassPlaceholders})
            ORDER BY s.id, g.id, c.id
        ");
        $stmtClasses->execute($accessibleClassIds);
        $classes = $stmtClasses->fetchAll();
    }
} else {
    $classes = $db->query('
        SELECT c.id, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        JOIN stages s ON g.stage_id = s.id
        ORDER BY s.id, g.id, c.id
    ')->fetchAll();
}

// Fetch exams list (scoped if servant)
$examsQuery = '
    SELECT e.*, u.full_name as servant_name, c.name_ar as class_name, g.name_ar as grade_name,
           (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) as total_questions,
           (SELECT COUNT(*) FROM exam_results r WHERE r.exam_id = e.id) as total_submissions
    FROM exams e
    LEFT JOIN users u ON e.servant_id = u.id
    LEFT JOIN classes c ON e.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
';
$examsParams = [];

if ($accessibleClassIds !== null) {
    if (empty($accessibleClassIds)) {
        $examsQuery .= ' WHERE e.servant_id = ? OR e.created_by = ?';
        $examsParams = [$userId, $userId];
    } else {
        $inExamClasses = implode(',', array_fill(0, count($accessibleClassIds), '?'));
        $examsQuery .= " WHERE e.class_id IN ({$inExamClasses}) OR e.servant_id = ? OR e.created_by = ?";
        $examsParams = array_merge($accessibleClassIds, [$userId, $userId]);
    }
}

$examsQuery .= ' ORDER BY e.id DESC';
$stmtExams = $db->prepare($examsQuery);
$stmtExams->execute($examsParams);
$exams = $stmtExams->fetchAll();

// Supervisor Inspection View for an Exam
$examDetails = null;
$questionsList = [];
$studentResults = [];
if ($selectedExamId) {
    $stmtE = $db->prepare('
        SELECT e.*, c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name, u.full_name as servant_name
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        LEFT JOIN stages s ON g.stage_id = s.id
        LEFT JOIN users u ON e.servant_id = u.id
        WHERE e.id = ?
    ');
    $stmtE->execute([$selectedExamId]);
    $examDetails = $stmtE->fetch();

    if ($examDetails) {
        if ($userRole === 'servant' && ! can_servant_access_class($userId, $examDetails['class_id'], $userRole) && $examDetails['servant_id'] != $userId && $examDetails['created_by'] != $userId) {
            $_SESSION['flash_error'] = 'عذراً، غير مصرح لك بمتابعة امتحانات خارج فصول خدمتك.';
            header('Location: '.BASE_URL.'servant/exams.php');
            exit;
        }

        $stmtQ = $db->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC');
        $stmtQ->execute([$selectedExamId]);
        $questionsList = $stmtQ->fetchAll();

        // Fetch class students and their results (scoped to servant if applicable)
        $classFilter = '';
        $resultsParams = [$selectedExamId];

        if ($examDetails['class_id']) {
            $classFilter = 'AND u.class_id = ?';
            $resultsParams[] = (int) $examDetails['class_id'];
        } elseif ($accessibleClassIds !== null && ! empty($accessibleClassIds)) {
            $inStudentClasses = implode(',', array_fill(0, count($accessibleClassIds), '?'));
            $classFilter = "AND u.class_id IN ({$inStudentClasses})";
            foreach ($accessibleClassIds as $clsId) {
                $resultsParams[] = (int) $clsId;
            }
        }

        $stmtResults = $db->prepare("
            SELECT u.id as student_id, u.full_name, u.qr_code_token, r.id as result_id, r.score, r.total_marks, r.status, r.points_awarded, r.cheating_violations, r.cheating_details, r.taken_at, r.answers_json, r.servant_feedback
            FROM users u
            LEFT JOIN exam_results r ON u.id = r.student_id AND r.exam_id = ?
            WHERE u.role = 'student' AND u.status = 'active' {$classFilter}
            ORDER BY u.full_name ASC
        ");
        $stmtResults->execute($resultsParams);
        $studentResults = $stmtResults->fetchAll();
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
            <div class="badge badge-success alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:right;">
                <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['flash_error'])) { ?>
            <div class="badge badge-danger alert-dismissible" style="width:100%; padding:0.85rem; margin-bottom:1.5rem; text-align:right;">
                <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
            </div>
        <?php } ?>

        <!-- Exam Builder & Question Creator -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
            <!-- Create Exam Form -->
            <div class="glass-card">
                <h3 style="color:var(--royal-blue); margin-bottom:1rem;">
                    إنشاء امتحان للفصل
                    <?php if ($userRole === 'servant') { ?>
                        <span class="badge badge-info" style="font-size:0.75rem; vertical-align:middle;">فصول خدمتك فقط</span>
                    <?php } ?>
                </h3>

                <?php if ($userRole === 'servant' && empty($classes)) { ?>
                    <div class="badge badge-warning" style="display:block; padding:1rem; margin-bottom:1rem; line-height:1.6; text-align:right;">
                        ⚠️ تنبيه: لم يتم إسناد أي فصول لخدمتك بعد. يرجى مراجعة إدارة المدرسة لإسناد فصلك حتى تتمكن من وضع الامتحانات له.
                    </div>
                <?php } ?>

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
                            <select name="class_id" class="form-control" required <?= ($userRole === 'servant' && empty($classes)) ? 'disabled' : '' ?>>
                                <option value="">-- اختر الفصل المستهدف --</option>
                                <?php
                                $currentGroup = null;
foreach ($classes as $cls) {
    $groupLabel = $cls['stage_name'].' ◀ '.$cls['grade_name'];
    if ($currentGroup !== $groupLabel) {
        if ($currentGroup !== null) {
            echo '</optgroup>';
        }
        $currentGroup = $groupLabel;
        echo '<optgroup label="'.sanitize($groupLabel).'">';
    }
    ?>
                                    <option value="<?= $cls['id'] ?>"><?= sanitize($cls['class_name']) ?> (<?= sanitize($cls['grade_name']) ?>)</option>
                                <?php }
if ($currentGroup !== null) {
    echo '</optgroup>';
}
?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المدة بالدقائق *</label>
                            <input type="number" name="duration_minutes" class="form-control" value="20" min="5" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">🪙 نقاط الطايو عند النجاح *</label>
                            <input type="number" name="reward_points" class="form-control" value="5" min="0" required placeholder="5">
                            <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">
                                عدد نقاط الطايو التي يكسبها الطفل عند النجاح.
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">🎯 نسبة النجاح المطلوبة (%) *</label>
                            <input type="number" name="pass_percentage" class="form-control" value="50" min="1" max="100" required placeholder="50">
                            <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">
                                أقل نسبة مئوية (مثال: 50%) لاجتياز الامتحان ونيل الطايو.
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الموعد النهائي لغلق الامتحان (Deadline) ⏱️</label>
                        <input type="datetime-local" name="deadline" class="form-control">
                        <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                            💡 بعد هذا الموعد والتاريخ، يُغلق الامتحان تلقائياً ولا يستطيع أي مخدوم الدخول إليه أو تسليمه.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;" <?= ($userRole === 'servant' && empty($classes)) ? 'disabled' : '' ?>>حفظ وإنشاء الامتحان</button>
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
            <h3 style="color:var(--royal-blue); margin-bottom:1rem;">قائمة الامتحانات ومتابعة نتائج الفصول</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>اسم الامتحان</th>
                            <th>الخادم واضع الامتحان</th>
                            <th>الفصل المستهدف</th>
                            <th>الموعد النهائي (Deadline)</th>
                            <th>🪙 مكافأة النجاح</th>
                            <th>عدد الأسئلة</th>
                            <th>الذين اختبروا</th>
                            <th>إجراءات ومتابعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams)) { ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:var(--text-muted); padding:1.5rem;">لا توجد امتحانات مسجلة حتى الآن.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($exams as $ex) {
                                $hasDeadline = ! empty($ex['deadline']);
                                $isExpired = $hasDeadline && (strtotime($ex['deadline']) < time());
                                $rewPts = (int) ($ex['reward_points'] ?? 5);
                                $pPct = (int) ($ex['pass_percentage'] ?? 50);
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($ex['title']) ?></strong></td>
                                    <td><?= sanitize($ex['servant_name'] ?? 'الإدارة') ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?= sanitize($ex['grade_name'] ?? '') ?> - <?= sanitize($ex['class_name'] ?? 'عام لجميع الفصول') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($hasDeadline) { ?>
                                            <span class="badge <?= $isExpired ? 'badge-danger' : 'badge-warning' ?>" style="display:inline-flex; align-items:center; gap:0.3rem;">
                                                <?= $isExpired ? '🔒 مغلق (منتهي)' : '⏱️ متاح حتى' ?><br>
                                                <?= date('d/m/Y h:i A', strtotime($ex['deadline'])) ?>
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge badge-secondary">مفتوح دائماً</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-gold" style="font-weight:700;">
                                            🪙 <?= $rewPts ?> طايو (نسبة <?= $pPct ?>%)
                                        </span>
                                    </td>
                                    <td><span class="badge badge-info"><?= $ex['total_questions'] ?> أسئلة</span></td>
                                    <td><span class="badge badge-gold"><?= $ex['total_submissions'] ?> شماس</span></td>
                                    <td>
                                        <div style="display:flex; gap:0.4rem; align-items:center;">
                                            <a href="?exam_id=<?= $ex['id'] ?>" class="btn btn-primary btn-sm">👁️ درجات الفصل</a>
                                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا الامتحان نهائياً؟');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_exam">
                                                <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.6rem;">🗑️</button>
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

        <!-- Detailed Inspection & Manual Essay Grading Section -->
        <?php if ($examDetails) {
            $hasD = ! empty($examDetails['deadline']);
            $isExp = $hasD && (strtotime($examDetails['deadline']) < time());
            ?>
            <div class="glass-card" style="border:2px solid var(--royal-blue);">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="color:var(--royal-blue); font-weight:800;"><?= sanitize($examDetails['title']) ?></h2>
                        <p style="color:var(--text-muted); margin-top:0.25rem;">
                            الخادم المسؤول: <strong><?= sanitize($examDetails['servant_name'] ?? 'الإدارة') ?></strong> |
                            الفصل: <strong><?= sanitize($examDetails['grade_name'] ?? '') ?> (<?= sanitize($examDetails['class_name'] ?? 'عام') ?>)</strong> |
                            المدة: <strong><?= $examDetails['duration_minutes'] ?> دقيقة</strong>
                            <?php if ($hasD) { ?>
                                | الموعد النهائي: <strong><?= date('d/m/Y h:i A', strtotime($examDetails['deadline'])) ?></strong>
                                <span class="badge <?= $isExp ? 'badge-danger' : 'badge-success' ?>" style="margin-right:0.35rem;">
                                    <?= $isExp ? '🔒 مغلق بعد انتهاء الموعد' : '⏱️ جاري وساري حتى الموعد' ?>
                                </span>
                            <?php } ?>
                        </p>
                    </div>
                    <a href="<?= ($userRole === 'servant') ? BASE_URL.'servant/exams.php' : BASE_URL.'admin/exams.php' ?>" class="btn btn-secondary btn-sm">إغلاق المتابعة ✖</a>
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
                <h4 style="color:var(--gold); margin-bottom:0.75rem;">2. كشف درجات وتصحيح شمامسة الفصل:</h4>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>اسم الشماس</th>
                                <th>الكود</th>
                                <th>تاريخ الاختبار</th>
                                <th>حالة الامتحان</th>
                                <th>الدرجة المحصلة</th>
                                <th>النسبة المئوية</th>
                                <th>🪙 الطايو والمراقبة</th>
                                <th>تصحيح مقالي / ملاحظات الخادم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentResults as $sr) {
                                $pct = ($sr['total_marks'] > 0) ? round(($sr['score'] / $sr['total_marks']) * 100) : 0;
                                $awardedTayo = (int) ($sr['points_awarded'] ?? 0);
                                $cheatCount = (int) ($sr['cheating_violations'] ?? 0);
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($sr['full_name']) ?></strong></td>
                                    <td><code><?= sanitize($sr['qr_code_token']) ?></code></td>
                                    <td><?= $sr['taken_at'] ? format_arabic_date($sr['taken_at']) : 'لم يختبر' ?></td>
                                    <td>
                                        <?php if (! $sr['taken_at']) { ?>
                                            <?php if ($isExp) { ?>
                                                <span class="badge badge-danger">فات موعد الامتحان 🔒</span>
                                            <?php } else { ?>
                                                <span class="badge badge-secondary">لم يدخل بعد ⏳</span>
                                            <?php } ?>
                                        <?php } elseif ($sr['status'] === 'needs_grading') { ?>
                                            <span class="badge badge-warning">يتطلب تصحيح مقالي 📝</span>
                                        <?php } else { ?>
                                            <span class="badge badge-success">تم الاعتماد بنجاح ✅</span>
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
                                        <?php if ($sr['taken_at']) { ?>
                                            <span class="badge <?= $pct >= 85 ? 'badge-success' : ($pct >= 65 ? 'badge-info' : 'badge-warning') ?>">
                                                <?= $pct ?>% (<?= $pct >= 85 ? 'ممتاز 🌟' : ($pct >= 75 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : 'يحتاج مراجعة')) ?>)
                                            </span>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($sr['taken_at']) { ?>
                                            <div style="display:flex; flex-direction:column; gap:0.25rem;">
                                                <?php if ($awardedTayo > 0) { ?>
                                                    <span class="badge badge-gold" style="font-size:0.8rem;">🪙 +<?= $awardedTayo ?> طايو</span>
                                                <?php } ?>
                                                <?php if ($cheatCount > 0) { ?>
                                                    <span class="badge badge-danger" style="font-size:0.75rem;" title="<?= sanitize($sr['cheating_details'] ?? '') ?>">
                                                        ⚠️ <?= $cheatCount ?> مخالفة غش
                                                    </span>
                                                <?php } ?>
                                                <?php if ($awardedTayo == 0 && $cheatCount == 0) { ?>
                                                    <span style="color:var(--text-muted); font-size:0.8rem;">-</span>
                                                <?php } ?>
                                            </div>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                            <?php if ($sr['taken_at']) { ?>
                                                <a href="<?= ($userRole === 'servant') ? BASE_URL.'servant/student_answers.php?result_id='.$sr['result_id'] : BASE_URL.'admin/student_answers.php?result_id='.$sr['result_id'] ?>" 
                                                   class="btn btn-primary btn-sm" 
                                                   style="padding:0.35rem 0.65rem; font-size:0.8rem;" 
                                                   title="مشاهدة إجابات الشماس وما جاوبه صح وخطأ">
                                                    👁️ عرض الإجابات
                                                </a>
                                            <?php } ?>

                                            <?php if ($sr['taken_at'] && $sr['status'] === 'needs_grading') { ?>
                                                <!-- Manual Essay Grading Form -->
                                                <form action="" method="POST" style="display:flex; gap:0.4rem; align-items:center; margin:0;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="grade_essay">
                                                    <input type="hidden" name="result_id" value="<?= $sr['result_id'] ?>">
                                                    <input type="number" name="essay_score" class="form-control" style="width:85px; padding:0.35rem 0.5rem; font-size:0.85rem;" placeholder="درجة المقالي" required>
                                                    <input type="text" name="servant_feedback" class="form-control" style="width:140px; padding:0.35rem 0.5rem; font-size:0.85rem;" placeholder="ملاحظات...">
                                                    <button type="submit" class="btn btn-gold btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">اعتماد</button>
                                                </form>
                                            <?php } else { ?>
                                                <?= sanitize($sr['servant_feedback'] ?? '') ?>
                                            <?php } ?>
                                        </div>
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

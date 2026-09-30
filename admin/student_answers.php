<?php
$pageTitle = 'تفاصيل إجابات المخدوم في الامتحان';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/csrf.php';

require_role('admin', 'servant');

$db = getDB();
$currentUserId = $_SESSION['user']['id'];
$currentUserRole = $_SESSION['user']['role'];

$resultId = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);
$examId = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

// Resolve result record
$result = null;
if ($resultId) {
    $stmtRes = $db->prepare('
        SELECT r.*, e.title as exam_title, e.description as exam_description, e.duration_minutes,
               u.full_name as student_name, u.qr_code_token as student_code, u.profile_pic,
               c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM exam_results r
        JOIN exams e ON r.exam_id = e.id
        JOIN users u ON r.student_id = u.id
        LEFT JOIN classes c ON u.class_id = c.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN stages s ON u.stage_id = s.id
        WHERE r.id = ?
    ');
    $stmtRes->execute([$resultId]);
    $result = $stmtRes->fetch();
} elseif ($examId && $studentId) {
    $stmtRes = $db->prepare('
        SELECT r.*, e.title as exam_title, e.description as exam_description, e.duration_minutes,
               u.full_name as student_name, u.qr_code_token as student_code, u.profile_pic,
               c.name_ar as class_name, g.name_ar as grade_name, s.name_ar as stage_name
        FROM exam_results r
        JOIN exams e ON r.exam_id = e.id
        JOIN users u ON r.student_id = u.id
        LEFT JOIN classes c ON u.class_id = c.id
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN stages s ON u.stage_id = s.id
        WHERE r.exam_id = ? AND r.student_id = ?
        ORDER BY r.id DESC LIMIT 1
    ');
    $stmtRes->execute([$examId, $studentId]);
    $result = $stmtRes->fetch();
}

if (! $result) {
    $_SESSION['flash_error'] = 'عذراً، لم يتم العثور على نتيجة الامتحان المطلوبة أو لم يقم المخدوم بأداء الامتحان بعد.';
    header('Location: '.($currentUserRole === 'servant' ? BASE_URL.'servant/exams.php' : BASE_URL.'admin/exams.php'));
    exit;
}

// Check permission for servant
if ($currentUserRole === 'servant' && ! can_servant_access_student($currentUserId, (int) $result['student_id'], $currentUserRole)) {
    $_SESSION['flash_error'] = 'غير مصرح لك باستعراض إجابات مخدوم خارج نطاق فصول خدمتك.';
    header('Location: '.BASE_URL.'servant/exams.php');
    exit;
}

// Fetch exam questions
$stmtQ = $db->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC');
$stmtQ->execute([$result['exam_id']]);
$questions = $stmtQ->fetchAll();

// Parse answers JSON safely
$rawAnswers = json_decode($result['answers_json'] ?? '[]', true) ?: [];

// Normalize answers array (supports both nested format ['objective', 'matching', 'essay'] and flat format)
$objectiveAnswers = $rawAnswers['objective'] ?? [];
$matchingAnswers = $rawAnswers['matching'] ?? [];
$essayAnswers = $rawAnswers['essay'] ?? [];

// If flat dictionary structure:
if (empty($objectiveAnswers) && empty($matchingAnswers) && empty($essayAnswers)) {
    foreach ($rawAnswers as $k => $val) {
        if (is_array($val)) {
            $matchingAnswers[$k] = $val;
        } elseif (is_string($val) && mb_strlen($val) > 10) {
            $essayAnswers[$k] = $val;
        } else {
            $objectiveAnswers[$k] = $val;
        }
    }
}

$pct = ($result['total_marks'] > 0) ? round(($result['score'] / $result['total_marks']) * 100) : 0;

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navigation / Breadcrumbs -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <a href="<?= ($currentUserRole === 'servant') ? BASE_URL.'servant/exams.php?exam_id='.$result['exam_id'] : BASE_URL.'admin/exams.php?exam_id='.$result['exam_id'] ?>" 
                   style="color:var(--text-muted); text-decoration:none; font-size:0.9rem; font-weight:600; display:inline-flex; align-items:center; gap:0.4rem; margin-bottom:0.4rem;">
                    ← العودة للامتحان والمتابعة
                </a>
                <h1 style="color:var(--royal-blue); font-weight:800; font-size:1.6rem; margin:0;">
                    تفاصيل إجابات المخدوم في الامتحان 📝
                </h1>
            </div>

            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>admin/student_report.php?id=<?= $result['student_id'] ?>" class="btn btn-secondary btn-sm">
                    📊 تقرير المخدوم الشامل
                </a>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ طباعة نموذج الإجابة</button>
            </div>
        </div>

        <!-- Student & Exam Information Header Card -->
        <div class="glass-card" style="margin-bottom:1.5rem; border-right:4px solid var(--royal-blue); padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <img src="<?= BASE_URL ?>uploads/profile/<?= sanitize($result['profile_pic'] ?? 'default-avatar.png') ?>" 
                         style="width:58px; height:58px; border-radius:50%; object-fit:cover; border:2px solid var(--gold);" 
                         alt="الصورة" 
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-avatar.png'">
                    <div>
                        <div style="font-size:1.2rem; font-weight:800; color:var(--text-primary);">
                            <?= sanitize($result['student_name']) ?>
                            <span class="code-badge" style="margin-right:0.5rem; font-size:0.8rem;"><?= sanitize($result['student_code']) ?></span>
                        </div>
                        <div style="font-size:0.88rem; color:var(--text-muted); margin-top:0.25rem;">
                            <span><?= sanitize($result['stage_name'] ?? '') ?> - <?= sanitize($result['grade_name'] ?? '') ?> (<?= sanitize($result['class_name'] ?? 'عام') ?>)</span>
                            <span style="margin:0 0.5rem;">•</span>
                            <span>الامتحان: <strong><?= sanitize($result['exam_title']) ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- Result Badge & Summary -->
                <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div style="text-align:center; background:var(--bg-primary); padding:0.6rem 1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <div style="font-size:0.78rem; color:var(--text-muted);">الدرجة الكلية</div>
                        <div style="font-size:1.35rem; font-weight:900; color:var(--royal-blue); margin-top:0.15rem;">
                            <?= $result['score'] ?> / <?= $result['total_marks'] ?>
                        </div>
                    </div>

                    <div style="text-align:center; background:var(--bg-primary); padding:0.6rem 1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <div style="font-size:0.78rem; color:var(--text-muted);">النسبة المئوية</div>
                        <div style="margin-top:0.15rem;">
                            <span class="badge <?= $pct >= 85 ? 'badge-success' : ($pct >= 65 ? 'badge-info' : 'badge-warning') ?>" style="font-size:0.95rem;">
                                <?= $pct ?>% (<?= $pct >= 85 ? 'ممتاز 🌟' : ($pct >= 75 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : 'يحتاج مراجعة')) ?>)
                            </span>
                        </div>
                    </div>

                    <div style="text-align:center; background:var(--bg-primary); padding:0.6rem 1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <div style="font-size:0.78rem; color:var(--text-muted);">تاريخ الأداء</div>
                        <div style="font-size:0.88rem; font-weight:700; color:var(--text-primary); margin-top:0.35rem;">
                            <?= $result['taken_at'] ? format_arabic_date($result['taken_at']) : '-' ?>
                        </div>
                    </div>

                    <div style="text-align:center; background:var(--bg-primary); padding:0.6rem 1.25rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                        <div style="font-size:0.78rem; color:var(--text-muted);">نقاط الطايو</div>
                        <div style="font-size:1.2rem; font-weight:900; color:var(--gold); margin-top:0.15rem;">
                            🪙 <?= (int) ($result['points_awarded'] ?? 0) ?> طايو
                        </div>
                    </div>
                </div>
            </div>

            <?php if (! empty($result['cheating_violations'])) { ?>
                <div class="alert-box alert-box-danger" style="margin-top:1rem; flex-direction:column; align-items:flex-start; gap:0.35rem;">
                    <div style="font-weight:800; font-size:0.95rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>⚠️🚨</span>
                        <span>رصد مخالفات أمنية لمنع الغش أثناء أداء الامتحان (<?= (int) $result['cheating_violations'] ?> مخالفة):</span>
                    </div>
                    <div style="font-size:0.85rem; line-height:1.5; opacity:0.95;">
                        <?= sanitize($result['cheating_details'] ?? 'تم رصد محاولات لمغادرة صفحة الاختبار أو تبديل النوافذ.') ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (! empty($result['servant_feedback'])) { ?>
                <div style="margin-top:1rem; padding:0.75rem 1rem; background:rgba(212,175,55,0.1); border-right:3px solid var(--gold); border-radius:4px; font-size:0.9rem;">
                    <strong style="color:var(--royal-blue);">ملاحظات الخادم السابقة:</strong> <?= sanitize($result['servant_feedback']) ?>
                </div>
            <?php } ?>
        </div>

        <!-- Questions & Detailed Answers Sheet -->
        <h3 style="color:var(--royal-blue); font-weight:800; font-size:1.25rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
            <span>📋</span> تفاصيل إجابات الأسئلة ونموذج التصحيح (<?= count($questions) ?> سؤال)
        </h3>

        <div style="display:flex; flex-direction:column; gap:1.25rem; margin-bottom:2.5rem;">
            <?php foreach ($questions as $idx => $q) {
                $qId = $q['id'];
                $qType = $q['question_type'];
                $points = (int) $q['points'];

                // Determine correctness for objective types
                $isCorrect = false;
                $userAns = null;

                if ($qType === 'mcq' || $qType === 'true_false') {
                    $userAns = $objectiveAnswers[$qId] ?? null;
                    $isCorrect = ($userAns !== null && $userAns === $q['correct_option']);
                }
                ?>
                <div class="glass-card" style="padding:1.25rem; border-radius:var(--radius-sm); border-right:4px solid <?= ($qType === 'essay') ? '#f59e0b' : ($isCorrect ? '#16a34a' : '#dc2626') ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                        <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary);">
                            <span style="color:var(--royal-blue);">س<?= $idx + 1 ?>:</span> <?= sanitize($q['question_text']) ?>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span class="badge badge-secondary" style="font-size:0.8rem;">
                                <?php
                                $typeNames = ['mcq' => 'اختيار من متعدد', 'true_false' => 'صح أم خطأ', 'matching' => 'توصيل', 'essay' => 'سؤال مقالي'];
                echo $typeNames[$qType] ?? $qType;
                ?>
                            </span>
                            <span class="badge badge-gold" style="font-size:0.8rem;"><?= $points ?> درجات</span>
                            <?php if ($qType === 'mcq' || $qType === 'true_false') { ?>
                                <?php if ($isCorrect) { ?>
                                    <span class="badge badge-success" style="font-size:0.85rem;">إجابة صحيحة ✔️ (+<?= $points ?>)</span>
                                <?php } else { ?>
                                    <span class="badge badge-danger" style="font-size:0.85rem;">إجابة خاطئة ❌ (0)</span>
                                <?php } ?>
                            <?php } elseif ($qType === 'essay') { ?>
                                <span class="badge badge-warning" style="font-size:0.85rem;">سؤال مقالي (تصحيح يدوي) 📝</span>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Question Specific Body -->
                    <?php if ($qType === 'mcq') {
                        $options = [
                            'a' => ['label' => '(أ)', 'text' => $q['option_a']],
                            'b' => ['label' => '(ب)', 'text' => $q['option_b']],
                            'c' => ['label' => '(جـ)', 'text' => $q['option_c']],
                            'd' => ['label' => '(د)', 'text' => $q['option_d']],
                        ];
                        ?>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:0.6rem; margin-top:0.75rem;">
                            <?php foreach ($options as $optKey => $optVal) {
                                if (empty($optVal['text'])) {
                                    continue;
                                }
                                $isThisUserAns = ($userAns === $optKey);
                                $isThisCorrect = ($q['correct_option'] === $optKey);

                                $bg = 'var(--bg-primary)';
                                $border = 'var(--border-color)';
                                $icon = '';

                                if ($isThisCorrect) {
                                    $bg = 'rgba(34, 197, 94, 0.12)';
                                    $border = '#16a34a';
                                    $icon = ' <strong style="color:#16a34a;">(الإجابة الصحيحة النموذجية ✅)</strong>';
                                }
                                if ($isThisUserAns && ! $isThisCorrect) {
                                    $bg = 'rgba(239, 68, 68, 0.12)';
                                    $border = '#dc2626';
                                    $icon = ' <strong style="color:#dc2626;">(إجابة المخدوم الخاطئة ❌)</strong>';
                                } elseif ($isThisUserAns && $isThisCorrect) {
                                    $icon = ' <strong style="color:#16a34a;">(إجابة المخدوم الصحيحة 🌟)</strong>';
                                }
                                ?>
                                <div style="padding:0.75rem 1rem; border-radius:var(--radius-sm); background:<?= $bg ?>; border:1px solid <?= $border ?>; font-size:0.92rem;">
                                    <strong><?= $optVal['label'] ?></strong> <?= sanitize($optVal['text']) ?>
                                    <?= $icon ?>
                                </div>
                            <?php } ?>
                        </div>

                    <?php } elseif ($qType === 'true_false') {
                        $tfOptions = [
                            'a' => 'صح (True) ✔️',
                            'b' => 'خطأ (False) ❌',
                        ];
                        ?>
                        <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:0.75rem;">
                            <?php foreach ($tfOptions as $tfKey => $tfLabel) {
                                $isThisUserAns = ($userAns === $tfKey);
                                $isThisCorrect = ($q['correct_option'] === $tfKey);

                                $bg = 'var(--bg-primary)';
                                $border = 'var(--border-color)';
                                $icon = '';

                                if ($isThisCorrect) {
                                    $bg = 'rgba(34, 197, 94, 0.12)';
                                    $border = '#16a34a';
                                    $icon = ' <strong style="color:#16a34a;">(الصحيحة ✅)</strong>';
                                }
                                if ($isThisUserAns && ! $isThisCorrect) {
                                    $bg = 'rgba(239, 68, 68, 0.12)';
                                    $border = '#dc2626';
                                    $icon = ' <strong style="color:#dc2626;">(إجابة المخدوم ❌)</strong>';
                                } elseif ($isThisUserAns && $isThisCorrect) {
                                    $icon = ' <strong style="color:#16a34a;">(إجابة المخدوم 🌟)</strong>';
                                }
                                ?>
                                <div style="padding:0.75rem 1.25rem; border-radius:var(--radius-sm); background:<?= $bg ?>; border:1px solid <?= $border ?>; font-size:0.95rem;">
                                    <?= $tfLabel ?> <?= $icon ?>
                                </div>
                            <?php } ?>
                        </div>

                    <?php } elseif ($qType === 'matching') {
                        $pairs = json_decode($q['matching_pairs'] ?? '[]', true) ?: [];
                        $userMatch = $matchingAnswers[$qId] ?? [];
                        ?>
                        <div class="table-responsive" style="margin-top:0.75rem;">
                            <table class="custom-table" style="font-size:0.88rem;">
                                <thead>
                                    <tr>
                                        <th style="width:35%;">العنصر (العمود الأول)</th>
                                        <th style="width:35%;">إجابة المخدوم المختارة</th>
                                        <th style="width:30%;">الإجابة النموذجية المطابقة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pairs as $pIdx => $pair) {
                                        $chosenRight = $userMatch[$pIdx] ?? ($userMatch['left_'.$pIdx] ?? null);
                                        $isPairCorrect = ($chosenRight !== null && $chosenRight === $pair['right']);
                                        ?>
                                        <tr>
                                            <td><strong><?= sanitize($pair['left']) ?></strong></td>
                                            <td>
                                                <?php if ($chosenRight !== null) { ?>
                                                    <span style="color:<?= $isPairCorrect ? '#16a34a' : '#dc2626' ?>; font-weight:700;">
                                                        <?= sanitize($chosenRight) ?>
                                                        <?= $isPairCorrect ? ' ✔️' : ' ❌' ?>
                                                    </span>
                                                <?php } else { ?>
                                                    <span style="color:var(--text-muted);">لم يتم الاختيار</span>
                                                <?php } ?>
                                            </td>
                                            <td style="color:var(--royal-blue); font-weight:700;">
                                                <?= sanitize($pair['right']) ?> ✅
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    <?php } elseif ($qType === 'essay') {
                        $studentEssay = $essayAnswers[$qId] ?? ($rawAnswers[$qId] ?? 'لم تتم كتابة إجابة');
                        ?>
                        <div style="margin-top:0.75rem;">
                            <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.35rem;">إجابة المخدوم المكتوبة:</div>
                            <div style="background:var(--bg-primary); border:1px solid var(--border-color); padding:1rem; border-radius:var(--radius-sm); font-size:0.95rem; line-height:1.7; white-space:pre-wrap; color:var(--text-primary);">
                                <?= sanitize(is_string($studentEssay) ? $studentEssay : json_encode($studentEssay, JSON_UNESCAPED_UNICODE)) ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </main>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>

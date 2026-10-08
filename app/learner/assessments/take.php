<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];
$attemptId = filter_var($_GET['attempt_id'] ?? '', FILTER_VALIDATE_INT);

if (!$attemptId || $attemptId < 1) {
    flash('error', 'Invalid assessment attempt.');
    redirect('app/learner/assessments/index.php');
}

$stmt = $pdo->prepare(
    "SELECT aa.id, aa.lesson_id, aa.status, aa.started_at, l.title AS lesson_title
     FROM assessment_attempts aa
     INNER JOIN lessons l ON l.id = aa.lesson_id
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE aa.id = :attempt_id
       AND aa.learner_id = :learner_id
       AND l.is_published = 1
       AND s.is_active = 1
     LIMIT 1"
);
$stmt->execute([
    'attempt_id' => (int) $attemptId,
    'learner_id' => $learnerId,
]);
$attempt = $stmt->fetch();

if (!$attempt) {
    http_response_code(404);
    exit('Assessment attempt not found.');
}

if ($attempt['status'] === 'submitted') {
    redirect('app/learner/results/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, question_text, question_order, marks
     FROM questions
     WHERE lesson_id = :lesson_id AND is_active = 1
     ORDER BY question_order ASC, id ASC"
);
$stmt->execute(['lesson_id' => (int) $attempt['lesson_id']]);
$questions = $stmt->fetchAll();

if (!$questions) {
    flash('error', 'This assessment currently has no active questions.');
    redirect('app/learner/assessments/index.php');
}

$optionsStmt = $pdo->prepare(
    "SELECT id, option_text, option_order
     FROM question_options
     WHERE question_id = :question_id
     ORDER BY option_order ASC, id ASC"
);

foreach ($questions as &$question) {
    $optionsStmt->execute(['question_id' => (int) $question['id']]);
    $question['options'] = $optionsStmt->fetchAll();
}
unset($question);

$pageTitle = 'Take Assessment';
$showNavbar = true;
$totalPossibleMarks = array_sum(array_map(
    static fn (array $question): int => (int) $question['marks'],
    $questions
));

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <section class="learner-page-heading">
            <span class="section-label">ASSESSMENT IN PROGRESS</span>
            <h1><?= e($attempt['lesson_title']) ?></h1>
            <p>Choose one answer for each question, then submit your responses.</p>
        </section>

        <div class="learner-assessment-summary">
            <span><?= count($questions) ?> question(s)</span>
            <span><?= $totalPossibleMarks ?> total mark(s)</span>
        </div>

        <form method="post" action="<?= e(url('app/learner/assessments/submit.php')) ?>" class="learner-assessment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="attempt_id" value="<?= (int) $attemptId ?>">

            <?php foreach ($questions as $index => $question): ?>
                <fieldset class="learner-question-card">
                    <legend>
                        <span class="learner-question-number">Question <?= $index + 1 ?></span>
                        <span class="learner-question-marks"><?= (int) $question['marks'] ?> mark(s)</span>
                    </legend>

                    <p class="learner-question-text"><?= nl2br(e($question['question_text'])) ?></p>

                    <?php if (!$question['options']): ?>
                        <p class="learner-muted">Answer options have not been added for this question. Please contact your instructor.</p>
                    <?php else: ?>
                        <div class="learner-answer-options">
                            <?php foreach ($question['options'] as $option): ?>
                                <label class="learner-answer-option">
                                    <input
                                        type="radio"
                                        name="answers[<?= (int) $question['id'] ?>]"
                                        value="<?= (int) $option['id'] ?>"
                                    >
                                    <span><?= e($option['option_text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <div class="learner-assessment-actions">
                <p>Review your answers before submitting. A submitted attempt cannot be changed.</p>
                <button class="btn btn-primary" type="submit" onclick="return confirm('Submit this assessment now? You will not be able to change your answers afterwards.');">Submit Assessment</button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

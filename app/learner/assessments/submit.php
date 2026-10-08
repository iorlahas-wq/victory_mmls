<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

if (!is_post()) {
    redirect('app/learner/assessments/index.php');
}

verify_csrf();

$user = current_user();
$learnerId = (int) $user['id'];
$attemptId = filter_var($_POST['attempt_id'] ?? '', FILTER_VALIDATE_INT);
$submittedAnswers = $_POST['answers'] ?? [];

if (!$attemptId || $attemptId < 1 || !is_array($submittedAnswers)) {
    flash('error', 'Invalid assessment submission.');
    redirect('app/learner/assessments/index.php');
}

$stmt = $pdo->prepare(
    "SELECT aa.id, aa.lesson_id, aa.status
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

if ($attempt['status'] !== 'in_progress') {
    flash('error', 'This assessment has already been submitted.');
    redirect('app/learner/results/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, question_text, marks
     FROM questions
     WHERE lesson_id = :lesson_id AND is_active = 1
     ORDER BY question_order ASC, id ASC"
);
$stmt->execute(['lesson_id' => (int) $attempt['lesson_id']]);
$questions = $stmt->fetchAll();

if (!$questions) {
    flash('error', 'There are no active questions to submit.');
    redirect('app/learner/assessments/index.php');
}

/* Validate selected options before writing any answers. */
$validated = [];
$optionStmt = $pdo->prepare(
    "SELECT id, question_id, is_correct
     FROM question_options
     WHERE id = :option_id AND question_id = :question_id
     LIMIT 1"
);

foreach ($questions as $question) {
    $questionId = (int) $question['id'];
    $rawOptionId = $submittedAnswers[$questionId] ?? null;

    if ($rawOptionId === null || $rawOptionId === '') {
        $validated[$questionId] = null;
        continue;
    }

    $optionId = filter_var($rawOptionId, FILTER_VALIDATE_INT);

    if (!$optionId || $optionId < 1) {
        flash('error', 'One of the selected answers is invalid. Please review your responses.');
        redirect('app/learner/assessments/take.php?attempt_id=' . (int) $attemptId);
    }

    $optionStmt->execute([
        'option_id' => (int) $optionId,
        'question_id' => $questionId,
    ]);
    $option = $optionStmt->fetch();

    if (!$option) {
        flash('error', 'One of the selected answers does not belong to its question.');
        redirect('app/learner/assessments/take.php?attempt_id=' . (int) $attemptId);
    }

    $validated[$questionId] = [
        'option_id' => (int) $option['id'],
        'is_correct' => (int) $option['is_correct'] === 1,
    ];
}

$totalMarks = 0.0;
$score = 0.0;

try {
    $pdo->beginTransaction();

    $answerStmt = $pdo->prepare(
        "INSERT INTO assessment_answers
            (attempt_id, question_id, option_id, is_correct, marks_awarded)
         VALUES
            (:attempt_id, :question_id, :option_id, :is_correct, :marks_awarded)"
    );

    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $marks = (float) $question['marks'];
        $answer = $validated[$questionId] ?? null;
        $isCorrect = is_array($answer) && $answer['is_correct'];

        $totalMarks += $marks;
        $awarded = $isCorrect ? $marks : 0.0;
        $score += $awarded;

        $answerStmt->execute([
            'attempt_id' => (int) $attemptId,
            'question_id' => $questionId,
            'option_id' => is_array($answer) ? $answer['option_id'] : null,
            'is_correct' => $isCorrect ? 1 : 0,
            'marks_awarded' => $awarded,
        ]);
    }

    $percentage = $totalMarks > 0
        ? round(($score / $totalMarks) * 100, 2)
        : 0.0;

    $updateAttempt = $pdo->prepare(
        "UPDATE assessment_attempts
         SET score = :score,
             total_marks = :total_marks,
             submitted_at = CURRENT_TIMESTAMP,
             status = 'submitted'
         WHERE id = :attempt_id
           AND learner_id = :learner_id
           AND status = 'in_progress'"
    );
    $updateAttempt->execute([
        'score' => $score,
        'total_marks' => $totalMarks,
        'attempt_id' => (int) $attemptId,
        'learner_id' => $learnerId,
    ]);

    if ($updateAttempt->rowCount() !== 1) {
        throw new RuntimeException('Attempt was already submitted or is no longer available.');
    }

    $resultStmt = $pdo->prepare(
        "INSERT INTO results
            (attempt_id, learner_id, lesson_id, score, total_marks, percentage)
         VALUES
            (:attempt_id, :learner_id, :lesson_id, :score, :total_marks, :percentage)"
    );
    $resultStmt->execute([
        'attempt_id' => (int) $attemptId,
        'learner_id' => $learnerId,
        'lesson_id' => (int) $attempt['lesson_id'],
        'score' => $score,
        'total_marks' => $totalMarks,
        'percentage' => $percentage,
    ]);

    $pdo->commit();

    flash('success', 'Assessment submitted successfully. Your score is ' .
        number_format($score, 2) . ' out of ' . number_format($totalMarks, 2) . '.');

    redirect('app/learner/results/index.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('error', 'Your assessment could not be submitted. Please try again or contact the administrator.');
    redirect('app/learner/assessments/take.php?attempt_id=' . (int) $attemptId);
}

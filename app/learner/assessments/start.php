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
$lessonId = filter_var($_POST['lesson_id'] ?? '', FILTER_VALIDATE_INT);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Please select a valid assessment.');
    redirect('app/learner/assessments/index.php');
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.title
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :lesson_id
       AND l.is_published = 1
       AND s.is_active = 1
     LIMIT 1"
);
$stmt->execute(['lesson_id' => (int) $lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Assessment lesson not found or unavailable.');
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM questions
     WHERE lesson_id = :lesson_id AND is_active = 1"
);
$stmt->execute(['lesson_id' => (int) $lessonId]);

if ((int) $stmt->fetchColumn() < 1) {
    flash('error', 'There are no active questions for this lesson yet.');
    redirect('app/learner/assessments/index.php');
}

/* Resume an existing unfinished attempt instead of creating duplicates. */
$stmt = $pdo->prepare(
    "SELECT id
     FROM assessment_attempts
     WHERE learner_id = :learner_id
       AND lesson_id = :lesson_id
       AND status = 'in_progress'
     ORDER BY id DESC
     LIMIT 1"
);
$stmt->execute([
    'learner_id' => $learnerId,
    'lesson_id' => (int) $lessonId,
]);
$attemptId = (int) ($stmt->fetchColumn() ?: 0);

if ($attemptId === 0) {
    $stmt = $pdo->prepare(
        "INSERT INTO assessment_attempts
            (learner_id, lesson_id, status)
         VALUES
            (:learner_id, :lesson_id, 'in_progress')"
    );
    $stmt->execute([
        'learner_id' => $learnerId,
        'lesson_id' => (int) $lessonId,
    ]);
    $attemptId = (int) $pdo->lastInsertId();
}

redirect('app/learner/assessments/take.php?attempt_id=' . $attemptId);

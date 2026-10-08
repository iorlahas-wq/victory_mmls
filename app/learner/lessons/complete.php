<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

if (!is_post()) {
    redirect('app/learner/lessons/index.php');
}

verify_csrf();

$user = current_user();
$learnerId = (int) $user['id'];
$lessonId = filter_var($_POST['lesson_id'] ?? '', FILTER_VALIDATE_INT);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/learner/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT l.id
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :lesson_id
       AND l.is_published = 1
       AND s.is_active = 1
     LIMIT 1"
);
$stmt->execute(['lesson_id' => (int) $lessonId]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Lesson not found or unavailable.');
}

$stmt = $pdo->prepare(
    "INSERT INTO learner_progress
        (learner_id, lesson_id, status, started_at, completed_at)
     VALUES
        (:learner_id, :lesson_id, 'completed', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE
        status = 'completed',
        started_at = COALESCE(started_at, CURRENT_TIMESTAMP),
        completed_at = CURRENT_TIMESTAMP"
);
$stmt->execute([
    'learner_id' => $learnerId,
    'lesson_id' => (int) $lessonId,
]);

flash('success', 'Lesson marked as completed.');
redirect('app/learner/lessons/view.php?lesson_id=' . (int) $lessonId);

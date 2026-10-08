<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

if (!is_post()) {
    redirect('app/instructor/lessons/index.php');
}

verify_csrf();

$user = current_user();
$instructorId = (int) $user['id'];

$lessonId = filter_var(
    $_POST['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/instructor/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, is_published
     FROM lessons
     WHERE id = :id
       AND created_by = :created_by
     LIMIT 1"
);

$stmt->execute([
    'id' => $lessonId,
    'created_by' => $instructorId,
]);

$lesson = $stmt->fetch();

if (!$lesson) {
    flash('error', 'Lesson not found or you do not have permission to change it.');
    redirect('app/instructor/lessons/index.php');
}

$newStatus = ((int) $lesson['is_published'] === 1) ? 0 : 1;

$update = $pdo->prepare(
    "UPDATE lessons
     SET is_published = :is_published
     WHERE id = :id
       AND created_by = :created_by"
);

$update->execute([
    'is_published' => $newStatus,
    'id' => $lessonId,
    'created_by' => $instructorId,
]);

flash(
    'success',
    $newStatus === 1
        ? 'Lesson published successfully.'
        : 'Lesson unpublished successfully.'
);

redirect('app/instructor/lessons/index.php');

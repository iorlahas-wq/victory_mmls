<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'instructor');

if (!is_post()) {
    redirect('app/assessment/index.php');
}

verify_csrf();

$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$questionId = filter_var(
    $_POST['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$questionId || $questionId < 1) {
    flash('error', 'Invalid assessment question.');
    redirect('app/assessment/index.php');
}

$sql = "
    SELECT
        q.id,
        q.lesson_id,
        q.is_active
    FROM questions q
    INNER JOIN lessons l
        ON l.id = q.lesson_id
    WHERE q.id = :question_id
";

$params = [
    'question_id' => (int) $questionId,
];

if ($role === 'instructor') {
    $sql .= ' AND l.created_by = :user_id';
    $params['user_id'] = $userId;
}

$sql .= ' LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$question = $stmt->fetch();

if (!$question) {
    http_response_code(404);
    exit('Assessment question not found or access denied.');
}

$update = $pdo->prepare(
    "UPDATE questions
     SET is_active = :is_active
     WHERE id = :question_id"
);

$update->execute([
    'is_active' => (int) $question['is_active'] === 1 ? 0 : 1,
    'question_id' => (int) $questionId,
]);

flash(
    'success',
    (int) $question['is_active'] === 1
        ? 'Assessment question deactivated.'
        : 'Assessment question activated.'
);

redirect(
    'app/assessment/index.php?lesson_id=' .
    (int) $question['lesson_id']
);

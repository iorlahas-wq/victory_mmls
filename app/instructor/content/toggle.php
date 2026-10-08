<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();

$user = current_user();
$userId = (int) $user['id'];

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Invalid content item.');
    redirect('app/instructor/content/index.php');
}

$stmt = $pdo->prepare(
    "SELECT lc.id, lc.lesson_id, lc.is_active
     FROM lesson_contents lc
     INNER JOIN lessons l ON l.id = lc.lesson_id
     WHERE lc.id = :id AND l.created_by = :created_by
     LIMIT 1"
);
$stmt->execute([
    'id' => $id,
    'created_by' => $userId,
]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit('Content item not found.');
}

$newStatus = (int) $item['is_active'] === 1 ? 0 : 1;

$update = $pdo->prepare(
    "UPDATE lesson_contents
     SET is_active = :is_active,
         updated_at = CURRENT_TIMESTAMP
     WHERE id = :id"
);
$update->execute([
    'is_active' => $newStatus,
    'id' => $id,
]);

flash(
    'success',
    $newStatus === 1 ? 'Content activated.' : 'Content deactivated.'
);

redirect('app/instructor/content/index.php?lesson_id=' . (int) $item['lesson_id']);

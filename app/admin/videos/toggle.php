<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

if (!is_post()) {
    redirect('app/admin/videos/index.php');
}

verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    flash('error', 'Invalid video selected.');
    redirect('app/admin/videos/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, is_active
     FROM lesson_contents
     WHERE id = :id
       AND content_type = 'video'
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$video = $stmt->fetch();

if (!$video) {
    flash('error', 'Video not found.');
    redirect('app/admin/videos/index.php');
}

$newStatus = ((int) $video['is_active'] === 1) ? 0 : 1;

$update = $pdo->prepare(
    "UPDATE lesson_contents
     SET is_active = :is_active,
         updated_at = CURRENT_TIMESTAMP
     WHERE id = :id
       AND content_type = 'video'"
);

$update->execute([
    'is_active' => $newStatus,
    'id' => $id,
]);

flash(
    'success',
    $newStatus === 1
        ? 'Instructional video activated.'
        : 'Instructional video deactivated.'
);

redirect('app/admin/videos/index.php');

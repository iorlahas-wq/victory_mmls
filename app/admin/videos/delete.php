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
    "SELECT id, file_path
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

try {

    $pdo->beginTransaction();

    $delete = $pdo->prepare(
        "DELETE FROM lesson_contents
         WHERE id = :id
           AND content_type = 'video'"
    );

    $delete->execute(['id' => $id]);

    $pdo->commit();

    $relativePath = (string) ($video['file_path'] ?? '');

    if ($relativePath !== '') {
        $file = BASE_PATH . '/' . ltrim($relativePath, '/');

        if (is_file($file)) {
            @unlink($file);
        }
    }

    flash('success', 'Instructional video removed.');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash(
        'error',
        DEBUG
            ? $e->getMessage()
            : 'The instructional video could not be removed.'
    );
}

redirect('app/admin/videos/index.php');

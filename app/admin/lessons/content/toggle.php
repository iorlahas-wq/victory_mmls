<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../includes/auth.php';

require_role('admin');

if (!is_post()) {
    http_response_code(405);
    exit('Method Not Allowed');
}

verify_csrf();
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid content selected.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, lesson_id, is_active
     FROM lesson_contents
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'The selected content could not be found.');
    redirect('app/admin/lessons/index.php');
}

$newStatus = (int) $item['is_active'] === 1 ? 0 : 1;

$stmt = $pdo->prepare(
    "UPDATE lesson_contents SET is_active = :is_active WHERE id = :id"
);
$stmt->execute([
    'is_active' => $newStatus,
    'id' => $id,
]);

flash('success', $newStatus === 1 ? 'Content activated successfully.' : 'Content deactivated successfully.');
redirect('app/admin/lessons/content/index.php?lesson_id=' . (int) $item['lesson_id']);

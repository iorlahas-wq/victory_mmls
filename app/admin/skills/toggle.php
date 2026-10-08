<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

if (!is_post()) {
    redirect('app/admin/skills/index.php');
}

verify_csrf();

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    flash('error', 'Invalid skill selected.');
    redirect('app/admin/skills/index.php');
}

$stmt = $pdo->prepare(
    "
    SELECT id, name, is_active
    FROM skills
    WHERE id = :id
    LIMIT 1
    "
);

$stmt->execute([':id' => $id]);

$skill = $stmt->fetch();

if (!$skill) {
    flash('error', 'The selected skill could not be found.');
    redirect('app/admin/skills/index.php');
}

$newStatus = (int) $skill['is_active'] === 1 ? 0 : 1;

$stmt = $pdo->prepare(
    "
    UPDATE skills
    SET is_active = :is_active
    WHERE id = :id
    "
);

$stmt->execute([
    ':is_active' => $newStatus,
    ':id' => $id
]);

flash(
    'success',
    $newStatus === 1
        ? 'Skill activated successfully.'
        : 'Skill deactivated successfully.'
);

redirect('app/admin/skills/index.php');

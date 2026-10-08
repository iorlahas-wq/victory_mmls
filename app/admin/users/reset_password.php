<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Reset User Password';
$showNavbar = true;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid user selected.'); redirect('app/admin/users/index.php'); }
$stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();
if (!$user) { flash('error', 'The selected user could not be found.'); redirect('app/admin/users/index.php'); }
$errors = [];
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
if (is_post()) {
    verify_csrf();
    if (strlen($password) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Password confirmation does not match.';
    if ($errors === []) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
        flash('success', 'Password reset successfully for ' . $user['full_name'] . '.');
        redirect('app/admin/users/index.php');
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>
<main class="admin-page users-management"><div class="container">
<section class="module-heading"><div><span class="section-label">USER MANAGEMENT</span><h1>Reset Password</h1><p>Set a new password for <?= e($user['full_name']) ?>.</p></div><div class="module-heading-actions"><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">← Back to Users</a></div></section>
<?php if ($errors !== []): ?><div class="form-errors"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="form-card"><div class="user-summary"><strong><?= e($user['full_name']) ?></strong><span><?= e($user['email']) ?></span></div><form method="post">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="form-group"><label for="password">New Password <span class="required">*</span></label><input type="password" id="password" name="password" minlength="8" required><small>Minimum 8 characters.</small></div>
<div class="form-group"><label for="confirm_password">Confirm New Password <span class="required">*</span></label><input type="password" id="confirm_password" name="confirm_password" minlength="8" required></div>
<div class="form-actions"><button type="submit" class="btn btn-primary">Reset Password</button><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">Cancel</a></div>
</form></section></div></main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

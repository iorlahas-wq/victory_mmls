<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Edit User';
$showNavbar = true;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid user selected.'); redirect('app/admin/users/index.php'); }
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();
if (!$user) { flash('error', 'The selected user could not be found.'); redirect('app/admin/users/index.php'); }
$errors = [];
$fullName = trim((string) ($_POST['full_name'] ?? $user['full_name']));
$email = strtolower(trim((string) ($_POST['email'] ?? $user['email'])));
$role = (string) ($_POST['role'] ?? $user['role']);
$isActive = isset($_POST['is_active']) ? 1 : (int) $user['is_active'];
$currentUserId = (int) current_user()['id'];
if (is_post()) {
    verify_csrf();
    if ($fullName === '') $errors[] = 'Full name is required.';
    elseif (mb_strlen($fullName) > 150) $errors[] = 'Full name must not exceed 150 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    elseif (mb_strlen($email) > 150) $errors[] = 'Email address must not exceed 150 characters.';
    if (!in_array($role, ['admin', 'instructor', 'learner'], true)) $errors[] = 'Invalid user role.';
    if ($id === $currentUserId && $isActive === 0) $errors[] = 'You cannot deactivate your own account.';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
    $stmt->execute(['email' => $email, 'id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Another user already uses this email address.';
    if ($errors === []) {
        $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, role = :role, is_active = :is_active WHERE id = :id');
        $stmt->execute(['full_name' => $fullName, 'email' => $email, 'role' => $role, 'is_active' => $isActive, 'id' => $id]);
        flash('success', 'User updated successfully.');
        redirect('app/admin/users/index.php');
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>
<main class="admin-page users-management"><div class="container">
<section class="module-heading"><div><span class="section-label">USER MANAGEMENT</span><h1>Edit User</h1><p>Update the account details and role.</p></div><div class="module-heading-actions"><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">← Back to Users</a></div></section>
<?php if ($errors !== []): ?><div class="form-errors"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="form-card"><form method="post">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="form-group"><label for="full_name">Full Name <span class="required">*</span></label><input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="150" required></div>
<div class="form-group"><label for="email">Email Address <span class="required">*</span></label><input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="150" required></div>
<div class="form-group"><label for="role">Role <span class="required">*</span></label><select id="role" name="role" required><option value="learner" <?= $role === 'learner' ? 'selected' : '' ?>>Learner</option><option value="instructor" <?= $role === 'instructor' ? 'selected' : '' ?>>Instructor</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
<div class="checkbox-group"><label><input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?> <?= $id === $currentUserId ? 'disabled checked' : '' ?>><span>Active user account</span></label><small><?= $id === $currentUserId ? 'Your own administrator account cannot be deactivated here.' : 'Inactive users cannot log in.' ?></small></div>
<div class="form-actions"><button type="submit" class="btn btn-primary">Save Changes</button><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">Cancel</a></div>
</form></section></div></main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

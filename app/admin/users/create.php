<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Create User';
$showNavbar = true;
$errors = [];

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$role = (string) ($_POST['role'] ?? 'learner');
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

if (!in_array($role, ['admin', 'instructor', 'learner'], true)) {
    $role = 'learner';
}

if (is_post()) {
    verify_csrf();

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 150) {
        $errors[] = 'Full name must not exceed 150 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (mb_strlen($email) > 150) {
        $errors[] = 'Email address must not exceed 150 characters.';
    }

    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ((int) $stmt->fetchColumn() > 0) {
        $errors[] = 'A user with this email address already exists.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (:full_name, :email, :password_hash, :role, :is_active)'
        );
        $stmt->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => $isActive,
        ]);

        flash('success', 'User created successfully.');
        redirect('app/admin/users/index.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<main class="admin-page users-management">
<div class="container">
<section class="module-heading"><div><span class="section-label">USER MANAGEMENT</span><h1>Create User</h1><p>Create an administrator, instructor or learner account.</p></div><div class="module-heading-actions"><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">← Back to Users</a></div></section>
<?php if ($errors !== []): ?><div class="form-errors"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="form-card"><form method="post">
<?= csrf_field() ?>
<div class="form-group"><label for="full_name">Full Name <span class="required">*</span></label><input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="150" required></div>
<div class="form-group"><label for="email">Email Address <span class="required">*</span></label><input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="150" required></div>
<div class="form-group"><label for="role">Role <span class="required">*</span></label><select id="role" name="role" required><option value="learner" <?= $role === 'learner' ? 'selected' : '' ?>>Learner</option><option value="instructor" <?= $role === 'instructor' ? 'selected' : '' ?>>Instructor</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
<div class="form-group"><label for="password">Password <span class="required">*</span></label><input type="password" id="password" name="password" minlength="8" required><small>Minimum 8 characters.</small></div>
<div class="form-group"><label for="confirm_password">Confirm Password <span class="required">*</span></label><input type="password" id="confirm_password" name="confirm_password" minlength="8" required></div>
<div class="checkbox-group"><label><input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>><span>Active user account</span></label><small>Inactive users cannot log in.</small></div>
<div class="form-actions"><button type="submit" class="btn btn-primary">Create User</button><a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">Cancel</a></div>
</form></section>
</div></main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

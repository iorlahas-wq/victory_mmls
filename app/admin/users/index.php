<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'User Management';
$showNavbar = true;

$search = trim((string) ($_GET['search'] ?? ''));
$role = (string) ($_GET['role'] ?? 'all');
$status = (string) ($_GET['status'] ?? 'all');

if (!in_array($role, ['all', 'admin', 'instructor', 'learner'], true)) {
    $role = 'all';
}
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE :search OR u.email LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($role !== 'all') {
    $where[] = 'u.role = :role';
    $params['role'] = $role;
}
if ($status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'u.is_active = 0';
}

$sql = 'SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at FROM users u';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY u.created_at DESC, u.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$currentUserId = (int) current_user()['id'];

require_once __DIR__ . '/../../includes/header.php';
?>
<main class="admin-page users-management">
    <div class="container">
        <section class="module-heading">
            <div>
                <span class="section-label">USER MANAGEMENT</span>
                <h1>Users</h1>
                <p>Manage administrators, instructors and learners registered on the system.</p>
            </div>
            <div class="module-heading-actions">
                <a href="<?= e(url('app/admin/users/create.php')) ?>" class="btn btn-primary">+ Add User</a>
            </div>
        </section>

        <section class="filter-card">
            <form method="get">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Search name or email...">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>All roles</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            <option value="instructor" <?= $role === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="learner" <?= $role === 'learner' ? 'selected' : '' ?>>Learner</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="<?= e(url('app/admin/users/index.php')) ?>" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="module-card">
            <div class="module-card-heading">
                <div>
                    <span class="section-label">USER DIRECTORY</span>
                    <h2><?= count($users) ?> <?= count($users) === 1 ? 'user' : 'users' ?></h2>
                </div>
            </div>

            <?php if ($users === []): ?>
                <div class="empty-state">
                    <h3>No users found</h3>
                    <p>Create a user or change the current filters.</p>
                    <a href="<?= e(url('app/admin/users/create.php')) ?>" class="btn btn-primary">Create User</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table users-table">
                        <thead>
                            <tr>
                                <th>NAME</th>
                                <th>EMAIL</th>
                                <th>ROLE</th>
                                <th>STATUS</th>
                                <th>CREATED</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?= e($user['full_name']) ?></strong></td>
                                <td><?= e($user['email']) ?></td>
                                <td><span class="role-badge role-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span></td>
                                <td>
                                    <?php if ((int) $user['is_active'] === 1): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= e(url('app/admin/users/edit.php?id=' . (int) $user['id'])) ?>" class="action-link action-edit">Edit</a>
                                        <a href="<?= e(url('app/admin/users/reset_password.php?id=' . (int) $user['id'])) ?>" class="action-link action-reset">Password</a>
                                        <?php if ((int) $user['id'] !== $currentUserId): ?>
                                            <form method="post" action="<?= e(url('app/admin/users/toggle.php')) ?>" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                                <button type="submit" class="action-button <?= (int) $user['is_active'] === 1 ? 'action-disable' : 'action-enable' ?>">
                                                    <?= (int) $user['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="self-note">Current user</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

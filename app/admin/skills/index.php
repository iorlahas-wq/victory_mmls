<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Vocational Skills';
$showNavbar = true;

$search = trim((string) ($_GET['search'] ?? ''));
$status = $_GET['status'] ?? 'active';

if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    $status = 'active';
}

$sql = "
    SELECT
        s.id,
        s.name,
        s.description,
        s.is_active,
        s.created_at,
        s.updated_at,
        COUNT(l.id) AS lesson_count
    FROM skills s
    LEFT JOIN lessons l ON l.skill_id = s.id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (s.name LIKE :search OR s.description LIKE :search) ";
    $params[':search'] = '%' . $search . '%';
}

if ($status === 'active') {
    $sql .= " AND s.is_active = 1 ";
} elseif ($status === 'inactive') {
    $sql .= " AND s.is_active = 0 ";
}

$sql .= "
    GROUP BY s.id, s.name, s.description, s.is_active, s.created_at, s.updated_at
    ORDER BY s.is_active DESC, s.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$skills = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="admin-page">
    <div class="container">

        <section class="module-header">
            <div>
                <span class="section-label">CONTENT MANAGEMENT</span>
                <h1>Vocational Skills</h1>
                <p>
                    Create and manage the practical vocational skills
                    available in the learning system.
                </p>
            </div>

            <a
                href="<?= e(url('app/admin/skills/create.php')) ?>"
                class="btn btn-primary"
            >
                + Add Skill
            </a>
        </section>

        <section class="module-toolbar">
            <form method="get"
                  action="<?= e(url('app/admin/skills/index.php')) ?>"
                  class="filter-form">

                <div class="filter-field">
                    <label for="search">Search</label>
                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search skills..."
                    >
                </div>

                <div class="filter-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                            Active
                        </option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                            Inactive
                        </option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>
                            All
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a
                        href="<?= e(url('app/admin/skills/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="module-card">

            <div class="module-card-header">
                <div>
                    <span class="section-label">SKILL CATALOGUE</span>
                    <h2>
                        <?= count($skills) ?>
                        skill<?= count($skills) === 1 ? '' : 's' ?>
                    </h2>
                </div>
            </div>

            <?php if (empty($skills)): ?>

                <div class="empty-state">
                    <h3>No skills found</h3>
                    <p>
                        <?= $search !== ''
                            ? 'Try a different search term or reset the filters.'
                            : 'Create your first vocational skill to begin building the learning content.' ?>
                    </p>

                    <?php if ($search === ''): ?>
                        <a
                            href="<?= e(url('app/admin/skills/create.php')) ?>"
                            class="btn btn-primary"
                        >
                            Create First Skill
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="table-wrapper">
                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Description</th>
                                <th>Lessons</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="table-actions-heading">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($skills as $skill): ?>
                            <tr>

                                <td>
                                    <strong class="table-title">
                                        <?= e($skill['name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="table-description">
                                        <?= e(
                                            $skill['description'] !== null &&
                                            $skill['description'] !== ''
                                                ? mb_strimwidth(
                                                    $skill['description'],
                                                    0,
                                                    90,
                                                    '...'
                                                )
                                                : 'No description'
                                        ) ?>
                                    </span>
                                </td>

                                <td><?= (int) $skill['lesson_count'] ?></td>

                                <td>
                                    <?php if ((int) $skill['is_active'] === 1): ?>
                                        <span class="status-badge status-active">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e(date('d M Y', strtotime($skill['created_at']))) ?>
                                </td>

                                <td>
                                    <div class="table-actions">

                                        <a
                                            href="<?= e(
                                                url(
                                                    'app/admin/skills/edit.php?id=' .
                                                    (int) $skill['id']
                                                )
                                            ) ?>"
                                            class="action-link"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            action="<?= e(url('app/admin/skills/toggle.php')) ?>"
                                            class="inline-form"
                                        >
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $skill['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button <?= (int) $skill['is_active'] === 1
                                                    ? 'action-danger'
                                                    : 'action-success' ?>"
                                            >
                                                <?= (int) $skill['is_active'] === 1
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>
                                            </button>
                                        </form>

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

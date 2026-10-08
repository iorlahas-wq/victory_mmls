<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$pageTitle = 'My Lessons';
$showNavbar = true;

$user = current_user();
$instructorId = (int) $user['id'];

$search = trim((string) ($_GET['search'] ?? ''));
$skillId = filter_var($_GET['skill_id'] ?? '', FILTER_VALIDATE_INT);
$status = (string) ($_GET['status'] ?? 'all');

$skillStmt = $pdo->query(
    "SELECT id, name
     FROM skills
     WHERE is_active = 1
     ORDER BY name ASC"
);

$skills = $skillStmt->fetchAll();

$where = [
    'l.created_by = :created_by'
];

$params = [
    'created_by' => $instructorId
];

if ($search !== '') {
    $where[] = '(l.title LIKE :search OR l.description LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($skillId !== false && $skillId !== null && $skillId > 0) {
    $where[] = 'l.skill_id = :skill_id';
    $params['skill_id'] = $skillId;
}

if ($status === 'published') {
    $where[] = 'l.is_published = 1';
} elseif ($status === 'draft') {
    $where[] = 'l.is_published = 0';
}

$sql = "
    SELECT
        l.id,
        l.title,
        l.description,
        l.lesson_order,
        l.is_published,
        l.created_at,
        s.name AS skill_name,

        (
            SELECT COUNT(*)
            FROM lesson_contents lc
            WHERE lc.lesson_id = l.id
              AND lc.is_active = 1
              AND lc.content_type IN ('text', 'image')
        ) AS content_count,

        (
            SELECT COUNT(*)
            FROM lesson_contents lc
            WHERE lc.lesson_id = l.id
              AND lc.content_type = 'video'
              AND lc.is_active = 1
        ) AS video_count

    FROM lessons l
    INNER JOIN skills s ON s.id = l.skill_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.name ASC, l.lesson_order ASC, l.title ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="instructor-page">
    <div class="container">

        <section class="module-header instructor-module-header">
            <div>
                <span class="section-label">MY LESSONS</span>
                <h1>Vocational Lessons</h1>
                <p>
                    Create and manage the lessons you are responsible for.
                </p>
            </div>

            <a
                href="<?= e(url('app/instructor/lessons/create.php')) ?>"
                class="btn btn-primary"
            >
                Create Lesson
            </a>
        </section>

        <section class="module-card">
            <form method="get" class="instructor-filter-form">

                <div class="form-group">
                    <label for="search">Search</label>
                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search your lessons..."
                    >
                </div>

                <div class="form-group">
                    <label for="skill_id">Skill</label>
                    <select id="skill_id" name="skill_id">
                        <option value="">All skills</option>

                        <?php foreach ($skills as $skill): ?>
                            <option
                                value="<?= (int) $skill['id'] ?>"
                                <?= ((int) $skill['id'] === (int) $skillId) ? 'selected' : '' ?>
                            >
                                <?= e($skill['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>
                            All
                        </option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>
                            Published
                        </option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>
                            Draft
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a
                        href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>
                </div>

            </form>
        </section>

        <section class="module-card">

            <div class="table-header">
                <div>
                    <span class="section-label">LESSON CATALOGUE</span>
                    <h2><?= count($lessons) ?> lessons</h2>
                </div>
            </div>

            <?php if (!$lessons): ?>

                <div class="empty-state">
                    <h3>No lessons found</h3>
                    <p>
                        Create your first vocational lesson or adjust the filters.
                    </p>
                </div>

            <?php else: ?>

                <div class="table-wrap">
                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Lesson</th>
                                <th>Contents</th>
                                <th>Video</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($lessons as $lesson): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= e($lesson['skill_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($lesson['lesson_order']) ?>.
                                            <?= e($lesson['title']) ?>
                                        </strong>

                                        <?php if (!empty($lesson['description'])): ?>
                                            <small class="table-description">
                                                <?= e(
                                                    mb_strimwidth(
                                                        (string) $lesson['description'],
                                                        0,
                                                        100,
                                                        '...'
                                                    )
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= (int) $lesson['content_count'] ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $lesson['video_count'] > 0): ?>
                                            <span class="status-badge status-active">
                                                Available
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-missing">
                                                Not added
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $lesson['is_published'] === 1): ?>
                                            <span class="status-badge status-active">
                                                Published
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="actions-cell">

                                        <!-- Manage lesson content -->
                                        <a
                                            href="<?= e(
                                                url(
                                                    'app/instructor/content/index.php?lesson_id=' .
                                                    (int) $lesson['id']
                                                )
                                            ) ?>"
                                            class="action-link"
                                        >
                                            Manage
                                        </a>

                                        <!-- Edit lesson -->
                                        <a
                                            href="<?= e(
                                                url(
                                                    'app/instructor/lessons/edit.php?id=' .
                                                    (int) $lesson['id']
                                                )
                                            ) ?>"
                                            class="action-link"
                                        >
                                            Edit
                                        </a>

                                        <!-- Publish / Unpublish -->
                                        <form
                                            method="post"
                                            action="<?= e(
                                                url('app/instructor/lessons/toggle.php')
                                            ) ?>"
                                            class="inline-form"
                                        >
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $lesson['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button"
                                            >
                                                <?= (int) $lesson['is_published'] === 1
                                                    ? 'Unpublish'
                                                    : 'Publish' ?>
                                            </button>
                                        </form>

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

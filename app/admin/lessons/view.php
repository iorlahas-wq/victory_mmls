<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'View Lesson';
$showNavbar = true;

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "
    SELECT
        l.*,
        s.name AS skill_name,
        u.full_name AS creator_name
    FROM lessons l
    INNER JOIN skills s
        ON s.id = l.skill_id
    LEFT JOIN users u
        ON u.id = l.created_by
    WHERE l.id = :id
    LIMIT 1
    "
);
$stmt->execute(['id' => $id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    flash('error', 'The selected lesson could not be found.');
    redirect('app/admin/lessons/index.php');
}

$contentStmt = $pdo->prepare(
    "
    SELECT
        id,
        content_type,
        title,
        content,
        file_path,
        content_order,
        is_active
    FROM lesson_contents
    WHERE lesson_id = :lesson_id
    ORDER BY content_order ASC, id ASC
    "
);
$contentStmt->execute(['lesson_id' => $id]);
$contents = $contentStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="admin-page lessons-management">

    <div class="container">

        <section class="module-heading">

            <div>
                <span class="section-label">LESSON DETAILS</span>

                <h1><?= e($lesson['title']) ?></h1>

                <p>
                    <?= e($lesson['skill_name']) ?>
                </p>
            </div>

            <div class="module-heading-actions">

                <a
                    href="<?= e(
                        url('app/admin/lessons/content/index.php?lesson_id=' . $id)
                    ) ?>"
                    class="btn btn-primary"
                >
                    Manage Content
                </a>

                <a
                    href="<?= e(
                        url('app/admin/lessons/edit.php?id=' . $id)
                    ) ?>"
                    class="btn btn-primary"
                >
                    Edit Lesson
                </a>

                <a
                    href="<?= e(url('app/admin/lessons/index.php')) ?>"
                    class="btn btn-secondary"
                >
                    ← Back
                </a>

            </div>

        </section>

        <section class="detail-card">

            <div class="detail-grid">

                <div class="detail-item">
                    <span>Skill</span>
                    <strong><?= e($lesson['skill_name']) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Lesson Order</span>
                    <strong><?= (int) $lesson['lesson_order'] ?></strong>
                </div>

                <div class="detail-item">
                    <span>Status</span>

                    <strong>
                        <?php if ((int) $lesson['is_published'] === 1): ?>
                            <span class="status-badge status-published">
                                Published
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-draft">
                                Draft
                            </span>
                        <?php endif; ?>
                    </strong>
                </div>

                <div class="detail-item">
                    <span>Created By</span>
                    <strong>
                        <?= e($lesson['creator_name'] ?? 'System') ?>
                    </strong>
                </div>

            </div>

            <div class="detail-description">

                <span>Description</span>

                <?php if (!empty($lesson['description'])): ?>

                    <p>
                        <?= nl2br(e((string) $lesson['description'])) ?>
                    </p>

                <?php else: ?>

                    <p class="muted">
                        No description has been added.
                    </p>

                <?php endif; ?>

            </div>

        </section>

        <section class="module-card">

            <div class="module-card-heading">

                <div>
                    <span class="section-label">LESSON CONTENT</span>

                    <h2>
                        <?= count($contents) ?>
                        <?= count($contents) === 1 ? 'content item' : 'content items' ?>
                    </h2>
                </div>

                <div>
                    <a
                        href="<?= e(
                            url('app/admin/lessons/content/index.php?lesson_id=' . $id)
                        ) ?>"
                        class="btn btn-secondary"
                    >
                        Manage Content
                    </a>
                </div>

            </div>

            <?php if ($contents === []): ?>

                <div class="empty-state">

                    <h3>No content added yet</h3>

                    <p>
                        Add text and images to build this lesson. Live instructional
                        video support will be added later.
                    </p>

                    <a
                        href="<?= e(
                            url('app/admin/lessons/content/create.php?lesson_id=' . $id)
                        ) ?>"
                        class="btn btn-primary"
                    >
                        Add First Content
                    </a>

                </div>

            <?php else: ?>

                <div class="table-wrap">

                    <table class="data-table lessons-table">

                        <thead>
                            <tr>
                                <th>ORDER</th>
                                <th>TYPE</th>
                                <th>TITLE</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($contents as $content): ?>

                                <tr>

                                    <td>
                                        <?= (int) $content['content_order'] ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            ucfirst(
                                                (string) $content['content_type']
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $content['title']
                                                ?: 'Untitled content'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $content['is_active'] === 1): ?>
                                            <span class="status-badge status-published">
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-draft">
                                                Inactive
                                            </span>
                                        <?php endif; ?>
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

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

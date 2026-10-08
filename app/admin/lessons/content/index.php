<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../includes/auth.php';

require_role('admin');

$pageTitle = 'Lesson Content';
$showNavbar = true;

$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($lessonId <= 0) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.title, l.description, l.lesson_order, l.is_published,
            s.id AS skill_id, s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    flash('error', 'The selected lesson could not be found.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT id, content_type, title, content, file_path, content_order, is_active, created_at
     FROM lesson_contents
     WHERE lesson_id = :lesson_id
     ORDER BY content_order ASC, id ASC"
);
$stmt->execute(['lesson_id' => $lessonId]);
$contents = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<main class="admin-page lesson-content-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">LESSON CONTENT</span>
                <h1><?= e($lesson['title']) ?></h1>
                <p>
                    <?= e($lesson['skill_name']) ?> · Add text and images to this lesson.
                </p>
            </div>

            <div class="module-heading-actions">
                <a href="<?= e(url('app/admin/lessons/content/create.php?lesson_id=' . $lessonId)) ?>" class="btn btn-primary">
                    + Add Content
                </a>
                <a href="<?= e(url('app/admin/lessons/view.php?id=' . $lessonId)) ?>" class="btn btn-secondary">
                    ← View Lesson
                </a>
            </div>
        </section>

        <?php foreach (consume_flash() as $message): ?>
            <div class="flash-message flash-<?= e($message['type']) ?>">
                <?= e($message['message']) ?>
            </div>
        <?php endforeach; ?>

        <section class="content-info-card">
            <div>
                <span>Lesson order</span>
                <strong><?= (int) $lesson['lesson_order'] ?></strong>
            </div>
            <div>
                <span>Lesson status</span>
                <strong>
                    <?php if ((int) $lesson['is_published'] === 1): ?>
                        <span class="status-badge status-published">Published</span>
                    <?php else: ?>
                        <span class="status-badge status-draft">Draft</span>
                    <?php endif; ?>
                </strong>
            </div>
            <div>
                <span>Content items</span>
                <strong><?= count($contents) ?></strong>
            </div>
        </section>

        <section class="module-card">
            <div class="module-card-heading">
                <div>
                    <span class="section-label">CONTENT SEQUENCE</span>
                    <h2><?= count($contents) ?> <?= count($contents) === 1 ? 'item' : 'items' ?></h2>
                </div>
            </div>

            <?php if ($contents === []): ?>
                <div class="empty-state">
                    <h3>No lesson content yet</h3>
                    <p>
                        Start with text and images. Live instructional video support will be added later.
                    </p>
                    <a href="<?= e(url('app/admin/lessons/content/create.php?lesson_id=' . $lessonId)) ?>" class="btn btn-primary">
                        Add First Content
                    </a>
                </div>
            <?php else: ?>
                <div class="content-list">
                    <?php foreach ($contents as $content): ?>
                        <article class="content-item <?= (int) $content['is_active'] === 0 ? 'content-inactive' : '' ?>">
                            <div class="content-number">
                                <?= (int) $content['content_order'] ?>
                            </div>

                            <div class="content-main">
                                <div class="content-meta">
                                    <span class="content-type-badge">
                                        <?= e(ucfirst((string) $content['content_type'])) ?>
                                    </span>
                                    <?php if ((int) $content['is_active'] === 1): ?>
                                        <span class="status-badge status-published">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-draft">Inactive</span>
                                    <?php endif; ?>
                                </div>

                                <h3><?= e($content['title'] ?: 'Untitled content') ?></h3>

                                <?php if ($content['content_type'] === 'text'): ?>
                                    <p class="content-preview">
                                        <?= e(mb_strimwidth((string) ($content['content'] ?? ''), 0, 300, '...')) ?>
                                    </p>
                                <?php elseif ($content['content_type'] === 'image' && !empty($content['file_path'])): ?>
                                    <div class="content-image-preview">
                                        <img
                                            src="<?= e(url((string) $content['file_path'])) ?>"
                                            alt="<?= e($content['title'] ?: 'Lesson image') ?>"
                                        >
                                    </div>
                                    <?php if (!empty($content['content'])): ?>
                                        <p class="content-preview"><?= e((string) $content['content']) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="content-actions">
                                <a href="<?= e(url('app/admin/lessons/content/edit.php?id=' . (int) $content['id'])) ?>" class="action-link action-edit">Edit</a>

                                <form method="post" action="<?= e(url('app/admin/lessons/content/toggle.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $content['id'] ?>">
                                    <button type="submit" class="action-button <?= (int) $content['is_active'] === 1 ? 'action-unpublish' : 'action-publish' ?>">
                                        <?= (int) $content['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>

                                <form method="post" action="<?= e(url('app/admin/lessons/content/delete.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this content item? This action cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $content['id'] ?>">
                                    <button type="submit" class="action-button action-delete">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

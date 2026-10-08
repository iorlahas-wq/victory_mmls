<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$user = current_user();
$userId = (int) $user['id'];

$lessonsStmt = $pdo->prepare(
    "SELECT l.id, l.title
     FROM lessons l
     WHERE l.created_by = :created_by
     ORDER BY l.lesson_order ASC, l.title ASC"
);
$lessonsStmt->execute(['created_by' => $userId]);
$lessons = $lessonsStmt->fetchAll();

$lessonId = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);

if (!$lessonId && !empty($lessons)) {
    $lessonId = (int) $lessons[0]['id'];
}

$selectedLesson = null;
$contents = [];

if ($lessonId) {
    $lessonStmt = $pdo->prepare(
        "SELECT id, title, description, is_published
         FROM lessons
         WHERE id = :id AND created_by = :created_by
         LIMIT 1"
    );
    $lessonStmt->execute([
        'id' => $lessonId,
        'created_by' => $userId,
    ]);
    $selectedLesson = $lessonStmt->fetch();

    if ($selectedLesson) {
        $contentStmt = $pdo->prepare(
            "SELECT id, content_type, title, content, file_path,
                    content_order, is_active, created_at
             FROM lesson_contents
             WHERE lesson_id = :lesson_id
             ORDER BY content_order ASC, id ASC"
        );
        $contentStmt->execute(['lesson_id' => $lessonId]);
        $contents = $contentStmt->fetchAll();
    }
}

$pageTitle = 'Lesson Content';
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="video-management-page lesson-content-management">
    <div class="container">

        <div class="module-heading">
            <div>
                <span class="section-label">MY LEARNING CONTENT</span>
                <h1>Lesson Content</h1>
                <p>Add and arrange the text and images learners will use in each lesson.</p>
            </div>

            <?php if ($selectedLesson): ?>
                <div class="module-heading-actions">
                    <a
                        href="<?= e(url('app/instructor/content/create.php?lesson_id=' . (int) $selectedLesson['id'])) ?>"
                        class="btn btn-primary"
                    >
                        Add Content
                    </a>
                    <a
                        href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        My Lessons
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($lessons)): ?>

            <div class="module-card empty-state">
                <h3>No lessons available</h3>
                <p>Create a lesson first. Once you have a lesson, you can add its text and images here.</p>
                <a href="<?= e(url('app/instructor/lessons/index.php')) ?>" class="btn btn-primary">
                    My Lessons
                </a>
            </div>

        <?php else: ?>

            <div class="module-card">
                <form method="get" class="video-filter-form">
                    <div class="form-group">
                        <label for="lesson_id">Select Lesson</label>
                        <select name="lesson_id" id="lesson_id" onchange="this.form.submit()">
                            <?php foreach ($lessons as $lesson): ?>
                                <option
                                    value="<?= (int) $lesson['id'] ?>"
                                    <?= (int) $lesson['id'] === (int) $lessonId ? 'selected' : '' ?>
                                >
                                    <?= e($lesson['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">View Content</button>
                    </div>
                </form>
            </div>

            <?php if ($selectedLesson): ?>

                <div class="content-info-card">
                    <div>
                        <span>Lesson</span>
                        <strong><?= e($selectedLesson['title']) ?></strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong><?= (int) $selectedLesson['is_published'] === 1 ? 'Published' : 'Draft' ?></strong>
                    </div>
                    <div>
                        <span>Learning Items</span>
                        <strong><?= count($contents) ?></strong>
                    </div>
                </div>

                <div class="module-card">
                    <div class="module-card-heading">
                        <span class="section-label">CONTENT ITEMS</span>
                        <h2><?= e($selectedLesson['title']) ?></h2>
                    </div>

                    <?php if (empty($contents)): ?>

                        <div class="empty-state">
                            <h3>No content yet</h3>
                            <p>Add the first text or image learning item for this lesson.</p>
                            <a
                                href="<?= e(url('app/instructor/content/create.php?lesson_id=' . (int) $selectedLesson['id'])) ?>"
                                class="btn btn-primary"
                            >
                                Add First Content
                            </a>
                        </div>

                    <?php else: ?>

                        <div class="content-list">
                            <?php foreach ($contents as $item): ?>
                                <article class="content-item <?= (int) $item['is_active'] === 1 ? '' : 'content-inactive' ?>">

                                    <div class="content-number">
                                        <?= (int) $item['content_order'] ?>
                                    </div>

                                    <div class="content-main">
                                        <div class="content-meta">
                                            <span class="content-type-badge">
                                                <?= e(ucfirst($item['content_type'])) ?>
                                            </span>

                                            <span class="status-badge <?= (int) $item['is_active'] === 1 ? 'status-published' : 'status-draft' ?>">
                                                <?= (int) $item['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>

                                        <h3><?= e($item['title']) ?></h3>

                                        <?php if ($item['content_type'] === 'text'): ?>
                                            <p class="content-preview">
                                                <?= e(mb_strimwidth((string) $item['content'], 0, 260, '…', 'UTF-8')) ?>
                                            </p>
                                        <?php elseif ($item['content_type'] === 'image' && !empty($item['file_path'])): ?>
                                            <div class="content-image-preview">
                                                <img
                                                    src="<?= e(url($item['file_path'])) ?>"
                                                    alt="<?= e($item['title']) ?>"
                                                >
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="content-actions">
                                        <a
                                            href="<?= e(url('app/instructor/content/edit.php?id=' . (int) $item['id'])) ?>"
                                            class="action-link action-edit"
                                        >
                                            Edit
                                        </a>

                                        <form method="post" action="<?= e(url('app/instructor/content/toggle.php')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="lesson_id" value="<?= (int) $selectedLesson['id'] ?>">
                                            <button
                                                type="submit"
                                                class="action-button <?= (int) $item['is_active'] === 1 ? 'action-unpublish' : 'action-publish' ?>"
                                            >
                                                <?= (int) $item['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

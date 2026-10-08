<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];
$lessonId = filter_var($_GET['lesson_id'] ?? '', FILTER_VALIDATE_INT);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Please select a lesson.');
    redirect('app/learner/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.title, l.description, l.lesson_order, s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :lesson_id
       AND l.is_published = 1
       AND s.is_active = 1
     LIMIT 1"
);
$stmt->execute(['lesson_id' => (int) $lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('This lesson is unavailable or has not been published.');
}

/* Create or update progress when the learner opens a lesson. */
$progressStmt = $pdo->prepare(
    "INSERT INTO learner_progress
        (learner_id, lesson_id, status, started_at)
     VALUES
        (:learner_id, :lesson_id, 'in_progress', CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE
        status = IF(status = 'not_started', 'in_progress', status),
        started_at = COALESCE(started_at, CURRENT_TIMESTAMP)"
);
$progressStmt->execute([
    'learner_id' => $learnerId,
    'lesson_id' => (int) $lessonId,
]);

$contentStmt = $pdo->prepare(
    "SELECT id, content_type, title, content, file_path, content_order
     FROM lesson_contents
     WHERE lesson_id = :lesson_id AND is_active = 1
     ORDER BY content_order ASC, id ASC"
);
$contentStmt->execute(['lesson_id' => (int) $lessonId]);
$contents = $contentStmt->fetchAll();

$progressStmt = $pdo->prepare(
    "SELECT status, started_at, completed_at
     FROM learner_progress
     WHERE learner_id = :learner_id AND lesson_id = :lesson_id
     LIMIT 1"
);
$progressStmt->execute([
    'learner_id' => $learnerId,
    'lesson_id' => (int) $lessonId,
]);
$progress = $progressStmt->fetch() ?: ['status' => 'in_progress'];

$questionStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM questions
     WHERE lesson_id = :lesson_id AND is_active = 1"
);
$questionStmt->execute(['lesson_id' => (int) $lessonId]);
$questionCount = (int) $questionStmt->fetchColumn();

$pageTitle = (string) $lesson['title'];
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <nav class="learner-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('app/learner/lessons/index.php')) ?>">Lessons</a>
            <span> / </span>
            <span><?= e($lesson['title']) ?></span>
        </nav>

        <section class="learner-lesson-intro">
            <span class="learner-skill-label"><?= e($lesson['skill_name']) ?></span>
            <h1><?= e($lesson['title']) ?></h1>
            <?php if (!empty($lesson['description'])): ?>
                <p><?= nl2br(e($lesson['description'])) ?></p>
            <?php endif; ?>
            <span class="learner-progress-badge learner-progress-<?= e((string) $progress['status']) ?>">
                <?= e(ucwords(str_replace('_', ' ', (string) $progress['status']))) ?>
            </span>
        </section>

        <section class="learner-content-list">
            <div class="learner-section-heading">
                <div>
                    <span class="section-label">LESSON MATERIALS</span>
                    <h2>Study this lesson</h2>
                </div>
            </div>

            <?php if (!$contents): ?>
                <div class="learner-empty">
                    <h3>Learning materials are not available yet</h3>
                    <p>Your instructor may still be preparing this lesson.</p>
                </div>
            <?php else: ?>
                <?php foreach ($contents as $item): ?>
                    <article class="learner-content-item">
                        <h3><?= e((string) ($item['title'] ?: ucfirst((string) $item['content_type']))) ?></h3>

                        <?php if ($item['content_type'] === 'text'): ?>
                            <div class="learner-text-content"><?= nl2br(e((string) ($item['content'] ?? ''))) ?></div>

                        <?php elseif ($item['content_type'] === 'image' && !empty($item['file_path'])): ?>
                            <figure class="learner-media-figure">
                                <img
                                    src="<?= e(url((string) $item['file_path'])) ?>"
                                    alt="<?= e((string) ($item['title'] ?: $lesson['title'])) ?>"
                                    loading="lazy"
                                >
                                <?php if (!empty($item['title'])): ?>
                                    <figcaption><?= e($item['title']) ?></figcaption>
                                <?php endif; ?>
                            </figure>

                        <?php elseif ($item['content_type'] === 'video' && !empty($item['file_path'])): ?>
                            <div class="learner-video-frame">
                                <video controls preload="metadata">
                                    <source src="<?= e(url((string) $item['file_path'])) ?>">
                                    Your browser does not support embedded video.
                                </video>
                            </div>

                        <?php else: ?>
                            <p class="learner-muted">This learning item has no available content file.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="learner-lesson-actions">
            <?php if ((string) $progress['status'] !== 'completed'): ?>
                <form method="post" action="<?= e(url('app/learner/lessons/complete.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="lesson_id" value="<?= (int) $lessonId ?>">
                    <button class="btn btn-primary" type="submit">Mark Lesson as Completed</button>
                </form>
            <?php else: ?>
                <p class="learner-completed-note">You have marked this lesson as completed.</p>
            <?php endif; ?>

            <?php if ($questionCount > 0): ?>
                <a class="btn btn-secondary" href="<?= e(url('app/learner/assessments/index.php?lesson_id=' . (int) $lessonId)) ?>">
                    Take Lesson Assessment (<?= $questionCount ?> questions)
                </a>
            <?php else: ?>
                <span class="learner-muted">No assessment is available for this lesson yet.</span>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

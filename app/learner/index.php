<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];

$pageTitle = 'Learner Dashboard';
$showNavbar = true;

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.is_published = 1 AND s.is_active = 1"
);
$stmt->execute();
$availableLessons = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_progress,
        COALESCE(SUM(CASE WHEN lp.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_lessons,
        COALESCE(SUM(CASE WHEN lp.status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress_lessons
     FROM learner_progress lp
     INNER JOIN lessons l ON l.id = lp.lesson_id
     WHERE lp.learner_id = :learner_id
       AND l.is_published = 1"
);
$stmt->execute(['learner_id' => $learnerId]);
$progress = $stmt->fetch() ?: [];

$completedLessons = (int) ($progress['completed_lessons'] ?? 0);
$inProgressLessons = (int) ($progress['in_progress_lessons'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM assessment_attempts
     WHERE learner_id = :learner_id AND status = 'submitted'"
);
$stmt->execute(['learner_id' => $learnerId]);
$submittedAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.score,
        r.total_marks,
        r.percentage,
        r.created_at,
        l.title AS lesson_title
     FROM results r
     INNER JOIN lessons l ON l.id = r.lesson_id
     WHERE r.learner_id = :learner_id
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 5"
);
$stmt->execute(['learner_id' => $learnerId]);
$recentResults = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT l.id, l.title, l.description, s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.is_published = 1 AND s.is_active = 1
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT 4"
);
$stmt->execute();
$featuredLessons = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <section class="learner-welcome">
            <span class="section-label">LEARNER AREA</span>
            <h1>Welcome, <?= e($user['full_name']) ?></h1>
            <p>Continue learning practical vocational skills through lesson notes, images, instructional videos and assessments.</p>
            <a class="btn btn-primary" href="<?= e(url('app/learner/lessons/index.php')) ?>">Browse Lessons</a>
        </section>

        <section class="learner-stat-grid" aria-label="Learning summary">
            <article class="learner-stat-card">
                <span>Available Lessons</span>
                <strong><?= $availableLessons ?></strong>
                <small>Published lessons you can study</small>
            </article>
            <article class="learner-stat-card">
                <span>Completed Lessons</span>
                <strong><?= $completedLessons ?></strong>
                <small>Lessons marked completed</small>
            </article>
            <article class="learner-stat-card">
                <span>In Progress</span>
                <strong><?= $inProgressLessons ?></strong>
                <small>Lessons you have started</small>
            </article>
            <article class="learner-stat-card">
                <span>Submitted Assessments</span>
                <strong><?= $submittedAttempts ?></strong>
                <small>Assessment attempts submitted</small>
            </article>
        </section>

        <section class="learner-section">
            <div class="learner-section-heading">
                <div>
                    <span class="section-label">START LEARNING</span>
                    <h2>Recently added lessons</h2>
                </div>
                <a class="learner-text-link" href="<?= e(url('app/learner/lessons/index.php')) ?>">View all lessons →</a>
            </div>

            <?php if (!$featuredLessons): ?>
                <div class="learner-empty">
                    <h3>No published lessons yet</h3>
                    <p>Lessons will appear here when an instructor or administrator publishes them.</p>
                </div>
            <?php else: ?>
                <div class="learner-card-grid">
                    <?php foreach ($featuredLessons as $lesson): ?>
                        <article class="learner-lesson-card">
                            <span class="learner-skill-label"><?= e($lesson['skill_name']) ?></span>
                            <h3><?= e($lesson['title']) ?></h3>
                            <p><?= e(mb_strimwidth((string) ($lesson['description'] ?? ''), 0, 150, '…', 'UTF-8')) ?></p>
                            <a class="btn btn-primary" href="<?= e(url('app/learner/lessons/view.php?lesson_id=' . (int) $lesson['id'])) ?>">Open Lesson</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="learner-section">
            <div class="learner-section-heading">
                <div>
                    <span class="section-label">ASSESSMENT HISTORY</span>
                    <h2>Recent results</h2>
                </div>
                <a class="learner-text-link" href="<?= e(url('app/learner/results/index.php')) ?>">View all results →</a>
            </div>

            <?php if (!$recentResults): ?>
                <div class="learner-empty">
                    <p>You have no assessment results yet. Complete a lesson and take its assessment when one is available.</p>
                </div>
            <?php else: ?>
                <div class="learner-table-wrap">
                    <table class="learner-table">
                        <thead><tr><th>Lesson</th><th>Score</th><th>Percentage</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentResults as $result): ?>
                            <tr>
                                <td><?= e($result['lesson_title']) ?></td>
                                <td><?= e(number_format((float) $result['score'], 2)) ?> / <?= e(number_format((float) $result['total_marks'], 2)) ?></td>
                                <td><?= e(number_format((float) $result['percentage'], 2)) ?>%</td>
                                <td><?= e(date('d M Y', strtotime((string) $result['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

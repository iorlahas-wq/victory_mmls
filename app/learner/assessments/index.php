<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];
$pageTitle = 'My Assessments';
$showNavbar = true;

$lessonId = filter_var($_GET['lesson_id'] ?? '', FILTER_VALIDATE_INT);
if ($lessonId === false || $lessonId < 1) {
    $lessonId = null;
}

$sql = "
    SELECT
        l.id AS lesson_id,
        l.title AS lesson_title,
        s.name AS skill_name,
        COUNT(DISTINCT q.id) AS active_question_count,
        COUNT(DISTINCT CASE WHEN aa.status = 'submitted' THEN aa.id END) AS submitted_attempts,
        MAX(r.percentage) AS best_percentage,
        MAX(aa.submitted_at) AS last_submitted_at
    FROM lessons l
    INNER JOIN skills s ON s.id = l.skill_id
    INNER JOIN questions q
        ON q.lesson_id = l.id AND q.is_active = 1
    LEFT JOIN assessment_attempts aa
        ON aa.lesson_id = l.id AND aa.learner_id = :attempt_learner_id
    LEFT JOIN results r
        ON r.attempt_id = aa.id AND r.learner_id = :result_learner_id
    WHERE l.is_published = 1 AND s.is_active = 1
";

$params = [
    'attempt_learner_id' => $learnerId,
    'result_learner_id' => $learnerId,
];

if ($lessonId !== null) {
    $sql .= ' AND l.id = :lesson_id';
    $params['lesson_id'] = $lessonId;
}

$sql .= "
    GROUP BY l.id, l.title, s.name
    ORDER BY s.name ASC, l.lesson_order ASC, l.title ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assessments = $stmt->fetchAll();

$pageTitle = 'My Assessments';
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <section class="learner-page-heading">
            <span class="section-label">ASSESSMENT CENTRE</span>
            <h1>My Assessments</h1>
            <p>Choose a lesson assessment, submit your answers and review your results.</p>
        </section>

        <?php if (!$assessments): ?>
            <div class="learner-empty">
                <h3>No assessments available</h3>
                <p>Assessments appear here when a published lesson has active questions.</p>
                <a class="btn btn-secondary" href="<?= e(url('app/learner/lessons/index.php')) ?>">Browse Lessons</a>
            </div>
        <?php else: ?>
            <div class="learner-card-grid">
                <?php foreach ($assessments as $assessment): ?>
                    <article class="learner-lesson-card">
                        <span class="learner-skill-label"><?= e($assessment['skill_name']) ?></span>
                        <h3><?= e($assessment['lesson_title']) ?></h3>
                        <p><?= (int) $assessment['active_question_count'] ?> active question(s) are available for this lesson.</p>

                        <div class="learner-lesson-meta">
                            <span>Submitted attempts: <?= (int) $assessment['submitted_attempts'] ?></span>
                            <?php if ($assessment['best_percentage'] !== null): ?>
                                <span>Best score: <?= e(number_format((float) $assessment['best_percentage'], 2)) ?>%</span>
                            <?php else: ?>
                                <span>No result yet</span>
                            <?php endif; ?>
                        </div>

                        <form method="post" action="<?= e(url('app/learner/assessments/start.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="lesson_id" value="<?= (int) $assessment['lesson_id'] ?>">
                            <button class="btn btn-primary" type="submit">Start / Resume Assessment</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="learner-bottom-link">
            <a class="learner-text-link" href="<?= e(url('app/learner/results/index.php')) ?>">View my assessment results →</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

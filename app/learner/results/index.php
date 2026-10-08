<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];
$pageTitle = 'My Results';
$showNavbar = true;

$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.attempt_id,
        r.score,
        r.total_marks,
        r.percentage,
        r.created_at,
        l.title AS lesson_title,
        s.name AS skill_name
     FROM results r
     INNER JOIN lessons l ON l.id = r.lesson_id
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE r.learner_id = :learner_id
     ORDER BY r.created_at DESC, r.id DESC"
);
$stmt->execute(['learner_id' => $learnerId]);
$results = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <section class="learner-page-heading">
            <span class="section-label">MY LEARNING RECORD</span>
            <h1>Assessment Results</h1>
            <p>Review the scores recorded for your submitted lesson assessments.</p>
        </section>

        <?php if (!$results): ?>
            <div class="learner-empty">
                <h3>No results recorded yet</h3>
                <p>After you submit an assessment, your score and percentage will appear here.</p>
                <a class="btn btn-primary" href="<?= e(url('app/learner/assessments/index.php')) ?>">Browse Assessments</a>
            </div>
        <?php else: ?>
            <div class="learner-table-wrap">
                <table class="learner-table">
                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Lesson</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Submitted</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?= e($result['skill_name']) ?></td>
                            <td><?= e($result['lesson_title']) ?></td>
                            <td><?= e(number_format((float) $result['score'], 2)) ?> / <?= e(number_format((float) $result['total_marks'], 2)) ?></td>
                            <td>
                                <strong><?= e(number_format((float) $result['percentage'], 2)) ?>%</strong>
                                <div class="learner-result-track">
                                    <span style="width: <?= max(0, min(100, (float) $result['percentage'])) ?>%;"></span>
                                </div>
                            </td>
                            <td><?= e(date('d M Y, g:i a', strtotime((string) $result['created_at']))) ?></td>
                            <td>
                                <?php if ((float) $result['percentage'] >= 50): ?>
                                    <span class="learner-result-badge learner-result-pass">50% or above</span>
                                <?php else: ?>
                                    <span class="learner-result-badge learner-result-low">Below 50%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="learner-note">The 50% label is a simple display guide, not a formal pass/fail rule. Your project can apply a different threshold if required.</p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
